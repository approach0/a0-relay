<?php
/* set CORS policy to allow access with any Origin field in header */
function enable_cors_policy()
{
	if (isset($_SERVER['HTTP_ORIGIN'])) {
		header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
		header('Access-Control-Allow-Credentials: true');
		header('Cache-Control: no-cache');
	}

	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
			header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
	}
}

?>
