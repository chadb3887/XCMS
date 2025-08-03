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

function shutdown()
{
	global $db;
	global $rChannelInfo;
	global $rPID;
	global $rStreamID;

	if (is_object($db)) {
		$db->close_mysql();
	}
	if (XCMS::$rSettings['on_demand_instant_off'] && ($rChannelInfo['on_demand'] == 1)) {
		XCMS::removeFromQueue($rStreamID, $rPID);
	}
}

register_shutdown_function('shutdown');
header('Access-Control-Allow-Origin: *');
set_time_limit(0);
require '../init.php';
$rIP = XCMS::getUserIP();
$rPID = getmypid();

if (XCMS::$rSettings['use_buffer'] == 0) {
	header('X-Accel-Buffering: no');
}

if (!empty(XCMS::$rRequest['uitoken'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['uitoken'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);
	XCMS::$rRequest['stream'] = $rTokenData['stream_id'];
	XCMS::$rRequest['extension'] = 'm3u8';
	$rIPMatch = (XCMS::$rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rTokenData['ip']), 0, -1)) == implode('.', array_slice(explode('.', XCMS::getUserIP()), 0, -1)) : XCMS::getUserIP() == $rTokenData['ip']);
	if (($rTokenData['expires'] < time()) || !$rIPMatch) {
		generate404();
	}

	$rPrebuffer = XCMS::$rSegmentSettings['seg_time'];
}
else if (empty(XCMS::$rRequest['password']) || (XCMS::$rSettings['live_streaming_pass'] != XCMS::$rRequest['password'])) {
	generate404();
}
else if (!in_array($rIP, XCMS::getAllowedIPs())) {
	generate404();
}
else {
	$rPrebuffer = (isset(XCMS::$rRequest['prebuffer']) ? XCMS::$rSegmentSettings['seg_time'] : 0);

	foreach (getallheaders() as $rKey => $rValue) {
		if (strtoupper($rKey) == 'X-XCMS-PREBUFFER') {
			$rPrebuffer = XCMS::$rSegmentSettings['seg_time'];
		}
	}
}

$db = new Database();
XCMS::$db = & $db;
$rPassword = XCMS::$rSettings['live_streaming_pass'];
$rStreamID = (int) XCMS::$rRequest['stream'];
$rExtension = XCMS::$rRequest['extension'];
$rWaitTime = 20;
$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id AND t2.server_id = ? WHERE t1.`id` = ?', SERVER_ID, $rStreamID);

if (0 < $db->num_rows()) {
	touch(SIGNALS_TMP_PATH . 'admin_' . (int) $rStreamID);
	$rChannelInfo = $db->get_row();
	$db->close_mysql();

	if (file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
		$rChannelInfo['pid'] = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
	}

	if (file_exists(STREAMS_PATH . $rStreamID . '_.monitor')) {
		$rChannelInfo['monitor_pid'] = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
	}
	if (XCMS::$rSettings['on_demand_instant_off'] && ($rChannelInfo['on_demand'] == 1)) {
		XCMS::addToQueue($rStreamID, $rPID);
	}

	if (!XCMS::isStreamRunning($rChannelInfo['pid'], $rStreamID)) {
		$rChannelInfo['pid'] = NULL;

		if ($rChannelInfo['on_demand'] == 1) {
			if (!XCMS::isMonitorRunning($rChannelInfo['monitor_pid'], $rStreamID)) {
				XCMS::startMonitor($rStreamID);

				for ($rRetries = 0; !file_exists(STREAMS_PATH . (int) $rStreamID . '_.monitor') && ($rRetries < 300); $rRetries++) {
					usleep(10000);
				}

				$rChannelInfo['monitor_pid'] = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
			}
		}
		else {
			generate404();
		}
	}

	$rRetries = 0;
	$rPlaylist = STREAMS_PATH . $rStreamID . '_.m3u8';

	if ($rExtension == 'ts') {
		if (!file_exists($rPlaylist)) {
			$rFirstTS = STREAMS_PATH . $rStreamID . '_0.ts';
			$rFP = NULL;

			while ($rRetries < ((int) $rWaitTime * 100)) {
				if (file_exists($rFirstTS) && !$rFP) {
					$rFP = fopen($rFirstTS, 'r');
				}
				if ($rFP && !!fread($rFP, 1)) {
					break;
				}

				usleep(10000);
				$rRetries++;
			}

			if ($rFP) {
				fclose($rFP);
			}
		}
	}
	else {
		$rFirstTS = STREAMS_PATH . $rStreamID . '_.m3u8';

		while (!file_exists($rPlaylist) && !file_exists($rFirstTS) && ($rRetries < ((int) $rWaitTime * 100))) {
			usleep(10000);
			$rRetries++;
		}
	}

	if ($rRetries == (int) $rWaitTime * 10) {
		if (isset(XCMS::$rRequest['odstart'])) {
			echo '0';
			exit();
		}
		else {
			generate404();
		}
	}
	else if (isset(XCMS::$rRequest['odstart'])) {
		echo '1';
		exit();
	}

	if (!$rChannelInfo['pid']) {
		$rChannelInfo['pid'] = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
	}

	switch ($rExtension) {
	case 'm3u8':
		if (XCMS::isValidStream($rPlaylist, $rChannelInfo['pid'])) {
			if (empty(XCMS::$rRequest['segment'])) {
				if ($rSource = XCMS::generateAdminHLS($rPlaylist, $rPassword, $rStreamID, XCMS::$rRequest['uitoken'])) {
					header('Content-Type: application/vnd.apple.mpegurl');
					header('Content-Length: ' . strlen($rSource));
					ob_end_flush();
					echo $rSource;
					exit();
				}
			}
			else {
				$rSegment = STREAMS_PATH . str_replace(['\\', '/'], '', urldecode(XCMS::$rRequest['segment']));

				if (file_exists($rSegment)) {
					$rBytes = filesize($rSegment);
					header('Content-Length: ' . $rBytes);
					header('Content-Type: video/mp2t');
					readfile($rSegment);
					exit();
				}
			}
		}

		break;
	default:
		header('Content-Type: video/mp2t');

		if (file_exists($rPlaylist)) {
			if (file_exists(STREAMS_PATH . $rStreamID . '_.dur')) {
				$rDuration = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.dur');

				if (XCMS::$rSegmentSettings['seg_time'] < $rDuration) {
					XCMS::$rSegmentSettings['seg_time'] = $rDuration;
				}
			}

			$rSegments = XCMS::getPlaylistSegments($rPlaylist, $rPrebuffer, XCMS::$rSegmentSettings['seg_time']);
		}
		else {
			$rSegments = NULL;
		}

		if (!is_null($rSegments)) {
			if (is_array($rSegments)) {
				$rBytes = 0;
				$rStartTime = time();

				foreach ($rSegments as $rSegment) {
					if (file_exists(STREAMS_PATH . $rSegment)) {
						$rBytes += readfile(STREAMS_PATH . $rSegment);
					}
					else {
						exit();
					}
				}

				preg_match('/_(.*)\\./', array_pop($rSegments), $rCurrentSegment);
				$rCurrent = $rCurrentSegment[1];
			}
			else {
				$rCurrent = $rSegments;
			}
		}
		else if (!file_exists($rPlaylist)) {
			$rCurrent = -1;
		}
		else {
			exit();
		}

		$rFails = 0;
		$rTotalFails = XCMS::$rSegmentSettings['seg_time'] * 2;

		if (($rTotalFails < (int) XCMS::$rSettings['segment_wait_time']) ?: 20) {
			$rTotalFails = (int) XCMS::$rSettings['segment_wait_time'] ?: 20;
		}

		while (true) {
			$rSegmentFile = sprintf('%d_%d.ts', $rStreamID, $rCurrent + 1);
			$rNextSegment = sprintf('%d_%d.ts', $rStreamID, $rCurrent + 2);

			for ($rChecks = 0; !file_exists(STREAMS_PATH . $rSegmentFile) && ($rChecks <= $rTotalFails * 10); $rChecks++) {
				usleep(100000);
			}

			if (!file_exists(STREAMS_PATH . $rSegmentFile)) {
				exit();
			}
			if (empty($rChannelInfo['pid']) && file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
				$rChannelInfo['pid'] = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
			}

			$rFails = 0;
			$rTimeStart = time();
			$rFP = fopen(STREAMS_PATH . $rSegmentFile, 'r');

			while (($rFails <= $rTotalFails) && !file_exists(STREAMS_PATH . $rNextSegment)) {
				$rData = stream_get_line($rFP, XCMS::$rSettings['read_buffer_size']);

				if (empty($rData)) {
					if (!XCMS::isStreamRunning($rChannelInfo['pid'], $rStreamID)) {
						break;
					}

					sleep(1);
					$rFails++;
					continue;
				}

				echo $rData;
				$rData = '';
				$rFails = 0;
			}
			if (XCMS::isStreamRunning($rChannelInfo['pid'], $rStreamID) && ($rFails <= $rTotalFails) && file_exists(STREAMS_PATH . $rSegmentFile) && is_resource($rFP)) {
				$rSegmentSize = filesize(STREAMS_PATH . $rSegmentFile);
				$rRestSize = $rSegmentSize - ftell($rFP);

				if (0 < $rRestSize) {
					echo stream_get_line($rFP, $rRestSize);
				}
			}
			else {
				exit();
			}

			fclose($rFP);
			$rFails = 0;
			$rCurrent++;
		}

		break;
	}
}
else {
	generate404();
}

?>