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

	foreach ($rQueue as $rItem) {
		$rLength += $rItem['filesize'];
	}

	return $rLength;
}

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
$rStartTime = time();

if (isset(XCMS::$rRequest['token'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['token'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);

	if (!is_array($rTokenData)) {
		XCMS::clientLog(0, 0, 'LB_TOKEN_INVALID', $rIP);
		generateError('LB_TOKEN_INVALID');
	}
	if (isset($rTokenData['expires']) && ($rTokenData['expires'] < (time() - (int) XCMS::$rServers[SERVER_ID]['time_offset']))) {
		generateError('TOKEN_EXPIRED');
	}

	$rUsername = $rTokenData['username'];
	$rPassword = $rTokenData['password'];
	$rStreamID = $rTokenData['stream'];
	$rExtension = $rTokenData['extension'];
	$rStartDate = $rTokenData['start'];
	$rDuration = $rTokenData['duration'];
	$rRedirectID = $rTokenData['redirect_id'];
	$rOriginatorID = $rTokenData['originator_id'] ?: NULL;
	$rUserInfo = $rTokenData['user_info'];
	$rActivityStart = $rTokenData['activity_start'];
	$rCountryCode = $rTokenData['country_code'];
	if (!empty($rTokenData['http_range']) && !isset($_SERVER['HTTP_RANGE'])) {
		$_SERVER['HTTP_RANGE'] = $rTokenData['http_range'];
	}
}
else {
	generateError('NO_TOKEN_SPECIFIED');
}

if (XCMS::$rSettings['use_buffer'] == 0) {
	header('X-Accel-Buffering: no');
}

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

$rFile = ARCHIVE_PATH . $rStreamID . '/' . gmdate('Y-m-d:H-i', $rTimestamp) . '.ts';
if (empty($rStreamID) || empty($rTimestamp) || empty($rDuration)) {
	generateError('NO_TIMESTAMP');
}
if (!file_exists($rFile) || !is_readable($rFile)) {
	generateError('ARCHIVE_DOESNT_EXIST');
}

$rQueue = [];

for ($i = 0; $i < $rDuration; $i++) {
	$rFile = ARCHIVE_PATH . $rStreamID . '/' . gmdate('Y-m-d:H-i', $rTimestamp + ($i * 60)) . '.ts';

	if (file_exists($rFile)) {
		$rQueue[] = ['filename' => $rFile, 'filesize' => filesize($rFile)];
	}
}

if (count($rQueue) == 0) {
	generateError('ARCHIVE_DOESNT_EXIST');
}

if ($rUserInfo) {
	$rOffset = (file_exists($rQueue[0]['filename'] . '.offset') ? (int) file_get_contents($rQueue[0]['filename'] . '.offset') : 0);

	if ($rOriginatorID) {
		$rServerID = $rOriginatorID;
		$rProxyID = $rRedirectID;
	}
	else {
		$rProxyID = NULL;
		$rServerID = $rRedirectID ?: SERVER_ID;
	}

	if (XCMS::$rSettings['redis_handler']) {
		XCMS::connectRedis();
	}
	else {
		XCMS::connectDatabase();
	}

	switch ($rExtension) {
	case 'm3u8':
		if (XCMS::$rSettings['redis_handler']) {
			$rConnection = XCMS::getConnection($rTokenData['uuid']);
		}
		else {
			XCMS::$db->query('SELECT `activity_id`, `pid`, `user_ip` FROM `lines_live` WHERE `uuid` = ?;', $rTokenData['uuid']);

			if (0 < XCMS::$db->num_rows()) {
				$rConnection = XCMS::$db->get_row();
			}
		}

		if (!$rConnection) {
			if (!file_exists(CONS_TMP_PATH . $rTokenData['uuid']) && ((($rActivityStart + $rCreateExpiration) - (int) XCMS::$rServers[SERVER_ID]['time_offset']) < time())) {
				generateError('TOKEN_EXPIRED');
			}

			if (XCMS::$rSettings['redis_handler']) {
				$rConnectionData = ['user_id' => $rUserInfo['id'], 'stream_id' => $rStreamID, 'server_id' => $rServerID, 'proxy_id' => $rProxyID, 'user_agent' => $rUserAgent, 'user_ip' => $rIP, 'container' => 'hls', 'pid' => NULL, 'date_start' => $rActivityStart, 'geoip_country_code' => $rCountryCode, 'isp' => $rUserInfo['con_isp_name'], 'external_device' => '', 'hls_end' => 0, 'hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], 'on_demand' => 0, 'identity' => $rUserInfo['id'], 'uuid' => $rTokenData['uuid']];
				$rResult = XCMS::createConnection($rConnectionData);
			}
			else {
				$rResult = XCMS::$db->query('INSERT INTO `lines_live` (`user_id`,`stream_id`,`server_id`,`proxy_id`,`user_agent`,`user_ip`,`container`,`pid`,`uuid`,`date_start`,`geoip_country_code`,`isp`,`external_device`,`hls_last_read`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?);', $rUserInfo['id'], $rStreamID, $rServerID, $rProxyID, $rUserAgent, $rIP, 'hls', NULL, $rTokenData['uuid'], $rActivityStart, $rCountryCode, $rUserInfo['con_isp_name'], '', time() - (int) XCMS::$rServers[SERVER_ID]['time_offset']);
			}
		}
		else {
			$rIPMatch = (XCMS::$rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rConnection['user_ip']), 0, -1)) == implode('.', array_slice(explode('.', $rIP), 0, -1)) : $rIP == $rConnection['user_ip']);
			if (!$rIPMatch && XCMS::$rSettings['restrict_same_ip']) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'IP_MISMATCH', $rIP);
				generateError('IP_MISMATCH');
			}

			if (XCMS::$rSettings['redis_handler']) {
				$rChanges = ['hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset']];

				if ($rConnection = XCMS::updateConnection($rConnection, $rChanges, 'open')) {
					$rResult = true;
				}
				else {
					$rResult = false;
				}
			}
			else {
				$rResult = XCMS::$db->query('UPDATE `lines_live` SET `hls_last_read` = ?, `hls_end` = 0 WHERE `activity_id` = ?', time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], $rConnection['activity_id']);
			}
		}

		if (!$rResult) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'LINE_CREATE_FAIL', $rIP);
			generateError('LINE_CREATE_FAIL');
		}

		XCMS::validateConnections($rUserInfo, NULL, NULL, $rIP, $rUserAgent);

		if (XCMS::$rSettings['redis_handler']) {
			XCMS::closeRedis();
		}
		else {
			XCMS::closeDatabase();
		}

		touch(CONS_TMP_PATH . $rTokenData['uuid']);
		$rOutput = '#EXTM3U' . "\n";
		$rOutput .= '#EXT-X-VERSION:3' . "\n";
		$rOutput .= '#EXT-X-TARGETDURATION:60' . "\n";
		$rOutput .= '#EXT-X-MEDIA-SEQUENCE:0' . "\n";
		$rOutput .= '#EXT-X-PLAYLIST-TYPE:VOD' . "\n";

		for ($i = 0; $i < count($rQueue); $i++) {
			$rOutput .= '#EXTINF:60.0,' . "\n";
			$rOutput .= ($rProxyID ? '/' . md5($rProxyID . '_' . $rServerID . '_' . OPENSSL_EXTRA) : '') . '/hls/' . Xcms\Functions::encrypt('TS/' . $rUsername . '/' . $rPassword . '/' . $rIP . '/' . $rDuration . '/' . $rStartDate . '/' . $rStreamID . '_' . basename($rQueue[$i]['filename']) . '_' . ($i == 0 ? $rOffset : 0) . '/' . $rTokenData['uuid'] . '/' . $rServerID, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA) . "\n";
		}

		$rOutput .= '#EXT-X-ENDLIST';
		touch(CONS_TMP_PATH . $rTokenData['uuid']);
		ob_end_clean();
		header('Content-Type: application/x-mpegurl');
		header('Content-Length: ' . strlen($rOutput));
		echo $rOutput;
		exit();
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
			XCMS::$db->query('SELECT `server_id`, `activity_id`, `pid`, `user_ip` FROM `lines_live` WHERE `hmac_id` = ? AND `hmac_identifier` = ? AND `container` = ? AND `user_agent` = ? AND `stream_id` = ?;', $rIsHMAC, $rIdentifier, 'hls', $rUserAgent, $rStreamID);

			if (0 < XCMS::$db->num_rows()) {
				$rConnection = XCMS::$db->get_row();
			}
		}
	}

	if (!$rConnection) {
		if (!file_exists(CONS_TMP_PATH . $rTokenData['uuid']) && ((($rActivityStart + $rCreateExpiration) - (int) XCMS::$rServers[SERVER_ID]['time_offset']) < time())) {
			generateError('TOKEN_EXPIRED');
		}

		if (XCMS::$rSettings['redis_handler']) {
			$rConnectionData = ['user_id' => $rUserInfo['id'], 'stream_id' => $rStreamID, 'server_id' => $rServerID, 'proxy_id' => $rProxyID, 'user_agent' => $rUserAgent, 'user_ip' => $rIP, 'container' => $rExtension, 'pid' => $rPID, 'date_start' => $rActivityStart, 'geoip_country_code' => $rCountryCode, 'isp' => $rUserInfo['con_isp_name'], 'external_device' => '', 'hls_end' => 0, 'hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], 'on_demand' => 0, 'identity' => $rUserInfo['id'], 'uuid' => $rTokenData['uuid']];
			$rResult = XCMS::createConnection($rConnectionData);
		}
		else {
			$rResult = XCMS::$db->query('INSERT INTO `lines_live` (`user_id`,`stream_id`,`server_id`,`proxy_id`,`user_agent`,`user_ip`,`container`,`pid`,`uuid`,`date_start`,`geoip_country_code`,`isp`,`external_device`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', $rUserInfo['id'], $rStreamID, $rServerID, $rProxyID, $rUserAgent, $rIP, $rExtension, $rPID, $rTokenData['uuid'], $rActivityStart, $rCountryCode, $rUserInfo['con_isp_name'], '');
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

	XCMS::validateConnections($rUserInfo, NULL, NULL, $rIP, $rUserAgent);

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
	header('Content-Type: video/mp2t');
	$rConSpeedFile = DIVERGENCE_TMP_PATH . $rTokenData['uuid'];
	$rLength = $rSize = getlength($rQueue) - $rOffset;
	$rBitrate = ($rSize * 0.008) / ($rDuration * 60);
	header('Accept-Ranges: 0-' . $rLength);
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
	$rStartFrom = 0;

	if (0 < $rStart) {
		$rStartFrom = floor($rStart / ($rSize / count($rQueue)));
	}

	$rFirstFile = false;
	$rSeekTo = 0;
	$rSizeToDate = 0;
	$rDownloadBytes = $rBitrate * 125;
	$rDownloadBytes += $rDownloadBytes * XCMS::$rSettings['vod_bitrate_plus'] * 0.01;
	$rLastCheck = $rTimeChecked = $rTimeStart = time();
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
		fseek($rFP, $rSeekTo + $rOffset);
		$rOffset = 0;

		while (!feof($rFP)) {
			$rPosition = ftell($rFP);
			$rResponse = stream_get_line($rFP, $rBuffer);
			echo $rResponse;
			$rBytesRead += strlen($rResponse);
			$i++;
			if (!$rApplyLimit && ($rLimitAt <= $o * $rBuffer)) {
				$rApplyLimit = true;
			}
			else {
				$o++;
			}
			if ((0 < $rDownloadBytes) && $rApplyLimit && (ceil($rDownloadBytes / $rBuffer) <= $i)) {
				sleep(1);
				$i = 0;
			}

			if (30 <= time() - $rTimeStart) {
				file_put_contents($rConSpeedFile, (int) ($rBytesRead / 1024 / 30));
				$rTimeStart = time();
				$rBytesRead = 0;
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

		if (is_resource($rFP)) {
			fclose($rFP);
		}

		$rSeekTo = 0;
	}
}
else {
	generateError('TOKEN_ERROR');
}

?>