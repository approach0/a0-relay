<?php
require 'config.php';

function click_relay($qryobj)
{
	$qry_json = json_encode($qryobj);
	$req_head = array(
		'Content-Type: application/json',
		'Content-Length: '.strlen($qry_json)
	);

	$c = curl_init($GLOBALS['clickd_url']);

	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 3); /* connection timeout (secs) */
	curl_setopt($c, CURLOPT_TIMEOUT, 10); /* curl execution timeout (secs) */

	curl_setopt($c, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($c, CURLOPT_HTTPHEADER, $req_head);
	curl_setopt($c, CURLOPT_POSTFIELDS, $qry_json);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

	curl_exec($c);
	if (curl_errno($c)) {
		error_log(curl_error($c)."\n");
		throw new Exception(curl_error($c));
	}

	curl_close($c);
}

$data = json_decode(file_get_contents('php://input'), true); /* POST data */
$remote_ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
$data['ip'] = $remote_ip;

// var_dump($data); /* echo message */
try {
	click_relay($data);
	echo 'OK';

} catch (Exception $e) {
	http_response_code(500);
	echo '[search-relay] Internal Server Error! ';
	echo '(', $e->getMessage(), ')';
	exit;
}
?>
