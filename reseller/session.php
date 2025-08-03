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

$rSessionTimeout = 60;

if (!defined('TMP_PATH')) {
	define('TMP_PATH', '/home/xcms/tmp/');
}

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
if (isset($_SESSION['reseller']) && isset($_SESSION['rlast_activity']) && (($rSessionTimeout * 60) < (time() - $_SESSION['rlast_activity']))) {
	foreach (['reseller', 'rip', 'rcode', 'rverify', 'rlast_activity'] as $rKey) {
		if (isset($_SESSION[$rKey])) {
			unset($_SESSION[$rKey]);
		}
	}

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
}

if (!isset($_SESSION['reseller'])) {
	if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
		echo json_encode(['result' => false]);
		exit();
	}
	else {
		header('Location: login?referrer=' . urlencode(basename($_SERVER['REQUEST_URI'], '.php')));
		exit();
	}
}
else {
	if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
		echo json_encode(['result' => true]);
		exit();
	}
	else {
		$_SESSION['rlast_activity'] = time();
	}
}

session_write_close();

?>