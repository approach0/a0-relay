<?php
require 'config.php';
require 'cors.php';

/*
 * search relay: send query to searchd and return search
 * results.
 */
function search_relay($query_obj)
{
	$qry_json = json_encode($query_obj);
	$req_head = array(
		'Content-Type: application/json',
		'Content-Length: '.strlen($qry_json)
	);

	$c = curl_init($GLOBALS['searchd_url']);

	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 3); /* connection timeout (secs) */
	curl_setopt($c, CURLOPT_TIMEOUT, 10); /* curl execution timeout (secs) */

	curl_setopt($c, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($c, CURLOPT_HTTPHEADER, $req_head);
	curl_setopt($c, CURLOPT_POSTFIELDS, $qry_json);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($c);

	if (curl_errno($c)) {
		error_log(curl_error($c)."\n");
		throw new Exception(curl_error($c));
	}

	curl_close($c);
	return $response;
}

/*
 * Send query to query log collector
 */
function send_query_log($query_obj)
{
	$qry_json = json_encode($query_obj);
	$req_head = array(
		'Content-Type: application/json',
		'Content-Length: '.strlen($qry_json)
	);

	$c = curl_init($GLOBALS['logd_url']);

	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 3); /* connection timeout (secs) */
	curl_setopt($c, CURLOPT_TIMEOUT, 10); /* curl execution timeout (secs) */

	curl_setopt($c, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($c, CURLOPT_HTTPHEADER, $req_head);
	curl_setopt($c, CURLOPT_POSTFIELDS, $qry_json);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

	curl_exec($c);
	if (curl_errno($c))
		error_log(curl_error($c)."\n");

	curl_close($c);
}

/*
 * qry_explode: return keywords array extracted from
 * $qry_str, splitted by commas that are not wrapped
 * by "$" signs.
 */
function qry_explode($qry_str)
{
	$kw_arr = array();
	$kw = '';
	$dollar_open = false;
	$i = 0;

	for ($i = 0; $i < strlen($qry_str); $i++) {
		$c = $qry_str[$i];
		if ($c == '$') {
			if ($dollar_open)
				$dollar_open = false;
			else
				$dollar_open = true;
		}

		if (! $dollar_open && $c == ',') {
			array_push($kw_arr, $kw);
			$kw = '';
		} else {
			$kw = $kw.$c;
		}
	}

	array_push($kw_arr, $kw);
	return $kw_arr;
}

/*
 * replace_interrogation: return string with question
 * marks replaced by wildcards with different names,
 * e.g., \qvar{X}, \qvar{Y}.
 */
function replace_interrogation($str)
{
	$wildcards_letters = array();
	$offsets = range(0, 26 - 1);
	foreach ($offsets as $offset) {
		$c = chr(ord('A') + $offset);
		if (strpos($str, $c) === false)
			array_push($wildcards_letters, $c);
	}
	if (sizeof($wildcards_letters) == 0)
		array_push($wildcards_letters, 'x');

	foreach ($wildcards_letters as $letter) {
		$pos = strpos($str, '?');
		if ($pos === false) break;
		$replace = '\qvar{'.$letter.'}';
		$str = substr_replace($str, $replace, $pos, strlen($letter));
	}

	return $str;
}

/*
 * parsing GET request parameters
 */
$req_qry_str = '';
$req_page = 1;

/* q for query string */
if(!isset($_GET['q']) || !is_scalar($_GET['q'])) {
	http_response_code(400);
	echo '[search-relay] Bad GET Request!';
	exit;
} else {
	$req_qry_str = $_GET['q'];
}

/* p for page */
if(isset($_GET['p']) && is_scalar($_GET['p']))
	$req_page = intval($_GET['p']);

/* extract client IP and Geo-* information */
$remote_ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
$geo_city = $_SERVER['HTTP_GEO_CITY'] ?? 'Unknown';
$geo_region = $_SERVER['HTTP_GEO_SUBD'] ?? 'Unknown';
$geo_country = $_SERVER['HTTP_GEO_CTRY'] ?? 'Unknown';

/* log HTTP header (DEBUG) */
//$headers = getallheaders();
//foreach ($headers as $header => $value) {
//	error_log("$header: $value\n");
//}

/*
 * split and handle each query keyword
 */
$query_obj = array(
	"ip" => $remote_ip,
	"page" => $req_page,
	"geo" => array(
		"city" => $geo_city,
		"region" => $geo_region,
		"country" => $geo_country
	),
	"kw" => array()
);
$keywords = qry_explode($req_qry_str);

foreach ($keywords as $kw) {
	$kw_type = 'unknown';
	$kw = trim($kw);
	$ret = preg_match('/(OR|AND|NOT) ([a-z]+):(.*)/', $kw, $fields,
	                  PREG_UNMATCHED_AS_NULL);
	if ($ret !== 1)
		continue; // no match or any error

	$op = $fields[1] ?? 'OR';
	$fi = $fields[2] ?? 'content';
	$kw = $fields[3] ?? '';

	// error_log("$op $fi:$kw |");

	if ($kw == '') {
		/* skip this empty keyword */
		continue;
	} else if ($kw[0] == '$') {
		$kw_str = trim($kw, '$');
		/* treat question mark as wildcard */
		$kw_str = replace_interrogation($kw_str);
		$kw_type = 'tex';
	} else {
		$kw_str = $kw;
		$kw_type = 'term';
	}

	array_push($query_obj["kw"], array(
			"type"  => $kw_type,
			"op"    => $op,
			"field" => $fi,
			"str"   => $kw_str
		)
	);
}

//var_dump($query_obj);
//exit;

/* enable CORS only in develop environment  */
if ($searchd == 'localhost' && $logd == 'localhost')
	enable_cors_policy();

try {
	/* add to query logs */
	send_query_log($query_obj);

	/* relay query and return searchd response */
	$searchd_response = search_relay($query_obj);
	echo $searchd_response;

} catch (Exception $e) {
	http_response_code(500);
	echo '[search-relay] Internal Server Error! ';
	echo '(', $e->getMessage(), ')';
	exit;
}
?>
