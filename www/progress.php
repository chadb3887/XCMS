<?php
/**
*
* @ This file is created by http://DeZender.Net
* @ deZender (PHP7 Decoder for ionCube Encoder)
*
* @ Version			:	5.0.1.0
* @ Author			:	DeZender
* @ Release on		:	22.04.2022
* @ Official site	:	http://DeZender.Net
*
*/

ignore_user_abort(true);
require 'constants.php';
$rPost = trim(file_get_contents('php://input'));
if (($_SERVER['REMOTE_ADDR'] != '127.0.0.1') || empty($_GET['stream_id']) || empty($rPost)) {
	generate404();
}

$rStreamID = (int) $_GET['stream_id'];
$rData = array_filter(array_map('trim', explode("\n", $rPost)));
$rOutput = [];

foreach ($rData as $rRow) {
	list($rKey, $rValue) = explode('=', $rRow);
	$rOutput[trim($rKey)] = trim($rValue);
}

file_put_contents(STREAMS_PATH . ($rStreamID . '_.progress'), json_encode($rOutput));

?>