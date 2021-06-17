<?php
/* global config variables */
$searchd_port = 8921;
$searchd = getenv('A0_SEARCHD') ?: 'localhost';
$searchd_url = "http://$searchd:$searchd_port/search";

$logd_port = 3207;
$logd = getenv('A0_QRYLOGD') ?: 'localhost';
$logd_url = "http://$logd:$logd_port/push/query";
$clickd_url = "http://$logd:$logd_port/push/clicks";
?>
