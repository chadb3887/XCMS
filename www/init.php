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

require_once 'constants.php';
require_once INCLUDES_PATH . 'xcms.php';
require_once INCLUDES_PATH . 'pdo.php';

if (!function_exists('getallheaders')) {
	function getallheaders()
	{
		$rHeaders = [];

		foreach ($_SERVER as $rName => $rValue) {
			if (substr($rName, 0, 5) == 'HTTP_') {
				$rHeaders[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($rName, 5)))))] = $rValue;
			}
		}

		return $rHeaders;
	}
}

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
	generate404();
}

$rFilename = strtolower(basename(get_included_files()[0], '.php'));
if (!in_array($rFilename, ['enigma2' => true, 'epg' => true, 'playlist' => true, 'api' => true, 'xplugin' => true, 'live' => true, 'proxy_api' => true, 'thumb' => true, 'timeshift' => true, 'vod' => true]) || @$argc) {
	$db = new Database();
	XCMS::$db = & $db;
	XCMS::init();

	if (!$db) {
		exit('Database Error!');
	}
}
else {
	$db = new Database(NULL);
	XCMS::$db = & $db;
	XCMS::init(true);

	if (!XCMS::$rCached) {
		$db = new Database();
		XCMS::$db = & $db;
	}

	if (!$db) {
		exit('Database Error!');
	}
}

?>