<?php
/* global config variables */
$searchd_port = 8921;
$searchd = getenv('A0_SEARCHD') ?: 'localhost';
$searchd_url = "http://$searchd:$searchd_port/search";

$logd_port = 3207;
$logd = getenv('A0_QRYLOGD') ?: 'localhost';
$logd_url = "http://$logd:$logd_port/push/query";

//error_log(serialize(getenv('A0_SEARCHD')));

/* open CORS policy to allow access with any Origin field in header */
function enable_cors_policy()
{
	if (isset($_SERVER['HTTP_ORIGIN'])) {
		header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
		header('Access-Control-Allow-Credentials: true');
		header('Cache-Control: no-cache');
	}

	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
			header("Access-Control-Allow-Methods: GET, OPTIONS");
	}
}

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
 * get remote IP
 */
$remote_ip=$_SERVER['REMOTE_ADDR'];

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

/*
 * split and handle each query keyword
 */
$query_obj = array(
	"ip" => $remote_ip,
	"page" => $req_page,
	"kw" => array()
);
$keywords = qry_explode($req_qry_str);

foreach ($keywords as $kw) {
	$kw = trim($kw);
	$kw_type = 'unknown';
	$kw_str = '';

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
		"type" => $kw_type,
		"str" => $kw_str)
	);
}

/*
 * relay
 */
# var_dump($query_obj);
try {
	/* add to query logs */
	send_query_log($query_obj);

	/* relay query and return searchd response */
	$searchd_response = search_relay($query_obj);
	enable_cors_policy();
	echo $searchd_response;

} catch (Exception $e) {
	http_response_code(500);
	echo '[search-relay] Internal Server Error! ';
	echo '(', $e->getMessage(), ')';
	exit;
}
?>