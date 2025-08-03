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
	global $rDeny;
	global $rIP;

	if ($rDeny) {
		XCMS::checkFlood($rIP);
	}

	if (is_object($db)) {
		$db->close_mysql();
	}
}
if (($_GET['addr'] == '127.0.0.1') && ($_GET['call'] == 'publish')) {
	http_response_code(200);
	exit();
}

register_shutdown_function('shutdown');
set_time_limit(0);
require_once 'init.php';
error_reporting(0);
ini_set('display_errors', 0);
$rAllowed = XCMS::getAllowedRTMP();
$rDeny = true;

if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
	generate404();
}

$rIP = XCMS::$rRequest['addr'];
$rStreamID = (int) XCMS::$rRequest['name'];
$rRestreamDetect = false;

foreach (getallheaders() as $rKey => $rValue) {
	if (strtoupper($rKey) == 'X-XCMS-DETECT') {
		$rRestreamDetect = true;
	}
}

if (XCMS::$rRequest['call'] == 'publish') {
	if ((XCMS::$rRequest['password'] == XCMS::$rSettings['live_streaming_pass']) || (isset($rAllowed[$rIP]) && $rAllowed[$rIP]['push'] && (($rAllowed[$rIP]['password'] == XCMS::$rRequest['password']) || !$rAllowed[$rIP]['password']))) {
		$rDeny = false;
		http_response_code(200);
		exit();
	}
	else {
		http_response_code(404);
		exit();
	}
}

if (XCMS::$rRequest['call'] == 'play_done') {
	$rDeny = false;

	if (XCMS::$rSettings['redis_handler']) {
		XCMS::closeConnection(md5(XCMS::$rRequest['clientid']));
	}
	else {
		XCMS::closeRTMP(XCMS::$rRequest['clientid']);
	}

	http_response_code(200);
	exit();
}
if ((XCMS::$rRequest['password'] == XCMS::$rSettings['live_streaming_pass']) || (isset($rAllowed[$rIP]) && $rAllowed[$rIP]['pull'] && (($rAllowed[$rIP]['password'] == XCMS::$rRequest['password']) || !$rAllowed[$rIP]['password']))) {
	$rDeny = false;
	$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id AND t2.server_id = ? WHERE t1.`id` = ?', SERVER_ID, $rStreamID);
	$rChannelInfo = $db->get_row();

	if ($rChannelInfo) {
		if (!XCMS::isStreamRunning($rChannelInfo['pid'], $rStreamID)) {
			if ($rChannelInfo['on_demand'] == 1) {
				if (!XCMS::isMonitorRunning($rChannelInfo['monitor_pid'], $rStreamID)) {
					XCMS::startMonitor($rStreamID);
					sleep(5);
				}
			}
			else {
				http_response_code(404);
				exit();
			}
		}
	}
	else {
		http_response_code(200);
		exit();
	}

	http_response_code(200);
	exit();
}
if (!isset(XCMS::$rRequest['tcurl']) || !isset(XCMS::$rRequest['app'])) {
	http_response_code(404);
	exit();
}

if (isset(XCMS::$rRequest['token'])) {
	if (!ctype_xdigit(XCMS::$rRequest['token'])) {
		$rTokenData = explode('/', Xcms\Functions::decrypt(XCMS::$rRequest['token'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA));
		$rUsername = $rTokenData[0];
		$rPassword = $rTokenData[1];
		$rUserInfo = XCMS::getUserInfo(NULL, $rUsername, $rPassword, true, false, $rIP);
	}
	else {
		$rAccessToken = XCMS::$rRequest['token'];
		$rUserInfo = XCMS::getUserInfo(NULL, $rAccessToken, NULL, true, false, $rIP);
	}
}
else {
	$rUsername = XCMS::$rRequest['username'];
	$rPassword = XCMS::$rRequest['password'];
	$rUserInfo = XCMS::getUserInfo(NULL, $rUsername, $rPassword, true, false, $rIP);
}

$rExtension = 'rtmp';
$rExternalDevice = '';

if ($rUserInfo) {
	$rDeny = false;
	if (!is_null($rUserInfo['exp_date']) && ($rUserInfo['exp_date'] <= time())) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_EXPIRED', $rIP);
		http_response_code(404);
		exit();
	}

	if ($rUserInfo['admin_enabled'] == 0) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_BAN', $rIP);
		http_response_code(404);
		exit();
	}

	if ($rUserInfo['enabled'] == 0) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_DISABLED', $rIP);
		http_response_code(404);
		exit();
	}
	if (!empty($rUserInfo['allowed_ips']) && !in_array($rIP, array_map('gethostbyname', $rUserInfo['allowed_ips']))) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'IP_BAN', $rIP);
		http_response_code(404);
		exit();
	}

	$rCountryCode = XCMS::getIPInfo($rIP)['country']['iso_code'];

	if (!empty($rCountryCode)) {
		$rForceCountry = !empty($rUserInfo['forced_country']);
		if ($rForceCountry && ($rUserInfo['forced_country'] != 'ALL') && ($rCountryCode != $rUserInfo['forced_country'])) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'COUNTRY_DISALLOW', $rIP);
			http_response_code(404);
			exit();
		}
		if (!$rForceCountry && !in_array('ALL', XCMS::$rSettings['allow_countries']) && !in_array($rCountryCode, XCMS::$rSettings['allow_countries'])) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'COUNTRY_DISALLOW', $rIP);
			http_response_code(404);
			exit();
		}
	}

	if (isset($rUserInfo['ip_limit_reached'])) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_ALREADY_CONNECTED', $rIP);
		http_response_code(404);
		exit();
	}

	if (!in_array($rExtension, $rUserInfo['output_formats'])) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_DISALLOW_EXT', $rIP);
		http_response_code(404);
		exit();
	}

	if (!in_array($rStreamID, $rUserInfo['channel_ids'])) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'NOT_IN_BOUQUET', $rIP);
		http_response_code(404);
		exit();
	}

	if ($rUserInfo['isp_violate'] == 1) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'ISP_LOCK_FAILED', $rIP, json_encode(['old' => $rUserInfo['isp_desc'], 'new' => $rUserInfo['con_isp_name']]));
		http_response_code(404);
		exit();
	}
	if (($rUserInfo['isp_is_server'] == 1) && !$rUserInfo['is_restreamer']) {
		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'BLOCKED_ASN', $rIP, json_encode(['user_agent' => '', 'isp' => $rUserInfo['con_isp_name'], 'asn' => $rUserInfo['isp_asn']]), true);
		http_response_code(404);
		exit();
	}
	if ($rRestreamDetect && !$rUserInfo['is_restreamer']) {
		if (XCMS::$rSettings['detect_restream_block_user']) {
			$db->query('UPDATE `lines` SET `admin_enabled` = 0 WHERE `id` = ?;', $rUserInfo['id']);
		}

		XCMS::clientLog($rStreamID, $rUserInfo['id'], 'RESTREAM_DETECT', $rIP);
		http_response_code(404);
		exit();
	}

	if ($rChannelInfo = XCMS::redirectStream($rStreamID, $rExtension, $rUserInfo, $rCountryCode, $rUserInfo['con_isp_name'], 'live')) {
		if (!$rChannelInfo['redirect_id'] || ($rChannelInfo['redirect_id'] == SERVER_ID)) {
			if (!XCMS::isStreamRunning($rChannelInfo['pid'], $rStreamID)) {
				if ($rChannelInfo['on_demand'] == 1) {
					if (!XCMS::isMonitorRunning($rChannelInfo['monitor_pid'], $rStreamID)) {
						XCMS::startMonitor($rStreamID);
						sleep(5);
					}
				}
				else {
					http_response_code(404);
					exit();
				}
			}

			if (XCMS::$rSettings['redis_handler']) {
				XCMS::connectRedis();
				$rConnectionData = ['user_id' => $rUserInfo['id'], 'stream_id' => $rStreamID, 'server_id' => SERVER_ID, 'proxy_id' => 0, 'user_agent' => '', 'user_ip' => $rIP, 'container' => $rExtension, 'pid' => XCMS::$rRequest['clientid'], 'date_start' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], 'geoip_country_code' => $rCountryCode, 'isp' => $rUserInfo['con_isp_name'], 'external_device' => $rExternalDevice, 'hls_end' => 0, 'hls_last_read' => time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'], 'on_demand' => $rChannelInfo['on_demand'], 'identity' => $rUserInfo['id'], 'uuid' => md5(XCMS::$rRequest['clientid'])];
				$rResult = XCMS::createConnection($rConnectionData);
			}
			else {
				$rResult = $db->query('INSERT INTO `lines_live` (`user_id`,`stream_id`,`server_id`,`proxy_id`,`user_agent`,`user_ip`,`container`,`pid`,`uuid`,`date_start`,`geoip_country_code`,`isp`,`external_device`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', $rUserInfo['id'], $rStreamID, SERVER_ID, 0, '', $rIP, $rExtension, XCMS::$rRequest['clientid'], md5(XCMS::$rRequest['clientid']), time(), $rCountryCode, $rUserInfo['con_isp_name'], $rExternalDevice);
			}

			if (!$rResult) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'LINE_CREATE_FAIL', $rIP);
				http_response_code(404);
				exit();
			}

			XCMS::validateConnections($rUserInfo, false, '', $rIP, NULL);
			http_response_code(200);
			exit();
		}
		else {
			http_response_code(404);
			exit();
		}
	}
}
else {
	if (isset($rUsername)) {
		XCMS::checkBruteforce($rIP, NULL, $rUsername);
	}

	XCMS::clientLog($rStreamID, 0, 'AUTH_FAILED', $rIP);
}

http_response_code(404);
exit();

?>