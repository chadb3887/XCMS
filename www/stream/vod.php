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
	global $rCloseCon;
	global $rTokenData;
	global $rPID;
	XCMS::$rSettings = XCMS::getCache('settings');

	if ($rCloseCon) {
		if (XCMS::$rSettings['redis_handler']) {
			if (!is_object(XCMS::$redis)) {
				XCMS::connectRedis();
			}

			$rConnection = XCMS::getConnection($rTokenData['uuid']);
			if ($rConnection && ($rPID == $rConnection['pid'])) {
				$rChanges = ['hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset']];
				XCMS::updateConnection($rConnection, $rChanges, 'close');
			}
		}
		else {
			if (!is_object(XCMS::$db)) {
				XCMS::connectDatabase();
			}

			XCMS::$db->query('UPDATE `lines_live` SET `hls_end` = 1, `hls_last_read` = ? WHERE `uuid` = ? AND `pid` = ?;', time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], $rTokenData['uuid'], $rPID);
		}
	}
	if (!XCMS::$rSettings['redis_handler'] && is_object(XCMS::$db)) {
		XCMS::closeDatabase();
	}
	else if (XCMS::$rSettings['redis_handler'] && is_object(XCMS::$redis)) {
		XCMS::closeRedis();
	}
}

register_shutdown_function('shutdown');
set_time_limit(0);
require_once 'init.php';
unset(unset(XCMS::$rSettings)['watchdog_data']);
unset(unset(XCMS::$rSettings)['server_hardware']);
header('Access-Control-Allow-Origin: *');

if (!empty(XCMS::$rSettings['send_server_header'])) {
	header('Server: ' . XCMS::$rSettings['send_server_header']);
}

if (XCMS::$rSettings['send_protection_headers']) {
	header('X-XSS-Protection: 0');
	header('X-Content-Type-Options: nosniff');
}

if (XCMS::$rSettings['send_altsvc_header']) {
	header('Alt-Svc: h3-29=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-T051=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q050=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q046=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q043=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,quic=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000; v="46,43"');
}
if (empty(XCMS::$rSettings['send_unique_header_domain']) && !filter_var(HOST, FILTER_VALIDATE_IP)) {
	XCMS::$rSettings['send_unique_header_domain'] = '.' . HOST;
}

if (!empty(XCMS::$rSettings['send_unique_header'])) {
	$rExpires = new DateTime('+6 months', new DateTimeZone('GMT'));
	header('Set-Cookie: ' . XCMS::$rSettings['send_unique_header'] . '=' . XCMS::generateString(11) . '; Domain=' . XCMS::$rSettings['send_unique_header_domain'] . '; Expires=' . $rExpires->format(DATE_RFC2822) . '; Path=/; Secure; HttpOnly; SameSite=none');
}

$rCreateExpiration = 60;
$rProxyID = NULL;
$rIP = XCMS::getUserIP();
$rUserAgent = (empty($_SERVER['HTTP_USER_AGENT']) ? '' : htmlentities(trim($_SERVER['HTTP_USER_AGENT'])));
$rConSpeedFile = NULL;
$rDivergence = 0;
$rCloseCon = false;
$rPID = getmypid();
$rIsMag = false;

if (isset(XCMS::$rRequest['token'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['token'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);

	if (!is_array($rTokenData)) {
		XCMS::clientLog(0, 0, 'LB_TOKEN_INVALID', $rIP);
		generateError('LB_TOKEN_INVALID');
	}
	if (isset($rTokenData['expires']) && ($rTokenData['expires'] < (time() - (int) XCMS::$rServers[SERVER_ID]['time_offset']))) {
		generateError('TOKEN_EXPIRED');
	}

	if (isset($rTokenData['hmac_id'])) {
		$rIsHMAC = $rTokenData['hmac_id'];
		$rIdentifier = $rTokenData['identifier'];
	}
	else {
		$rUsername = $rTokenData['username'];
		$rPassword = $rTokenData['password'];
	}

	$rStreamID = (int) $rTokenData['stream_id'];
	$rExtension = $rTokenData['extension'];
	$rType = $rTokenData['type'];
	$rChannelInfo = $rTokenData['channel_info'];
	$rUserInfo = $rTokenData['user_info'];
	$rActivityStart = $rTokenData['activity_start'];
	$rCountryCode = $rTokenData['country_code'];
	$rIsMag = $rTokenData['is_mag'];
	$rDirectProxy = $rChannelInfo['proxy'] ?: NULL;
	if (!empty($rTokenData['http_range']) && !isset($_SERVER['HTTP_RANGE'])) {
		$_SERVER['HTTP_RANGE'] = $rTokenData['http_range'];
	}
}
else {
	generateError('NO_TOKEN_SPECIFIED');
}

$rRequest = VOD_PATH . $rStreamID . '.' . $rExtension;
if (!file_exists($rRequest) && !$rDirectProxy) {
	generateError('VOD_DOESNT_EXIST');
}

if (XCMS::$rSettings['use_buffer'] == 0) {
	header('X-Accel-Buffering: no');
}

if ($rChannelInfo) {
	if ($rChannelInfo['originator_id']) {
		$rServerID = $rChannelInfo['originator_id'];
		$rProxyID = $rChannelInfo['redirect_id'];
	}
	else {
		$rServerID = $rChannelInfo['redirect_id'] ?: SERVER_ID;
		$rProxyID = NULL;
	}

	if (XCMS::$rSettings['redis_handler']) {
		XCMS::connectRedis();
	}
	else {
		XCMS::connectDatabase();
	}

	if (XCMS::$rSettings['redis_handler']) {
		$rConnection = XCMS::getConnection($rTokenData['uuid']);
	}
	else {
		XCMS::$db->query('SELECT `server_id`, `activity_id`, `pid`, `user_ip` FROM `lines_live` WHERE `uuid` = ?;', $rTokenData['uuid']);

		if (0 < XCMS::$db->num_rows()) {
			$rConnection = XCMS::$db->get_row();
		}
		else if (!empty($_SERVER['HTTP_RANGE'])) {
			if (!$rIsHMAC) {
				XCMS::$db->query('SELECT `server_id`, `activity_id`, `pid`, `user_ip` FROM `lines_live` WHERE `user_id` = ? AND `container` = ? AND `user_agent` = ? AND `stream_id` = ?;', $rUserInfo['id'], 'VOD', $rUserAgent, $rStreamID);
			}
			else {
				XCMS::$db->query('SELECT `server_id`, `activity_id`, `pid`, `user_ip` FROM `lines_live` WHERE `hmac_id` = ? AND `hmac_identifier` = ? AND `container` = ? AND `user_agent` = ? AND `stream_id` = ?;', $rIsHMAC, $rIdentifier, 'VOD', $rUserAgent, $rStreamID);
			}

			if (0 < XCMS::$db->num_rows()) {
				$rConnection = XCMS::$db->get_row();
			}
		}
	}

	if (!$rConnection) {
		if (!file_exists(CONS_TMP_PATH . $rTokenData['uuid']) && ((($rActivityStart + $rCreateExpiration) - (int) XCMS::$rServers[SERVER_ID]['time_offset']) < time())) {
			generateError('TOKEN_EXPIRED');
		}

		if (!$rIsHMAC) {
			if (XCMS::$rSettings['redis_handler']) {
				$rConnectionData = ['user_id' => $rUserInfo['id'], 'stream_id' => $rStreamID, 'server_id' => $rServerID, 'proxy_id' => $rProxyID, 'user_agent' => $rUserAgent, 'user_ip' => $rIP, 'container' => 'VOD', 'pid' => $rPID, 'date_start' => $rActivityStart, 'geoip_country_code' => $rCountryCode, 'isp' => $rUserInfo['con_isp_name'], 'external_device' => '', 'hls_end' => 0, 'hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], 'on_demand' => 0, 'identity' => $rUserInfo['id'], 'uuid' => $rTokenData['uuid']];
				$rResult = XCMS::createConnection($rConnectionData);
			}
			else {
				$rResult = XCMS::$db->query('INSERT INTO `lines_live` (`user_id`,`stream_id`,`server_id`,`proxy_id`,`user_agent`,`user_ip`,`container`,`pid`,`uuid`,`date_start`,`geoip_country_code`,`isp`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?);', $rUserInfo['id'], $rStreamID, $rServerID, $rProxyID, $rUserAgent, $rIP, 'VOD', $rPID, $rTokenData['uuid'], $rActivityStart, $rCountryCode, $rUserInfo['con_isp_name']);
			}
		}
		else if (XCMS::$rSettings['redis_handler']) {
			$rConnectionData = ['hmac_id' => $rIsHMAC, 'hmac_identifier' => $rIdentifier, 'stream_id' => $rStreamID, 'server_id' => $rServerID, 'proxy_id' => $rProxyID, 'user_agent' => $rUserAgent, 'user_ip' => $rIP, 'container' => 'VOD', 'pid' => $rPID, 'date_start' => $rActivityStart, 'geoip_country_code' => $rCountryCode, 'isp' => $rUserInfo['con_isp_name'], 'external_device' => '', 'hls_end' => 0, 'hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], 'on_demand' => 0, 'identity' => $rIsHMAC . '_' . $rIdentifier, 'uuid' => $rTokenData['uuid']];
			$rResult = XCMS::createConnection($rConnectionData);
		}
		else {
			$rResult = XCMS::$db->query('INSERT INTO `lines_live` (`hmac_id`,`hmac_identifier`,`stream_id`,`server_id`,`proxy_id`,`user_agent`,`user_ip`,`container`,`pid`,`uuid`,`date_start`,`geoip_country_code`,`isp`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', $rIsHMAC, $rIdentifier, $rStreamID, $rServerID, $rProxyID, $rUserAgent, $rIP, 'VOD', $rPID, $rTokenData['uuid'], $rActivityStart, $rCountryCode, $rUserInfo['con_isp_name']);
		}
	}
	else {
		$rIPMatch = (XCMS::$rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rConnection['user_ip']), 0, -1)) == implode('.', array_slice(explode('.', $rIP), 0, -1)) : $rIP == $rConnection['user_ip']);
		if (!$rIPMatch && XCMS::$rSettings['restrict_same_ip']) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'IP_MISMATCH', $rIP);
			generateError('IP_MISMATCH');
		}
		if (XCMS::isProcessRunning($rConnection['pid'], 'php-fpm') && ($rPID != $rConnection['pid']) && is_numeric($rConnection['pid']) && (0 < $rConnection['pid'])) {
			if ($rConnection['server_id'] == SERVER_ID) {
				posix_kill((int) $rConnection['pid'], 9);
			}
			else {
				XCMS::$db->query('INSERT INTO `signals` (`pid`,`server_id`,`time`) VALUES(?,?,UNIX_TIMESTAMP())', $rConnection['pid'], $rConnection['server_id']);
			}
		}

		if (XCMS::$rSettings['redis_handler']) {
			$rChanges = ['pid' => $rPID, 'hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset']];

			if ($rConnection = XCMS::updateConnection($rConnection, $rChanges, 'open')) {
				$rResult = true;
			}
			else {
				$rResult = false;
			}
		}
		else {
			$rResult = XCMS::$db->query('UPDATE `lines_live` SET `hls_end` = 0, `pid` = ? WHERE `activity_id` = ?;', $rPID, $rConnection['activity_id']);
		}
	}

	if (!$rResult) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'LINE_CREATE_FAIL', $rIP);
		generateError('LINE_CREATE_FAIL');
	}

	XCMS::validateConnections($rUserInfo, $rIsHMAC, $rIdentifier, $rIP, $rUserAgent);

	if (XCMS::$rSettings['redis_handler']) {
		XCMS::closeRedis();
	}
	else {
		XCMS::closeDatabase();
	}

	$rCloseCon = true;

	if (XCMS::$rSettings['monitor_connection_status']) {
		ob_implicit_flush(true);

		while (ob_get_level()) {
			ob_end_clean();
		}
	}

	touch(CONS_TMP_PATH . $rTokenData['uuid']);

	if (!$rDirectProxy) {
		$rConSpeedFile = DIVERGENCE_TMP_PATH . $rTokenData['uuid'];

		switch ($rChannelInfo['target_container']) {
		case 'mp4':
		case 'm4v':
			header('Content-type: video/mp4');
			break;
		case 'mkv':
			header('Content-type: video/x-matroska');
			break;
		case 'avi':
			header('Content-type: video/x-msvideo');
			break;
		case '3gp':
			header('Content-type: video/3gpp');
			break;
		case 'flv':
			header('Content-type: video/x-flv');
			break;
		case 'wmv':
			header('Content-type: video/x-ms-wmv');
			break;
		case 'mov':
			header('Content-type: video/quicktime');
			break;
		case 'ts':
			header('Content-type: video/mp2t');
			break;
		case 'mpg':
		case 'mpeg':
			header('Content-Type: video/mpeg');
			break;
		default:
			header('Content-Type: application/octet-stream');
		}

		$rDownloadBytes = (!empty($rChannelInfo['bitrate']) ? $rChannelInfo['bitrate'] * 125 : 0);
		$rDownloadBytes += $rDownloadBytes * XCMS::$rSettings['vod_bitrate_plus'] * 0.01;
		$rRequest = VOD_PATH . $rStreamID . '.' . $rExtension;

		if (file_exists($rRequest)) {
			$rFP = @fopen($rRequest, 'rb');
			$rSize = filesize($rRequest);
			$rLength = $rSize;
			$rStart = 0;
			$rEnd = $rSize - 1;
			header('Accept-Ranges: 0-' . $rLength);

			if (!empty($_SERVER['HTTP_RANGE'])) {
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
				fseek($rFP, $rStart);
				header('HTTP/1.1 206 Partial Content');
			}

			header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
			header('Content-Length: ' . $rLength);
			$rLastCheck = $rTimeStart = $rTimeChecked = time();
			$rBytesRead = 0;
			$rBuffer = XCMS::$rSettings['read_buffer_size'];
			$i = 0;
			$o = 0;
			if ((0 < XCMS::$rSettings['vod_limit_perc']) && !$rUserInfo['is_restreamer']) {
				$rLimitAt = (int) ($rLength * (float) (XCMS::$rSettings['vod_limit_perc'] / 100));
			}
			else {
				$rLimitAt = $rLength;
			}

			$rApplyLimit = false;

			while (!feof($rFP) && (($p = ftell($rFP)) <= $rEnd)) {
				$rResponse = stream_get_line($rFP, $rBuffer);
				$i++;
				if (!$rApplyLimit && ($rLimitAt <= $o * $rBuffer)) {
					$rApplyLimit = true;
				}
				else {
					$o++;
				}

				echo $rResponse;
				$rBytesRead += strlen($rResponse);

				if (30 <= time() - $rTimeStart) {
					file_put_contents($rConSpeedFile, (int) ($rBytesRead / 1024 / 30));
					$rTimeStart = time();
					$rBytesRead = 0;
				}
				if ((0 < $rDownloadBytes) && $rApplyLimit && (ceil($rDownloadBytes / $rBuffer) <= $i)) {
					sleep(1);
					$i = 0;
				}
				if (XCMS::$rSettings['monitor_connection_status'] && (5 <= time() - $rTimeChecked)) {
					if (connection_status() != CONNECTION_NORMAL) {
						exit();
					}

					$rTimeChecked = time();
				}

				if (300 <= time() - $rLastCheck) {
					$rLastCheck = time();
					$rConnection = NULL;
					XCMS::$rSettings = XCMS::getCache('settings');

					if (XCMS::$rSettings['redis_handler']) {
						XCMS::connectRedis();
						$rConnection = XCMS::getConnection($rTokenData['uuid']);
						XCMS::closeRedis();
					}
					else {
						XCMS::connectDatabase();
						XCMS::$db->query('SELECT `pid`, `hls_end` FROM `lines_live` WHERE `uuid` = ?', $rTokenData['uuid']);

						if (XCMS::$db->num_rows() == 1) {
							$rConnection = XCMS::$db->get_row();
						}

						XCMS::closeDatabase();
					}
					if (!is_array($rConnection) || ($rConnection['hls_end'] != 0) || ($rPID != $rConnection['pid'])) {
						exit();
					}
				}
			}

			fclose($rFP);
			exit();
		}
	}
	else {
		$rHeaders = get_headers($rDirectProxy, 1);
		$rContentType = (is_array($rHeaders['Content-Type']) ? $rHeaders['Content-Type'][count($rHeaders['Content-Type']) - 1] : $rHeaders['Content-Type']);
		$rSize = $rLength = $rHeaders['Content-Length'];
		if ((0 < $rLength) && in_array($rContentType, ['video/mp4' => true, 'video/x-matroska' => true, 'video/x-msvideo' => true, 'video/3gpp' => true, 'video/x-flv' => true, 'video/x-ms-wmv' => true, 'video/quicktime' => true, 'video/mp2t' => true, 'video/mpeg' => true, 'application/octet-stream' => true])) {
			if ($rHeaders['Location']) {
				$rDirectProxy = $rHeaders['Location'];
			}

			header('Content-Type: ' . $rContentType);
			header('Accept-Ranges: bytes');
			$rStart = 0;
			$rEnd = $rSize - 1;

			if (!empty($_SERVER['HTTP_RANGE'])) {
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
			$ch = curl_init();

			if (isset($_SERVER['HTTP_RANGE'])) {
				preg_match('/bytes=(\\d+)-(\\d+)?/', $_SERVER['HTTP_RANGE'], $rMatches);
				$rOffset = (int) $rMatches[1];
				$rLength = $rSize - $rOffset - 1;
				$rHeaders = ['Range: bytes=' . $rOffset . '-' . ($rOffset + $rLength)];
				curl_setopt($ch, CURLOPT_HTTPHEADER, $rHeaders);
			}

			if (536870912 < $rSize) {
				$rMaxRate = (!empty($rChannelInfo['bitrate']) ? (($rSize * 0.008) / $rChannelInfo['bitrate']) * 125 * 3 : 20971520);

				if ($rMaxRate < 1048576) {
					$rMaxRate = 1048576;
				}

				curl_setopt($ch, CURLOPT_MAX_RECV_SPEED_LARGE, (int) $rMaxRate);
			}

			curl_setopt($ch, CURLOPT_BUFFERSIZE, 10485760);
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 0);
			curl_setopt($ch, CURLOPT_URL, $rDirectProxy);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_NOBODY, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
			curl_exec($ch);
			exit();
		}
		else {
			generateError('VOD_DOESNT_EXIST');
		}
	}
}
else {
	generateError('TOKEN_ERROR');
}

?>