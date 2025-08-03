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

function getImageSizeKeepAspectRatio($origWidth, $origHeight, $maxWidth, $maxHeight)
{
	if ($maxWidth == 0) {
		$maxWidth = $origWidth;
	}

	if ($maxHeight == 0) {
		$maxHeight = $origHeight;
	}

	$widthRatio = $maxWidth / ($origWidth ?: 1);
	$heightRatio = $maxHeight / ($origHeight ?: 1);
	$ratio = min($widthRatio, $heightRatio);

	if ($ratio < 1) {
		$newWidth = $ratio * (int) $origWidth;
		$newHeight = $ratio * (int) $origHeight;
	}
	else {
		$newHeight = $origHeight;
		$newWidth = $origWidth;
	}

	return ['height' => round($newHeight, 0), 'width' => round($newWidth, 0)];
}

function isAbsoluteUrl($rURL)
{
	$rPattern = '/^(?:ftp|https?|feed)?:?\\/\\/(?:(?:(?:[\\w\\.\\-\\+!$&\'\\(\\)*\\+,;=]|%[0-9a-f]{2})+:)*' . "\r\n" . '    (?:[\\w\\.\\-\\+%!$&\'\\(\\)*\\+,;=]|%[0-9a-f]{2})+@)?(?:' . "\r\n" . '    (?:[a-z0-9\\-\\.]|%[0-9a-f]{2})+|(?:\\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\\]))(?::[0-9]+)?(?:[\\/|\\?]' . "\r\n" . '    (?:[\\w#!:\\.\\?\\+\\|=&@$\'~*,;\\/\\(\\)\\[\\]\\-]|%[0-9a-f]{2})*)?$/xi';
	return (bool) preg_match($rPattern, $rURL);
}

session_start();
session_write_close();

if (!isset($_SESSION['reseller'])) {
	exit();
}

set_time_limit(2);
ini_set('default_socket_timeout', 2);
define('XCMS_HOME', '/home/xcms/');
define('WWW_PATH', XCMS_HOME . 'www/');
define('IMAGES_PATH', WWW_PATH . 'images/');
define('TMP_PATH', XCMS_HOME . 'tmp/');
define('CACHE_TMP_PATH', TMP_PATH . 'cache/');
$rServers = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'servers'));
$rURL = $_GET['url'];
$rMaxW = 0;
$rMaxH = 0;

if (isset($_GET['maxw'])) {
	$rMaxW = (int) $_GET['maxw'];
}

if (isset($_GET['maxh'])) {
	$rMaxH = (int) $_GET['maxh'];
}

if (isset($_GET['max'])) {
	$rMaxW = (int) $_GET['max'];
	$rMaxH = (int) $_GET['max'];
}

if (substr($rURL, 0, 2) == 's:') {
	$rSplit = explode(':', $rURL, 3);
	$rServerID = (int) $rSplit[1];
	$rDomain = (empty($rServers[$rServerID]['domain_name']) ? $rServers[$rServerID]['server_ip'] : explode(',', $rServers[$rServerID]['domain_name'])[0]);
	$rServerURL = $rServers[$rServerID]['server_protocol'] . '://' . $rDomain . ':' . $rServers[$rServerID]['request_port'] . '/';
	$rURL = $rServerURL . 'images/' . basename($rURL);
}
if ($rURL && (0 < $rMaxW) && (0 < $rMaxH)) {
	$rImagePath = IMAGES_PATH . 'admin/' . md5($rURL) . '_' . $rMaxW . '_' . $rMaxH . '.png';
	if (!file_exists($rImagePath) || (filesize($rImagePath) == 0)) {
		if (isabsoluteurl($rURL)) {
			$rActURL = $rURL;
		}
		else {
			$rActURL = IMAGES_PATH . basename($rURL);
		}

		$rImageInfo = getimagesize($rActURL);
		$rImageSize = getimagesizekeepaspectratio($rImageInfo[0], $rImageInfo[1], $rMaxW, $rMaxH);
		if ($rImageSize['width'] && $rImageSize['height']) {
			if ($rImageInfo['mime'] == 'image/png') {
				$rImage = imagecreatefrompng($rActURL);
			}
			else if ($rImageInfo['mime'] == 'image/jpeg') {
				$rImage = imagecreatefromjpeg($rActURL);
			}
			else {
				$rImage = NULL;
			}

			if ($rImage) {
				$rImageP = imagecreatetruecolor($rImageSize['width'], $rImageSize['height']);
				imagealphablending($rImageP, false);
				imagesavealpha($rImageP, true);
				imagecopyresampled($rImageP, $rImage, 0, 0, 0, 0, $rImageSize['width'], $rImageSize['height'], $rImageInfo[0], $rImageInfo[1]);
				imagepng($rImageP, $rImagePath);
			}
		}
	}

	if (file_exists($rImagePath)) {
		header('Content-Type: image/png');
		echo file_get_contents($rImagePath);
		exit();
	}
}

header('Content-Type: image/png');
$rImage = imagecreatetruecolor(1, 1);
imagesavealpha($rImage, true);
imagefill($rImage, 0, 0, imagecolorallocatealpha($rImage, 0, 0, 0, 127));
imagepng($rImage);

?>