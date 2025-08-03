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

function getLength($rQueue)
{
	$rLength = 0;

	foreach ($rQueue as $item) {
		$rLength += $item['filesize'];
	}

	return $rLength;
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
header('Access-Control-Allow-Origin: *');
set_time_limit(0);
require '../init.php';
$rIP = XCMS::getUserIP();

if (XCMS::$rSettings['use_buffer'] == 0) {
	header('X-Accel-Buffering: no');
}

if (!empty(XCMS::$rRequest['uitoken'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['uitoken'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);
	XCMS::$rRequest['stream'] = $rTokenData['stream_id'];
	XCMS::$rRequest['extension'] = 'm3u8';

	if (isset($rTokenData['start'])) {
		XCMS::$rRequest['start'] = $rTokenData['start'];
	}

	if (isset($rTokenData['duration'])) {
		XCMS::$rRequest['duration'] = $rTokenData['duration'];
	}

	$rIPMatch = (XCMS::$rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rTokenData['ip']), 0, -1)) == implode('.', array_slice(explode('.', XCMS::getUserIP()), 0, -1)) : XCMS::getUserIP() == $rTokenData['ip']);
	if (($rTokenData['expires'] < time()) || !$rIPMatch) {
		generate404();
	}
}
else if (!in_array($rIP, XCMS::getAllowedIPs())) {
	generate404();
}
else if (empty(XCMS::$rRequest['password']) || (XCMS::$rSettings['live_streaming_pass'] != XCMS::$rRequest['password'])) {
	generate404();
}

$db = new Database();
XCMS::$db = & $db;
$rPassword = XCMS::$rSettings['live_streaming_pass'];
$rStreamID = (int) XCMS::$rRequest['stream'];
$rExtension = XCMS::$rRequest['extension'];

if (empty(XCMS::$rRequest['segment'])) {
	$rStartDate = XCMS::$rRequest['start'];
	$rDuration = XCMS::$rRequest['duration'];

	if (!is_numeric($rStartDate)) {
		if (substr_count($rStartDate, '-') == 1) {
			list($rDate, $rTime) = explode('-', $rStartDate);
			$rYear = substr($rDate, 0, 4);
			$rMonth = substr($rDate, 4, 2);
			$rDay = substr($rDate, 6, 2);
			$rMinutes = 0;
			$rHour = $rTime;
		}
		else {
			list($rDate, $rTime) = explode(':', $rStartDate);
			list($rYear, $rMonth, $rDay) = explode('-', $rDate);
			list($rHour, $rMinutes) = explode('-', $rTime);
		}

		$rTimestamp = mktime($rHour, $rMinutes, 0, $rMonth, $rDay, $rYear);
	}
	else {
		$rTimestamp = $rStartDate;
	}
}

$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id AND t2.server_id = ? WHERE t1.`id` = ?', SERVER_ID, $rStreamID);

if (0 < $db->num_rows()) {
	$rChannelInfo = $db->get_row();
	$db->close_mysql();

	if (empty(XCMS::$rRequest['segment'])) {
		$rQueue = [];
		$rFile = ARCHIVE_PATH . $rStreamID . '/' . date('Y-m-d:H-i', $rTimestamp) . '.ts';
		if (empty($rStreamID) || empty($rTimestamp) || empty($rDuration)) {
			generate404();
		}
		if (!file_exists($rFile) || !is_readable($rFile)) {
			generate404();
		}

		$rQueue = [];

		for ($i = 0; $i < $rDuration; $i++) {
			$rFile = ARCHIVE_PATH . $rStreamID . '/' . date('Y-m-d:H-i', $rTimestamp + ($i * 60)) . '.ts';

			if (file_exists($rFile)) {
				$rQueue[] = ['filename' => $rFile, 'filesize' => filesize($rFile)];
			}
		}

		if (count($rQueue) == 0) {
			generate404();
		}
	}

	switch ($rExtension) {
	case 'm3u8':
		if (empty(XCMS::$rRequest['segment'])) {
			$rOutput = '#EXTM3U' . "\n";
			$rOutput .= '#EXT-X-VERSION:3' . "\n";
			$rOutput .= '#EXT-X-TARGETDURATION:60' . "\n";
			$rOutput .= '#EXT-X-MEDIA-SEQUENCE:0' . "\n";
			$rOutput .= '#EXT-X-PLAYLIST-TYPE:VOD' . "\n";

			foreach ($rQueue as $rKey => $rItem) {
				$rOutput .= '#EXTINF:60.0,' . "\n";

				if (!empty(XCMS::$rRequest['uitoken'])) {
					$rOutput .= '/admin/timeshift?extension=m3u8&segment=' . basename($rItem['filename']) . '&uitoken=' . XCMS::$rRequest['uitoken'] . "\n";
				}
				else {
					$rOutput .= '/admin/timeshift?extension=m3u8&stream=' . $rStreamID . '&segment=' . basename($rItem['filename']) . '&password=' . $rPassword . "\n";
				}
			}

			$rOutput .= '#EXT-X-ENDLIST';
			ob_end_clean();
			header('Content-Type: application/x-mpegurl');
			header('Content-Length: ' . strlen($rOutput));
			echo $rOutput;
			exit();
		}
		else {
			$rSegment = ARCHIVE_PATH . $rStreamID . '/' . str_replace(['\\', '/'], '', urldecode(XCMS::$rRequest['segment']));

			if (file_exists($rSegment)) {
				$rBytes = filesize($rSegment);
				header('Content-Length: ' . $rBytes);
				header('Content-Type: video/mp2t');
				readfile($rSegment);
			}
			else {
				generate404();
			}
		}

		break;
	case 'ts':
		header('Content-Type: video/mp2t');
		$rLength = $rSize = getlength($rQueue);
		header('Accept-Ranges: 0-' . $rLength);
		$rStart = 0;
		$rEnd = $rSize - 1;

		if (isset($_SERVER['HTTP_RANGE'])) {
			$rRangeStart = $rStart;
			$rRangeEnd = $rEnd;
			list(1 => $rRange) = explode('=', $_SERVER['HTTP_RANGE'], 2);

			if (strpos($rRange, ',') !== false) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
				exit();
			}

			if ($rRange == '-') {
				$rRangeStart = $rSize - substr($rRange, 1);
			}
			else {
				$rRange = explode('-', $rRange);
				$rRangeStart = $rRange[0];
				$rRangeEnd = (isset($rRange[1]) && is_numeric($rRange[1]) ? $rRange[1] : $rSize);
			}

			$rRangeEnd = ($rEnd < $rRangeEnd ? $rEnd : $rRangeEnd);
			if (($rRangeEnd < $rRangeStart) || (($rSize - 1) < $rRangeStart) || ($rSize <= $rRangeEnd)) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
				exit();
			}

			$rStart = $rRangeStart;
			$rEnd = $rRangeEnd;
			$rLength = ($rEnd - $rStart) + 1;
			header('HTTP/1.1 206 Partial Content');
		}

		header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
		header('Content-Length: ' . $rLength);
		$rStartFrom = 0;

		if (0 < $rStart) {
			$rStartFrom = floor($rStart / ($rSize / count($rQueue)));
		}

		$rFirstFile = false;
		$rSeekTo = 0;
		$rSizeToDate = 0;
		$rBuffer = XCMS::$rSettings['read_buffer_size'];

		foreach ($rQueue as $rKey => $rItem) {
			$rSizeToDate += $rItem['filesize'];
			if (!$rFirstFile && (0 < $rStartFrom)) {
				if ($rKey < $rStartFrom) {
					continue;
				}
				else {
					$rFirstFile = true;
					$rSeekTo = $rStart - $rSizeToDate;
				}
			}

			$rFP = fopen($rItem['filename'], 'rb');
			fseek($rFP, $rSeekTo);

			while (!feof($rFP)) {
				$rPosition = ftell($rFP);
				$rResponse = stream_get_line($rFP, $rBuffer);
				echo $rResponse;
			}

			if (is_resource($rFP)) {
				fclose($rFP);
			}

			$rSeekTo = 0;
		}
	}
}
else {
	generate404();
}

?>