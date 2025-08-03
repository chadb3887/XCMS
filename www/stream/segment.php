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

function getUserIP()
{
	return $_SERVER['REMOTE_ADDR'];
}

header('Access-Control-Allow-Origin: *');
set_time_limit(0);
require_once 'init.php';
$rSettings = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'settings'));
$rServers = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'servers'));
$rConfig = parse_ini_file(CONFIG_PATH . 'config.ini');

if (!defined('SERVER_ID')) {
	define('SERVER_ID', (int) $rConfig['server_id']);
}

if (empty($rSettings['live_streaming_pass'])) {
	generate404();
}

if (!empty($rSettings['send_server_header'])) {
	header('Server: ' . $rSettings['send_server_header']);
}

if ($rSettings['send_protection_headers']) {
	header('X-XSS-Protection: 0');
	header('X-Content-Type-Options: nosniff');
}

if ($rSettings['send_altsvc_header']) {
	header('Alt-Svc: h3-29=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-T051=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q050=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q046=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q043=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,quic=":' . $rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000; v="46,43"');
}
if (empty($rSettings['send_unique_header_domain']) && !filter_var(HOST, FILTER_VALIDATE_IP)) {
	$rSettings['send_unique_header_domain'] = '.' . HOST;
}

$rVideoCodec = 'h264';
$rIsHMAC = NULL;

if (isset($_GET['token'])) {
	$rOffset = 0;
	$rTokenArray = explode('/', Xcms\Functions::decrypt($_GET['token'], $rSettings['live_streaming_pass'], OPENSSL_EXTRA));

	if (6 <= count($rTokenArray)) {
		if ($rTokenArray[0] == 'TS') {
			$rServerID = $rTokenArray[8];
		}
		else {
			$rServerID = $rTokenArray[6];
		}

		if ($rServerID != SERVER_ID) {
			if ($rServers[$rServerID]['random_ip'] && (0 < count($rServers[$rServerID]['domains']['urls']))) {
				$rURL = $rServers[$rServerID]['domains']['protocol'] . '://' . $rServers[$rServerID]['domains']['urls'][array_rand($rServers[$rServerID]['domains']['urls'])] . ':' . $rServers[$rServerID]['domains']['port'];
			}
			else {
				$rURL = rtrim($rServers[$rServerID]['site_url'], '/');
			}

			header('Location: ' . $rURL . '/hls/' . $_GET['token']);
			exit();
		}

		if ($rTokenArray[0] == 'TS') {
			$rType = 'ARCHIVE';
			$rUsername = $rTokenArray[1];
			$rPassword = $rTokenArray[2];
			$rUserIP = $rTokenArray[3];
			$rDuration = $rTokenArray[4];
			$rStartDate = $rTokenArray[5];
			$rSegmentData = $rTokenArray[6];
			$rUUID = $rTokenArray[7];
			list($rStreamID, $rSegmentID, $rOffset) = explode('_', $rSegmentData);
			$rStreamID = (int) $rStreamID;
			$rSegment = ARCHIVE_PATH . $rStreamID . '/' . $rSegmentID;

			if (!file_exists($rSegment)) {
				generate404();
			}
		}
		else {
			$rType = 'LIVE';

			if (substr($rTokenArray[0], 0, 5) == 'HMAC#') {
				$rIsHMAC = (int) explode('#', $rTokenArray[0])[1];
				$rIdentifier = $rTokenArray[1];
			}
			else {
				$rUsername = $rTokenArray[0];
				$rPassword = $rTokenArray[1];
			}

			$rUserIP = $rTokenArray[2];
			$rStreamID = (int) $rTokenArray[3];
			$rSegmentID = basename($rTokenArray[4]);
			$rUUID = $rTokenArray[5];
			$rVideoCodec = $rTokenArray[7] ?: 'h264';
			$rOnDemand = $rTokenArray[8] ?: 0;
			$rSegment = STREAMS_PATH . $rSegmentID;
			$rSegmentData = explode('_', $rSegmentID);
			if (!file_exists($rSegment) || ($rStreamID != $rSegmentData[0])) {
				generate404();
			}
		}

		if (!file_exists(CONS_TMP_PATH . $rUUID)) {
			generate404();
		}

		$rFilesize = filesize($rSegment);
		$rIPMatch = ($rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rUserIP), 0, -1)) == implode('.', array_slice(explode('.', getuserip()), 0, -1)) : $rUserIP == getuserip());
		if (!$rIPMatch && $rSettings['restrict_same_ip']) {
			generate404();
		}

		header('Access-Control-Allow-Origin: *');
		header('Content-Type: video/mp2t');

		if ($rType == 'LIVE') {
			if ($rOnDemand) {
				$rSettings['encrypt_hls'] = false;
			}

			if (file_exists(SIGNALS_PATH . $rUUID)) {
				$rSignalData = json_decode(file_get_contents(SIGNALS_PATH . $rUUID), true);

				if ($rSignalData['type'] == 'signal') {
					require_once INCLUDES_PATH . 'streaming.php';
					XCMS::init(false);

					if ($rSettings['encrypt_hls']) {
						$rKey = file_get_contents(STREAMS_PATH . $rStreamID . '_.key');
						$rIV = file_get_contents(STREAMS_PATH . $rStreamID . '_.iv');
						$rData = XCMS::sendSignal($rSignalData, basename($rSegment), $rVideoCodec, true);
						echo openssl_encrypt($rData, 'aes-128-cbc', $rKey, OPENSSL_RAW_DATA, $rIV);
					}
					else {
						XCMS::sendSignal($rSignalData, basename($rSegment), $rVideoCodec);
					}

					unlink(SIGNALS_PATH . $rUUID);
					exit();
				}
			}

			if ($rSettings['encrypt_hls']) {
				$rSegmentData = explode('_', pathinfo($rSegmentID)['filename']);

				if (!file_exists(STREAMS_PATH . $rStreamID . '_' . $rSegmentData[1] . '.ts')) {
					generate404();
				}

				if (file_exists($rSegment . '.enc_write')) {
					$rChecks = 0;

					if (file_exists(STREAMS_PATH . $rStreamID . '_.dur')) {
						$rTotalTries = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.dur') * 2;
					}
					else {
						$rTotalTries = $rSettings['seg_time'] * 2;
					}

					while (file_exists($rSegment . '.enc_write') && !file_exists($rSegment . '.enc') && ($rChecks <= $rTotalTries * 10)) {
						usleep(100000);
						$rChecks++;
					}
				}
				else {
					ignore_user_abort(true);
					touch($rSegment . '.enc_write');
					$rKey = file_get_contents(STREAMS_PATH . $rStreamID . '_.key');
					$rIV = file_get_contents(STREAMS_PATH . $rStreamID . '_.iv');
					$rData = openssl_encrypt(file_get_contents($rSegment), 'aes-128-cbc', $rKey, OPENSSL_RAW_DATA, $rIV);
					file_put_contents($rSegment . '.enc', $rData);
					unset($rData);
					unlink($rSegment . '.enc_write');
					ignore_user_abort(false);
				}

				if (file_exists($rSegment . '.enc')) {
					header('Content-Length: ' . filesize($rSegment . '.enc'));
					readfile($rSegment . '.enc');
				}
				else {
					generate404();
				}
			}
			else {
				header('Content-Length: ' . $rFilesize);
				readfile($rSegment);
			}
		}
		else if (0 < $rOffset) {
			header('Content-Length: ' . ($rFilesize - $rOffset));
			$rFP = @fopen($rSegment, 'rb');

			if ($rFP) {
				fseek($rFP, $rOffset);

				while (!feof($rFP)) {
					echo stream_get_line($rFP, $rSettings['read_buffer_size']);
				}

				fclose($rFP);
			}
		}
		else {
			header('Content-Length: ' . $rFilesize);
			readfile($rSegment);
		}

		exit();
	}
}

generate404();

?>