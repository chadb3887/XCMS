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
	global $db;

	if ($rDeny) {
		XCMS::checkFlood();
	}

	if (is_object($db)) {
		$db->close_mysql();
	}
}

header('Cache-Control: no-store, no-cache, must-revalidate');
require_once 'init.php';
if (($rSettings['enable_cache'] && !file_exists(CACHE_TMP_PATH . 'cache_complete')) || empty($rSettings['live_streaming_pass'])) {
	generateError('CACHE_INCOMPLETE');
}

$rIsMag = false;
$rMagToken = NULL;
if (isset($_GET['token']) && !ctype_xdigit($_GET['token'])) {
	$rData = explode('/', Xcms\Functions::decrypt($_GET['token'], $rSettings['live_streaming_pass'], OPENSSL_EXTRA));
	$_GET['type'] = $rData[0];
	$rTypeSplit = explode('::', $_GET['type']);

	if (count($rTypeSplit) == 2) {
		$_GET['type'] = $rTypeSplit[1];
		$rIsMag = true;
	}

	if ($_GET['type'] == 'timeshift') {
		$_GET['username'] = $rData[1];
		$_GET['password'] = $rData[2];
		$_GET['duration'] = $rData[3];
		$_GET['start'] = $rData[4];
		$_GET['stream'] = $rData[5];

		if ($rIsMag) {
			$rMagToken = $rData[6];
		}

		$_GET['extension'] = 'ts';
	}
	else {
		$_GET['username'] = $rData[1];
		$_GET['password'] = $rData[2];
		$_GET['stream'] = $rData[3];

		if (5 <= count($rData)) {
			$_GET['extension'] = $rData[4];
		}

		if (count($rData) == 6) {
			if ($rIsMag) {
				$rMagToken = $rData[5];
			}
			else {
				$rExpiry = $rData[5];
			}
		}

		if (!isset($_GET['extension'])) {
			$_GET['extension'] = 'ts';
		}
	}

	unset($_GET['token']);
	unset($rData);
}

if (isset($_GET['utc'])) {
	$_GET['type'] = 'timeshift';
	$_GET['start'] = $_GET['utc'];
	$_GET['duration'] = 21600;
	unset($_GET['utc']);
}

$rType = $_GET['type'] ?? 'live';
$rStreamID = (int) $_GET['stream'];
$rExtension = (isset($_GET['extension']) ? strtolower(preg_replace('/[^A-Za-z0-9 ]/', '', trim($_GET['extension']))) : NULL);
if (!$rExtension && in_array($rType, ['movie' => true, 'series' => true, 'subtitle' => true])) {
	$rStream = pathinfo($_GET['stream']);
	$rStreamID = (int) $rStream['filename'];
	$rExtension = strtolower(preg_replace('/[^A-Za-z0-9 ]/', '', trim($rStream['extension'])));
}

if (!$rExtension) {
	switch ($rType) {
	case 'timeshift':
	case 'live':
		$rExtension = 'ts';
		break;
	case 'series':
	case 'movie':
		$rExtension = 'mp4';
		break;
	}
}
if (!$rStreamID || ($rSettings['enable_cache'] && !file_exists(STREAMS_TMP_PATH . 'stream_' . $rStreamID))) {
	generateError('INVALID_STREAM_ID');
}
if ($rSettings['ignore_invalid_users'] && $rSettings['enable_cache']) {
	if (isset($_GET['token'])) {
		if (!file_exists(LINES_TMP_PATH . 'line_t_' . $_GET['token'])) {
			generateError('INVALID_CREDENTIALS');
		}
	}
	else if (isset($_GET['username']) && isset($_GET['password'])) {
		if ($rSettings['case_sensitive_line']) {
			$rPath = LINES_TMP_PATH . 'line_c_' . $_GET['username'] . '_' . $_GET['password'];
		}
		else {
			$rPath = LINES_TMP_PATH . 'line_c_' . strtolower($_GET['username']) . '_' . strtolower($_GET['password']);
		}

		if (!file_exists($rPath)) {
			generateError('INVALID_CREDENTIALS');
		}
	}
}

require_once INCLUDES_PATH . 'streaming.php';
XCMS::$rAccess = 'auth';
XCMS::$rSettings = $rSettings;
XCMS::init(false);

if (!XCMS::$rCached) {
	XCMS::connectDatabase();
	$db = &XCMS::$db;
}
if ($rSettings['enable_cache'] && !$rSettings['show_not_on_air_video'] && file_exists(CACHE_TMP_PATH . 'servers')) {
	$rServers = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'servers'));
	$rStream = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . $rStreamID)) ?: NULL;
	$rAvailableServers = [];

	if ($rType == 'archive') {
		if ((0 < $rStream['info']['tv_archive_duration']) && (0 < $rStream['info']['tv_archive_server_id']) && array_key_exists($rStream['info']['tv_archive_server_id'], $rServers) && $rServers[rStream['info']['tv_archive_server_id']]['server_online']) {
			$rAvailableServers[] = [$rStream['info']['tv_archive_server_id']];
		}
	}
	else {
		if (($rStream['info']['direct_source'] == 1) && ($rStream['info']['direct_proxy'] == 0)) {
			$rAvailableServers[] = $rServerID;
		}

		foreach ($rServers as $rServerID => $rServerInfo) {
			if (!array_key_exists($rServerID, $rStream['servers']) || !$rServerInfo['server_online'] || ($rServerInfo['server_type'] != 0)) {
				continue;
			}

			if (isset($rStream['servers'][$rServerID])) {
				if ($rType == 'movie') {
					if (((!empty($rStream['servers'][$rServerID]['pid']) && ($rStream['servers'][$rServerID]['to_analyze'] == 0) && ($rStream['servers'][$rServerID]['stream_status'] == 0)) || (($rStream['info']['direct_source'] == 1) && ($rStream['info']['direct_proxy'] == 1))) && (($rExtension == $rStream['info']['target_container']) || ($rExtension = 'srt')) && ($rServerInfo['timeshift_only'] == 0)) {
						$rAvailableServers[] = $rServerID;
					}
				}
				else if ((((($rStream['servers'][$rServerID]['on_demand'] == 1) && ($rStream['servers'][$rServerID]['stream_status'] != 1)) || ((0 < $rStream['servers'][$rServerID]['pid']) && ($rStream['servers'][$rServerID]['stream_status'] == 0))) && ($rStream['servers'][$rServerID]['to_analyze'] == 0) && ((int) $rStream['servers'][$rServerID]['delay_available_at'] <= time()) && ($rServerInfo['timeshift_only'] == 0)) || (($rStream['info']['direct_source'] == 1) && ($rStream['info']['direct_proxy'] == 1))) {
					$rAvailableServers[] = $rServerID;
				}
			}
		}
	}

	if (count($rAvailableServers) == 0) {
		XCMS::showVideoServer('show_not_on_air_video', 'not_on_air_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
	}
}

header('Access-Control-Allow-Origin: *');
register_shutdown_function('shutdown');
$rRestreamDetect = false;
$rPrebuffer = isset(XCMS::$rRequest['prebuffer']);

foreach (getallheaders() as $rKey => $rValue) {
	if (strtoupper($rKey) == 'X-XCMS-DETECT') {
		$rRestreamDetect = true;
	}
	else if (strtoupper($rKey) == 'X-XCMS-PREBUFFER') {
		$rPrebuffer = true;
	}
}

$rIsEnigma = false;
$rUserInfo = NULL;
$rIsHMAC = NULL;
$rIdentifier = '';
$rPID = getmypid();
$rUUID = md5(uniqid());
$rIP = XCMS::getUserIP();
$rCountryCode = XCMS::getIPInfo($rIP)['country']['iso_code'];
$rUserAgent = (empty($_SERVER['HTTP_USER_AGENT']) ? '' : htmlentities(trim($_SERVER['HTTP_USER_AGENT'])));
$rDeny = true;
$rExternalDevice = NULL;
$rActivityStart = time();

if (!isset($rExpiry)) {
	$rExpiry = NULL;
}

if (isset(XCMS::$rRequest['token'])) {
	$rAccessToken = XCMS::$rRequest['token'];
	$rUserInfo = XCMS::getUserInfo(NULL, $rAccessToken, NULL, false, false, $rIP);
}
else if (isset(XCMS::$rRequest['hmac'])) {
	if (!in_array($rType, ['live' => true, 'movie' => true, 'series' => true])) {
		$rDeny = false;
		generateError('INVALID_TYPE_TOKEN');
	}

	$rIdentifier = (empty(XCMS::$rRequest['identifier']) ? '' : XCMS::$rRequest['identifier']);
	$rHMACIP = (empty(XCMS::$rRequest['ip']) ? '' : XCMS::$rRequest['ip']);
	$rMaxConnections = (isset(XCMS::$rRequest['max']) ? (int) XCMS::$rRequest['max'] : 0);
	$rExpiry = (isset(XCMS::$rRequest['expiry']) ? XCMS::$rRequest['expiry'] : NULL);
	if ($rExpiry && ($rExpiry < time())) {
		$rDeny = false;
		generateError('TOKEN_EXPIRED');
	}

	$rIsHMAC = XCMS::validateHMAC(XCMS::$rRequest['hmac'], $rExpiry, $rStreamID, $rExtension, $rIP, $rHMACIP, $rIdentifier, $rMaxConnections);

	if ($rIsHMAC) {
		$rUserInfo = ['id' => NULL, 'is_restreamer' => 0, 'force_server_id' => 0, 'con_isp_name' => NULL, 'max_connections' => $rMaxConnections];

		if (XCMS::$rSettings['show_isps']) {
			$rISPLock = XCMS::getISP($rIP);

			if (is_array($rISPLock)) {
				$rUserInfo['con_isp_name'] = $rISPLock['isp'];
			}
		}
	}
}
else {
	$rUsername = XCMS::$rRequest['username'];
	$rPassword = XCMS::$rRequest['password'];
	$rUserInfo = XCMS::getUserInfo(NULL, $rUsername, $rPassword, false, false, $rIP);
}
if ($rUserInfo || $rIsHMAC) {
	$rDeny = false;
	XCMS::checkAuthFlood($rUserInfo, $rIP);
	if (XCMS::$rServers[SERVER_ID]['enable_proxy'] && !XCMS::isProxy($_SERVER['HTTP_X_IP']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
		generateError('PROXY_ACCESS_DENIED');
	}

	if ($rUserInfo['is_e2']) {
		$rIsEnigma = true;
	}

	if (isset($rAccessToken)) {
		$rUsername = $rUserInfo['username'];
		$rPassword = $rUserInfo['password'];
	}

	if (!$rIsHMAC) {
		if (!is_null($rUserInfo['exp_date']) && ($rUserInfo['exp_date'] <= time())) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_EXPIRED', $rIP);

			if (in_array($rType, ['live' => true, 'timeshift' => true])) {
				XCMS::showVideoServer('show_expired_video', 'expired_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
			}
			else if (in_array($rType, ['movie' => true, 'series' => true])) {
				XCMS::showVideoServer('show_expired_video', 'expired_video_path', 'ts', $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
			}
			else {
				generateError('EXPIRED');
			}
		}

		if ($rUserInfo['admin_enabled'] == 0) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_BAN', $rIP);

			if (in_array($rType, ['live' => true, 'timeshift' => true])) {
				XCMS::showVideoServer('show_banned_video', 'banned_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
			}
			else if (in_array($rType, ['movie' => true, 'series' => true])) {
				XCMS::showVideoServer('show_banned_video', 'banned_video_path', 'ts', $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
			}
			else {
				generateError('BANNED');
			}
		}

		if ($rUserInfo['enabled'] == 0) {
			XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_DISABLED', $rIP);

			if (in_array($rType, ['live' => true, 'timeshift' => true])) {
				XCMS::showVideoServer('show_banned_video', 'banned_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
			}
			else if (in_array($rType, ['movie' => true, 'series' => true])) {
				XCMS::showVideoServer('show_banned_video', 'banned_video_path', 'ts', $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
			}
			else {
				generateError('DISABLED');
			}
		}

		if ($rType != 'subtitle') {
			if ($rUserInfo['bypass_ua'] == 0) {
				if (XCMS::checkBlockedUAs($rUserAgent)) {
					generateError('BLOCKED_USER_AGENT');
				}
			}
			if (empty($rUserAgent) && XCMS::$rSettings['disallow_empty_user_agents']) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'EMPTY_UA', $rIP);
				generateError('EMPTY_USER_AGENT');
			}
			if (!empty($rUserInfo['allowed_ips']) && !in_array($rIP, array_map('gethostbyname', $rUserInfo['allowed_ips']))) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'IP_BAN', $rIP);
				generateError('NOT_IN_ALLOWED_IPS');
			}

			if (!empty($rCountryCode)) {
				$rForceCountry = !empty($rUserInfo['forced_country']);
				if ($rForceCountry && ($rUserInfo['forced_country'] != 'ALL') && ($rCountryCode != $rUserInfo['forced_country'])) {
					XCMS::clientLog($rStreamID, $rUserInfo['id'], 'COUNTRY_DISALLOW', $rIP);
					generateError('FORCED_COUNTRY_INVALID');
				}
				if (!$rForceCountry && !in_array('ALL', XCMS::$rSettings['allow_countries']) && !in_array($rCountryCode, XCMS::$rSettings['allow_countries'])) {
					XCMS::clientLog($rStreamID, $rUserInfo['id'], 'COUNTRY_DISALLOW', $rIP);
					generateError('NOT_IN_ALLOWED_COUNTRY');
				}
			}
			if (!empty($rUserInfo['allowed_ua']) && !in_array($rUserAgent, $rUserInfo['allowed_ua'])) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_AGENT_BAN', $rIP);
				generateError('NOT_IN_ALLOWED_UAS');
			}

			if ($rUserInfo['isp_violate']) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'ISP_LOCK_FAILED', $rIP, json_encode(['old' => $rUserInfo['isp_desc'], 'new' => $rUserInfo['con_isp_name']]));
				generateError('ISP_BLOCKED');
			}
			if ($rUserInfo['isp_is_server'] && !$rUserInfo['is_restreamer']) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'BLOCKED_ASN', $rIP, json_encode(['user_agent' => $rUserAgent, 'isp' => $rUserInfo['con_isp_name'], 'asn' => $rUserInfo['isp_asn']]), true);
				generateError('ASN_BLOCKED');
			}
			if ($rUserInfo['is_mag'] && !$rIsMag) {
				generateError('DEVICE_NOT_ALLOWED');
			}
			else if ($rIsMag && !XCMS::$rSettings['disable_mag_token'] && (!$rMagToken || ($rMagToken != $rUserInfo['mag_token']))) {
				generateError('TOKEN_EXPIRED');
			}
			else if ($rExpiry && ($rExpiry < time())) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'TOKEN_EXPIRED', $rIP);
				generateError('TOKEN_EXPIRED');
			}
		}
		if ($rUserInfo['is_stalker'] && in_array($rType, ['live' => true, 'movie' => true, 'series' => true, 'timeshift' => true])) {
			if (empty(XCMS::$rRequest['stalker_key']) || ($rExtension != 'ts')) {
				generateError('STALKER_INVALID_KEY');
			}

			$rStalkerKey = base64_decode(urldecode(XCMS::$rRequest['stalker_key']));

			if ($rDecryptKey = XCMS::mc_decrypt($rStalkerKey, md5(XCMS::$rSettings['live_streaming_pass']))) {
				$rStalkerData = explode('=', $rDecryptKey);

				if ($rStreamID != $rStalkerData[2]) {
					XCMS::clientLog($rStreamID, $rUserInfo['id'], 'STALKER_CHANNEL_MISMATCH', $rIP);
					generateError('STALKER_CHANNEL_MISMATCH');
				}

				$rIPMatch = (XCMS::$rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rStalkerData[1]), 0, -1)) == implode('.', array_slice(explode('.', $rIP), 0, -1)) : $rIP == $rStalkerData[1]);
				if (!$rIPMatch && XCMS::$rSettings['restrict_same_ip']) {
					XCMS::clientLog($rStreamID, $rUserInfo['id'], 'STALKER_IP_MISMATCH', $rIP);
					generateError('STALKER_IP_MISMATCH');
				}

				$rCreateExpiration = XCMS::$rSettings['create_expiration'] ?: 5;

				if ($rStalkerData[3] < (time() - $rCreateExpiration)) {
					XCMS::clientLog($rStreamID, $rUserInfo['id'], 'STALKER_KEY_EXPIRED', $rIP);
					generateError('STALKER_KEY_EXPIRED');
				}

				$rExternalDevice = $rStalkerData[0];
			}
			else {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'STALKER_DECRYPT_FAILED', $rIP);
				generateError('STALKER_DECRYPT_FAILED');
			}
		}

		if (!in_array($rType, ['thumb' => true, 'subtitle' => true])) {
			if (!$rUserInfo['is_restreamer'] && !in_array($rIP, XCMS::$rAllowedIPs)) {
				if (XCMS::$rSettings['block_streaming_servers'] || XCMS::$rSettings['block_proxies']) {
					$rCIDR = XCMS::matchCIDR($rUserInfo['isp_asn'], $rIP);

					if ($rCIDR) {
						if (XCMS::$rSettings['block_streaming_servers'] && $rCIDR[3] && !$rCIDR[4]) {
							XCMS::clientLog($rStreamID, $rUserInfo['id'], 'HOSTING_DETECT', $rIP, json_encode(['user_agent' => $rUserAgent, 'isp' => $rUserInfo['con_isp_name'], 'asn' => $rUserInfo['isp_asn']]), true);
							generateError('HOSTING_DETECT');
						}
						if (XCMS::$rSettings['block_proxies'] && $rCIDR[4]) {
							XCMS::clientLog($rStreamID, $rUserInfo['id'], 'PROXY_DETECT', $rIP, json_encode(['user_agent' => $rUserAgent, 'isp' => $rUserInfo['con_isp_name'], 'asn' => $rUserInfo['isp_asn']]), true);
							generateError('PROXY_DETECT');
						}
					}
				}

				if ($rRestreamDetect) {
					if (XCMS::$rSettings['detect_restream_block_user']) {
						if (XCMS::$rCached) {
							XCMS::setSignal('restream_block_user/' . $rUserInfo['id'] . '/' . $rStreamID . '/' . $rIP, 1);
						}
						else {
							$db->query('UPDATE `lines` SET `admin_enabled` = 0 WHERE `id` = ?;', $rUserInfo['id']);
						}
					}
					if (XCMS::$rSettings['restream_deny_unauthorised'] || XCMS::$rSettings['detect_restream_block_user']) {
						XCMS::clientLog($rStreamID, $rUserInfo['id'], 'RESTREAM_DETECT', $rIP, json_encode(['user_agent' => $rUserAgent, 'isp' => $rUserInfo['con_isp_name'], 'asn' => $rUserInfo['isp_asn']]), true);
						generateError('RESTREAM_DETECT');
					}
				}
			}
		}

		if ($rType == 'live') {
			if (!in_array($rExtension, $rUserInfo['output_formats'])) {
				XCMS::clientLog($rStreamID, $rUserInfo['id'], 'USER_DISALLOW_EXT', $rIP);
				generateError('USER_DISALLOW_EXT');
			}
		}
		if (($rType == 'live') && XCMS::$rSettings['show_expiring_video'] && !$rUserInfo['is_trial'] && (!is_null($rUserInfo['exp_date']) && (($rUserInfo['exp_date'] - 604800) <= time())) && ((86400 <= time() - $rUserInfo['last_expiration_video']) || !$rUserInfo['last_expiration_video'])) {
			if (XCMS::$rCached) {
				XCMS::setSignal('expiring/' . $rUserInfo['id'], time());
			}
			else {
				$db->query('UPDATE `lines` SET `last_expiration_video` = ? WHERE `id` = ?;', time(), $rUserInfo['id']);
			}

			XCMS::showVideoServer('show_expiring_video', 'expiring_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
		}
	}
}
else {
	XCMS::checkBruteforce($rIP, NULL, $rUsername);
	XCMS::clientLog($rStreamID, 0, 'AUTH_FAILED', $rIP);
	generateError('INVALID_CREDENTIALS');
}

if ($rIsMag) {
	$rForceHTTP = XCMS::$rSettings['mag_disable_ssl'];
}
else if ($rIsEnigma) {
	$rForceHTTP = true;
}
else {
	$rForceHTTP = false;
}

$rRand = XCMS::$rSettings['nginx_key'];
$rRandValue = md5(mt_rand(0, 65535) . time() . mt_rand(0, 65535));

switch ($rType) {
case 'live':
	$rChannelInfo = XCMS::redirectStream($rStreamID, $rExtension, $rUserInfo, $rCountryCode, $rUserInfo['con_isp_name'], 'live');

	if (is_array($rChannelInfo)) {
		if (count(array_keys($rChannelInfo)) == 0) {
			generateError('NO_SERVERS_AVAILABLE');
		}

		if (!array_intersect($rUserInfo['bouquet'], $rChannelInfo['bouquets'])) {
			generateError('NOT_IN_BOUQUET');
		}
		if (XCMS::isProxied($rChannelInfo['redirect_id']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
			$rProxies = XCMS::getProxies($rChannelInfo['redirect_id']);
			$rProxyID = XCMS::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

			if (!$rProxyID) {
				generateError('NO_SERVERS_AVAILABLE');
			}

			$rChannelInfo['originator_id'] = $rChannelInfo['redirect_id'];
			$rChannelInfo['redirect_id'] = $rProxyID;
		}

		$rURL = XCMS::getStreamingURL($rChannelInfo['redirect_id'], $rChannelInfo['originator_id'] ?: NULL, $rForceHTTP);
		$rStreamInfo = json_decode($rChannelInfo['stream_info'], true);
		$rVideoCodec = $rStreamInfo['codecs']['video']['codec_name'] ?: 'h264';

		switch ($rExtension) {
		case 'm3u8':
			if (XCMS::$rSettings['disable_hls'] && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['disable_hls_allow_restream'])) {
				generateError('HLS_DISABLED');
			}

			if ($rChannelInfo['direct_proxy']) {
				generateError('HLS_DISABLED');
			}

			$rAdaptive = json_decode($rChannelInfo['adaptive_link'], true);
			if (!$rIsHMAC && is_array($rAdaptive) && (0 < count($rAdaptive))) {
				$rParts = [];

				foreach (array_merge([$rStreamID], $rAdaptive) as $rAdaptiveID) {
					if ($rAdaptiveID != $rStreamID) {
						$rAdaptiveInfo = XCMS::redirectStream($rAdaptiveID, $rExtension, $rUserInfo, $rCountryCode, $rUserInfo['con_isp_name'], 'live');
						if (XCMS::isProxied($rAdaptiveInfo['redirect_id']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
							$rProxies = XCMS::getProxies($rAdaptiveInfo['redirect_id']);
							$rProxyID = XCMS::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

							if (!$rProxyID) {
								generateError('NO_SERVERS_AVAILABLE');
							}

							$rAdaptiveInfo['originator_id'] = $rAdaptiveInfo['redirect_id'];
							$rAdaptiveInfo['redirect_id'] = $rProxyID;
						}

						$rURL = XCMS::getStreamingURL($rAdaptiveInfo['redirect_id'], $rAdaptiveInfo['originator_id'] ?: NULL, $rForceHTTP);
					}
					else {
						$rAdaptiveInfo = $rChannelInfo;
					}

					$rStreamInfo = json_decode($rAdaptiveInfo['stream_info'], true);
					$rBitrate = $rStreamInfo['bitrate'] ?: 0;
					$rWidth = $rStreamInfo['codecs']['video']['width'] ?: 0;
					$rHeight = $rStreamInfo['codecs']['video']['height'] ?: 0;
					if ((0 < $rBitrate) && (0 < $rHeight) && (0 < $rWidth)) {
						$rTokenData = [
							'stream_id'       => $rAdaptiveID,
							'username'        => $rUserInfo['username'],
							'password'        => $rUserInfo['password'],
							'extension'       => $rExtension,
							'pid'             => $rPID,
							'channel_info'    => ['redirect_id' => $rAdaptiveInfo['redirect_id'], 'originator_id' => $rAdaptiveInfo['originator_id'] ?: NULL, 'pid' => $rAdaptiveInfo['pid'], 'on_demand' => $rAdaptiveInfo['on_demand'], 'monitor_pid' => $rAdaptiveInfo['monitor_pid']],
							'user_info'       => ['id' => $rUserInfo['id'], 'max_connections' => $rUserInfo['max_connections'], 'pair_id' => $rUserInfo['pair_id'], 'con_isp_name' => $rUserInfo['con_isp_name'], 'is_restreamer' => $rUserInfo['is_restreamer']],
							'external_device' => $rExternalDevice,
							'activity_start'  => $rActivityStart,
							'country_code'    => $rCountryCode,
							'video_codec'     => $rStreamInfo['codecs']['video']['codec_name'] ?: 'h264',
							'uuid'            => $rUUID,
							'adaptive'        => [$rChannelInfo['redirect_id'], $rStreamID]
						];
						$rStreamURL = $rURL . '/auth/' . Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
						$rParts[$rBitrate] = '#EXT-X-STREAM-INF:BANDWIDTH=' . $rBitrate . ',RESOLUTION=' . $rWidth . 'x' . $rHeight . "\n" . $rStreamURL;
					}
				}

				if (0 < count($rParts)) {
					krsort($rParts);
					$rM3U8 = '#EXTM3U' . "\n" . implode("\n", array_values($rParts));
					ob_end_clean();
					header('Content-Type: application/x-mpegurl');
					header('Content-Length: ' . strlen($rM3U8));
					echo $rM3U8;
					exit();
				}
				else {
					XCMS::showVideoServer('show_not_on_air_video', 'not_on_air_video_path', 'ts', $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], $rChannelInfo['originator_id'] ?: $rChannelInfo['redirect_id'], $rChannelInfo['originator_id'] ? $rChannelInfo['redirect_id'] : NULL);
				}
			}
			else {
				if (!$rIsHMAC) {
					$rTokenData = [
						'stream_id'       => $rStreamID,
						'username'        => $rUserInfo['username'],
						'password'        => $rUserInfo['password'],
						'extension'       => $rExtension,
						'pid'             => $rPID,
						'channel_info'    => ['redirect_id' => $rChannelInfo['redirect_id'], 'originator_id' => $rChannelInfo['originator_id'] ?: NULL, 'pid' => $rChannelInfo['pid'], 'on_demand' => $rChannelInfo['on_demand'], 'llod' => $rChannelInfo['llod'], 'monitor_pid' => $rChannelInfo['monitor_pid']],
						'user_info'       => ['id' => $rUserInfo['id'], 'max_connections' => $rUserInfo['max_connections'], 'pair_id' => $rUserInfo['pair_id'], 'con_isp_name' => $rUserInfo['con_isp_name'], 'is_restreamer' => $rUserInfo['is_restreamer']],
						'external_device' => $rExternalDevice,
						'activity_start'  => $rActivityStart,
						'country_code'    => $rCountryCode,
						'video_codec'     => $rVideoCodec,
						'uuid'            => $rUUID
					];
				}
				else {
					$rTokenData = [
						'stream_id'       => $rStreamID,
						'hmac_hash'       => XCMS::$rRequest['hmac'],
						'hmac_id'         => $rIsHMAC,
						'identifier'      => $rIdentifier,
						'extension'       => $rExtension,
						'channel_info'    => ['redirect_id' => $rChannelInfo['redirect_id'], 'originator_id' => $rChannelInfo['originator_id'] ?: NULL, 'pid' => $rChannelInfo['pid'], 'on_demand' => $rChannelInfo['on_demand'], 'llod' => $rChannelInfo['llod'], 'monitor_pid' => $rChannelInfo['monitor_pid']],
						'user_info'       => $rUserInfo,
						'pid'             => $rPID,
						'external_device' => $rExternalDevice,
						'activity_start'  => $rActivityStart,
						'country_code'    => $rCountryCode,
						'video_codec'     => $rVideoCodec,
						'uuid'            => $rUUID
					];
				}

				$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);

				if (!XCMS::$rSettings['encrypt_playlist']) {
					header('Location: ' . $rURL . '/auth/' . $rStreamID . '.m3u8?token=' . $rToken . '&' . $rRand . '=' . $rRandValue);
					exit();
				}
				else {
					header('Location: ' . $rURL . '/auth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
					exit();
				}
			}

			exit();
		case 'ts':
			if (XCMS::$rSettings['disable_ts'] && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['disable_ts_allow_restream'])) {
				generateError('TS_DISABLED');
			}

			if (!$rIsHMAC) {
				$rTokenData = [
					'stream_id'       => $rStreamID,
					'username'        => $rUserInfo['username'],
					'password'        => $rUserInfo['password'],
					'extension'       => $rExtension,
					'channel_info'    => ['stream_id' => $rChannelInfo['stream_id'], 'redirect_id' => $rChannelInfo['redirect_id'] ?: NULL, 'originator_id' => $rChannelInfo['originator_id'] ?: NULL, 'pid' => $rChannelInfo['pid'], 'on_demand' => $rChannelInfo['on_demand'], 'llod' => $rChannelInfo['llod'], 'monitor_pid' => $rChannelInfo['monitor_pid'], 'proxy' => $rChannelInfo['direct_proxy']],
					'user_info'       => ['id' => $rUserInfo['id'], 'max_connections' => $rUserInfo['max_connections'], 'pair_id' => $rUserInfo['pair_id'], 'con_isp_name' => $rUserInfo['con_isp_name'], 'is_restreamer' => $rUserInfo['is_restreamer']],
					'pid'             => $rPID,
					'prebuffer'       => $rPrebuffer,
					'country_code'    => $rCountryCode,
					'activity_start'  => $rActivityStart,
					'external_device' => $rExternalDevice,
					'video_codec'     => $rVideoCodec,
					'uuid'            => $rUUID
				];
			}
			else {
				$rTokenData = [
					'stream_id'       => $rStreamID,
					'hmac_hash'       => XCMS::$rRequest['hmac'],
					'hmac_id'         => $rIsHMAC,
					'identifier'      => $rIdentifier,
					'extension'       => $rExtension,
					'channel_info'    => ['stream_id' => $rChannelInfo['stream_id'], 'redirect_id' => $rChannelInfo['redirect_id'] ?: NULL, 'originator_id' => $rChannelInfo['originator_id'] ?: NULL, 'pid' => $rChannelInfo['pid'], 'on_demand' => $rChannelInfo['on_demand'], 'llod' => $rChannelInfo['llod'], 'monitor_pid' => $rChannelInfo['monitor_pid'], 'proxy' => $rChannelInfo['direct_proxy']],
					'user_info'       => $rUserInfo,
					'pid'             => $rPID,
					'prebuffer'       => $rPrebuffer,
					'country_code'    => $rCountryCode,
					'activity_start'  => $rActivityStart,
					'external_device' => $rExternalDevice,
					'video_codec'     => $rVideoCodec,
					'uuid'            => $rUUID
				];
			}

			$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);

			if (!XCMS::$rSettings['encrypt_playlist']) {
				header('Location: ' . $rURL . '/auth/' . $rStreamID . '.ts?token=' . $rToken . '&' . $rRand . '=' . $rRandValue);
				exit();
				break;
			}

			header('Location: ' . $rURL . '/auth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
			exit();
		}
	}
	else {
		XCMS::showVideoServer('show_not_on_air_video', 'not_on_air_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
	}

	break;
case 'movie':
case 'series':
	$rChannelInfo = XCMS::redirectStream($rStreamID, $rExtension, $rUserInfo, $rCountryCode, $rUserInfo['con_isp_name'], 'movie');

	if ($rChannelInfo) {
		if (XCMS::isProxied($rChannelInfo['redirect_id']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
			$rProxies = XCMS::getProxies($rChannelInfo['redirect_id']);
			$rProxyID = XCMS::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

			if (!$rProxyID) {
				generateError('NO_SERVERS_AVAILABLE');
			}

			$rChannelInfo['originator_id'] = $rChannelInfo['redirect_id'];
			$rChannelInfo['redirect_id'] = $rProxyID;
		}

		$rURL = XCMS::getStreamingURL($rChannelInfo['redirect_id'], $rChannelInfo['originator_id'] ?: NULL, $rForceHTTP);

		if ($rChannelInfo['direct_proxy']) {
			$rChannelInfo['bitrate'] = json_decode($rChannelInfo['movie_properties'], true)['duration_secs'] ?: 0;
		}

		if (!$rIsHMAC) {
			$rTokenData = [
				'stream_id'      => $rStreamID,
				'username'       => $rUserInfo['username'],
				'password'       => $rUserInfo['password'],
				'extension'      => $rExtension,
				'type'           => $rType,
				'pid'            => $rPID,
				'channel_info'   => ['stream_id' => $rChannelInfo['stream_id'], 'bitrate' => $rChannelInfo['bitrate'], 'target_container' => $rChannelInfo['target_container'], 'redirect_id' => $rChannelInfo['redirect_id'], 'originator_id' => $rChannelInfo['originator_id'] ?: NULL, 'pid' => $rChannelInfo['pid'], 'proxy' => $rChannelInfo['direct_proxy'] ? json_decode($rChannelInfo['stream_source'], true)[0] : NULL],
				'user_info'      => ['id' => $rUserInfo['id'], 'max_connections' => $rUserInfo['max_connections'], 'pair_id' => $rUserInfo['pair_id'], 'con_isp_name' => $rUserInfo['con_isp_name'], 'is_restreamer' => $rUserInfo['is_restreamer']],
				'country_code'   => $rCountryCode,
				'activity_start' => $rActivityStart,
				'is_mag'         => $rIsMag,
				'uuid'           => $rUUID,
				'http_range'     => isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : NULL
			];
		}
		else {
			$rTokenData = [
				'stream_id'      => $rStreamID,
				'hmac_hash'      => XCMS::$rRequest['hmac'],
				'hmac_id'        => $rIsHMAC,
				'identifier'     => $rIdentifier,
				'extension'      => $rExtension,
				'type'           => $rType,
				'pid'            => $rPID,
				'channel_info'   => ['stream_id' => $rChannelInfo['stream_id'], 'bitrate' => $rChannelInfo['bitrate'], 'target_container' => $rChannelInfo['target_container'], 'redirect_id' => $rChannelInfo['redirect_id'], 'originator_id' => $rChannelInfo['originator_id'] ?: NULL, 'pid' => $rChannelInfo['pid'], 'proxy_source' => $rChannelInfo['direct_proxy'] ? json_decode($rChannelInfo['stream_source'], true)[0] : NULL],
				'user_info'      => $rUserInfo,
				'country_code'   => $rCountryCode,
				'activity_start' => $rActivityStart,
				'is_mag'         => $rIsMag,
				'uuid'           => $rUUID,
				'http_range'     => isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : NULL
			];
		}

		$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);

		if (!XCMS::$rSettings['encrypt_playlist']) {
			header('Location: ' . $rURL . '/vauth/' . $rStreamID . '.' . $rExtension . '?token=' . $rToken . '&' . $rRand . '=' . $rRandValue);
			exit();
		}
		else {
			header('Location: ' . $rURL . '/vauth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
			exit();
		}
	}
	else {
		XCMS::showVideoServer('show_not_on_air_video', 'not_on_air_video_path', 'ts', $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
	}

	break;
case 'timeshift':
	$rOriginatorID = NULL;
	$rRedirectID = XCMS::redirectStream($rStreamID, $rExtension, $rUserInfo, $rCountryCode, $rUserInfo['con_isp_name'], 'archive');

	if ($rRedirectID) {
		if (XCMS::isProxied($rChannelInfo['redirect_id']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
			$rProxies = XCMS::getProxies($rChannelInfo['redirect_id']);
			$rProxyID = XCMS::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

			if (!$rProxyID) {
				generateError('NO_SERVERS_AVAILABLE');
			}

			$rOriginatorID = $rChannelInfo['redirect_id'];
			$rRedirectID = $rProxyID;
		}

		$rURL = XCMS::getStreamingURL($rRedirectID, $rOriginatorID ?: NULL, $rForceHTTP);
		$rStartDate = XCMS::$rRequest['start'];
		$rDuration = (int) XCMS::$rRequest['duration'];

		switch ($rExtension) {
		case 'm3u8':
			if (XCMS::$rSettings['disable_hls'] && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['disable_hls_allow_restream'])) {
				generateError('HLS_DISABLED');
			}

			$rTokenData = [
				'stream'         => $rStreamID,
				'username'       => $rUserInfo['username'],
				'password'       => $rUserInfo['password'],
				'extension'      => $rExtension,
				'pid'            => $rPID,
				'start'          => $rStartDate,
				'duration'       => $rDuration,
				'redirect_id'    => $rRedirectID,
				'originator_id'  => $rOriginatorID,
				'user_info'      => ['id' => $rUserInfo['id'], 'max_connections' => $rUserInfo['max_connections'], 'pair_line_info' => $rUserInfo['pair_line_info'], 'pair_id' => $rUserInfo['pair_id'], 'active_cons' => $rUserInfo['active_cons'], 'con_isp_name' => $rUserInfo['con_isp_name'], 'is_restreamer' => $rUserInfo['is_restreamer']],
				'country_code'   => $rCountryCode,
				'activity_start' => $rActivityStart,
				'uuid'           => $rUUID,
				'http_range'     => isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : NULL
			];
			$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);

			if (!XCMS::$rSettings['encrypt_playlist']) {
				header('Location: ' . $rURL . '/tsauth/' . $rStreamID . '_' . $rStartDate . '_' . $rDuration . '.m3u8?token=' . $rToken . '&' . $rRand . '=' . $rRandValue);
				exit();
				break;
			}

			header('Location: ' . $rURL . '/tsauth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
			exit();
		}
		if (XCMS::$rSettings['disable_ts'] && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['disable_ts_allow_restream'])) {
			generateError('TS_DISABLED');
		}

		$rActivityStart = time();
		$rTokenData = [
			'stream'         => $rStreamID,
			'username'       => $rUserInfo['username'],
			'password'       => $rUserInfo['password'],
			'extension'      => $rExtension,
			'pid'            => $rPID,
			'start'          => $rStartDate,
			'duration'       => $rDuration,
			'redirect_id'    => $rRedirectID,
			'originator_id'  => $rOriginatorID,
			'user_info'      => ['id' => $rUserInfo['id'], 'max_connections' => $rUserInfo['max_connections'], 'pair_line_info' => $rUserInfo['pair_line_info'], 'pair_id' => $rUserInfo['pair_id'], 'active_cons' => $rUserInfo['active_cons'], 'con_isp_name' => $rUserInfo['con_isp_name'], 'is_restreamer' => $rUserInfo['is_restreamer']],
			'country_code'   => $rCountryCode,
			'activity_start' => $rActivityStart,
			'uuid'           => $rUUID,
			'http_range'     => isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : NULL
		];
		$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);

		if (!XCMS::$rSettings['encrypt_playlist']) {
			header('Location: ' . $rURL . '/tsauth/' . $rStreamID . '_' . $rStartDate . '_' . $rDuration . '.ts?token=' . $rToken . '&' . $rRand . '=' . $rRandValue);
			exit();
		}
		else {
			header('Location: ' . $rURL . '/tsauth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
			exit();
		}
	}
	else {
		XCMS::showVideoServer('show_not_on_air_video', 'not_on_air_video_path', $rExtension, $rUserInfo, $rIP, $rCountryCode, $rUserInfo['con_isp_name'], SERVER_ID);
	}

	break;
case 'thumb':
	$rStreamInfo = NULL;

	if (XCMS::$rCached) {
		$rStreamInfo = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . $rStreamID));
	}
	else {
		$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type AND t2.live = 1 LEFT JOIN `profiles` t4 ON t1.transcode_profile_id = t4.profile_id WHERE t1.direct_source = 0 AND t1.id = ?', $rStreamID);

		if (0 < $db->num_rows()) {
			$rStreamInfo = ['info' => $db->get_row()];
		}
	}

	if (!$rStreamInfo) {
		generateError('INVALID_STREAM_ID');
	}

	if ($rStreamInfo['info']['vframes_server_id'] == 0) {
		generateError('THUMBNAILS_NOT_ENABLED');
	}

	$rTokenData = ['stream' => $rStreamID, 'expires' => time() + 5];
	$rOriginatorID = NULL;
	if (XCMS::isProxied($rStreamInfo['info']['vframes_server_id']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
		$rProxies = XCMS::getProxies($rStreamInfo['info']['vframes_server_id']);
		$rProxyID = XCMS::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

		if (!$rProxyID) {
			generateError('THUMBNAILS_NOT_ENABLED');
		}

		$rOriginatorID = $rStreamInfo['info']['vframes_server_id'];
		$rStreamInfo['info']['vframes_server_id'] = $rProxyID;
	}

	$rURL = XCMS::getStreamingURL($rStreamInfo['info']['vframes_server_id'], $rOriginatorID, $rForceHTTP);
	$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
	header('Location: ' . $rURL . '/thauth/' . $rToken . '&' . $rRand . '=' . $rRandValu);
	exit();
case 'subtitle':
	$rChannelInfo = XCMS::redirectStream($rStreamID, 'srt', $rUserInfo, $rCountryCode, $rUserInfo['con_isp_name'], 'movie');

	if ($rChannelInfo) {
		if (XCMS::isProxied($rChannelInfo['redirect_id']) && (!$rUserInfo['is_restreamer'] || !XCMS::$rSettings['restreamer_bypass_proxy'])) {
			$rProxies = XCMS::getProxies($rChannelInfo['redirect_id']);
			$rProxyID = XCMS::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

			if (!$rProxyID) {
				generateError('NO_SERVERS_AVAILABLE');
			}

			$rChannelInfo['originator_id'] = $rChannelInfo['redirect_id'];
			$rChannelInfo['redirect_id'] = $rProxyID;
		}

		$rURL = XCMS::getStreamingURL($rChannelInfo['redirect_id'], $rChannelInfo['originator_id'] ?: NULL, $rForceHTTP);
		$rTokenData = ['stream_id' => $rStreamID, 'sub_id' => (int) XCMS::$rRequest['sid'] ?: 0, 'webvtt' => (int) XCMS::$rRequest['webvtt'] ?: 0, 'expires' => time() + 5];
		$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
		header('Location: ' . $rURL . '/subauth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
		exit();
	}
	else {
		generateError('INVALID_STREAM_ID');
	}

	break;
}

?>