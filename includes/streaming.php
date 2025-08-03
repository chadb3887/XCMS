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

class XCMS
{
	static public $db = null;
	static public $redis = null;
	static public $rRequest = [];
	static public $rConfig = [];
	static public $rSettings = [];
	static public $rBouquets = [];
	static public $rServers = [];
	static public $rSegmentSettings = [];
	static public $rBlockedUA = [];
	static public $rBlockedISP = [];
	static public $rBlockedIPs = [];
	static public $rBlockedServers = [];
	static public $rAllowedIPs = [];
	static public $rCategories = [];
	static public $rProxies = [];
	static public $rFFMPEG_CPU = null;
	static public $rFFMPEG_GPU = null;
	static public $rCached = null;
	static public $rAccess = null;

	static public function init($rDatabase = false)
	{
		if (!empty($_GET)) {
			self::cleanGlobals($_GET);
		}

		if (!empty($_POST)) {
			self::cleanGlobals($_POST);
		}

		if (!empty($_SESSION)) {
			self::cleanGlobals($_SESSION);
		}

		if (!empty($_COOKIE)) {
			self::cleanGlobals($_COOKIE);
		}

		$rInput = @self::parseIncomingRecursively($_GET, []);
		self::$rRequest = @self::parseIncomingRecursively($_POST, $rInput);
		self::$rConfig = parse_ini_file(CONFIG_PATH . 'config.ini');

		if (!defined('SERVER_ID')) {
			define('SERVER_ID', (int) self::$rConfig['server_id']);
		}

		if (!self::$rSettings) {
			self::$rSettings = self::getCache('settings');
		}

		if (!empty(self::$rSettings['default_timezone'])) {
			date_default_timezone_set(self::$rSettings['default_timezone']);
		}

		if (self::$rSettings['on_demand_wait_time'] == 0) {
			self::$rSettings['on_demand_wait_time'] = 15;
		}

		switch (self::$rSettings['ffmpeg_cpu']) {
		case '5.0':
			self::$rFFMPEG_CPU = FFMPEG_BIN_50;
			break;
		default:
			self::$rFFMPEG_CPU = FFMPEG_BIN_40;
			break;
		}

		self::$rFFMPEG_GPU = FFMPEG_BIN_40;
		self::$rCached = self::confirmCache();
		self::$rServers = self::getCache('servers');
		self::$rBlockedUA = self::getCache('blocked_ua');
		self::$rBlockedISP = self::getCache('blocked_isp');
		self::$rBlockedIPs = self::getCache('blocked_ips');
		self::$rBlockedServers = self::getCache('blocked_servers');
		self::$rAllowedIPs = self::getCache('allowed_ips');
		self::$rProxies = self::getCache('proxy_servers');
		self::$rSegmentSettings = ['seg_time' => (int) self::$rSettings['seg_time'], 'seg_list_size' => (int) self::$rSettings['seg_list_size']];
		self::connectDatabase($rDatabase);
	}

	static public function confirmCache()
	{
		if (self::$rSettings['enable_cache']) {
			return file_exists(CACHE_TMP_PATH . 'cache_complete');
		}

		return false;
	}

	static public function connectDatabase($rRealCon = true)
	{
		self::$db = new Database($rRealCon);
	}

	static public function closeDatabase()
	{
		if (self::$db) {
			self::$db->close_mysql();
			self::$db = NULL;
		}
	}

	static public function getCache($rCache)
	{
		$rData = file_get_contents(CACHE_TMP_PATH . $rCache) ?: NULL;
		return igbinary_unserialize($rData);
	}

	static public function mc_decrypt($rData, $rKey)
	{
		$rData = explode('|', $rData . '|');
		$rDecoded = base64_decode($rData[0]);
		$rIV = base64_decode($rData[1]);

		if (mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC) !== strlen($rIV)) {
			return false;
		}

		$rKey = pack('H*', $rKey);
		$rDecrypted = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $rKey, $rDecoded, MCRYPT_MODE_CBC, $rIV));
		$rMAC = substr($rDecrypted, -64);
		$rDecrypted = substr($rDecrypted, 0, -64);
		$rCalcHMAC = hash_hmac('sha256', $rDecrypted, substr(bin2hex($rKey), -32));

		if ($rCalcHMAC !== $rMAC) {
			return false;
		}

		$rDecrypted = unserialize($rDecrypted);
		return $rDecrypted;
	}

	static public function cleanGlobals(&$rData, $rIteration = 0)
	{
		if (10 <= $rIteration) {
			return NULL;
		}

		foreach ($rData as $rKey => $rValue) {
			if (is_array($rValue)) {
				self::cleanGlobals($rData[$rKey], ++$rIteration);
			}
			else {
				$rValue = str_replace(chr('0'), '', $rValue);
				$rValue = str_replace("\0", '', $rValue);
				$rValue = str_replace("\0", '', $rValue);
				$rValue = str_replace('../', '&#46;&#46;/', $rValue);
				$rValue = str_replace('&#8238;', '', $rValue);
				$rData[$rKey] = $rValue;
			}
		}
	}

	static public function parseIncomingRecursively(&$rData, $rInput = [], $rIteration = 0)
	{
		if (20 <= $rIteration) {
			return $rInput;
		}

		if (!is_array($rData)) {
			return $rInput;
		}

		foreach ($rData as $rKey => $rValue) {
			if (is_array($rValue)) {
				$rInput[$rKey] = self::parseIncomingRecursively($rData[$rKey], [], $rIteration + 1);
			}
			else {
				$rKey = self::parseCleanKey($rKey);
				$rValue = self::parseCleanValue($rValue);
				$rInput[$rKey] = $rValue;
			}
		}

		return $rInput;
	}

	static public function parseCleanKey($rKey)
	{
		if ($rKey === '') {
			return '';
		}

		$rKey = htmlspecialchars(urldecode($rKey));
		$rKey = str_replace('..', '', $rKey);
		$rKey = preg_replace('/\\_\\_(.+?)\\_\\_/', '', $rKey);
		$rKey = preg_replace('/^([\\w\\.\\-\\_]+)$/', '$1', $rKey);
		return $rKey;
	}

	static public function parseCleanValue($rValue)
	{
		if ($rValue == '') {
			return '';
		}

		$rValue = str_replace(["\r\n", "\n\r", "\r"], "\n", $rValue);
		$rValue = str_replace('<!--', '&#60;&#33;--', $rValue);
		$rValue = str_replace('-->', '--&#62;', $rValue);
		$rValue = str_ireplace('<script', '&#60;script', $rValue);
		$rValue = preg_replace('/&amp;#([0-9]+);/s', '&#\\1;', $rValue);
		$rValue = preg_replace('/&#(\\d+?)([^\\d;])/i', '&#\\1;\\2', $rValue);
		return trim($rValue);
	}

	static public function checkFlood($rIP = NULL)
	{
		if (self::$rSettings['flood_limit'] == 0) {
			return NULL;
		}

		if (!$rIP) {
			$rIP = self::getUserIP();
		}
		if (empty($rIP) || in_array($rIP, self::$rAllowedIPs)) {
			return NULL;
		}

		$rFloodExclude = array_filter(array_unique(explode(',', self::$rSettings['flood_ips_exclude'])));

		if (in_array($rIP, $rFloodExclude)) {
			return NULL;
		}

		$rIPFile = FLOOD_TMP_PATH . $rIP;

		if (file_exists($rIPFile)) {
			$rFloodRow = json_decode(file_get_contents($rIPFile), true);
			$rFloodSeconds = self::$rSettings['flood_seconds'];
			$rFloodLimit = self::$rSettings['flood_limit'];

			if ((time() - $rFloodRow['last_request']) <= $rFloodSeconds) {
				$rFloodRow['requests']++;

				if ($rFloodLimit <= $rFloodRow['requests']) {
					if (!in_array($rIP, self::$rBlockedIPs)) {
						if (self::$rCached) {
							self::setSignal('flood_attack/' . $rIP, 1);
						}
						else {
							self::$db->query('INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES(?,?,?)', $rIP, 'FLOOD ATTACK', time());
						}

						touch(FLOOD_TMP_PATH . 'block_' . $rIP);
					}

					unlink($rIPFile);
					return NULL;
				}

				$rFloodRow['last_request'] = time();
				file_put_contents($rIPFile, json_encode($rFloodRow), LOCK_EX);
			}
			else {
				$rFloodRow['requests'] = 0;
				$rFloodRow['last_request'] = time();
				file_put_contents($rIPFile, json_encode($rFloodRow), LOCK_EX);
			}
		}
		else {
			file_put_contents($rIPFile, json_encode(['requests' => 0, 'last_request' => time()]), LOCK_EX);
		}
	}

	static public function checkBruteforce($rIP = NULL, $rMAC = NULL, $rUsername = NULL)
	{
		if (!$rMAC && !$rUsername) {
			return NULL;
		}
		if ($rMAC && (self::$rSettings['bruteforce_mac_attempts'] == 0)) {
			return NULL;
		}
		if ($rUsername && (self::$rSettings['bruteforce_username_attempts'] == 0)) {
			return NULL;
		}

		if (!$rIP) {
			$rIP = self::getUserIP();
		}
		if (empty($rIP) || in_array($rIP, self::$rAllowedIPs)) {
			return NULL;
		}

		$rFloodExclude = array_filter(array_unique(explode(',', self::$rSettings['flood_ips_exclude'])));

		if (in_array($rIP, $rFloodExclude)) {
			return NULL;
		}

		$rFloodType = (!is_null($rMAC) ? 'mac' : 'user');
		$rTerm = (!is_null($rMAC) ? $rMAC : $rUsername);
		$rIPFile = FLOOD_TMP_PATH . $rIP . '_' . $rFloodType;

		if (file_exists($rIPFile)) {
			$rFloodRow = json_decode(file_get_contents($rIPFile), true);
			$rFloodSeconds = (int) self::$rSettings['bruteforce_frequency'];
			$rFloodLimit = (int) self::$rSettings[['mac' => 'bruteforce_mac_attempts', 'user' => 'bruteforce_username_attempts'][$rFloodType]];
			$rFloodRow['attempts'] = self::truncateAttempts($rFloodRow['attempts'], $rFloodSeconds);

			if (!in_array($rTerm, array_keys($rFloodRow['attempts']))) {
				$rFloodRow['attempts'][$rTerm] = time();

				if ($rFloodLimit <= count($rFloodRow['attempts'])) {
					if (!in_array($rIP, self::$rBlockedIPs)) {
						if (self::$rCached) {
							self::setSignal('bruteforce_attack/' . $rIP, 1);
						}
						else {
							self::$db->query('INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES(?,?,?)', $rIP, 'BRUTEFORCE ' . strtoupper($rFloodType) . ' ATTACK', time());
						}

						touch(FLOOD_TMP_PATH . 'block_' . $rIP);
					}

					unlink($rIPFile);
					return NULL;
				}

				file_put_contents($rIPFile, json_encode($rFloodRow), LOCK_EX);
			}
		}
		else {
			$rFloodRow = [
				'attempts' => [$rTerm => time()]
			];
			file_put_contents($rIPFile, json_encode($rFloodRow), LOCK_EX);
		}
	}

	static public function checkAuthFlood($rUser, $rIP = NULL)
	{
		if (self::$rSettings['auth_flood_limit'] == 0) {
			return NULL;
		}

		if ($rUser['is_restreamer']) {
			return NULL;
		}

		if (!$rIP) {
			$rIP = self::getUserIP();
		}
		if (empty($rIP) || in_array($rIP, self::$rAllowedIPs)) {
			return NULL;
		}

		$rFloodExclude = array_filter(array_unique(explode(',', self::$rSettings['flood_ips_exclude'])));

		if (in_array($rIP, $rFloodExclude)) {
			return NULL;
		}

		$rUserFile = FLOOD_TMP_PATH . (int) $rUser['id'] . '_' . $rIP;

		if (file_exists($rUserFile)) {
			$rFloodRow = json_decode(file_get_contents($rUserFile), true);
			if (isset($rFloodRow['block_until']) && (time() < $rFloodRow['block_until'])) {
				sleep((int) self::$rSettings['auth_flood_sleep']);
			}

			$rFloodSeconds = self::$rSettings['auth_flood_seconds'];
			$rFloodLimit = self::$rSettings['auth_flood_limit'];
			$rFloodRow['attempts'] = self::truncateAttempts($rFloodRow['attempts'], $rFloodSeconds, true);

			if ($rFloodLimit <= count($rFloodRow['attempts'])) {
				$rFloodRow['block_until'] = time() + (int) self::$rSettings['auth_flood_seconds'];
			}

			$rFloodRow['attempts'][] = time();
			file_put_contents($rUserFile, json_encode($rFloodRow), LOCK_EX);
		}
		else {
			file_put_contents($rUserFile, json_encode([
				'attempts' => [time()]
			]), LOCK_EX);
		}
	}

	static public function isProxied($rServerID)
	{
		return self::$rServers[$rServerID]['enable_proxy'];
	}

	static public function isProxy($rIP)
	{
		if (isset(self::$rProxies[$rIP])) {
			return self::$rProxies[$rIP];
		}

		return NULL;
	}

	static public function truncateAttempts($rAttempts, $rFrequency, $rList = false)
	{
		$rAllowedAttempts = [];
		$rTime = time();

		if ($rList) {
			foreach ($rAttempts as $rAttemptTime) {
				if (($rTime - $rAttemptTime) <= $rFrequency) {
					$rAllowedAttempts[] = $rAttemptTime;
				}
			}
		}
		else {
			foreach ($rAttempts as $rAttempt => $rAttemptTime) {
				if (($rTime - $rAttemptTime) <= $rFrequency) {
					$rAllowedAttempts[$rAttempt] = $rAttemptTime;
				}
			}
		}

		return $rAllowedAttempts;
	}

	static public function getCapacity($rProxy = false)
	{
		return json_decode(file_get_contents(CACHE_TMP_PATH . ($rProxy ? 'proxy_capacity' : 'servers_capacity')), true);
	}

	static public function redirectStream($rStreamID, $rExtension, $rUserInfo, $rCountryCode, $rUserISP = '', $rType = '')
	{
		if (self::$rCached) {
			$rStream = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . $rStreamID)) ?: NULL;
			$rStream['bouquets'] = self::getBouquetMap($rStreamID);
		}
		else {
			$rStream = self::getStreamData($rStreamID);
		}

		if (!$rStream) {
			return false;
		}

		$rStream['info']['bouquets'] = $rStream['bouquets'];
		$rAvailableServers = [];

		if ($rType == 'archive') {
			if ((0 < $rStream['info']['tv_archive_duration']) && (0 < $rStream['info']['tv_archive_server_id']) && array_key_exists($rStream['info']['tv_archive_server_id'], self::$rServers)) {
				$rAvailableServers = [$rStream['info']['tv_archive_server_id']];
			}
		}
		else {
			if (($rStream['info']['direct_source'] == 1) && ($rStream['info']['direct_proxy'] == 0)) {
				header('Location: ' . str_replace(' ', '%20', json_decode($rStream['info']['stream_source'], true)[0]));
				exit();
			}

			foreach (self::$rServers as $rServerID => $rServerInfo) {
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

		if (empty($rAvailableServers)) {
			return false;
		}

		shuffle($rAvailableServers);
		$rServerCapacity = self::getCapacity();
		$rAcceptServers = [];

		foreach ($rAvailableServers as $rServerID) {
			$rOnlineClients = (isset($rServerCapacity[$rServerID]['online_clients']) ? $rServerCapacity[$rServerID]['online_clients'] : 0);

			if ($rOnlineClients == 0) {
				$rServerCapacity[$rServerID]['capacity'] = 0;
			}
			$rAcceptServers[$rServerID] = ((0 < self::$rServers[$rServerID]['total_clients']) && ($rOnlineClients < self::$rServers[$rServerID]['total_clients']) ? $rServerCapacity[$rServerID]['capacity'] : false);
		}

		$rAcceptServers = array_filter($rAcceptServers, 'is_numeric');

		if (!empty($rAcceptServers)) {
			$rKeys = array_keys($rAcceptServers);
			$rValues = array_values($rAcceptServers);
			array_multisort($rValues, SORT_ASC, $rKeys, SORT_ASC);
			$rAcceptServers = array_combine($rKeys, $rValues);
			if (($rExtension == 'rtmp') && array_key_exists(SERVER_ID, $rAcceptServers)) {
				$rRedirectID = SERVER_ID;
			}
			else if (isset($rUserInfo) && (($rUserInfo['force_server_id'] != 0) && array_key_exists($rUserInfo['force_server_id'], $rAcceptServers))) {
				$rRedirectID = $rUserInfo['force_server_id'];
			}
			else {
				$rPriorityServers = [];

				foreach (array_keys($rAcceptServers) as $rServerID) {
					if (self::$rServers[$rServerID]['enable_geoip'] == 1) {
						if (in_array($rCountryCode, self::$rServers[$rServerID]['geoip_countries'])) {
							$rRedirectID = $rServerID;
							break;
						}
						else if (self::$rServers[$rServerID]['geoip_type'] == 'strict') {
							unset($rAcceptServers[$rServerID]);
						}
						else if (isset($rStream) && !self::$rSettings['ondemand_balance_equal'] && $rStream['servers'][$rServerID]['on_demand']) {
							$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['geoip_type'] == 'low_priority' ? 3 : 2);
						}
						else {
							$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['geoip_type'] == 'low_priority' ? 2 : 1);
						}
					}
					else if (self::$rServers[$rServerID]['enable_isp'] == 1) {
						if (in_array(strtolower(trim(preg_replace('/[^A-Za-z0-9 ]/', '', $rUserISP))), self::$rServers[$rServerID]['isp_names'])) {
							$rRedirectID = $rServerID;
							break;
						}
						else if (self::$rServers[$rServerID]['isp_type'] == 'strict') {
							unset($rAcceptServers[$rServerID]);
						}
						else if (isset($rStream) && !self::$rSettings['ondemand_balance_equal'] && $rStream['servers'][$rServerID]['on_demand']) {
							$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['isp_type'] == 'low_priority' ? 3 : 2);
						}
						else {
							$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['isp_type'] == 'low_priority' ? 2 : 1);
						}
					}
					else if (isset($rStream) && !self::$rSettings['ondemand_balance_equal'] && $rStream['servers'][$rServerID]['on_demand']) {
						$rPriorityServers[$rServerID] = 2;
					}
					else {
						$rPriorityServers[$rServerID] = 1;
					}
				}
				if (empty($rPriorityServers) && empty($rRedirectID)) {
					return false;
				}

				$rRedirectID = (empty($rRedirectID) ? array_search(min($rPriorityServers), $rPriorityServers) : $rRedirectID);
			}

			if ($rType == 'archive') {
				return $rRedirectID;
			}
			else {
				$rStream['info']['redirect_id'] = $rRedirectID;
				$rRetServerID = $rRedirectID;
				return array_merge($rStream['info'], $rStream['servers'][$rRetServerID]);
			}
		}

		if ($rType == 'archive') {
			return NULL;
		}
		else {
			return [];
		}
	}

	static public function getVideoPath($rVideoPath)
	{
		if (isset(self::$rSettings[$rVideoPath]) && (0 < strlen(self::$rSettings[$rVideoPath]))) {
			return self::$rSettings[$rVideoPath];
		}

		switch ($rVideoPath) {
		case 'connected_video_path':
			if (file_exists(VIDEO_PATH . 'connected.ts')) {
				return VIDEO_PATH . 'connected.ts';
			}

			break;
		case 'expired_video_path':
			if (file_exists(VIDEO_PATH . 'expired.ts')) {
				return VIDEO_PATH . 'expired.ts';
			}

			break;
		case 'banned_video_path':
			if (file_exists(VIDEO_PATH . 'banned.ts')) {
				return VIDEO_PATH . 'banned.ts';
			}

			break;
		case 'not_on_air_video_path':
			if (file_exists(VIDEO_PATH . 'offline.ts')) {
				return VIDEO_PATH . 'offline.ts';
			}

			break;
		case 'expiring_video_path':
			if (file_exists(VIDEO_PATH . 'expiring.ts')) {
				return VIDEO_PATH . 'expiring.ts';
			}

			break;
		}

		return NULL;
	}

	static public function showVideoServer($rVideoSetting, $rVideoPath, $rExtension, $rUserInfo, $rIP, $rCountryCode, $rISP, $rServerID = NULL, $rProxyID = NULL)
	{
		$rVideoPath = self::getVideoPath($rVideoPath);
		$rRand = self::$rSettings['nginx_key'];
		$rRandValue = md5(mt_rand(0, 65535) . time() . mt_rand(0, 65535));
		if (!$rUserInfo['is_restreamer'] && self::$rSettings[$rVideoSetting] && (0 < strlen($rVideoPath))) {
			if (!$rServerID) {
				$rServerID = self::availableServer($rUserInfo, $rIP, $rCountryCode, $rISP);
			}

			if (!$rServerID) {
				$rServerID = SERVER_ID;
			}

			$rOriginatorID = NULL;
			if (self::isProxied($rServerID) && (!$rUserInfo['is_restreamer'] || !self::$rSettings['restreamer_bypass_proxy'])) {
				$rProxies = self::getProxies($rServerID);
				$rProxyID = self::availableProxy(array_keys($rProxies), $rCountryCode, $rUserInfo['con_isp_name']);

				if (!$rProxyID) {
					generate404();
				}

				$rOriginatorID = $rServerID;
				$rServerID = $rProxyID;
			}
			if (self::$rServers[$rServerID]['random_ip'] && (0 < count(self::$rServers[$rServerID]['domains']['urls']))) {
				$rURL = self::$rServers[$rServerID]['domains']['protocol'] . '://' . self::$rServers[$rServerID]['domains']['urls'][array_rand(self::$rServers[$rServerID]['domains']['urls'])] . ':' . self::$rServers[$rServerID]['domains']['port'];
			}
			else {
				$rURL = rtrim(self::$rServers[$rServerID]['site_url'], '/');
			}
			if ($rOriginatorID && !self::$rServers[$rOriginatorID]['is_main']) {
				$rURL .= '/' . md5($rServerID . '_' . $rOriginatorID . '_' . OPENSSL_EXTRA);
			}

			$rTokenData = ['expires' => time() + 10, 'video_path' => $rVideoPath];
			$rToken = Xcms\Functions::encrypt(json_encode($rTokenData), self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);

			if ($rExtension == 'm3u8') {
				$rM3U8 = '#EXTM3U' . "\n" . '#EXT-X-VERSION:3' . "\n" . '#EXT-X-MEDIA-SEQUENCE:0' . "\n" . '#EXT-X-ALLOW-CACHE:YES' . "\n" . '#EXT-X-TARGETDURATION:10' . "\n" . '#EXTINF:10.0,' . "\n" . $rURL . '/auth/' . $rToken . "\n" . '#EXT-X-ENDLIST';
				header('Content-Type: application/x-mpegurl');
				header('Content-Length: ' . strlen($rM3U8));
				echo $rM3U8;
				exit();
			}
			else {
				header('Location: ' . $rURL . '/auth/' . $rToken . '&' . $rRand . '=' . $rRandValue);
				exit();
			}
		}

		switch ($rVideoSetting) {
		case 'show_expired_video':
			generateError('EXPIRED');
			break;
		case 'show_banned_video':
			generateError('BANNED');
			break;
		case 'show_not_on_air_video':
			generateError('STREAM_OFFLINE');
			break;
		default:
			generate404();
			break;
		}
	}

	static public function availableServer($rUserInfo, $rUserIP, $rCountryCode, $rUserISP = '')
	{
		$rAvailableServers = [];

		foreach (self::$rServers as $rServerID => $rServerInfo) {
			if (!$rServerInfo['server_online'] || ($rServerInfo['server_type'] != 0)) {
				continue;
			}

			$rAvailableServers[] = $rServerID;
		}

		if (empty($rAvailableServers)) {
			return false;
		}

		shuffle($rAvailableServers);
		$rServerCapacity = self::getCapacity();
		$rAcceptServers = [];

		foreach ($rAvailableServers as $rServerID) {
			$rOnlineClients = (isset($rServerCapacity[$rServerID]['online_clients']) ? $rServerCapacity[$rServerID]['online_clients'] : 0);

			if ($rOnlineClients == 0) {
				$rServerCapacity[$rServerID]['capacity'] = 0;
			}
			$rAcceptServers[$rServerID] = ((0 < self::$rServers[$rServerID]['total_clients']) && ($rOnlineClients < self::$rServers[$rServerID]['total_clients']) ? $rServerCapacity[$rServerID]['capacity'] : false);
		}

		$rAcceptServers = array_filter($rAcceptServers, 'is_numeric');

		if (!empty($rAcceptServers)) {
			$rKeys = array_keys($rAcceptServers);
			$rValues = array_values($rAcceptServers);
			array_multisort($rValues, SORT_ASC, $rKeys, SORT_ASC);
			$rAcceptServers = array_combine($rKeys, $rValues);
			if (($rUserInfo['force_server_id'] != 0) && array_key_exists($rUserInfo['force_server_id'], $rAcceptServers)) {
				$rRedirectID = $rUserInfo['force_server_id'];
			}
			else {
				$rPriorityServers = [];

				foreach (array_keys($rAcceptServers) as $rServerID) {
					if (self::$rServers[$rServerID]['enable_geoip'] == 1) {
						if (in_array($rCountryCode, self::$rServers[$rServerID]['geoip_countries'])) {
							$rRedirectID = $rServerID;
							break;
						}
						else if (self::$rServers[$rServerID]['geoip_type'] == 'strict') {
							unset($rAcceptServers[$rServerID]);
						}
						else {
							$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['geoip_type'] == 'low_priority' ? 1 : 2);
						}
					}
					else if (self::$rServers[$rServerID]['enable_isp'] == 1) {
						if (in_array($rUserISP, self::$rServers[$rServerID]['isp_names'])) {
							$rRedirectID = $rServerID;
							break;
						}
						else if (self::$rServers[$rServerID]['isp_type'] == 'strict') {
							unset($rAcceptServers[$rServerID]);
						}
						else {
							$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['isp_type'] == 'low_priority' ? 1 : 2);
						}
					}
					else {
						$rPriorityServers[$rServerID] = 1;
					}
				}
				if (empty($rPriorityServers) && empty($rRedirectID)) {
					return false;
				}

				$rRedirectID = (empty($rRedirectID) ? array_search(min($rPriorityServers), $rPriorityServers) : $rRedirectID);
			}

			return $rRedirectID;
		}

		return false;
	}

	static public function availableProxy($rProxies, $rCountryCode, $rUserISP = '')
	{
		if (empty($rProxies)) {
			return NULL;
		}

		$rServerCapacity = self::getCapacity(true);
		$rAcceptServers = [];

		foreach ($rProxies as $rServerID) {
			$rOnlineClients = (isset($rServerCapacity[$rServerID]['online_clients']) ? $rServerCapacity[$rServerID]['online_clients'] : 0);

			if ($rOnlineClients == 0) {
				$rServerCapacity[$rServerID]['capacity'] = 0;
			}
			$rAcceptServers[$rServerID] = ((0 < self::$rServers[$rServerID]['total_clients']) && ($rOnlineClients < self::$rServers[$rServerID]['total_clients']) ? $rServerCapacity[$rServerID]['capacity'] : false);
		}

		$rAcceptServers = array_filter($rAcceptServers, 'is_numeric');

		if (!empty($rAcceptServers)) {
			$rKeys = array_keys($rAcceptServers);
			$rValues = array_values($rAcceptServers);
			array_multisort($rValues, SORT_ASC, $rKeys, SORT_ASC);
			$rAcceptServers = array_combine($rKeys, $rValues);
			$rPriorityServers = [];

			foreach (array_keys($rAcceptServers) as $rServerID) {
				if (self::$rServers[$rServerID]['enable_geoip'] == 1) {
					if (in_array($rCountryCode, self::$rServers[$rServerID]['geoip_countries'])) {
						$rRedirectID = $rServerID;
						break;
					}
					else if (self::$rServers[$rServerID]['geoip_type'] == 'strict') {
						unset($rAcceptServers[$rServerID]);
					}
					else {
						$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['geoip_type'] == 'low_priority' ? 1 : 2);
					}
				}
				else if (self::$rServers[$rServerID]['enable_isp'] == 1) {
					if (in_array($rUserISP, self::$rServers[$rServerID]['isp_names'])) {
						$rRedirectID = $rServerID;
						break;
					}
					else if (self::$rServers[$rServerID]['isp_type'] == 'strict') {
						unset($rAcceptServers[$rServerID]);
					}
					else {
						$rPriorityServers[$rServerID] = (self::$rServers[$rServerID]['isp_type'] == 'low_priority' ? 1 : 2);
					}
				}
				else {
					$rPriorityServers[$rServerID] = 1;
				}
			}
			if (empty($rPriorityServers) && empty($rRedirectID)) {
				return NULL;
			}

			$rRedirectID = (empty($rRedirectID) ? array_search(min($rPriorityServers), $rPriorityServers) : $rRedirectID);
			return $rRedirectID;
		}

		return NULL;
	}

	static public function closeConnections($rUserID, $rMaxConnections, $rIsHMAC = NULL, $rIdentifier = '', $rIP = NULL, $rUserAgent = NULL)
	{
		if (self::$rSettings['redis_handler']) {
			$rConnections = [];
			$rKeys = self::getConnections($rUserID, true, true);
			$rToKill = count($rKeys) - $rMaxConnections;

			if ($rToKill <= 0) {
				return NULL;
			}

			foreach (array_map('igbinary_unserialize', self::$redis->mGet($rKeys)) as $rConnection) {
				if (is_array($rConnection)) {
					$rConnections[] = $rConnection;
				}
			}

			unset($rKeys);
			$rDate = array_column($rConnections, 'date_start');
			array_multisort($rDate, SORT_ASC, $rConnections);
		}
		else {
			if ($rIsHMAC) {
				self::$db->query('SELECT `lines_live`.*, `on_demand` FROM `lines_live` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `lines_live`.`stream_id` AND `streams_servers`.`server_id` = `lines_live`.`server_id` WHERE `lines_live`.`hmac_id` = ? AND `lines_live`.`hls_end` = 0 AND `lines_live`.`hmac_identifier` = ? ORDER BY `lines_live`.`activity_id` ASC', $rIsHMAC, $rIdentifier);
			}
			else {
				self::$db->query('SELECT `lines_live`.*, `on_demand` FROM `lines_live` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `lines_live`.`stream_id` AND `streams_servers`.`server_id` = `lines_live`.`server_id` WHERE `lines_live`.`user_id` = ? AND `lines_live`.`hls_end` = 0 ORDER BY `lines_live`.`activity_id` ASC', $rUserID);
			}

			$rConnectionCount = self::$db->num_rows();
			$rToKill = $rConnectionCount - $rMaxConnections;

			if ($rToKill <= 0) {
				return NULL;
			}

			$rConnections = self::$db->get_rows();
		}

		$rIP = self::getUserIP();
		$rKilled = 0;
		$rDelSID = $rDelUUID = $rIDs = [];
		if ($rIP && $rUserAgent) {
			$rKillTypes = [2, 1, 0];
		}
		else if ($rIP) {
			$rKillTypes = [1, 0];
		}
		else {
			$rKillTypes = [0];
		}

		foreach ($rKillTypes as $rKillOwnIP) {
			for ($i = 0; ($i < count($rConnections)) && ($rKilled < $rToKill); $i++) {
				if ($rKilled == $rToKill) {
					break 2;
				}

				if (getmypid() == $rConnections[$i]['pid']) {
					continue;
				}
				if ((($rIP == $rConnections[$i]['user_ip']) && ($rUserAgent == $rConnections[$i]['user_agent']) && ($rKillOwnIP == 2)) || (($rIP == $rConnections[$i]['user_ip']) && ($rKillOwnIP == 1)) || ($rKillOwnIP == 0)) {
					if (self::closeConnection($rConnections[$i])) {
						$rKilled++;

						if ($rConnections[$i]['container'] != 'hls') {
							if (self::$rSettings['redis_handler']) {
								$rIDs[] = $rConnections[$i];
							}
							else {
								$rIDs[] = (int) $rConnections[$i]['activity_id'];
							}

							$rDelSID[$rConnections[$i]['stream_id']][] = $rDelUUID[] = $rConnections[$i]['uuid'];
						}
						if ($rConnections[$i]['on_demand'] && ($rConnections[$i]['server_id'] == SERVER_ID) && self::$rSettings['on_demand_instant_off']) {
							self::removeFromQueue($rConnections[$i]['stream_id'], $rConnections[$i]['pid']);
						}
					}
				}
			}
		}

		if (!empty($rIDs)) {
			if (self::$rSettings['redis_handler']) {
				$rUUIDs = [];
				$rRedis = self::$redis->multi();

				foreach ($rIDs as $rConnection) {
					$rRedis->zRem('LINE#' . $rConnection['identity'], $rConnection['uuid']);
					$rRedis->zRem('LINE_ALL#' . $rConnection['identity'], $rConnection['uuid']);
					$rRedis->zRem('STREAM#' . $rConnection['stream_id'], $rConnection['uuid']);
					$rRedis->zRem('SERVER#' . $rConnection['server_id'], $rConnection['uuid']);

					if ($rConnection['user_id']) {
						$rRedis->zRem('SERVER_LINES#' . $rConnection['server_id'], $rConnection['uuid']);
					}

					if ($rConnection['proxy_id']) {
						$rRedis->zRem('PROXY#' . $rConnection['proxy_id'], $rConnection['uuid']);
					}

					$rRedis->del($rConnection['uuid']);
					$rUUIDs[] = $rConnection['uuid'];
				}

				$rRedis->zRem('CONNECTIONS', ...$rUUIDs);
				$rRedis->zRem('LIVE', ...$rUUIDs);
				$rRedis->sRem('ENDED', ...$rUUIDs);
				$rRedis->exec();
			}
			else {
				self::$db->query('DELETE FROM `lines_live` WHERE `activity_id` IN (' . implode(',', array_map('intval', $rIDs)) . ')');
			}

			foreach ($rDelUUID as $rUUID) {
				@unlink(CONS_TMP_PATH . $rUUID);
			}

			foreach ($rDelSID as $rStreamID => $rUUIDs) {
				foreach ($rUUIDs as $rUUID) {
					@unlink(CONS_TMP_PATH . $rStreamID . '/' . $rUUID);
				}
			}
		}

		return $rKilled;
	}

	static public function closeConnection($rActivityInfo)
	{
		if (empty($rActivityInfo)) {
			return false;
		}

		if (!is_array($rActivityInfo)) {
			if (!self::$rSettings['redis_handler']) {
				if (strlen(strval($rActivityInfo)) == 32) {
					self::$db->query('SELECT * FROM `lines_live` WHERE `uuid` = ?', $rActivityInfo);
				}
				else {
					self::$db->query('SELECT * FROM `lines_live` WHERE `activity_id` = ?', $rActivityInfo);
				}

				$rActivityInfo = self::$db->get_row();
			}
			else {
				$rActivityInfo = igbinary_unserialize(self::$redis->get($rActivityInfo));
			}
		}

		if (!is_array($rActivityInfo)) {
			return false;
		}

		if ($rActivityInfo['container'] == 'rtmp') {
			if ($rActivityInfo['server_id'] == SERVER_ID) {
				shell_exec('wget --timeout=2 -O /dev/null -o /dev/null "' . self::$rServers[SERVER_ID]['rtmp_mport_url'] . 'control/drop/client?clientid=' . (int) $rActivityInfo['pid'] . '" >/dev/null 2>/dev/null &');
			}
			else if (self::$rSettings['redis_handler']) {
				self::redisSignal($rActivityInfo['pid'], $rActivityInfo['server_id'], 1);
			}
			else {
				self::$db->query('INSERT INTO `signals` (`pid`,`server_id`,`rtmp`,`time`) VALUES(?,?,?,UNIX_TIMESTAMP())', $rActivityInfo['pid'], $rActivityInfo['server_id'], 1);
			}
		}
		else if ($rActivityInfo['container'] == 'hls') {
			if (self::$rSettings['redis_handler']) {
				self::updateConnection($rActivityInfo, [], 'close');
			}
			else {
				self::$db->query('UPDATE `lines_live` SET `hls_end` = 1 WHERE `activity_id` = ?', $rActivityInfo['activity_id']);
			}
		}
		else if ($rActivityInfo['server_id'] == SERVER_ID) {
			if ((getmypid() != $rActivityInfo['pid']) && is_numeric($rActivityInfo['pid']) && (0 < $rActivityInfo['pid'])) {
				posix_kill((int) $rActivityInfo['pid'], 9);
			}
		}
		else if (self::$rSettings['redis_handler']) {
			self::redisSignal($rActivityInfo['pid'], $rActivityInfo['server_id'], 0);
		}
		else {
			self::$db->query('INSERT INTO `signals` (`pid`,`server_id`,`time`) VALUES(?,?,UNIX_TIMESTAMP())', $rActivityInfo['pid'], $rActivityInfo['server_id']);
		}

		self::writeOfflineActivity($rActivityInfo['server_id'], $rActivityInfo['proxy_id'], $rActivityInfo['user_id'], $rActivityInfo['stream_id'], $rActivityInfo['date_start'], $rActivityInfo['user_agent'], $rActivityInfo['user_ip'], $rActivityInfo['container'], $rActivityInfo['geoip_country_code'], $rActivityInfo['isp'], $rActivityInfo['external_device'], $rActivityInfo['divergence'], $rActivityInfo['hmac_id'], $rActivityInfo['hmac_identifier']);
		return true;
	}

	static public function closeRTMP($rPID)
	{
		if (empty($rPID)) {
			return false;
		}

		self::$db->query('SELECT * FROM `lines_live` WHERE `container` = \'rtmp\' AND `pid` = ? AND `server_id` = ?', $rPID, SERVER_ID);

		if (0 < self::$db->num_rows()) {
			$rActivityInfo = self::$db->get_row();
			self::$db->query('DELETE FROM `lines_live` WHERE `activity_id` = ?', $rActivityInfo['activity_id']);
			self::writeOfflineActivity($rActivityInfo['server_id'], $rActivityInfo['proxy_id'], $rActivityInfo['user_id'], $rActivityInfo['stream_id'], $rActivityInfo['date_start'], $rActivityInfo['user_agent'], $rActivityInfo['user_ip'], $rActivityInfo['container'], $rActivityInfo['geoip_country_code'], $rActivityInfo['isp'], $rActivityInfo['external_device'], $rActivityInfo['divergence'], $rActivityInfo['hmac_id'], $rActivityInfo['hmac_identifier']);
			return true;
		}

		return false;
	}

	static public function writeOfflineActivity($rServerID, $rProxyID, $rUserID, $rStreamID, $rStart, $rUserAgent, $rIP, $rExtension, $rGeoIP, $rISP, $rExternalDevice = '', $rDivergence = 0, $rIsHMAC = NULL, $rIdentifier = '')
	{
		if (self::$rSettings['save_closed_connection'] == 0) {
			return NULL;
		}
		if ($rServerID && $rUserID && $rStreamID) {
			$rActivityInfo = ['user_id' => (int) $rUserID, 'stream_id' => (int) $rStreamID, 'server_id' => (int) $rServerID, 'proxy_id' => (int) $rProxyID, 'date_start' => (int) $rStart, 'user_agent' => $rUserAgent, 'user_ip' => htmlentities($rIP), 'date_end' => time(), 'container' => $rExtension, 'geoip_country_code' => $rGeoIP, 'isp' => $rISP, 'external_device' => htmlentities($rExternalDevice), 'divergence' => (int) $rDivergence, 'hmac_id' => $rIsHMAC, 'hmac_identifier' => $rIdentifier];
			file_put_contents(LOGS_TMP_PATH . 'activity', base64_encode(json_encode($rActivityInfo)) . "\n", FILE_APPEND | LOCK_EX);
		}
	}

	static public function getAllowedRTMP()
	{
		$rReturn = [];
		self::$db->query('SELECT `ip`, `password`, `push`, `pull` FROM `rtmp_ips`');

		foreach (self::$db->get_rows() as $rRow) {
			$rReturn[gethostbyname($rRow['ip'])] = ['password' => $rRow['password'], 'push' => (bool) $rRow['push'], 'pull' => (bool) $rRow['pull']];
		}

		return $rReturn;
	}

	static public function canWatch($rStreamID, $rIDs = [], $rType = 'movie')
	{
		if ($rType == 'movie') {
			return in_array($rStreamID, $rIDs);
		}
		else if ($rType == 'series') {
			if (self::$rCached) {
				$rSeries = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'series_map'));
				return in_array($rSeries[$rStreamID], $rIDs);
			}
			else {
				self::$db->query('SELECT series_id FROM `streams_episodes` WHERE `stream_id` = ? LIMIT 1', $rStreamID);

				if (0 < self::$db->num_rows()) {
					return in_array(self::$db->get_col(), $rIDs);
				}
			}
		}

		return false;
	}

	static public function getUserInfo($rUserID = NULL, $rUsername = NULL, $rPassword = NULL, $rGetChannelIDs = false, $rGetConnections = false, $rIP = '')
	{
		$rUserInfo = NULL;

		if (self::$rCached) {
			if (empty($rPassword) && empty($rUserID) && (strlen($rUsername) == 32)) {
				if (self::$rSettings['case_sensitive_line']) {
					$rUserID = (int) file_get_contents(LINES_TMP_PATH . 'line_t_' . $rUsername);
				}
				else {
					$rUserID = (int) file_get_contents(LINES_TMP_PATH . 'line_t_' . strtolower($rUsername));
				}
			}
			else if (!empty($rUsername) && !empty($rPassword)) {
				if (self::$rSettings['case_sensitive_line']) {
					$rUserID = (int) file_get_contents(LINES_TMP_PATH . 'line_c_' . $rUsername . '_' . $rPassword);
				}
				else {
					$rUserID = (int) file_get_contents(LINES_TMP_PATH . 'line_c_' . strtolower($rUsername) . '_' . strtolower($rPassword));
				}
			}
			else if (empty($rUserID)) {
				return false;
			}

			if ($rUserID) {
				$rUserInfo = igbinary_unserialize(file_get_contents(LINES_TMP_PATH . 'line_i_' . $rUserID));
			}
		}
		else {
			if (empty($rPassword) && empty($rUserID) && (strlen($rUsername) == 32)) {
				self::$db->query('SELECT * FROM `lines` WHERE `is_mag` = 0 AND `is_e2` = 0 AND `access_token` = ? AND LENGTH(`access_token`) = 32', $rUsername);
			}
			else if (!empty($rUsername) && !empty($rPassword)) {
				self::$db->query('SELECT `lines`.*, `mag_devices`.`token` AS `mag_token` FROM `lines` LEFT JOIN `mag_devices` ON `mag_devices`.`user_id` = `lines`.`id` WHERE `username` = ? AND `password` = ? LIMIT 1', $rUsername, $rPassword);
			}
			else if (!empty($rUserID)) {
				self::$db->query('SELECT `lines`.*, `mag_devices`.`token` AS `mag_token` FROM `lines` LEFT JOIN `mag_devices` ON `mag_devices`.`user_id` = `lines`.`id` WHERE `id` = ?', $rUserID);
			}
			else {
				return false;
			}

			if (0 < self::$db->num_rows()) {
				$rUserInfo = self::$db->get_row();
			}
		}

		if ($rUserInfo) {
			if (self::$rCached) {
				if (empty($rPassword) && empty($rUserID) && (strlen($rUsername) == 32)) {
					if ($rUsername != $rUserInfo['access_token']) {
						return false;
					}
				}
				else if (!empty($rUsername) && !empty($rPassword)) {
					if (($rUsername != $rUserInfo['username']) || ($rPassword != $rUserInfo['password'])) {
						return false;
					}
				}
			}
			if ((self::$rSettings['county_override_1st'] == 1) && empty($rUserInfo['forced_country']) && !empty($rIP) && ($rUserInfo['max_connections'] == 1)) {
				$rUserInfo['forced_country'] = self::getIPInfo($rIP)['registered_country']['iso_code'];

				if (self::$rCached) {
					self::setSignal('forced_country/' . $rUserInfo['id'], $rUserInfo['forced_country']);
				}
				else {
					self::$db->query('UPDATE `lines` SET `forced_country` = ? WHERE `id` = ?', $rUserInfo['forced_country'], $rUserInfo['id']);
				}
			}

			$rUserInfo['bouquet'] = json_decode($rUserInfo['bouquet'], true);
			$rUserInfo['allowed_ips'] = @array_filter(array_map('trim', json_decode($rUserInfo['allowed_ips'], true)));
			$rUserInfo['allowed_ua'] = @array_filter(array_map('trim', json_decode($rUserInfo['allowed_ua'], true)));
			$rUserInfo['allowed_outputs'] = array_map('intval', json_decode($rUserInfo['allowed_outputs'], true));
			$rUserInfo['output_formats'] = [];

			if (self::$rCached) {
				foreach (igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'output_formats')) as $rRow) {
					if (in_array((int) $rRow['access_output_id'], $rUserInfo['allowed_outputs'])) {
						$rUserInfo['output_formats'][] = $rRow['output_key'];
					}
				}
			}
			else {
				self::$db->query('SELECT `access_output_id`, `output_key` FROM `output_formats`;');

				foreach (self::$db->get_rows() as $rRow) {
					if (in_array((int) $rRow['access_output_id'], $rUserInfo['allowed_outputs'])) {
						$rUserInfo['output_formats'][] = $rRow['output_key'];
					}
				}
			}

			$rUserInfo['con_isp_name'] = NULL;
			$rUserInfo['isp_violate'] = 0;
			$rUserInfo['isp_is_server'] = 0;
			if ((self::$rSettings['show_isps'] == 1) && !empty($rIP)) {
				$rISPLock = self::getISP($rIP);

				if (is_array($rISPLock)) {
					if (!empty($rISPLock['isp'])) {
						$rUserInfo['con_isp_name'] = $rISPLock['isp'];
						$rUserInfo['isp_asn'] = $rISPLock['autonomous_system_number'];
						$rUserInfo['isp_violate'] = self::checkISP($rUserInfo['con_isp_name']);

						if (self::$rSettings['block_svp'] == 1) {
							$rUserInfo['isp_is_server'] = (int) self::checkServer($rUserInfo['isp_asn']);
						}
					}
				}
				if (!empty($rUserInfo['con_isp_name']) && (self::$rSettings['enable_isp_lock'] == 1) && ($rUserInfo['is_stalker'] == 0) && ($rUserInfo['is_isplock'] == 1) && !empty($rUserInfo['isp_desc']) && (strtolower($rUserInfo['con_isp_name']) != strtolower($rUserInfo['isp_desc']))) {
					$rUserInfo['isp_violate'] = 1;
				}
				if (($rUserInfo['isp_violate'] == 0) && (strtolower($rUserInfo['con_isp_name']) != strtolower($rUserInfo['isp_desc']))) {
					if (self::$rCached) {
						self::setSignal('isp/' . $rUserInfo['id'], json_encode([$rUserInfo['con_isp_name'], $rUserInfo['isp_asn']]));
					}
					else {
						self::$db->query('UPDATE `lines` SET `isp_desc` = ?, `as_number` = ? WHERE `id` = ?', $rUserInfo['con_isp_name'], $rUserInfo['isp_asn'], $rUserInfo['id']);
					}
				}
			}

			if ($rGetChannelIDs) {
				$rLiveIDs = $rVODIDs = $rRadioIDs = $rCategoryIDs = $rChannelIDs = $rSeriesIDs = [];

				foreach ($rUserInfo['bouquet'] as $rID) {
					if (isset(self::$rBouquets[$rID]['streams'])) {
						$rChannelIDs = array_merge($rChannelIDs, self::$rBouquets[$rID]['streams']);
					}

					if (isset(self::$rBouquets[$rID]['series'])) {
						$rSeriesIDs = array_merge($rSeriesIDs, self::$rBouquets[$rID]['series']);
					}

					if (isset(self::$rBouquets[$rID]['channels'])) {
						$rLiveIDs = array_merge($rLiveIDs, self::$rBouquets[$rID]['channels']);
					}

					if (isset(self::$rBouquets[$rID]['movies'])) {
						$rVODIDs = array_merge($rVODIDs, self::$rBouquets[$rID]['movies']);
					}

					if (isset(self::$rBouquets[$rID]['radios'])) {
						$rRadioIDs = array_merge($rRadioIDs, self::$rBouquets[$rID]['radios']);
					}
				}

				$rUserInfo['channel_ids'] = array_map('intval', array_unique($rChannelIDs));
				$rUserInfo['series_ids'] = array_map('intval', array_unique($rSeriesIDs));
				$rUserInfo['vod_ids'] = array_map('intval', array_unique($rVODIDs));
				$rUserInfo['live_ids'] = array_map('intval', array_unique($rLiveIDs));
				$rUserInfo['radio_ids'] = array_map('intval', array_unique($rRadioIDs));
			}

			$rAllowedCategories = [];
			$rCategoryMap = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'category_map'));

			foreach ($rUserInfo['bouquet'] as $rID) {
				$rAllowedCategories = array_merge($rAllowedCategories, $rCategoryMap[$rID] ?: []);
			}

			$rUserInfo['category_ids'] = array_values(array_unique($rAllowedCategories));
			return $rUserInfo;
		}

		return false;
	}

	static public function setSignal($rKey, $rData)
	{
		file_put_contents(SIGNALS_TMP_PATH . 'cache_' . md5($rKey), json_encode([$rKey, $rData]));
	}

	static public function validateHMAC($rHMAC, $rExpiry, $rStreamID, $rExtension, $rIP = '', $rMACIP = '', $rIdentifier = '', $rMaxConnections = 0)
	{
		if ((0 < strlen($rIP)) && (0 < strlen($rMACIP))) {
			if ($rIP != $rMACIP) {
				return NULL;
			}
		}

		$rKeyID = NULL;

		if (self::$rCached) {
			$rKeys = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'hmac_keys'));
		}
		else {
			$rKeys = [];
			self::$db->query('SELECT `id`, `key` FROM `hmac_keys` WHERE `enabled` = 1;');

			foreach (self::$db->get_rows() as $rKey) {
				$rKeys[] = $rKey;
			}
		}

		foreach ($rKeys as $rKey) {
			$rResult = hash_hmac('sha256', $rStreamID . '##' . $rExtension . '##' . $rExpiry . '##' . $rMACIP . '##' . $rIdentifier . '##' . $rMaxConnections, Xcms\Functions::decrypt($rKey['key'], OPENSSL_EXTRA));

			if (md5($rResult) == md5($rHMAC)) {
				$rKeyID = $rKey['id'];
				break;
			}
		}

		return $rKeyID;
	}

	static public function clientLog($rStreamID, $rUserID, $rAction, $rIP, $rData = '', $bypass = false)
	{
		if ((self::$rSettings['client_logs_save'] == 0) && !$bypass) {
			return NULL;
		}

		$rUserAgent = (!empty($_SERVER['HTTP_USER_AGENT']) ? htmlentities($_SERVER['HTTP_USER_AGENT']) : '');
		$rData = ['user_id' => $rUserID, 'stream_id' => $rStreamID, 'action' => $rAction, 'query_string' => htmlentities($_SERVER['QUERY_STRING']), 'user_agent' => $rUserAgent, 'user_ip' => $rIP, 'time' => time(), 'extra_data' => $rData];
		file_put_contents(LOGS_TMP_PATH . 'client_request.log', base64_encode(json_encode($rData)) . "\n", FILE_APPEND);
	}

	static public function checkBlockedUAs($rUserAgent, $rReturn = false)
	{
		$rUserAgent = strtolower($rUserAgent);

		foreach (self::$rBlockedUA as $rKey => $rBlocked) {
			if ($rBlocked['exact_match'] == 1) {
				if ($rUserAgent == $rBlocked['blocked_ua']) {
					return true;
				}
			}
			else {
				if (stristr($rUserAgent, $rBlocked['blocked_ua'])) {
					return true;
				}
			}
		}

		return false;
	}

	static public function isMonitorRunning($rPID, $rStreamID, $rEXE = PHP_BIN)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe') && (strpos(basename(readlink('/proc/' . $rPID . '/exe')), basename($rEXE)) === 0)) {
			$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
			if (($rCommand == 'XCMS[' . $rStreamID . ']') || ($rCommand == 'XCMSProxy[' . $rStreamID . ']')) {
				return true;
			}
		}

		return false;
	}

	static public function isStreamRunning($rPID, $rStreamID)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe')) {
			if (strpos(basename(readlink('/proc/' . $rPID . '/exe')), 'ffmpeg') === 0) {
				$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
				if (stristr($rCommand, '/' . $rStreamID . '_.m3u8') || stristr($rCommand, '/' . $rStreamID . '_%d.ts')) {
					return true;
				}
			}
			else if (strpos(basename(readlink('/proc/' . $rPID . '/exe')), 'php') === 0) {
				return true;
			}
		}

		return false;
	}

	static public function isProcessRunning($rPID, $rEXE)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe') && (strpos(basename(readlink('/proc/' . $rPID . '/exe')), basename($rEXE)) === 0)) {
			return true;
		}

		return false;
	}

	static public function startMonitor($rStreamID, $rRestart = 0)
	{
		shell_exec(PHP_BIN . ' ' . CLI_PATH . 'monitor.php ' . (int) $rStreamID . ' ' . (int) $rRestart . ' >/dev/null 2>/dev/null &');
		return true;
	}

	static public function startProxy($rStreamID)
	{
		shell_exec(PHP_BIN . ' ' . CLI_PATH . 'proxy.php ' . (int) $rStreamID . ' >/dev/null 2>/dev/null &');
		return true;
	}

	static public function sendSignal($rSignalData, $rSegmentFile, $rCodec = 'h264', $rReturn = false)
	{
		if (empty($rSignalData['xy_offset'])) {
			$x = rand(150, 380);
			$y = rand(110, 250);
		}
		else {
			list($x, $y) = explode('x', $rSignalData['xy_offset']);
		}

		if ($rReturn) {
			$rOutput = SIGNALS_TMP_PATH . $rSignalData['activity_id'] . '_' . $rSegmentFile;
			shell_exec(self::$rFFMPEG_CPU . ' -copyts -vsync 0 -nostats -nostdin -hide_banner -loglevel quiet -y -i ' . escapeshellarg(STREAMS_PATH . $rSegmentFile) . ' -filter_complex "drawtext=fontfile=' . FFMPEG_FONT . ':text=\'' . escapeshellcmd($rSignalData['message']) . '\':fontsize=' . escapeshellcmd($rSignalData['font_size']) . ':x=' . (int) $x . ':y=' . (int) $y . ':fontcolor=' . escapeshellcmd($rSignalData['font_color']) . ('" -map 0 -vcodec ' . $rCodec . ' -preset ultrafast -acodec copy -scodec copy -mpegts_flags +initial_discontinuity -mpegts_copyts 1 -f mpegts ') . escapeshellarg($rOutput));
			$rData = file_get_contents($rOutput);
			unlink($rOutput);
			return $rData;
		}
		else {
			passthru(self::$rFFMPEG_CPU . ' -copyts -vsync 0 -nostats -nostdin -hide_banner -loglevel quiet -y -i ' . escapeshellarg(STREAMS_PATH . $rSegmentFile) . ' -filter_complex "drawtext=fontfile=' . FFMPEG_FONT . ':text=\'' . escapeshellcmd($rSignalData['message']) . '\':fontsize=' . escapeshellcmd($rSignalData['font_size']) . ':x=' . (int) $x . ':y=' . (int) $y . ':fontcolor=' . escapeshellcmd($rSignalData['font_color']) . ('" -map 0 -vcodec ' . $rCodec . ' -preset ultrafast -acodec copy -scodec copy -mpegts_flags +initial_discontinuity -mpegts_copyts 1 -f mpegts -'));
			return true;
		}
	}

	static public function getUserIP()
	{
		return $_SERVER['REMOTE_ADDR'];
	}

	static public function getISP($rIP)
	{
		if (empty($rIP)) {
			return false;
		}

		$rResponse = (file_exists(CONS_TMP_PATH . md5($rIP) . '_isp') ? json_decode(file_get_contents(CONS_TMP_PATH . md5($rIP) . '_isp'), true) : NULL);

		if (!is_array($rResponse)) {
			$rGeoIP = new MaxMind\Db\Reader(GEOISP_BIN);
			$rResponse = $rGeoIP->get($rIP);
			$rGeoIP->close();

			if (is_array($rResponse)) {
				file_put_contents(CONS_TMP_PATH . md5($rIP) . '_isp', json_encode($rResponse));
			}
		}

		return $rResponse;
	}

	static public function checkISP($rConISP)
	{
		foreach (self::$rBlockedISP as $rISP) {
			if (strtolower($rConISP) == strtolower($rISP['isp'])) {
				return (int) $rISP['blocked'];
			}
		}

		return 0;
	}

	static public function checkServer($rASN)
	{
		return in_array($rASN, self::$rBlockedServers);
	}

	static public function getIPInfo($rIP)
	{
		if (empty($rIP)) {
			return false;
		}

		if (file_exists(CONS_TMP_PATH . md5($rIP) . '_geo2')) {
			return json_decode(file_get_contents(CONS_TMP_PATH . md5($rIP) . '_geo2'), true);
		}

		$rGeoIP = new MaxMind\Db\Reader(GEOLITE2_BIN);
		$rResponse = $rGeoIP->get($rIP);
		$rGeoIP->close();

		if ($rResponse) {
			file_put_contents(CONS_TMP_PATH . md5($rIP) . '_geo2', json_encode($rResponse));
		}

		return $rResponse;
	}

	static public function validateImage($rURL, $rForceProtocol = NULL)
	{
		if (substr($rURL, 0, 2) == 's:') {
			$rSplit = explode(':', $rURL, 3);
			$rServerURL = self::getPublicURL((int) $rSplit[1], $rForceProtocol);

			if ($rServerURL) {
				return $rServerURL . 'images/' . basename($rURL);
			}
			else {
				return '';
			}
		}
		else {
			return $rURL;
		}
	}

	static public function isRunning()
	{
		$rNginx = 0;
		exec('ps -fp $(pgrep -u xcms)', $rOutput, $rReturnVar);

		foreach ($rOutput as $rProcess) {
			$rSplit = explode(' ', preg_replace('!\\s+!', ' ', trim($rProcess)));
			if (($rSplit[8] == 'nginx:') && ($rSplit[9] == 'master')) {
				$rNginx++;
			}
		}

		return 0 < $rNginx;
	}

	static public function getPublicURL($rServerID = NULL, $rForceProtocol = NULL)
	{
		$rOriginatorID = NULL;

		if (!isset($rServerID)) {
			$rServerID = SERVER_ID;
		}

		if ($rForceProtocol) {
			$rProtocol = $rForceProtocol;
		}
		else if (isset($_SERVER['SERVER_PORT']) && self::$rSettings['keep_protocol']) {
			$rProtocol = ((!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) || ($_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http');
		}
		else {
			$rProtocol = self::$rServers[$rServerID]['server_protocol'];
		}

		if (self::$rServers[$rServerID]) {
			if (self::$rServers[$rServerID]['enable_proxy']) {
				$rProxyIDs = array_keys(self::getProxies($rServerID));

				if (count($rProxyIDs) == 0) {
					$rProxyIDs = array_keys(self::getProxies($rServerID, false));
				}

				if (count($rProxyIDs) == 0) {
					return '';
				}

				$rOriginatorID = $rServerID;
				$rServerID = $rProxyIDs[array_rand($rProxyIDs)];
			}

			$rHost = (defined('host') ? HOST : NULL);
			if ($rHost && in_array(strtolower($rHost), array_map('strtolower', self::$rServers[$rServerID]['domains']['urls']))) {
				$rDomain = $rHost;
			}
			else {
				$rDomain = (empty(self::$rServers[$rServerID]['domain_name']) ? self::$rServers[$rServerID]['server_ip'] : explode(',', self::$rServers[$rServerID]['domain_name'])[0]);
			}

			$rServerURL = $rProtocol . '://' . $rDomain . ':' . self::$rServers[$rServerID][$rProtocol . '_broadcast_port'] . '/';
			if ((self::$rServers[$rServerID]['server_type'] == 1) && $rOriginatorID && (self::$rServers[$rOriginatorID]['is_main'] == 0)) {
				$rServerURL .= md5($rServerID . '_' . $rOriginatorID . '_' . OPENSSL_EXTRA) . '/';
			}

			return $rServerURL;
		}

		return NULL;
	}

	static public function getCategories($rType = NULL)
	{
		$rReturn = [];

		foreach (self::$rCategories as $rCategory) {
			if (($rType == $rCategory['category_type']) || !$rType) {
				$rReturn[] = $rCategory;
			}
		}

		return $rReturn;
	}

	static public function matchCIDR($rASN, $rIP)
	{
		if (file_exists(CIDR_TMP_PATH . $rASN)) {
			$rCIDRs = json_decode(file_get_contents(CIDR_TMP_PATH . $rASN), true);

			foreach ($rCIDRs as $rCIDR => $rData) {
				if ((ip2long($rData[1]) <= ip2long($rIP)) && (ip2long($rIP) <= ip2long($rData[2]))) {
					return $rData;
				}
			}
		}

		return NULL;
	}

	static public function getLLODSegments($rStreamID, $rPlaylist, $rPrebuffer = 1)
	{
		$rPrebuffer++;
		$rSegments = $rKeySegments = [];

		if (file_exists($rPlaylist)) {
			$rSource = file_get_contents($rPlaylist);

			if (preg_match_all('/(.*?).ts((#\\w+)+|#?)/', $rSource, $rMatches)) {
				if (0 < count($rMatches[1])) {
					$rLastKey = NULL;

					for ($i = 0; $i < count($rMatches[1]); $i++) {
						$rFilename = $rMatches[1][$i];
						list($rSID, $rSegmentID) = explode('_', $rFilename);

						if (!empty($rMatches[2][$i])) {
							$rKeySegments[$rSegmentID] = [];
							$rLastKey = $rSegmentID;
						}

						if ($rLastKey) {
							$rKeySegments[$rLastKey][] = $rSegmentID;
						}
					}
				}
			}

			$rKeySegments = array_slice($rKeySegments, count($rKeySegments) - $rPrebuffer, $rPrebuffer, true);

			foreach ($rKeySegments as $rKeySegment => $rSubSegments) {
				foreach ($rSubSegments as $rSegmentID) {
					$rSegments[] = $rStreamID . '_' . $rSegmentID . '.ts';
				}
			}
		}

		return !empty($rSegments) ? $rSegments : NULL;
	}

	static public function getPlaylistSegments($rPlaylist, $rPrebuffer = 0, $rSegmentDuration = 10)
	{
		if (file_exists($rPlaylist)) {
			$rSource = file_get_contents($rPlaylist);

			if (preg_match_all('/(.*?).ts/', $rSource, $rMatches)) {
				if (0 < $rPrebuffer) {
					$rTotalSegments = (int) ($rPrebuffer / $rSegmentDuration);

					if (!$rTotalSegments) {
						$rTotalSegments = 1;
					}

					return array_slice($rMatches[0], $rTotalSegments * -1);
				}
				else if ($rPrebuffer == -1) {
					return $rMatches[0];
				}
				else {
					preg_match('/_(.*)\\./', array_pop($rMatches[0]), $rCurrentSegment);
					return $rCurrentSegment[1];
				}
			}
		}

		return NULL;
	}

	static public function generateHLS($rM3U8, $rUsername, $rPassword, $rStreamID, $rUUID, $rIP, $rIsHMAC = NULL, $rIdentifier = '', $rVideoCodec = 'h264', $rOnDemand = 0, $rServerID = NULL, $rProxyID = NULL)
	{
		if (file_exists($rM3U8)) {
			$rSource = file_get_contents($rM3U8);
			$rRand = self::$rSettings['nginx_key'];
			$rRandValue = md5(mt_rand(0, 65535) . time() . mt_rand(0, 65535));
			if (self::$rSettings['encrypt_hls'] && !$rOnDemand) {
				$rKeyToken = Xcms\Functions::encrypt($rIP . '/' . $rStreamID, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
				$rSource = '#EXTM3U' . "\n" . '#EXT-X-KEY:METHOD=AES-128,URI="' . ($rProxyID ? '/' . md5($rProxyID . '_' . $rServerID . '_' . OPENSSL_EXTRA) : '') . ('/key/' . $rKeyToken . '",IV=0x') . bin2hex(file_get_contents(STREAMS_PATH . $rStreamID . '_.iv')) . "\n" . substr($rSource, 8, strlen($rSource) - 8);
			}

			if (preg_match_all('/(.*?)\\.ts/', $rSource, $rMatches)) {
				foreach ($rMatches[0] as $rMatch) {
					if ($rIsHMAC) {
						$rToken = Xcms\Functions::encrypt('HMAC#' . $rIsHMAC . '/' . $rIdentifier . '/' . $rIP . '/' . $rStreamID . '/' . $rMatch . '/' . $rUUID . '/' . SERVER_ID . '/' . $rVideoCodec . '/' . $rOnDemand, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
					}
					else {
						$rToken = Xcms\Functions::encrypt($rUsername . '/' . $rPassword . '/' . $rIP . '/' . $rStreamID . '/' . $rMatch . '/' . $rUUID . '/' . SERVER_ID . '/' . $rVideoCodec . '/' . $rOnDemand, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
					}

					if (self::$rSettings['allow_cdn_access']) {
						$rSource = str_replace($rMatch, ($rProxyID ? '/' . md5($rProxyID . '_' . $rServerID . '_' . OPENSSL_EXTRA) : '') . ('/hls/' . $rMatch . '?token=' . $rToken . '&' . $rRand . '=' . $rRandValue), $rSource);
					}
					else {
						$rSource = str_replace($rMatch, ($rProxyID ? '/' . md5($rProxyID . '_' . $rServerID . '_' . OPENSSL_EXTRA) : '') . ('/hls/' . $rToken . '&' . $rRand . '=' . $rRandValue), $rSource);
					}
				}

				return $rSource;
			}
		}

		return false;
	}

	static public function validateConnections($rUserInfo, $rIsHMAC = false, $rIdentifier = '', $rIP = NULL, $rUserAgent = NULL)
	{
		if ($rUserInfo['max_connections'] != 0) {
			if (!$rIsHMAC) {
				if (!empty($rUserInfo['pair_id'])) {
					self::closeConnections($rUserInfo['pair_id'], $rUserInfo['max_connections'], NULL, '', $rIP, $rUserAgent);
				}

				self::closeConnections($rUserInfo['id'], $rUserInfo['max_connections'], NULL, '', $rIP, $rUserAgent);
			}
			else {
				self::closeConnections(NULL, $rUserInfo['max_connections'], $rIsHMAC, $rIdentifier, $rIP, $rUserAgent);
			}
		}
	}

	static public function getBouquetMap($rStreamID)
	{
		$rBouquetMap = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'bouquet_map'));
		$rReturn = $rBouquetMap[$rStreamID] ?: [];
		unset($rBouquetMap);
		return $rReturn;
	}

	static public function getStreamData($rStreamID)
	{
		$rOutput = [];
		self::$db->query('SELECT * FROM `streams` t1 LEFT JOIN `streams_types` t2 ON t2.type_id = t1.type WHERE t1.`id` = ?', $rStreamID);

		if (0 < self::$db->num_rows()) {
			$rStreamInfo = self::$db->get_row();
			$rServers = [];
			if (($rStreamInfo['direct_source'] == 0) || ($rStreamInfo['direct_proxy'] == 1)) {
				self::$db->query('SELECT * FROM `streams_servers` WHERE `stream_id` = ?', $rStreamID);

				if (0 < self::$db->num_rows()) {
					$rServers = self::$db->get_rows(true, 'server_id');
				}
			}

			$rOutput['bouquets'] = self::getBouquetMap($rStreamID);
			$rOutput['info'] = $rStreamInfo;
			$rOutput['servers'] = $rServers;
		}

		return !empty($rOutput) ? $rOutput : false;
	}

	static public function getMainID()
	{
		foreach (self::$rServers as $rServerID => $rServer) {
			if ($rServer['is_main']) {
				return $rServerID;
			}
		}

		return NULL;
	}

	static public function addToQueue($rStreamID, $rAddPID)
	{
		$rActivePIDs = $rPIDs = [];

		if (file_exists(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID)) {
			$rPIDs = igbinary_unserialize(file_get_contents(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID));
		}

		foreach ($rPIDs as $rPID) {
			if (self::isProcessRunning($rPID, 'php-fpm')) {
				$rActivePIDs[] = $rPID;
			}
		}

		if (!in_array($rActivePIDs, $rAddPID)) {
			$rActivePIDs[] = $rAddPID;
		}

		file_put_contents(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID, igbinary_serialize($rActivePIDs));
	}

	static public function removeFromQueue($rStreamID, $rPID)
	{
		$rActivePIDs = [];

		foreach (igbinary_unserialize(file_get_contents(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID)) ?: [] as $rActivePID) {
			if (self::isProcessRunning($rActivePID, 'php-fpm') && ($rPID != $rActivePID)) {
				$rActivePIDs[] = $rActivePID;
			}
		}

		if (0 < count($rActivePIDs)) {
			file_put_contents(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID, igbinary_serialize($rActivePIDs));
		}
		else {
			unlink(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID);
		}
	}

	static public function generateString($rLength = 10)
	{
		$rCharacters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789qwertyuiopasdfghjklzxcvbnm';
		$rString = '';
		$rMax = strlen($rCharacters) - 1;

		for ($i = 0; $i < $rLength; $i++) {
			$rString .= $rCharacters[rand(0, $rMax)];
		}

		return $rString;
	}

	static public function formatTitle($rTitle, $rYear)
	{
		if (is_numeric($rYear) && (1900 <= $rYear) && ($rYear <= (int) (date('Y') + 1))) {
			if (self::$rSettings['movie_year_append'] == 0) {
				return trim($rTitle) . (' (' . $rYear . ')');
			}
			else if (self::$rSettings['movie_year_append'] == 0) {
				return trim($rTitle) . (' - ' . $rYear);
			}
		}

		return $rTitle;
	}

	static public function sortChannels($rChannels)
	{
		if ((0 < count($rChannels)) && file_exists(CACHE_TMP_PATH . 'channel_order') && (self::$rSettings['channel_number_type'] != 'bouquet')) {
			$rOrder = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'channel_order'));
			$rChannels = array_flip($rChannels);
			$rNewOrder = [];

			foreach ($rOrder as $rID) {
				if (isset($rChannels[$rID])) {
					$rNewOrder[] = $rID;
				}
			}

			if (0 < count($rNewOrder)) {
				return $rNewOrder;
			}
		}

		return $rChannels;
	}

	static public function sortSeries($rSeries)
	{
		if ((0 < count($rSeries)) && file_exists(CACHE_TMP_PATH . 'series_order')) {
			$rOrder = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'series_order'));
			$rSeries = array_flip($rSeries);
			$rNewOrder = [];

			foreach ($rOrder as $rID) {
				if (isset($rSeries[$rID])) {
					$rNewOrder[] = $rID;
				}
			}

			if (0 < count($rNewOrder)) {
				return $rNewOrder;
			}
		}

		return $rSeries;
	}

	static public function getDiffTimezone($rTimezone)
	{
		$rServerTZ = new DateTime('UTC', new DateTimeZone(date_default_timezone_get()));
		$rUserTZ = new DateTime('UTC', new DateTimeZone($rTimezone));
		return $rUserTZ->getTimestamp() - $rServerTZ->getTimestamp();
	}

	static public function getAdultCategories()
	{
		$rReturn = [];

		foreach (self::$rCategories as $rCategory) {
			if ($rCategory['is_adult']) {
				$rReturn[] = (int) $rCategory['id'];
			}
		}

		return $rReturn;
	}

	static public function connectRedis()
	{
		if (!is_object(self::$redis)) {
			try {
				self::$redis = new Redis();
				self::$redis->connect(self::$rConfig['hostname'], 6379);
				self::$redis->auth(self::$rSettings['redis_password']);
			}
			catch (Exception $e) {
				self::$redis = NULL;
				return false;
			}
		}

		return true;
	}

	static public function closeRedis()
	{
		if (is_object(self::$redis)) {
			self::$redis->close();
			self::$redis = NULL;
		}

		return true;
	}

	static public function getConnection($rUUID)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		return igbinary_unserialize(self::$redis->get($rUUID));
	}

	static public function createConnection($rData)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rRedis = self::$redis->multi();
		$rRedis->zAdd('LINE#' . $rData['identity'], $rData['date_start'], $rData['uuid']);
		$rRedis->zAdd('LINE_ALL#' . $rData['identity'], $rData['date_start'], $rData['uuid']);
		$rRedis->zAdd('STREAM#' . $rData['stream_id'], $rData['date_start'], $rData['uuid']);
		$rRedis->zAdd('SERVER#' . $rData['server_id'], $rData['date_start'], $rData['uuid']);

		if ($rData['user_id']) {
			$rRedis->zAdd('SERVER_LINES#' . $rData['server_id'], $rData['user_id'], $rData['uuid']);
		}

		if ($rData['proxy_id']) {
			$rRedis->zAdd('PROXY#' . $rData['proxy_id'], $rData['date_start'], $rData['uuid']);
		}

		$rRedis->zAdd('CONNECTIONS', $rData['date_start'], $rData['uuid']);
		$rRedis->zAdd('LIVE', $rData['date_start'], $rData['uuid']);

		if (!empty($rData['uuid'])) {
			$rRedis->set($rData['uuid'], igbinary_serialize($rData));
		}

		return $rRedis->exec();
	}

	static public function updateConnection($rData, $rChanges = [], $rOption = NULL)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rOrigData = $rData;

		foreach ($rChanges as $rKey => $rValue) {
			$rData[$rKey] = $rValue;
		}

		$rRedis = self::$redis->multi();

		if ($rOption == 'open') {
			$rRedis->sRem('ENDED', $rData['uuid']);
			$rRedis->zAdd('LIVE', $rData['date_start'], $rData['uuid']);
			$rRedis->zAdd('LINE#' . $rData['identity'], $rData['date_start'], $rData['uuid']);
			$rRedis->zAdd('STREAM#' . $rData['stream_id'], $rData['date_start'], $rData['uuid']);
			$rRedis->zAdd('SERVER#' . $rData['server_id'], $rData['date_start'], $rData['uuid']);

			if ($rData['proxy_id']) {
				$rRedis->zAdd('PROXY#' . $rData['proxy_id'], $rData['date_start'], $rData['uuid']);
			}

			if ($rData['hls_end'] == 1) {
				$rData['hls_end'] = 0;

				if ($rData['user_id']) {
					$rRedis->zAdd('SERVER_LINES#' . $rData['server_id'], $rData['user_id'], $rData['uuid']);
				}
			}
		}
		else if ($rOption == 'close') {
			$rRedis->sAdd('ENDED', $rData['uuid']);
			$rRedis->zRem('LIVE', $rData['uuid']);
			$rRedis->zRem('LINE#' . $rOrigData['identity'], $rData['uuid']);
			$rRedis->zRem('STREAM#' . $rOrigData['stream_id'], $rData['uuid']);
			$rRedis->zRem('SERVER#' . $rOrigData['server_id'], $rData['uuid']);

			if ($rData['proxy_id']) {
				$rRedis->zRem('PROXY#' . $rOrigData['proxy_id'], $rData['uuid']);
			}

			if ($rData['hls_end'] == 0) {
				$rData['hls_end'] = 1;

				if ($rData['user_id']) {
					$rRedis->zRem('SERVER_LINES#' . $rOrigData['server_id'], $rData['uuid']);
				}
			}
		}

		$rRedis->set($rData['uuid'], igbinary_serialize($rData));

		if ($rRedis->exec()) {
			return $rData;
		}
		else {
			return NULL;
		}
	}

	static public function getConnections($rUserID, $rActive = false, $rKeys = false)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rKeys = self::$redis->zRangeByScore(($rActive ? 'LINE#' : 'LINE_ALL#') . $rUserID, '-inf', '+inf');

		if ($rKeys) {
			return $rKeys;
		}
		else {
			if (0 < count($rKeys)) {
				return array_map('igbinary_unserialize', self::$redis->mGet($rKeys));
			}

			return [];
		}
	}

	static public function redisSignal($rPID, $rServerID, $rRTMP, $rCustomData = NULL)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rKey = 'SIGNAL#' . md5($rServerID . '#' . $rPID . '#' . $rRTMP);
		$rData = ['pid' => $rPID, 'server_id' => $rServerID, 'rtmp' => $rRTMP, 'time' => time(), 'custom_data' => $rCustomData, 'key' => $rKey];
		return self::$redis->multi()->sAdd('SIGNALS#' . $rServerID, $rKey)->set($rKey, igbinary_serialize($rData))->exec();
	}

	static public function getNearest($rSearch, $rArray)
	{
		$rClosest = NULL;

		foreach ($rArray as $rItem) {
			if (($rClosest === NULL) || (abs($rItem - $rSearch) < abs($rSearch - $rClosest))) {
				$rClosest = $rItem;
			}
		}

		return $rClosest;
	}

	static public function getDomainName($rForceSSL = false)
	{
		$rOriginatorID = NULL;
		$rServerID = SERVER_ID;

		if ($rForceSSL) {
			$rProtocol = 'https';
		}
		else if (isset($_SERVER['SERVER_PORT']) && self::$rSettings['keep_protocol']) {
			$rProtocol = ((!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) || ($_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http');
		}
		else {
			$rProtocol = self::$rServers[$rServerID]['server_protocol'];
		}

		$rProxied = self::$rServers[$rServerID]['enable_proxy'];

		if ($rProxied) {
			$rProxyIDs = array_keys(self::getProxies($rServerID));

			if (count($rProxyIDs) == 0) {
				$rProxyIDs = array_keys(self::getProxies($rServerID, false));
			}

			if (count($rProxyIDs) == 0) {
				return '';
			}

			$rOriginatorID = $rServerID;
			$rServerID = $rProxyIDs[array_rand($rProxyIDs)];
		}

		list($rDomain, $rAccessPort) = explode(':', $_SERVER['HTTP_HOST']);
		if ($rProxied || (self::$rSettings['use_mdomain_in_lists'] == 1)) {
			if (in_array(strtolower($rDomain), self::getCache('reseller_domains') ?: [])) {
			}
			else if (empty(self::$rServers[$rServerID]['domain_name'])) {
				$rDomain = escapeshellcmd(self::$rServers[$rServerID]['server_ip']);
			}
			else {
				$rDomain = str_replace(['http://', '/', 'https://'], '', escapeshellcmd(explode(',', self::$rServers[$rServerID]['domain_name'])[0]));
			}
		}

		$rServerURL = $rProtocol . '://' . $rDomain . ':' . self::$rServers[$rServerID][$rProtocol . '_broadcast_port'] . '/';
		if ((self::$rServers[$rServerID]['server_type'] == 1) && $rOriginatorID && (self::$rServers[$rOriginatorID]['is_main'] == 0)) {
			$rServerURL .= md5($rServerID . '_' . $rOriginatorID . '_' . OPENSSL_EXTRA) . '/';
		}

		return $rServerURL;
	}

	static public function getProxies($rServerID, $rOnline = true)
	{
		$rReturn = [];

		foreach (self::$rServers as $rProxyID => $rServerInfo) {
			if (($rServerInfo['server_type'] == 1) && (in_array($rServerID, $rServerInfo['parent_id']) && ($rServerInfo['server_online'] || !$rOnline))) {
				$rReturn[$rProxyID] = $rServerInfo;
			}
		}

		return $rReturn;
	}

	static public function getStreamingURL($rServerID = NULL, $rOriginatorID = NULL, $rForceHTTP = false)
	{
		if (!isset($rServerID)) {
			$rServerID = SERVER_ID;
		}

		if ($rForceHTTP) {
			$rProtocol = 'http';
		}
		else if (self::$rSettings['keep_protocol']) {
			$rProtocol = ((!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) || ($_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http');
		}
		else {
			$rProtocol = self::$rServers[$rServerID]['server_protocol'];
		}

		$rDomain = NULL;
		if ((0 < strlen(HOST)) && in_array(strtolower(HOST), array_map('strtolower', self::$rServers[$rServerID]['domains']['urls']))) {
			$rDomain = HOST;
		}
		else if (self::$rServers[$rServerID]['random_ip'] && (0 < count(self::$rServers[$rServerID]['domains']['urls']))) {
			$rDomain = self::$rServers[$rServerID]['domains']['urls'][array_rand(self::$rServers[$rServerID]['domains']['urls'])];
		}

		if ($rDomain) {
			$rURL = $rProtocol . '://' . $rDomain . ':' . self::$rServers[$rServerID][$rProtocol . '_broadcast_port'];
		}
		else {
			$rURL = rtrim(self::$rServers[$rServerID][$rProtocol . '_url'], '/');
		}
		if ((self::$rServers[$rServerID]['server_type'] == 1) && $rOriginatorID && (self::$rServers[$rOriginatorID]['is_main'] == 0)) {
			$rURL .= '/' . md5($rServerID . '_' . $rOriginatorID . '_' . OPENSSL_EXTRA);
		}

		return $rURL;
	}
}

if (!class_exists('Database')) {
	class Database
	{
		public $result = null;
		public $dbh = null;
		public $connected = false;

		public function __construct($rConnect = true)
		{
			$this->dbh = false;

			if ($rConnect) {
				$this->db_connect();
			}
		}

		public function close_mysql()
		{
			if ($this->connected) {
				$this->connected = false;
				$this->dbh = NULL;
			}

			return true;
		}

		public function __destruct()
		{
			$this->close_mysql();
		}

		public function ping()
		{
			try {
				$this->dbh->query('SELECT 1');
			}
			catch (Exception $e) {
				return false;
			}

			return true;
		}

		public function db_connect()
		{
			try {
				$this->dbh = Xcms\Functions::connect();

				if (!$this->dbh) {
					exit(json_encode(['error' => 'MySQL: Cannot connect to database! Please check credentials.']));
				}
			}
			catch (PDOException $e) {
				exit(json_encode(['error' => 'MySQL: ' . $e->getMessage()]));
			}

			$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->connected = true;
			return true;
		}

		public function query($query, $buffered = false)
		{
			if ($this->dbh) {
				$numargs = func_num_args();
				$arg_list = func_get_args();
				$next_arg_list = [];

				for ($i = 1; $i < $numargs; $i++) {
					if (is_null($arg_list[$i]) || (strtolower($arg_list[$i]) == 'null')) {
						$next_arg_list[] = NULL;
						continue;
					}

					$next_arg_list[] = $arg_list[$i];
				}

				if ($buffered === true) {
					$this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
				}

				try {
					$this->result = $this->dbh->prepare($query);
					$this->result->execute($next_arg_list);
				}
				catch (Exception $e) {
					return false;
				}

				return true;
			}

			return false;
		}

		public function simple_query($query)
		{
			try {
				$this->result = $this->dbh->query($query);
			}
			catch (Exception $e) {
				return false;
			}

			return true;
		}

		public function get_rows($use_id = false, $column_as_id = '', $unique_row = true, $sub_row_id = '')
		{
			if ($this->dbh && $this->result) {
				$rows = [];

				if (0 < $this->result->rowCount()) {
					foreach ($this->result->fetchAll(PDO::FETCH_ASSOC) as $row) {
						if ($use_id && array_key_exists($column_as_id, $row)) {
							if (!isset($rows[$row[$column_as_id]])) {
								$rows[$row[$column_as_id]] = [];
							}

							if (!$unique_row) {
								if (!empty($sub_row_id) && array_key_exists($sub_row_id, $row)) {
									$rows[$row[$column_as_id]][$row[$sub_row_id]] = $row;
								}
								else {
									$rows[$row[$column_as_id]][] = $row;
								}
							}
							else {
								$rows[$row[$column_as_id]] = $row;
							}
						}
						else {
							$rows[] = $row;
						}
					}
				}

				$this->result = NULL;
				return $rows;
			}

			return false;
		}

		public function get_row()
		{
			if ($this->dbh && $this->result) {
				$row = [];

				if (0 < $this->result->rowCount()) {
					$row = $this->result->fetch(PDO::FETCH_ASSOC);
				}

				$this->result = NULL;
				return $row;
			}

			return false;
		}

		public function get_col()
		{
			if ($this->dbh && $this->result) {
				$row = false;

				if (0 < $this->result->rowCount()) {
					$row = $this->result->fetch();
					$row = $row[0];
				}

				$this->result = NULL;
				return $row;
			}

			return false;
		}

		public function escape($string)
		{
			if ($this->dbh) {
				return $this->dbh->quote($string);
			}

			return NULL;
		}

		public function num_fields()
		{
			if ($this->dbh && $this->result) {
				$mysqli_num_fields = $this->result->columnCount();
				return empty($mysqli_num_fields) ? 0 : $mysqli_num_fields;
			}

			return 0;
		}

		public function last_insert_id()
		{
			if ($this->dbh) {
				$mysql_insert_id = $this->dbh->lastInsertId();
				return empty($mysql_insert_id) ? 0 : $mysql_insert_id;
			}

			return NULL;
		}

		public function num_rows()
		{
			if ($this->dbh && $this->result) {
				$mysqli_num_rows = $this->result->rowCount();
				return empty($mysqli_num_rows) ? 0 : $mysqli_num_rows;
			}

			return 0;
		}
	}
}

?>