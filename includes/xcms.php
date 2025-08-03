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
	static public $rProxies = [];
	static public $rAllowedDomains = [];
	static public $rCategories = [];
	static public $rFFMPEG_CPU = null;
	static public $rFFMPEG_GPU = null;
	static public $rFFPROBE = null;
	static public $rCached = null;

	static public function init($rUseCache = false)
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

		if ($rUseCache) {
			self::$rSettings = self::getCache('settings');
		}
		else {
			self::$rSettings = self::getSettings();
		}

		if (!empty(self::$rSettings['default_timezone'])) {
			date_default_timezone_set(self::$rSettings['default_timezone']);
		}

		if (self::$rSettings['on_demand_wait_time'] == 0) {
			self::$rSettings['on_demand_wait_time'] = 15;
		}

		self::$rSegmentSettings = ['seg_type' => self::$rSettings['segment_type'], 'seg_time' => (int) self::$rSettings['seg_time'], 'seg_list_size' => (int) self::$rSettings['seg_list_size'], 'seg_delete_threshold' => (int) self::$rSettings['seg_delete_threshold']];

		switch (self::$rSettings['ffmpeg_cpu']) {
		case '5.0':
			self::$rFFMPEG_CPU = FFMPEG_BIN_50;
			self::$rFFPROBE = FFPROBE_BIN_50;
			break;
		default:
			self::$rFFMPEG_CPU = FFMPEG_BIN_40;
			self::$rFFPROBE = FFPROBE_BIN_40;
			break;
		}

		self::$rFFMPEG_GPU = FFMPEG_BIN_40;
		self::$rCached = self::$rSettings['enable_cache'];

		if ($rUseCache) {
			self::$rServers = self::getCache('servers');
			self::$rBouquets = self::getCache('bouquets');
			self::$rBlockedUA = self::getCache('blocked_ua');
			self::$rBlockedISP = self::getCache('blocked_isp');
			self::$rBlockedIPs = self::getCache('blocked_ips');
			self::$rProxies = self::getCache('proxy_servers');
			self::$rBlockedServers = self::getCache('blocked_servers');
			self::$rAllowedDomains = self::getCache('allowed_domains');
			self::$rAllowedIPs = self::getCache('allowed_ips');
			self::$rCategories = self::getCache('categories');
		}
		else {
			self::$rServers = self::getServers();
			self::$rBouquets = self::getBouquets();
			self::$rBlockedUA = self::getBlockedUA();
			self::$rBlockedISP = self::getBlockedISP();
			self::$rBlockedIPs = self::getBlockedIPs();
			self::$rProxies = self::getProxyIPs();
			self::$rBlockedServers = self::getBlockedServers();
			self::$rAllowedDomains = self::getAllowedDomains();
			self::$rAllowedIPs = self::getAllowedIPs();
			self::$rCategories = self::getCategories();
			self::generateCron();
		}
	}

	static public function getDiffTimezone($rTimezone)
	{
		$rServerTZ = new DateTime('UTC', new DateTimeZone(date_default_timezone_get()));
		$rUserTZ = new DateTime('UTC', new DateTimeZone($rTimezone));
		return $rUserTZ->getTimestamp() - $rServerTZ->getTimestamp();
	}

	static public function getAllowedDomains($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('allowed_domains', 20);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		$rDomains = ['127.0.0.1', 'localhost'];
		self::$db->query('SELECT `server_ip`, `private_ip`, `domain_name` FROM `servers` WHERE `enabled` = 1;');

		foreach (self::$db->get_rows() as $rRow) {
			foreach (explode(',', $rRow['domain_name']) as $rDomain) {
				$rDomains[] = $rDomain;
			}

			if ($rRow['server_ip']) {
				$rDomains[] = $rRow['server_ip'];
			}

			if ($rRow['private_ip']) {
				$rDomains[] = $rRow['private_ip'];
			}
		}

		self::$db->query('SELECT `reseller_dns` FROM `users` WHERE `status` = 1;');

		foreach (self::$db->get_rows() as $rRow) {
			if ($rRow['reseller_dns']) {
				$rDomains[] = $rRow['reseller_dns'];
			}
		}

		$rDomains = array_filter(array_unique($rDomains));
		self::setCache('allowed_domains', $rDomains);
		return $rDomains;
	}

	static public function getProxyIPs($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('proxy_servers', 20);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		$rOutput = [];

		foreach (self::$rServers as $rServer) {
			if ($rServer['server_type'] == 1) {
				$rOutput[$rServer['server_ip']] = $rServer;

				if ($rServer['private_ip']) {
					$rOutput[$rServer['private_ip']] = $rServer;
				}
			}
		}

		self::setCache('proxy_servers', $rOutput);
		return $rOutput;
	}

	static public function isProxy($rIP)
	{
		if (isset(self::$rProxies[$rIP])) {
			return self::$rProxies[$rIP];
		}

		return NULL;
	}

	static public function getBlockedUA($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('blocked_ua', 20);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		self::$db->query('SELECT id,exact_match,LOWER(user_agent) as blocked_ua FROM `blocked_uas`');
		$rOutput = self::$db->get_rows(true, 'id');
		self::setCache('blocked_ua', $rOutput);
		return $rOutput;
	}

	static public function getBlockedIPs($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('blocked_ips', 20);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		$rOutput = [];
		self::$db->query('SELECT `ip` FROM `blocked_ips`');

		foreach (self::$db->get_rows() as $rRow) {
			$rOutput[] = $rRow['ip'];
		}

		self::setCache('blocked_ips', $rOutput);
		return $rOutput;
	}

	static public function getBlockedISP($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('blocked_isp', 20);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		self::$db->query('SELECT id,isp,blocked FROM `blocked_isps`');
		$rOutput = self::$db->get_rows();
		self::setCache('blocked_isp', $rOutput);
		return $rOutput;
	}

	static public function getBlockedServers($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('blocked_servers', 20);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		$rOutput = [];
		self::$db->query('SELECT `asn` FROM `blocked_asns` WHERE `blocked` = 1;');

		foreach (self::$db->get_rows() as $rRow) {
			$rOutput[] = $rRow['asn'];
		}

		self::setCache('blocked_servers', $rOutput);
		return $rOutput;
	}

	static public function getBouquets($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('bouquets', 60);

			if (!empty($rCache)) {
				return $rCache;
			}
		}

		$rOutput = [];
		self::$db->query('SELECT *, IF(`bouquet_order` > 0, `bouquet_order`, 999) AS `order` FROM `bouquets` ORDER BY `order` ASC;');

		foreach (self::$db->get_rows(true, 'id') as $rID => $rChannels) {
			$rOutput[$rID]['streams'] = array_merge(json_decode($rChannels['bouquet_channels'], true), json_decode($rChannels['bouquet_movies'], true), json_decode($rChannels['bouquet_radios'], true));
			$rOutput[$rID]['series'] = json_decode($rChannels['bouquet_series'], true);
			$rOutput[$rID]['channels'] = json_decode($rChannels['bouquet_channels'], true);
			$rOutput[$rID]['movies'] = json_decode($rChannels['bouquet_movies'], true);
			$rOutput[$rID]['radios'] = json_decode($rChannels['bouquet_radios'], true);
		}

		self::setCache('bouquets', $rOutput);
		return $rOutput;
	}

	static public function getSettings($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('settings', 20);

			if (!empty($rCache)) {
				return $rCache;
			}
		}

		$rOutput = [];
		self::$db->query('SELECT * FROM `settings`');
		$rRows = self::$db->get_row();

		foreach ($rRows as $rKey => $rValue) {
			$rOutput[$rKey] = $rValue;
		}

		$rOutput['allow_countries'] = json_decode($rOutput['allow_countries'], true);
		$rOutput['allowed_stb_types'] = array_map('strtolower', json_decode($rOutput['allowed_stb_types'], true));
		$rOutput['stalker_lock_images'] = json_decode($rOutput['stalker_lock_images'], true);

		if (array_key_exists('bouquet_name', $rOutput)) {
			$rOutput['bouquet_name'] = str_replace(' ', '_', $rOutput['bouquet_name']);
		}

		$rOutput['api_ips'] = explode(',', $rOutput['api_ips']);
		$rOutput['live_streaming_pass'] = md5(sha1(self::$rSettings['license']) . OPENSSL_EXTRA);
		self::setCache('settings', $rOutput);
		return $rOutput;
	}

	static public function setCache($rCache, $rData)
	{
		$rData = igbinary_serialize($rData);
		file_put_contents(CACHE_TMP_PATH . $rCache, $rData, LOCK_EX);
	}

	static public function getCache($rCache, $rSeconds = NULL)
	{
		if (file_exists(CACHE_TMP_PATH . $rCache)) {
			if (!$rSeconds || ((time() - filemtime(CACHE_TMP_PATH . $rCache)) < $rSeconds)) {
				$rData = file_get_contents(CACHE_TMP_PATH . $rCache);
				return igbinary_unserialize($rData);
			}
		}

		return false;
	}

	static public function getServers($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('servers', 10);

			if (!empty($rCache)) {
				return $rCache;
			}
		}

		if (empty($_SERVER['REQUEST_SCHEME'])) {
			$_SERVER['REQUEST_SCHEME'] = 'http';
		}

		self::$db->query('SELECT * FROM `servers`');
		$rServers = [];
		$rOnlineStatus = [1];

		foreach (self::$db->get_rows() as $rRow) {
			if (empty($rRow['domain_name'])) {
				$rURL = escapeshellcmd($rRow['server_ip']);
			}
			else {
				$rURL = str_replace(['http://', '/', 'https://'], '', escapeshellcmd(explode(',', $rRow['domain_name'])[0]));
			}

			if ($rRow['enable_https'] == 1) {
				$rProtocol = 'https';
			}
			else {
				$rProtocol = 'http';
			}

			$rPort = ($rProtocol == 'http' ? (int) $rRow['http_broadcast_port'] : (int) $rRow['https_broadcast_port']);
			$rRow['server_protocol'] = $rProtocol;
			$rRow['request_port'] = $rPort;
			$rRow['site_url'] = $rProtocol . '://' . $rURL . ':' . $rPort . '/';
			$rRow['http_url'] = 'http://' . $rURL . ':' . (int) $rRow['http_broadcast_port'] . '/';
			$rRow['https_url'] = 'https://' . $rURL . ':' . (int) $rRow['https_broadcast_port'] . '/';
			$rRow['rtmp_server'] = 'rtmp://' . $rURL . ':' . (int) $rRow['rtmp_port'] . '/live/';
			$rRow['domains'] = ['protocol' => $rProtocol, 'port' => $rPort, 'urls' => array_filter(array_map('escapeshellcmd', explode(',', $rRow['domain_name'])))];
			$rRow['rtmp_mport_url'] = 'http://127.0.0.1:31210/';
			$rRow['api_url_ip'] = 'http://' . escapeshellcmd($rRow['server_ip']) . ':' . (int) $rRow['http_broadcast_port'] . '/api?password=' . urlencode(self::$rSettings['live_streaming_pass']);
			$rRow['api_url'] = $rRow['api_url_ip'];
			$rRow['site_url_ip'] = $rProtocol . '://' . escapeshellcmd($rRow['server_ip']) . ':' . $rPort . '/';
			$rRow['private_url_ip'] = (!empty($rRow['private_ip']) ? 'http://' . escapeshellcmd($rRow['private_ip']) . ':' . (int) $rRow['http_broadcast_port'] . '/' : NULL);
			$rRow['public_url_ip'] = 'http://' . escapeshellcmd($rRow['server_ip']) . ':' . (int) $rRow['http_broadcast_port'] . '/';
			$rRow['geoip_countries'] = (empty($rRow['geoip_countries']) ? [] : json_decode($rRow['geoip_countries'], true));
			$rRow['isp_names'] = (empty($rRow['isp_names']) ? [] : json_decode($rRow['isp_names'], true));

			if (is_numeric($rRow['parent_id'])) {
				$rRow['parent_id'] = [(int) $rRow['parent_id']];
			}
			else {
				$rRow['parent_id'] = array_map('intval', json_decode($rRow['parent_id'], true));
			}

			if ($rRow['enable_https'] == 2) {
				$rRow['allow_http'] = false;
			}
			else {
				$rRow['allow_http'] = true;
			}

			if ($rRow['server_type'] == 1) {
				$rLastCheckTime = 180;
			}
			else {
				$rLastCheckTime = 90;
			}

			$rRow['watchdog'] = json_decode($rRow['watchdog_data'], true);
			$rRow['server_online'] = ($rRow['enabled'] && (in_array($rRow['status'], $rOnlineStatus) && ((time() - $rRow['last_check_ago']) <= $rLastCheckTime))) || (SERVER_ID == $rRow['id']);
			$rServers[(int) $rRow['id']] = $rRow;
		}

		self::setCache('servers', $rServers);
		return $rServers;
	}

	static public function getMultiCURL($rURLs, $callback = NULL, $rTimeout = 5)
	{
		if (empty($rURLs)) {
			return [];
		}

		$rOffline = [];
		$rCurl = [];
		$rResults = [];
		$rMulti = curl_multi_init();

		foreach ($rURLs as $rKey => $rValue) {
			if (!self::$rServers[$rKey]['server_online']) {
				$rOffline[] = $rKey;
				continue;
			}

			$rCurl[$rKey] = curl_init();
			curl_setopt($rCurl[$rKey], CURLOPT_URL, $rValue['url']);
			curl_setopt($rCurl[$rKey], CURLOPT_RETURNTRANSFER, true);
			curl_setopt($rCurl[$rKey], CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($rCurl[$rKey], CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($rCurl[$rKey], CURLOPT_TIMEOUT, $rTimeout);
			curl_setopt($rCurl[$rKey], CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($rCurl[$rKey], CURLOPT_SSL_VERIFYPEER, 0);

			if ($rValue['postdata'] != NULL) {
				curl_setopt($rCurl[$rKey], CURLOPT_POST, true);
				curl_setopt($rCurl[$rKey], CURLOPT_POSTFIELDS, http_build_query($rValue['postdata']));
			}

			curl_multi_add_handle($rMulti, $rCurl[$rKey]);
		}

		$rActive = NULL;

		do {
			$rMultiExec = curl_multi_exec($rMulti, $rActive);
		} while ($rMultiExec == CURLM_CALL_MULTI_PERFORM);

		while ($rActive && ($rMultiExec == CURLM_OK)) {
			if (curl_multi_select($rMulti) == -1) {
				usleep(50000);
			}

			do {
				$rMultiExec = curl_multi_exec($rMulti, $rActive);
			} while ($rMultiExec == CURLM_CALL_MULTI_PERFORM);
		}

		foreach ($rCurl as $rKey => $rValue) {
			$rResults[$rKey] = curl_multi_getcontent($rValue);

			if ($callback != NULL) {
				$rResults[$rKey] = call_user_func($callback, $rResults[$rKey], true);
			}

			curl_multi_remove_handle($rMulti, $rValue);
		}

		foreach ($rOffline as $rKey) {
			$rResults[$rKey] = false;
		}

		curl_multi_close($rMulti);
		return $rResults;
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
		return preg_replace('/^([\\w\\.\\-\\_]+)$/', '$1', $rKey);
	}

	static public function parseCleanValue($rValue)
	{
		if ($rValue == '') {
			return '';
		}

		$rValue = str_replace('&#032;', ' ', stripslashes($rValue));
		$rValue = str_replace(["\r\n", "\n\r", "\r"], "\n", $rValue);
		$rValue = str_replace('<!--', '&#60;&#33;--', $rValue);
		$rValue = str_replace('-->', '--&#62;', $rValue);
		$rValue = str_ireplace('<script', '&#60;script', $rValue);
		$rValue = preg_replace('/&amp;#([0-9]+);/s', '&#\\1;', $rValue);
		$rValue = preg_replace('/&#(\\d+?)([^\\d;])/i', '&#\\1;\\2', $rValue);
		return trim($rValue);
	}

	static public function saveLog($rType, $rMessage, $rExtra = '', $rLine = 0)
	{
		if ((stripos($rExtra, 'panel_logs') === false) && (stripos($rMessage, 'timeout exceeded') === false) && (stripos($rMessage, 'lock wait timeout') === false) && (stripos($rMessage, 'duplicate entry') === false)) {
			panelLog($rType, $rMessage, $rExtra, $rLine);
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

	static public function mergeRecursive($rArray)
	{
		if (!is_array($rArray)) {
			return $rArray;
		}

		$rArrayValues = [];

		foreach ($rArray as $rValue) {
			if (is_scalar($rValue) || is_resource($rValue)) {
				$rArrayValues[] = $rValue;
			}
			else if (is_array($rValue)) {
				$rArrayValues = array_merge($rArrayValues, self::mergeRecursive($rValue));
			}
		}

		return $rArrayValues;
	}

	static public function getTotalTmpfs()
	{
		$rTotal = 0;
		exec('df | grep tmpfs', $rOutput);

		foreach ($rOutput as $rLine) {
			$rSplit = explode(' ', preg_replace('!\\s+!', ' ', $rLine));

			if ($rSplit[0] == 'tmpfs') {
				$rTotal += (int) $rSplit[2];
			}
		}

		return $rTotal;
	}

	static public function getStats()
	{
		$rJSON = [];
		$rJSON['cpu'] = round(self::getTotalCPU(), 2);
		$rJSON['cpu_cores'] = (int) shell_exec('cat /proc/cpuinfo | grep "^processor" | wc -l');
		$rJSON['cpu_avg'] = round((sys_getloadavg()[0] * 100) / ($rJSON['cpu_cores'] ?: 1), 2);
		$rJSON['cpu_name'] = trim(shell_exec('cat /proc/cpuinfo | grep \'model name\' | uniq | awk -F: \'{print $2}\''));

		if (100 < $rJSON['cpu_avg']) {
			$rJSON['cpu_avg'] = 100;
		}

		$rFree = explode("\n", trim(shell_exec('free')));
		$rMemory = preg_split('/[\\s]+/', $rFree[1]);
		$rTotalUsed = (int) $rMemory[2];
		$rTotalRAM = (int) $rMemory[1];
		$rJSON['total_mem'] = $rTotalRAM;
		$rJSON['total_mem_free'] = $rTotalRAM - $rTotalUsed;
		$rJSON['total_mem_used'] = $rTotalUsed + self::getTotalTmpfs();
		$rJSON['total_mem_used_percent'] = round(($rJSON['total_mem_used'] / $rJSON['total_mem']) * 100, 2);
		$rJSON['total_disk_space'] = disk_total_space(XCMS_HOME);
		$rJSON['free_disk_space'] = disk_free_space(XCMS_HOME);
		$rJSON['kernel'] = trim(shell_exec('uname -r'));
		$rJSON['uptime'] = self::getUptime();
		$rJSON['total_running_streams'] = (int) trim(shell_exec('ps ax | grep -v grep | grep -c ffmpeg'));
		$rJSON['bytes_sent'] = 0;
		$rJSON['bytes_sent_total'] = 0;
		$rJSON['bytes_received'] = 0;
		$rJSON['bytes_received_total'] = 0;
		$rJSON['network_speed'] = 0;
		$rJSON['interfaces'] = self::getNetworkInterfaces();
		$rJSON['network_speed'] = 0;

		if (100 < $rJSON['cpu']) {
			$rJSON['cpu'] = 100;
		}

		if ($rJSON['total_mem'] < $rJSON['total_mem_used']) {
			$rJSON['total_mem_used'] = $rJSON['total_mem'];
		}

		if (100 < $rJSON['total_mem_used_percent']) {
			$rJSON['total_mem_used_percent'] = 100;
		}

		$rInterface = self::$rServers[SERVER_ID]['network_interface'];

		if (file_exists('/sys/class/net/' . $rInterface . '/speed')) {
			$rJSON['network_speed'] = (int) file_get_contents('/sys/class/net/' . $rInterface . '/speed');
			$rJSON['bytes_sent_total'] = (int) trim(file_get_contents('/sys/class/net/' . $rInterface . '/statistics/tx_bytes')) ?: 0;
			$rJSON['bytes_received_total'] = (int) trim(file_get_contents('/sys/class/net/' . $rInterface . '/statistics/rx_bytes')) ?: 0;
			sleep(1);
			$bytes_sent_total = (int) trim(file_get_contents('/sys/class/net/' . $rInterface . '/statistics/tx_bytes')) ?: 0;
			$bytes_received_total = (int) trim(file_get_contents('/sys/class/net/' . $rInterface . '/statistics/rx_bytes')) ?: 0;
			$rJSON['bytes_sent'] = $bytes_sent_total - $rJSON['bytes_sent_total'];
			$rJSON['bytes_received'] = $bytes_received_total - $rJSON['bytes_received_total'];
		}

		$rJSON['iostat_info'] = $rJSON['gpu_info'] = $rJSON['video_devices'] = $rJSON['audio_devices'] = [];

		if (shell_exec('which iostat')) {
			$rJSON['iostat_info'] = self::getIO();
		}

		if (shell_exec('which nvidia-smi')) {
			$rJSON['gpu_info'] = self::getGPUInfo();
		}

		if (shell_exec('which v4l2-ctl')) {
			$rJSON['video_devices'] = self::getVideoDevices();
		}

		if (shell_exec('which arecord')) {
			$rJSON['audio_devices'] = self::getAudioDevices();
		}

		$rJSON['cpu_load_average'] = sys_getloadavg()[0];
		return $rJSON;
	}

	static public function getNetworkInterfaces()
	{
		$rReturn = [];
		exec('ls /sys/class/net/', $rOutput, $rReturnVar);

		foreach ($rOutput as $rInterface) {
			$rInterface = trim(rtrim($rInterface, ':'));

			if ($rInterface != 'lo') {
				if ($rInterface != 'bonding_masters') {
					$rReturn[] = $rInterface;
				}
			}
		}

		return $rReturn;
	}

	static public function getVideoDevices()
	{
		$rReturn = [];
		$rID = 0;

		try {
			$rDevices = array_values(array_filter(explode("\n", shell_exec('v4l2-ctl --list-devices'))));

			if (is_array($rDevices)) {
				foreach ($rDevices as $rKey => $rValue) {
					if (($rKey % 2) == 0) {
						$rReturn[$rID]['name'] = $rValue;
						$rReturn[$rID]['video_device'] = explode('/dev/', $rDevices[$rKey + 1])[1];
						$rID++;
					}
				}
			}
		}
		catch (Exception $e) {
		}

		return $rReturn;
	}

	static public function getAudioDevices()
	{
		try {
			return array_filter(explode("\n", shell_exec('arecord -L | grep "hw:CARD="')));
		}
		catch (Exception $e) {
			return [];
		}
	}

	static public function getIO()
	{
		exec('iostat -o JSON -m', $rOutput, $rReturnVar);
		$rOutput = implode('', $rOutput);
		$rJSON = json_decode($rOutput, true);

		if (isset($rJSON['sysstat'])) {
			return $rJSON['sysstat']['hosts'][0]['statistics'][0];
		}
		else {
			return [];
		}
	}

	static public function getGPUInfo()
	{
		exec('nvidia-smi -x -q', $rOutput, $rReturnVar);
		$rOutput = implode('', $rOutput);

		if (stripos($rOutput, '<?xml') !== false) {
			$rJSON = json_decode(json_encode(simplexml_load_string($rOutput)), true);

			if (isset($rJSON['driver_version'])) {
				$rGPU = [
					'attached_gpus'  => $rJSON['attached_gpus'],
					'driver_version' => $rJSON['driver_version'],
					'cuda_version'   => $rJSON['cuda_version'],
					'gpus'           => []
				];

				if (isset($rJSON['gpu']['board_id'])) {
					$rJSON['gpu'] = [$rJSON['gpu']];
				}

				foreach ($rJSON['gpu'] as $rInstance) {
					$rArray = [
						'name'           => $rInstance['product_name'],
						'power_readings' => $rInstance['power_readings'],
						'utilisation'    => $rInstance['utilization'],
						'memory_usage'   => $rInstance['fb_memory_usage'],
						'fan_speed'      => $rInstance['fan_speed'],
						'temperature'    => $rInstance['temperature'],
						'clocks'         => $rInstance['clocks'],
						'uuid'           => $rInstance['uuid'],
						'id'             => (int) $rInstance['pci']['pci_device'],
						'processes'      => []
					];

					foreach ($rInstance['processes']['process_info'] as $rProcess) {
						$rArray['processes'][] = ['pid' => (int) $rProcess['pid'], 'memory' => $rProcess['used_memory']];
					}

					$rGPU['gpus'][] = $rArray;
				}

				return $rGPU;
			}
		}

		return [];
	}

	static public function searchEPG($rArray, $rKey, $rValue)
	{
		$rResults = [];
		self::searchRecursive($rArray, $rKey, $rValue, $rResults);
		return $rResults;
	}

	static public function searchRecursive($rArray, $rKey, $rValue, &$rResults)
	{
		if (!is_array($rArray)) {
			return NULL;
		}
		if (isset($rArray[$rKey]) && ($rValue == $rArray[$rKey])) {
			$rResults[] = $rArray;
		}

		foreach ($rArray as $subarray) {
			self::searchRecursive($subarray, $rKey, $rValue, $rResults);
		}
	}

	static public function checkCron($rFilename, $rTime = 1800)
	{
		if (file_exists($rFilename)) {
			$rPID = trim(file_get_contents($rFilename));

			if (file_exists('/proc/' . $rPID)) {
				if ((time() - filemtime($rFilename)) < $rTime) {
					exit('Running...');
				}
				if (is_numeric($rPID) && (0 < $rPID)) {
					posix_kill($rPID, 9);
				}
			}
		}

		file_put_contents($rFilename, getmypid());
		return false;
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
						self::$db->query('INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES(?,?,?)', $rIP, 'FLOOD ATTACK', time());
						self::$rBlockedIPs = self::getBlockedIPs();
					}

					touch(FLOOD_TMP_PATH . 'block_' . $rIP);
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
					self::$db->query('INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES(?,?,?)', $rIP, 'BRUTEFORCE ' . strtoupper($rFloodType) . ' ATTACK', time());
					touch(FLOOD_TMP_PATH . 'block_' . $rIP);
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

	static public function getTotalCPU()
	{
		$rTotalLoad = 0;
		exec('ps -Ao pid,pcpu', $processes);

		foreach ($processes as $process) {
			$cols = explode(' ', preg_replace('!\\s+!', ' ', trim($process)));
			$rTotalLoad += (float) $cols[1];
		}

		return $rTotalLoad / (int) shell_exec('grep -P \'^processor\' /proc/cpuinfo|wc -l');
	}

	static public function getCategories($rType = NULL, $rForce = false)
	{
		if (is_string($rType)) {
			self::$db->query('SELECT t1.* FROM `streams_categories` t1 WHERE t1.category_type = ? GROUP BY t1.id ORDER BY t1.cat_order ASC', $rType);
			return 0 < self::$db->num_rows() ? self::$db->get_rows(true, 'id') : [];
		}
		else {
			if (!$rForce) {
				$rCache = self::getCache('categories', 20);

				if (!empty($rCache)) {
					return $rCache;
				}
			}

			self::$db->query('SELECT t1.* FROM `streams_categories` t1 ORDER BY t1.cat_order ASC');
			$rCategories = (0 < self::$db->num_rows() ? self::$db->get_rows(true, 'id') : []);
			self::setCache('categories', $rCategories);
			return $rCategories;
		}
	}

	static public function generateUniqueCode()
	{
		return substr(md5(self::$rSettings['live_streaming_pass']), 0, 15);
	}

	static public function unserialize_php($rSessionData)
	{
		$rReturn = [];
		$rOffset = 0;

		while ($rOffset < strlen($rSessionData)) {
			if (!strstr(substr($rSessionData, $rOffset), '|')) {
				return [];
			}

			$rPos = strpos($rSessionData, '|', $rOffset);
			$rNum = $rPos - $rOffset;
			$rVarName = substr($rSessionData, $rOffset, $rNum);
			$rOffset += $rNum + 1;
			$rData = igbinary_unserialize(substr($rSessionData, $rOffset));
			$rReturn[$rVarName] = $rData;
			$rOffset += strlen(igbinary_serialize($rData));
		}

		return $rReturn;
	}

	static public function generatePlaylist($rUserInfo, $rDeviceKey, $rOutputKey = 'ts', $rTypeKey = NULL, $rNoCache = false, $rProxy = false)
	{
		if (empty($rDeviceKey)) {
			return false;
		}

		if ($rOutputKey == 'mpegts') {
			$rOutputKey = 'ts';
		}

		if ($rOutputKey == 'hls') {
			$rOutputKey = 'm3u8';
		}

		if (empty($rOutputKey)) {
			self::$db->query('SELECT t1.output_ext FROM `output_formats` t1 INNER JOIN `output_devices` t2 ON t2.default_output = t1.access_output_id AND `device_key` = ?', $rDeviceKey);
		}
		else {
			self::$db->query('SELECT t1.output_ext FROM `output_formats` t1 WHERE `output_key` = ?', $rOutputKey);
		}

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rCacheName = $rUserInfo['id'] . '_' . $rDeviceKey . '_' . $rOutputKey . '_' . implode('_', $rTypeKey ?: []);
		$rOutputExt = self::$db->get_col();
		$rEncryptPlaylist = ($rUserInfo['is_restreamer'] ? self::$rSettings['encrypt_playlist_restreamer'] : self::$rSettings['encrypt_playlist']);

		if ($rUserInfo['is_stalker']) {
			$rEncryptPlaylist = false;
		}

		$rDomainName = self::getDomainName();

		if (!$rDomainName) {
			exit();
		}

		if (!$rProxy) {
			$rRTMPRows = [];

			if ($rOutputKey == 'rtmp') {
				self::$db->query('SELECT t1.id,t2.server_id FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id WHERE t1.rtmp_output = 1');
				$rRTMPRows = self::$db->get_rows(true, 'id', false, 'server_id');
			}
		}
		else if ($rOutputKey == 'rtmp') {
			$rOutputKey = 'ts';
		}

		if (empty($rOutputExt)) {
			$rOutputExt = 'ts';
		}

		self::$db->query('SELECT t1.*,t2.* FROM `output_devices` t1 LEFT JOIN `output_formats` t2 ON t2.access_output_id = t1.default_output WHERE t1.device_key = ? LIMIT 1', $rDeviceKey);

		if (0 < self::$db->num_rows()) {
			$rDeviceInfo = self::$db->get_row();

			if (strlen($rUserInfo['access_token']) == 32) {
				$rFilename = str_replace('{USERNAME}', $rUserInfo['access_token'], $rDeviceInfo['device_filename']);
			}
			else {
				$rFilename = str_replace('{USERNAME}', $rUserInfo['username'], $rDeviceInfo['device_filename']);
			}
			if ((0 < self::$rSettings['cache_playlists']) && !$rNoCache && file_exists(PLAYLIST_PATH . md5($rCacheName))) {
				header('Content-Description: File Transfer');
				header('Content-Type: audio/mpegurl');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Disposition: attachment; filename="' . $rFilename . '"');
				header('Content-Length: ' . filesize(PLAYLIST_PATH . md5($rCacheName)));
				readfile(PLAYLIST_PATH . md5($rCacheName));
				exit();
			}

			$rData = '';
			$rSeriesAllocation = $rSeriesEpisodes = $rSeriesInfo = [];
			$rUserInfo['episode_ids'] = [];

			if (0 < count($rUserInfo['series_ids'])) {
				if (self::$rCached) {
					foreach ($rUserInfo['series_ids'] as $rSeriesID) {
						$rSeriesInfo[$rSeriesID] = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'series_' . (int) $rSeriesID));
						$rSeriesData = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'episodes_' . (int) $rSeriesID));

						foreach ($rSeriesData as $rSeasonID => $rEpisodes) {
							foreach ($rEpisodes as $rEpisode) {
								$rSeriesEpisodes[$rEpisode['stream_id']] = [$rSeasonID, $rEpisode['episode_num']];
								$rSeriesAllocation[$rEpisode['stream_id']] = $rSeriesID;
								$rUserInfo['episode_ids'][] = $rEpisode['stream_id'];
							}
						}
					}
				}
				else {
					self::$db->query('SELECT * FROM `streams_series` WHERE `id` IN (' . implode(',', $rUserInfo['series_ids']) . ')');
					$rSeriesInfo = self::$db->get_rows(true, 'id');

					if (0 < count($rUserInfo['series_ids'])) {
						self::$db->query('SELECT stream_id, series_id, season_num, episode_num FROM `streams_episodes` WHERE series_id IN (' . implode(',', $rUserInfo['series_ids']) . ') ORDER BY FIELD(series_id,' . implode(',', $rUserInfo['series_ids']) . '), season_num ASC, episode_num ASC');

						foreach (self::$db->get_rows(true, 'series_id', false) as $rSeriesID => $rEpisodes) {
							foreach ($rEpisodes as $rEpisode) {
								$rSeriesEpisodes[$rEpisode['stream_id']] = [$rEpisode['season_num'], $rEpisode['episode_num']];
								$rSeriesAllocation[$rEpisode['stream_id']] = $rSeriesID;
								$rUserInfo['episode_ids'][] = $rEpisode['stream_id'];
							}
						}
					}
				}
			}

			if (0 < count($rUserInfo['episode_ids'])) {
				$rUserInfo['channel_ids'] = array_merge($rUserInfo['channel_ids'], $rUserInfo['episode_ids']);
			}

			$rChannelIDs = [];
			$rAdded = false;

			if ($rTypeKey) {
				foreach ($rTypeKey as $rType) {
					switch ($rType) {
					case 'live':
					case 'created_live':
						if ($rAdded) {
							break;
						}

						$rChannelIDs = array_merge($rChannelIDs, $rUserInfo['live_ids']);
						$rAdded = true;
						break;
					case 'movie':
						$rChannelIDs = array_merge($rChannelIDs, $rUserInfo['vod_ids']);
						break;
					case 'radio_streams':
						$rChannelIDs = array_merge($rChannelIDs, $rUserInfo['radio_ids']);
						break;
					case 'series':
						$rChannelIDs = array_merge($rChannelIDs, $rUserInfo['episode_ids']);
						break;
					}
				}
			}
			else {
				$rChannelIDs = $rUserInfo['channel_ids'];
			}

			if (in_array(self::$rSettings['channel_number_type'], ['bouquet_new' => true, 'manual' => true])) {
				$rChannelIDs = self::sortChannels($rChannelIDs);
			}

			unset($rUserInfo['live_ids']);
			unset($rUserInfo['vod_ids']);
			unset($rUserInfo['radio_ids']);
			unset($rUserInfo['episode_ids']);
			unset($rUserInfo['channel_ids']);
			$rOutputFile = NULL;
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');

			if (strlen($rUserInfo['access_token']) == 32) {
				header('Content-Disposition: attachment; filename="' . str_replace('{USERNAME}', $rUserInfo['access_token'], $rDeviceInfo['device_filename']) . '"');
			}
			else {
				header('Content-Disposition: attachment; filename="' . str_replace('{USERNAME}', $rUserInfo['username'], $rDeviceInfo['device_filename']) . '"');
			}

			if (0 < self::$rSettings['cache_playlists']) {
				$rOutputPath = PLAYLIST_PATH . md5($rCacheName) . '.write';
				$rOutputFile = fopen($rOutputPath, 'w');
			}

			if ($rDeviceKey == 'starlivev5') {
				$rOutput = [];
				$rOutput['iptvstreams_list'] = [];
				$rOutput['iptvstreams_list']['@version'] = 1;
				$rOutput['iptvstreams_list']['group'] = [];
				$rOutput['iptvstreams_list']['group']['name'] = 'IPTV';
				$rOutput['iptvstreams_list']['group']['channel'] = [];

				foreach (array_chunk($rChannelIDs, 1000) as $rBlockIDs) {
					if (self::$rSettings['playlist_from_mysql'] || !self::$rCached) {
						$rOrder = 'FIELD(`t1`.`id`,' . implode(',', $rBlockIDs) . ')';
						self::$db->query('SELECT t1.id,t1.channel_id,t1.year,t1.movie_properties,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t2.type_output,t2.type_key,t1.target_container,t2.live FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type WHERE `t1`.`id` IN (' . implode(',', array_map('intval', $rBlockIDs)) . (') ORDER BY ' . $rOrder . ';'));
						$rRows = self::$db->get_rows();
					}
					else {
						$rRows = [];

						foreach ($rBlockIDs as $rID) {
							$rRows[] = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rID))['info'];
						}
					}

					foreach ($rRows as $rChannelInfo) {
						if ($rTypeKey && !in_array($rChannelInfo['type_output'], $rTypeKey)) {
							continue;
						}

						if (!$rChannelInfo['target_container']) {
							$rChannelInfo['target_container'] = 'mp4';
						}

						$rProperties = (!is_array($rChannelInfo['movie_properties']) ? json_decode($rChannelInfo['movie_properties'], true) : $rChannelInfo['movie_properties']);

						if ($rChannelInfo['type_key'] == 'series') {
							$rSeriesID = $rSeriesAllocation[$rChannelInfo['id']];
							$rChannelInfo['live'] = 0;
							$rChannelInfo['stream_display_name'] = $rSeriesInfo[$rSeriesID]['title'] . ' S' . sprintf('%02d', $rSeriesEpisodes[$rChannelInfo['id']][0]) . 'E' . sprintf('%02d', $rSeriesEpisodes[$rChannelInfo['id']][1]);
							$rChannelInfo['movie_properties'] = ['movie_image' => !empty($rProperties['movie_image']) ? $rProperties['movie_image'] : $rSeriesInfo['cover']];
							$rChannelInfo['type_output'] = 'series';
							$rChannelInfo['category_id'] = $rSeriesInfo[$rSeriesID]['category_id'];
						}
						else {
							$rChannelInfo['stream_display_name'] = self::formatTitle($rChannelInfo['stream_display_name'], $rChannelInfo['year']);
						}

						if (strlen($rUserInfo['access_token']) == 32) {
							$rURL = $rDomainName . ($rChannelInfo['type_output'] . '/' . $rUserInfo['access_token'] . '/');

							if ($rChannelInfo['live'] == 0) {
								$rURL .= $rChannelInfo['id'] . '.' . $rChannelInfo['target_container'];
							}
							else if (self::$rSettings['cloudflare'] && ($rOutputExt == 'ts')) {
								$rURL .= $rChannelInfo['id'];
							}
							else {
								$rURL .= $rChannelInfo['id'] . '.' . $rOutputExt;
							}
						}
						else if ($rEncryptPlaylist) {
							$rEncData = $rChannelInfo['type_output'] . '/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/';

							if ($rChannelInfo['live'] == 0) {
								$rEncData .= $rChannelInfo['id'] . '/' . $rChannelInfo['target_container'];
							}
							else if (self::$rSettings['cloudflare'] && ($rOutputExt == 'ts')) {
								$rEncData .= $rChannelInfo['id'];
							}
							else {
								$rEncData .= $rChannelInfo['id'] . '/' . $rOutputExt;
							}

							$rToken = Xcms\Functions::encrypt($rEncData, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
							$rURL = $rDomainName . ('play/' . $rToken);

							if ($rChannelInfo['live'] == 0) {
								$rURL .= '#.' . $rChannelInfo['target_container'];
							}
						}
						else {
							$rURL = $rDomainName . ($rChannelInfo['type_output'] . '/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/');

							if ($rChannelInfo['live'] == 0) {
								$rURL .= $rChannelInfo['id'] . '.' . $rChannelInfo['target_container'];
							}
							else if (self::$rSettings['cloudflare'] && ($rOutputExt == 'ts')) {
								$rURL .= $rChannelInfo['id'];
							}
							else {
								$rURL .= $rChannelInfo['id'] . '.' . $rOutputExt;
							}
						}

						if ($rChannelInfo['live'] == 0) {
							if (!empty($rProperties['movie_image'])) {
								$rIcon = $rProperties['movie_image'];
							}
						}
						else {
							$rIcon = $rChannelInfo['stream_icon'];
						}

						$rChannel = [];
						$rChannel['name'] = $rChannelInfo['stream_display_name'];
						$rChannel['icon'] = self::validateImage($rIcon);
						$rChannel['stream_url'] = $rURL;
						$rChannel['stream_type'] = 0;
						$rOutput['iptvstreams_list']['group']['channel'][] = $rChannel;
					}

					unset($rRows);
				}

				$rData = json_encode((object) $rOutput);
			}
			else {
				if (!empty($rDeviceInfo['device_header'])) {
					$rAppend = ($rDeviceInfo['device_header'] == '#EXTM3U' ? "\n" . '#EXT-X-SESSION-DATA:DATA-ID="com.xcms.' . str_replace('.', '_', XCMS_VERSION) . (XCMS_REVISION ? 'r' . XCMS_REVISION : '') . '"' : '');
					$rData = str_replace(['&lt;', '&gt;'], ['<', '>'], str_replace(['{BOUQUET_NAME}', '{USERNAME}', '{PASSWORD}', '{SERVER_URL}', '{OUTPUT_KEY}'], [self::$rSettings['server_name'], $rUserInfo['username'], $rUserInfo['password'], $rDomainName, $rOutputKey], $rDeviceInfo['device_header'] . $rAppend)) . "\n";

					if ($rOutputFile) {
						fwrite($rOutputFile, $rData);
					}

					echo $rData;
					unset($rData);
				}

				if (!empty($rDeviceInfo['device_conf'])) {
					if (preg_match('/\\{URL\\#(.*?)\\}/', $rDeviceInfo['device_conf'], $rMatches)) {
						$rCharts = str_split($rMatches[1]);
						$rPattern = $rMatches[0];
					}
					else {
						$rCharts = [];
						$rPattern = '{URL}';
					}

					foreach (array_chunk($rChannelIDs, 1000) as $rBlockIDs) {
						if (self::$rSettings['playlist_from_mysql'] || !self::$rCached) {
							$rOrder = 'FIELD(`t1`.`id`,' . implode(',', $rBlockIDs) . ')';
							self::$db->query('SELECT t1.id,t1.channel_id,t1.year,t1.movie_properties,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t2.type_output,t2.type_key,t1.target_container,t2.live,t1.tv_archive_duration,t1.tv_archive_server_id FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type WHERE `t1`.`id` IN (' . implode(',', array_map('intval', $rBlockIDs)) . (') ORDER BY ' . $rOrder . ';'));
							$rRows = self::$db->get_rows();
						}
						else {
							$rRows = [];

							foreach ($rBlockIDs as $rID) {
								$rRows[] = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rID))['info'];
							}
						}

						foreach ($rRows as $rChannel) {
							if (!$rTypeKey || in_array($rChannel['type_output'], $rTypeKey)) {
								if (!$rChannel['target_container']) {
									$rChannel['target_container'] = 'mp4';
								}

								$rConfig = $rDeviceInfo['device_conf'];

								if ($rDeviceInfo['device_key'] == 'm3u_plus') {
									if (!$rChannel['live']) {
										$rConfig = str_replace('tvg-id="{CHANNEL_ID}" ', '', $rConfig);
									}

									if (!$rEncryptPlaylist) {
										$rConfig = str_replace('xcms-id="{XCMS_ID}" ', '', $rConfig);
									}
									if ((0 < $rChannel['tv_archive_server_id']) && (0 < $rChannel['tv_archive_duration'])) {
										$rConfig = str_replace('#EXTINF:-1 ', '#EXTINF:-1 timeshift="' . (int) $rChannel['tv_archive_duration'] . '" ', $rConfig);
									}
								}

								$rProperties = (!is_array($rChannel['movie_properties']) ? json_decode($rChannel['movie_properties'], true) : $rChannel['movie_properties']);

								if ($rChannel['type_key'] == 'series') {
									$rSeriesID = $rSeriesAllocation[$rChannel['id']];
									$rChannel['live'] = 0;
									$rChannel['stream_display_name'] = $rSeriesInfo[$rSeriesID]['title'] . ' S' . sprintf('%02d', $rSeriesEpisodes[$rChannel['id']][0]) . 'E' . sprintf('%02d', $rSeriesEpisodes[$rChannel['id']][1]);
									$rChannel['movie_properties'] = ['movie_image' => !empty($rProperties['movie_image']) ? $rProperties['movie_image'] : $rSeriesInfo['cover']];
									$rChannel['type_output'] = 'series';
									$rChannel['category_id'] = $rSeriesInfo[$rSeriesID]['category_id'];
								}
								else {
									$rChannel['stream_display_name'] = self::formatTitle($rChannel['stream_display_name'], $rChannel['year']);
								}

								if ($rChannel['live'] == 0) {
									if (strlen($rUserInfo['access_token']) == 32) {
										$rURL = $rDomainName . ($rChannel['type_output'] . '/' . $rUserInfo['access_token'] . '/' . $rChannel['id'] . '.' . $rChannel['target_container']);
									}
									else if ($rEncryptPlaylist) {
										$rEncData = $rChannel['type_output'] . '/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'] . '/' . $rChannel['target_container'];
										$rToken = Xcms\Functions::encrypt($rEncData, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
										$rURL = $rDomainName . ('play/' . $rToken . '#.') . $rChannel['target_container'];
									}
									else {
										$rURL = $rDomainName . ($rChannel['type_output'] . '/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'] . '.' . $rChannel['target_container']);
									}

									if (!empty($rProperties['movie_image'])) {
										$rIcon = $rProperties['movie_image'];
									}
								}
								else {
									if (($rOutputKey != 'rtmp') || !array_key_exists($rChannel['id'], $rRTMPRows)) {
										if (strlen($rUserInfo['access_token']) == 32) {
											if (self::$rSettings['cloudflare'] && ($rOutputExt == 'ts')) {
												$rURL = $rDomainName . ($rChannel['type_output'] . '/' . $rUserInfo['access_token'] . '/' . $rChannel['id']);
											}
											else {
												$rURL = $rDomainName . ($rChannel['type_output'] . '/' . $rUserInfo['access_token'] . '/' . $rChannel['id'] . '.' . $rOutputExt);
											}
										}
										else if ($rEncryptPlaylist) {
											$rEncData = $rChannel['type_output'] . '/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'];
											$rToken = Xcms\Functions::encrypt($rEncData, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
											if (self::$rSettings['cloudflare'] && ($rOutputExt == 'ts')) {
												$rURL = $rDomainName . ('play/' . $rToken);
											}
											else {
												$rURL = $rDomainName . ('play/' . $rToken . '/' . $rOutputExt);
											}
										}
										else if (self::$rSettings['cloudflare'] && ($rOutputExt == 'ts')) {
											$rURL = $rDomainName . ($rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id']);
										}
										else {
											$rURL = $rDomainName . ($rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'] . '.' . $rOutputExt);
										}
									}
									else {
										$rAvailableServers = array_values(array_keys($rRTMPRows[$rChannel['id']]));

										if (in_array($rUserInfo['force_server_id'], $rAvailableServers)) {
											$rServerID = $rUserInfo['force_server_id'];
										}
										else if (self::$rSettings['rtmp_random'] == 1) {
											$rServerID = $rAvailableServers[array_rand($rAvailableServers, 1)];
										}
										else {
											$rServerID = $rAvailableServers[0];
										}

										if (strlen($rUserInfo['access_token']) == 32) {
											$rURL = self::$rServers[$rServerID]['rtmp_server'] . ($rChannel['id'] . '?token=' . $rUserInfo['access_token']);
										}
										else if ($rEncryptPlaylist) {
											$rEncData = $rUserInfo['username'] . '/' . $rUserInfo['password'];
											$rToken = Xcms\Functions::encrypt($rEncData, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
											$rURL = self::$rServers[$rServerID]['rtmp_server'] . ($rChannel['id'] . '?token=' . $rToken);
										}
										else {
											$rURL = self::$rServers[$rServerID]['rtmp_server'] . ($rChannel['id'] . '?username=' . $rUserInfo['username'] . '&password=' . $rUserInfo['password']);
										}
									}

									$rIcon = $rChannel['stream_icon'];
								}

								$rESRID = ($rChannel['live'] == 1 ? 1 : 4097);
								$rSID = (!empty($rChannel['custom_sid']) ? $rChannel['custom_sid'] : ':0:1:0:0:0:0:0:0:0:');
								$rCategoryIDs = json_decode($rChannel['category_id'], true);

								foreach ($rCategoryIDs as $rCategoryID) {
									if (isset(self::$rCategories[$rCategoryID])) {
										$rData = str_replace(['&lt;', '&gt;'], ['<', '>'], str_replace([$rPattern, '{ESR_ID}', '{SID}', '{CHANNEL_NAME}', '{CHANNEL_ID}', '{XCMS_ID}', '{CATEGORY}', '{CHANNEL_ICON}'], [str_replace($rCharts, array_map('urlencode', $rCharts), $rURL), $rESRID, $rSID, $rChannel['stream_display_name'], $rChannel['channel_id'], $rChannel['id'], self::$rCategories[$rCategoryID]['category_name'], self::validateImage($rIcon)], $rConfig)) . "\r\n";

										if ($rOutputFile) {
											fwrite($rOutputFile, $rData);
										}

										echo $rData;
										unset($rData);

										if (stripos($rDeviceInfo['device_conf'], '{CATEGORY}') === false) {
											break;
										}
									}
								}
							}
						}

						unset($rRows);
					}

					$rData = trim(str_replace(['&lt;', '&gt;'], ['<', '>'], $rDeviceInfo['device_footer']));

					if ($rOutputFile) {
						fwrite($rOutputFile, $rData);
					}

					echo $rData;
					unset($rData);
				}
			}

			if ($rOutputFile) {
				fclose($rOutputFile);
				rename(PLAYLIST_PATH . md5($rCacheName) . '.write', PLAYLIST_PATH . md5($rCacheName));
			}

			exit();
		}

		return false;
	}

	static public function generateCron()
	{
		if (file_exists(TMP_PATH . 'crontab')) {
			return false;
		}

		$rJobs = [];
		self::$db->query('SELECT * FROM `crontab` WHERE `enabled` = 1;');

		foreach (self::$db->get_rows() as $rRow) {
			$rFullPath = CRON_PATH . $rRow['filename'];
			if ((pathinfo($rFullPath, PATHINFO_EXTENSION) == 'php') && file_exists($rFullPath)) {
				$rJobs[] = $rRow['time'] . ' ' . PHP_BIN . ' ' . $rFullPath . ' # XCMS';
			}
		}

		shell_exec('crontab -r');
		$rTempName = tempnam('/tmp', 'crontab');
		$rHandle = fopen($rTempName, 'w');
		fwrite($rHandle, implode("\n", $rJobs) . "\n");
		fclose($rHandle);
		shell_exec('crontab -u xcms ' . $rTempName);
		@unlink($rTempName);
		file_put_contents(TMP_PATH . 'crontab', 1);
		return true;
	}

	static public function getUptime()
	{
		if (file_exists('/proc/uptime') && is_readable('/proc/uptime')) {
			$tmp = explode(' ', file_get_contents('/proc/uptime'));
			return self::secondsToTime((int) $tmp[0]);
		}

		return '';
	}

	static public function secondsToTime($rInputSeconds, $rInclSecs = true)
	{
		$rSecondsInAMinute = 60;
		$rSecondsInAnHour = $rSecondsInAMinute * 60;
		$rSecondsInADay = $rSecondsInAnHour * 24;
		$rDays = (int) floor($rInputSeconds / ($rSecondsInADay ?: 1));
		$rHourSeconds = $rInputSeconds % $rSecondsInADay;
		$rHours = (int) floor($rHourSeconds / ($rSecondsInAnHour ?: 1));
		$rMinuteSeconds = $rHourSeconds % $rSecondsInAnHour;
		$rMinutes = (int) floor($rMinuteSeconds / ($rSecondsInAMinute ?: 1));
		$rRemaining = $rMinuteSeconds % $rSecondsInAMinute;
		$rSeconds = (int) ceil($rRemaining);
		$rOutput = '';

		if ($rDays != 0) {
			$rOutput .= $rDays . 'd ';
		}

		if ($rHours != 0) {
			$rOutput .= $rHours . 'h ';
		}

		if ($rMinutes != 0) {
			$rOutput .= $rMinutes . 'm ';
		}

		if ($rInclSecs) {
			$rOutput .= $rSeconds . 's';
		}

		return $rOutput;
	}

	static public function isPIDsRunning($rServerIDS, $rPIDs, $rEXE)
	{
		if (!is_array($rServerIDS)) {
			$rServerIDS = [(int) $rServerIDS];
		}

		$rPIDs = array_map('intval', $rPIDs);
		$rOutput = [];

		foreach ($rServerIDS as $rServerID) {
			if (!array_key_exists($rServerID, self::$rServers)) {
				continue;
			}

			$rResponse = self::serverRequest($rServerID, self::$rServers[$rServerID]['api_url_ip'] . '&action=pidsAreRunning', ['program' => $rEXE, 'pids' => $rPIDs]);

			if ($rResponse) {
				$rOutput[$rServerID] = array_map('trim', json_decode($rResponse, true));
			}
			else {
				$rOutput[$rServerID] = false;
			}
		}

		return $rOutput;
	}

	static public function isPIDRunning($rServerID, $rPID, $rEXE)
	{
		if (is_null($rPID) || !is_numeric($rPID) || !array_key_exists($rServerID, self::$rServers)) {
			return false;
		}

		if ($rOutput = self::isPIDsRunning($rServerID, [$rPID], $rEXE)) {
			return $rOutput[$rServerID][$rPID];
		}

		return false;
	}

	static public function serverRequest($rServerID, $rURL, $rPostData = [])
	{
		if (!self::$rServers[$rServerID]['server_online']) {
			return false;
		}

		$rOutput = false;

		for ($i = 1; $i <= 2; $i++) {
			$rCurl = curl_init();
			curl_setopt($rCurl, CURLOPT_URL, $rURL);
			curl_setopt($rCurl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0) Gecko/20100101 Firefox/9.0');
			curl_setopt($rCurl, CURLOPT_HEADER, 0);
			curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($rCurl, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($rCurl, CURLOPT_TIMEOUT, 10);
			curl_setopt($rCurl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($rCurl, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($rCurl, CURLOPT_FORBID_REUSE, true);
			curl_setopt($rCurl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($rCurl, CURLOPT_SSL_VERIFYPEER, 0);

			if (!empty($rPostData)) {
				curl_setopt($rCurl, CURLOPT_POST, true);
				curl_setopt($rCurl, CURLOPT_POSTFIELDS, http_build_query($rPostData));
			}

			$rOutput = curl_exec($rCurl);
			$rResponseCode = curl_getinfo($rCurl, CURLINFO_HTTP_CODE);
			$rError = curl_errno($rCurl);
			@curl_close($rCurl);
			if (($rError != 0) || ($rResponseCode != 200)) {
				continue;
			}

			break;
		}

		return $rOutput;
	}

	static public function deleteCache($rSources)
	{
		if (empty($rSources)) {
			return NULL;
		}

		foreach ($rSources as $rSource) {
			if (file_exists(CACHE_TMP_PATH . md5($rSource))) {
				unlink(CACHE_TMP_PATH . md5($rSource));
			}
		}
	}

	static public function queueChannel($rStreamID, $rServerID = NULL)
	{
		if (!$rServerID) {
			$rServerID = SERVER_ID;
		}

		self::$db->query('SELECT `id` FROM `queue` WHERE `stream_id` = ? AND `server_id` = ?;', $rStreamID, $rServerID);

		if (self::$db->num_rows() == 0) {
			self::$db->query('INSERT INTO `queue`(`type`, `stream_id`, `server_id`, `added`) VALUES(\'channel\', ?, ?, ?);', $rStreamID, $rServerID, time());
		}
	}

	static public function createChannel($rStreamID)
	{
		shell_exec(PHP_BIN . ' ' . CLI_PATH . 'created.php ' . (int) $rStreamID . ' >/dev/null 2>/dev/null &');
		return true;
	}

	static public function createChannelItem($rStreamID, $rSource)
	{
		$rStream = [];
		self::$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type AND t1.type = 3 LEFT JOIN `profiles` t4 ON t1.transcode_profile_id = t4.profile_id WHERE t1.direct_source = 0 AND t1.id = ?', $rStreamID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['stream_info'] = self::$db->get_row();
		self::$db->query('SELECT * FROM `streams_servers` WHERE stream_id  = ? AND `server_id` = ?', $rStreamID, SERVER_ID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['server_info'] = self::$db->get_row();
		$rMD5 = md5($rSource);

		if (substr($rSource, 0, 2) == 's:') {
			$rSplit = explode(':', $rSource, 3);
			$rServerID = (int) $rSplit[1];

			if ($rServerID != SERVER_ID) {
				$rSourcePath = self::$rServers[$rServerID]['api_url'] . '&action=getFile&filename=' . urlencode($rSplit[2]);
			}
			else {
				$rSourcePath = $rSplit[2];
			}
		}
		else {
			$rServerID = SERVER_ID;
			$rSourcePath = $rSource;
		}
		if (($rServerID == SERVER_ID) && ($rStream['stream_info']['movie_symlink'] == 1)) {
			$rExtension = pathinfo($rSource)['extension'];

			if (strlen($rExtension) == 0) {
				$rExtension = 'mp4';
			}

			$rCommand = 'ln -sfn ' . escapeshellarg($rSourcePath) . ' "' . CREATED_PATH . (int) $rStreamID . '_' . $rMD5 . '.' . escapeshellcmd($rExtension) . '" >/dev/null 2>/dev/null & echo $! > "' . CREATED_PATH . (int) $rStreamID . '_' . $rMD5 . '.pid"';
		}
		else {
			$rStream['stream_info']['transcode_attributes'] = json_decode($rStream['stream_info']['profile_options'], true);
			$rLogoOptions = (isset($rStream['stream_info']['transcode_attributes'][16]) && !$rLoopback ? $rStream['stream_info']['transcode_attributes'][16]['cmd'] : '');
			$rGPUOptions = (isset($rStream['stream_info']['transcode_attributes']['gpu']) ? $rStream['stream_info']['transcode_attributes']['gpu']['cmd'] : '');
			$rInputCodec = '';

			if (!empty($rGPUOptions)) {
				$rFFProbeOutput = self::probeStream($rSourcePath);

				if (in_array($rFFProbeOutput['codecs']['video']['codec_name'], ['h264' => true, 'hevc' => true, 'mjpeg' => true, 'mpeg1' => true, 'mpeg2' => true, 'mpeg4' => true, 'vc1' => true, 'vp8' => true, 'vp9' => true])) {
					$rInputCodec = '-c:v ' . $rFFProbeOutput['codecs']['video']['codec_name'] . '_cuvid';
				}
			}

			$rCommand = (isset($rStream['stream_info']['transcode_attributes']['gpu']) ? self::$rFFMPEG_GPU : self::$rFFMPEG_CPU) . ' -y -nostdin -hide_banner -loglevel ' . (self::$rSettings['ffmpeg_warnings'] ? 'warning' : 'error') . ' -err_detect ignore_err {GPU} -fflags +genpts -async 1 -i {STREAM_SOURCE} {LOGO} ';

			if (!array_key_exists('-acodec', $rStream['stream_info']['transcode_attributes'])) {
				$rStream['stream_info']['transcode_attributes']['-acodec'] = 'copy';
			}

			if (!array_key_exists('-vcodec', $rStream['stream_info']['transcode_attributes'])) {
				$rStream['stream_info']['transcode_attributes']['-vcodec'] = 'copy';
			}

			if (isset($rStream['stream_info']['transcode_attributes']['gpu'])) {
				$rCommand .= '-gpu ' . (int) $rStream['stream_info']['transcode_attributes']['gpu']['device'] . ' ';
			}

			$rCommand .= implode(' ', self::parseTranscode($rStream['stream_info']['transcode_attributes'])) . ' ';
			$rCommand .= '-strict -2 -mpegts_flags +initial_discontinuity -f mpegts "' . CREATED_PATH . (int) $rStreamID . '_' . $rMD5 . '.ts"';
			$rCommand .= ' >/dev/null 2>"' . CREATED_PATH . (int) $rStreamID . '_' . $rMD5 . '.errors" & echo $! > "' . CREATED_PATH . (int) $rStreamID . '_' . $rMD5 . '.pid"';
			$rCommand = str_replace(['{GPU}', '{INPUT_CODEC}', '{LOGO}', '{STREAM_SOURCE}'], [$rGPUOptions, $rInputCodec, $rLogoOptions, escapeshellarg($rSourcePath)], $rCommand);
		}

		shell_exec($rCommand);
		return (int) file_get_contents(CREATED_PATH . (int) $rStreamID . ('_' . $rMD5 . '.pid'));
	}

	static public function extractSubtitle($rStreamID, $rSourceURL, $rIndex)
	{
		$rTimeout = 10;
		$rCommand = 'timeout ' . $rTimeout . ' ' . self::$rFFMPEG_CPU . ' -y -nostdin -hide_banner -loglevel ' . (self::$rSettings['ffmpeg_warnings'] ? 'warning' : 'error') . (' -err_detect ignore_err -i "' . $rSourceURL . '" -map 0:s:') . (int) $rIndex . ' ' . VOD_PATH . (int) $rStreamID . '_' . (int) $rIndex . '.srt';
		exec($rCommand, $rOutput);

		if (file_exists(VOD_PATH . (int) $rStreamID . '_' . (int) $rIndex . '.srt')) {
			if (filesize(VOD_PATH . (int) $rStreamID . '_' . (int) $rIndex . '.srt') == 0) {
				unlink(VOD_PATH . (int) $rStreamID . '_' . (int) $rIndex . '.srt');
				return false;
			}

			return true;
		}
		else {
			return false;
		}
	}

	static public function probeStream($rSourceURL, $rFetchArguments = [], $rPrepend = '', $rParse = true)
	{
		$rAnalyseDuration = abs((int) self::$rSettings['stream_max_analyze']);
		$rProbesize = abs((int) self::$rSettings['probesize']);
		$rTimeout = (int) ($rAnalyseDuration / 1000000) + self::$rSettings['probe_extra_wait'];
		$rCommand = $rPrepend . 'timeout ' . $rTimeout . ' ' . self::$rFFPROBE . (' -probesize ' . $rProbesize . ' -analyzeduration ' . $rAnalyseDuration . ' ') . implode(' ', $rFetchArguments) . (' -i "' . $rSourceURL . '" -v quiet -print_format json -show_streams -show_format');
		exec($rCommand, $rReturn);
		$result = implode("\n", $rReturn);

		if ($rParse) {
			return self::parseFFProbe(json_decode($result, true));
		}
		else {
			return json_decode($result, true);
		}
	}

	static public function parseFFProbe($rCodecs)
	{
		if (!empty($rCodecs)) {
			if (!empty($rCodecs['codecs'])) {
				return $rCodecs;
			}

			$rOutput = [];
			$rOutput['codecs']['video'] = '';
			$rOutput['codecs']['audio'] = '';
			$rOutput['container'] = $rCodecs['format']['format_name'];
			$rOutput['filename'] = $rCodecs['format']['filename'];
			$rOutput['bitrate'] = (!empty($rCodecs['format']['bit_rate']) ? $rCodecs['format']['bit_rate'] : NULL);
			$rOutput['of_duration'] = (!empty($rCodecs['format']['duration']) ? $rCodecs['format']['duration'] : 'N/A');
			$rOutput['duration'] = (!empty($rCodecs['format']['duration']) ? gmdate('H:i:s', (int) $rCodecs['format']['duration']) : 'N/A');

			foreach ($rCodecs['streams'] as $rCodec) {
				if (!isset($rCodec['codec_type'])) {
					continue;
				}
				if (($rCodec['codec_type'] != 'audio') && ($rCodec['codec_type'] != 'video') && ($rCodec['codec_type'] != 'subtitle')) {
					continue;
				}
				if (($rCodec['codec_type'] == 'audio') || ($rCodec['codec_type'] == 'video')) {
					if (empty($rOutput['codecs'][$rCodec['codec_type']])) {
						$rOutput['codecs'][$rCodec['codec_type']] = $rCodec;
					}
				}
				else if ($rCodec['codec_type'] == 'subtitle') {
					if (!isset($rOutput['codecs'][$rCodec['codec_type']])) {
						$rOutput['codecs'][$rCodec['codec_type']] = [];
					}

					$rOutput['codecs'][$rCodec['codec_type']][] = $rCodec;
				}
			}

			return $rOutput;
		}

		return false;
	}

	static public function stopStream($rStreamID, $rStop = false)
	{
		if (file_exists(STREAMS_PATH . $rStreamID . '_.monitor')) {
			$rMonitor = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
		}
		else {
			self::$db->query('SELECT `monitor_pid` FROM `streams_servers` WHERE `server_id` = ? AND `stream_id` = ? LIMIT 1;', SERVER_ID, $rStreamID);
			$rMonitor = (int) self::$db->get_row()['monitor_pid'];
		}

		if (0 < $rMonitor) {
			if (self::checkPID($rMonitor, ['XCMS[' . $rStreamID . ']', 'XCMSProxy[' . $rStreamID . ']']) && is_numeric($rMonitor) && (0 < $rMonitor)) {
				posix_kill($rMonitor, 9);
			}
		}

		if (file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
			$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
		}
		else {
			self::$db->query('SELECT `pid` FROM `streams_servers` WHERE `server_id` = ? AND `stream_id` = ? LIMIT 1;', SERVER_ID, $rStreamID);
			$rPID = (int) self::$db->get_row()['pid'];
		}

		if (0 < $rPID) {
			if (self::checkPID($rPID, [$rStreamID . '_.m3u8', $rStreamID . '_%d.ts', 'LLOD[' . $rStreamID . ']', 'XCMSProxy[' . $rStreamID . ']', 'Loopback[' . $rStreamID . ']']) && is_numeric($rPID) && (0 < $rPID)) {
				posix_kill($rPID, 9);
			}
		}

		if (file_exists(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID)) {
			unlink(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID);
		}

		self::streamLog($rStreamID, SERVER_ID, 'STREAM_STOP');
		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*');

		if ($rStop) {
			shell_exec('rm -f ' . DELAY_PATH . (int) $rStreamID . '_*');
			self::$db->query('UPDATE `streams_servers` SET `bitrate` = NULL,`current_source` = NULL,`to_analyze` = 0,`pid` = NULL,`stream_started` = NULL,`stream_info` = NULL,`audio_codec` = NULL,`video_codec` = NULL,`resolution` = NULL,`compatible` = 0,`stream_status` = 0,`monitor_pid` = NULL WHERE `stream_id` = ? AND `server_id` = ?', $rStreamID, SERVER_ID);
			self::updateStream($rStreamID);
		}
	}

	static public function checkPID($rPID, $rSearch)
	{
		if (!is_array($rSearch)) {
			$rSearch = [$rSearch];
		}

		if (file_exists('/proc/' . $rPID)) {
			$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));

			foreach ($rSearch as $rTerm) {
				if (stristr($rCommand, $rTerm)) {
					return true;
				}
			}
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

	static public function startThumbnail($rStreamID)
	{
		shell_exec(PHP_BIN . ' ' . CLI_PATH . 'thumbnail.php ' . (int) $rStreamID . ' >/dev/null 2>/dev/null &');
		return true;
	}

	static public function stopMovie($rStreamID, $rForce = false)
	{
		shell_exec('kill -9 `ps -ef | grep \'/' . (int) $rStreamID . '.\' | grep -v grep | awk \'{print $2}\'`;');

		if ($rForce) {
			exec('rm ' . XCMS_HOME . 'content/vod/' . (int) $rStreamID . '.*');
		}
		else {
			self::$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`, `cache`) VALUES(?, ?, ?, 1);', SERVER_ID, time(), json_encode(['type' => 'delete_vod', 'id' => $rStreamID]));
		}

		self::$db->query('UPDATE `streams_servers` SET `bitrate` = NULL,`current_source` = NULL,`to_analyze` = 0,`pid` = NULL,`stream_started` = NULL,`stream_info` = NULL,`audio_codec` = NULL,`video_codec` = NULL,`resolution` = NULL,`compatible` = 0,`stream_status` = 0 WHERE `stream_id` = ? AND `server_id` = ?', $rStreamID, SERVER_ID);
		self::updateStream($rStreamID);
	}

	static public function queueMovie($rStreamID, $rServerID = NULL)
	{
		if (!$rServerID) {
			$rServerID = SERVER_ID;
		}

		self::$db->query('DELETE FROM `queue` WHERE `stream_id` = ? AND `server_id` = ?;', $rStreamID, $rServerID);
		self::$db->query('INSERT INTO `queue`(`type`, `stream_id`, `server_id`, `added`) VALUES(\'movie\', ?, ?, ?);', $rStreamID, $rServerID, time());
	}

	static public function queueMovies($rStreamIDs, $rServerID = NULL)
	{
		if (!$rServerID) {
			$rServerID = SERVER_ID;
		}

		if (0 < count($rStreamIDs)) {
			self::$db->query('DELETE FROM `queue` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ') AND `server_id` = ?;', $rServerID);
			$rQuery = '';

			foreach ($rStreamIDs as $rStreamID) {
				if (0 < $rStreamID) {
					$rQuery .= '(\'movie\', ' . (int) $rStreamID . ', ' . (int) $rServerID . ', ' . time() . '),';
				}
			}

			if (!empty($rQuery)) {
				$rQuery = rtrim($rQuery, ',');
				self::$db->query('INSERT INTO `queue`(`type`, `stream_id`, `server_id`, `added`) VALUES ' . $rQuery . ';');
			}
		}
	}

	static public function refreshMovies($rIDs, $rType = 1)
	{
		if (0 < count($rIDs)) {
			self::$db->query('DELETE FROM `watch_refresh` WHERE `type` = ? AND `stream_id` IN (' . implode(',', array_map('intval', $rIDs)) . ');', $rType);
			$rQuery = '';

			foreach ($rIDs as $rID) {
				if (0 < $rID) {
					$rQuery .= '(' . (int) $rType . ', ' . (int) $rID . ', 0),';
				}
			}

			if (!empty($rQuery)) {
				$rQuery = rtrim($rQuery, ',');
				self::$db->query('INSERT INTO `watch_refresh`(`type`, `stream_id`, `status`) VALUES ' . $rQuery . ';');
			}
		}
	}

	static public function startMovie($rStreamID)
	{
		$rStream = [];
		self::$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type AND t2.live = 0 LEFT JOIN `profiles` t4 ON t1.transcode_profile_id = t4.profile_id WHERE t1.direct_source = 0 AND t1.id = ?', $rStreamID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['stream_info'] = self::$db->get_row();
		self::$db->query('SELECT * FROM `streams_servers` WHERE stream_id  = ? AND `server_id` = ?', $rStreamID, SERVER_ID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['server_info'] = self::$db->get_row();
		self::$db->query('SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 WHERE t1.stream_id = ? AND t1.argument_id = t2.id', $rStreamID);
		$rStream['stream_arguments'] = self::$db->get_rows();
		$rStreamSource = json_decode($rStream['stream_info']['stream_source'], true)[0];

		if (substr($rStreamSource, 0, 2) == 's:') {
			$rMovieSource = explode(':', $rStreamSource, 3);
			$rMovieServerID = $rMovieSource[1];

			if ($rMovieServerID != SERVER_ID) {
				$rMoviePath = self::$rServers[$rMovieServerID]['api_url'] . '&action=getFile&filename=' . urlencode($rMovieSource[2]);
			}
			else {
				$rMoviePath = $rMovieSource[2];
			}

			$rProtocol = NULL;
		}
		else if (substr($rStreamSource, 0, 1) == '/') {
			$rMovieServerID = SERVER_ID;
			$rMoviePath = $rStreamSource;
			$rProtocol = NULL;
		}
		else {
			$rProtocol = substr($rStreamSource, 0, strpos($rStreamSource, '://'));
			$rMoviePath = str_replace(' ', '%20', $rStreamSource);
			$rFetchOptions = implode(' ', self::getArguments($rStream['stream_arguments'], $rProtocol, 'fetch'));
		}
		if (((isset($rMovieServerID) && ($rMovieServerID == SERVER_ID)) || file_exists($rMoviePath)) && ($rStream['stream_info']['movie_symlink'] == 1)) {
			$rFFMPEG = 'ln -sfn ' . escapeshellarg($rMoviePath) . ' ' . VOD_PATH . (int) $rStreamID . '.' . escapeshellcmd(pathinfo($rMoviePath)['extension']) . ' >/dev/null 2>/dev/null & echo $! > ' . VOD_PATH . (int) $rStreamID . '_.pid';
		}
		else {
			$rSubtitles = json_decode($rStream['stream_info']['movie_subtitles'], true);
			$rSubtitlesImport = '';

			for ($i = 0; $i < count($rSubtitles['files']); $i++) {
				$rSubtitleFile = escapeshellarg($rSubtitles['files'][$i]);
				$rInputCharset = escapeshellarg($rSubtitles['charset'][$i]);

				if ($rSubtitles['location'] == SERVER_ID) {
					$rSubtitlesImport .= '-sub_charenc ' . $rInputCharset . ' -i ' . $rSubtitleFile . ' ';
					continue;
				}

				$rSubtitlesImport .= '-sub_charenc ' . $rInputCharset . ' -i "' . self::$rServers[$rSubtitles['location']]['api_url'] . '&action=getFile&filename=' . urlencode($rSubtitleFile) . '" ';
			}

			$rSubtitlesMetadata = '';

			for ($i = 0; $i < count($rSubtitles['files']); $i++) {
				$rSubtitlesMetadata .= '-map ' . ($i + 1) . (' -metadata:s:s:' . $i . ' title=') . escapeshellcmd($rSubtitles['names'][$i]) . (' -metadata:s:s:' . $i . ' language=') . escapeshellcmd($rSubtitles['names'][$i]) . ' ';
			}

			if ($rStream['stream_info']['read_native'] == 1) {
				$rReadNative = '-re';
			}
			else {
				$rReadNative = '';
			}

			if ($rStream['stream_info']['enable_transcode'] == 1) {
				if ($rStream['stream_info']['transcode_profile_id'] == -1) {
					$rStream['stream_info']['transcode_attributes'] = array_merge(self::getArguments($rStream['stream_arguments'], $rProtocol, 'transcode'), json_decode($rStream['stream_info']['transcode_attributes'], true));
				}
				else {
					$rStream['stream_info']['transcode_attributes'] = json_decode($rStream['stream_info']['profile_options'], true);
				}
			}
			else {
				$rStream['stream_info']['transcode_attributes'] = [];
			}
			$rLogoOptions = (isset($rStream['stream_info']['transcode_attributes'][16]) && !$rLoopback ? $rStream['stream_info']['transcode_attributes'][16]['cmd'] : '');
			$rGPUOptions = (isset($rStream['stream_info']['transcode_attributes']['gpu']) ? $rStream['stream_info']['transcode_attributes']['gpu']['cmd'] : '');
			$rInputCodec = '';

			if (!empty($rGPUOptions)) {
				$rFFProbeOutput = self::probeStream($rMoviePath);

				if (in_array($rFFProbeOutput['codecs']['video']['codec_name'], ['h264' => true, 'hevc' => true, 'mjpeg' => true, 'mpeg1' => true, 'mpeg2' => true, 'mpeg4' => true, 'vc1' => true, 'vp8' => true, 'vp9' => true])) {
					$rInputCodec = '-c:v ' . $rFFProbeOutput['codecs']['video']['codec_name'] . '_cuvid';
				}
			}

			$rFFMPEG = (isset($rStream['stream_info']['transcode_attributes']['gpu']) ? self::$rFFMPEG_GPU : self::$rFFMPEG_CPU) . ' -y -nostdin -hide_banner -loglevel ' . (self::$rSettings['ffmpeg_warnings'] ? 'warning' : 'error') . (' -err_detect ignore_err {GPU} {FETCH_OPTIONS} -fflags +genpts -async 1 {READ_NATIVE} -i {STREAM_SOURCE} {LOGO} ' . $rSubtitlesImport);
			$rMap = '-map 0 -copy_unknown ';

			if (!empty($rStream['stream_info']['custom_map'])) {
				$rMap = escapeshellcmd($rStream['stream_info']['custom_map']) . ' -copy_unknown ';
			}
			else if ($rStream['stream_info']['remove_subtitles'] == 1) {
				$rMap = '-map 0:a -map 0:v';
			}

			if (!array_key_exists('-acodec', $rStream['stream_info']['transcode_attributes'])) {
				$rStream['stream_info']['transcode_attributes']['-acodec'] = 'copy';
			}

			if (!array_key_exists('-vcodec', $rStream['stream_info']['transcode_attributes'])) {
				$rStream['stream_info']['transcode_attributes']['-vcodec'] = 'copy';
			}

			if ($rStream['stream_info']['target_container'] == 'mp4') {
				$rStream['stream_info']['transcode_attributes']['-scodec'] = 'mov_text';
			}
			else if ($rStream['stream_info']['target_container'] == 'mkv') {
				$rStream['stream_info']['transcode_attributes']['-scodec'] = 'srt';
			}
			else {
				$rStream['stream_info']['transcode_attributes']['-scodec'] = 'copy';
			}

			$rOutputs = [];
			$rOutputs[$rStream['stream_info']['target_container']] = '-movflags +faststart -dn ' . $rMap . ' -ignore_unknown ' . $rSubtitlesMetadata . ' ' . VOD_PATH . (int) $rStreamID . '.' . escapeshellcmd($rStream['stream_info']['target_container']);

			foreach ($rOutputs as $rOutputKey => $rOutputCommand) {
				$rFFMPEG .= implode(' ', self::parseTranscode($rStream['stream_info']['transcode_attributes'])) . ' ';
				$rFFMPEG .= $rOutputCommand;
			}

			$rFFMPEG .= ' >/dev/null 2>' . VOD_PATH . (int) $rStreamID . '.errors & echo $! > ' . VOD_PATH . (int) $rStreamID . '_.pid';
			$rFFMPEG = str_replace(['{GPU}', '{INPUT_CODEC}', '{LOGO}', '{FETCH_OPTIONS}', '{STREAM_SOURCE}', '{READ_NATIVE}'], [$rGPUOptions, $rInputCodec, $rLogoOptions, empty($rFetchOptions) ? '' : $rFetchOptions, escapeshellarg($rMoviePath), empty($rStream['stream_info']['custom_ffmpeg']) ? $rReadNative : ''], $rFFMPEG);
		}

		shell_exec($rFFMPEG);
		file_put_contents(VOD_PATH . $rStreamID . '_.ffmpeg', $rFFMPEG);
		$rPID = (int) file_get_contents(VOD_PATH . $rStreamID . '_.pid');
		self::$db->query('UPDATE `streams_servers` SET `to_analyze` = 1,`stream_started` = ?,`stream_status` = 0,`pid` = ? WHERE `stream_id` = ? AND `server_id` = ?', time(), $rPID, $rStreamID, SERVER_ID);
		self::updateStream($rStreamID);
		return $rPID;
	}

	static public function fixCookie($rCookie)
	{
		$rPath = false;
		$rDomain = false;
		$rSplit = explode(';', $rCookie);

		foreach ($rSplit as $rPiece) {
			list($rKey, $rValue) = explode('=', $rPiece, 1);

			if (strtolower($rKey) == 'path') {
				$rPath = true;
			}
			else if (strtolower($rKey) == 'domain') {
				$rDomain = true;
			}
		}

		if (!substr($rCookie, -1) == ';') {
			$rCookie .= ';';
		}

		if (!$rPath) {
			$rCookie .= 'path=/;';
		}

		if (!$rDomain) {
			$rCookie .= 'domain=;';
		}

		return $rCookie;
	}

	static public function startCapture($rStreamID)
	{
	}

	static public function startLLOD($rStreamID, $rStreamInfo, $rStreamArguments, $rForceSource = NULL)
	{
		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*.ts');

		if (file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
			unlink(STREAMS_PATH . $rStreamID . '_.pid');
		}

		$rSources = ($rForceSource ? [$rForceSource] : json_decode($rStreamInfo['stream_source'], true));
		$rArgumentMap = [];

		foreach ($rStreamArguments as $rStreamArgument) {
			$rArgumentMap[$rStreamArgument['argument_key']] = ['value' => $rStreamArgument['value'], 'argument_default_value' => $rStreamArgument['argument_default_value']];
		}

		shell_exec(PHP_BIN . ' ' . CLI_PATH . 'llod.php ' . (int) $rStreamID . ' "' . base64_encode(json_encode($rSources)) . '" "' . base64_encode(json_encode($rArgumentMap)) . '" >/dev/null 2>/dev/null & echo $! > ' . STREAMS_PATH . (int) $rStreamID . '_.pid');
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
		$rKey = openssl_random_pseudo_bytes(16);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.key', $rKey);
		$rIVSize = openssl_cipher_iv_length('AES-128-CBC');
		$rIV = openssl_random_pseudo_bytes($rIVSize);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.iv', $rIV);
		self::$db->query('UPDATE `streams_servers` SET `delay_available_at` = ?,`to_analyze` = 0,`stream_started` = ?,`stream_info` = ?,`stream_status` = 2,`pid` = ?,`progress_info` = ?,`current_source` = ? WHERE `stream_id` = ? AND `server_id` = ?', NULL, time(), NULL, $rPID, json_encode([]), $rSources[0], $rStreamID, SERVER_ID);
		self::updateStream($rStreamID);
		return ['main_pid' => $rPID, 'stream_source' => $rSources[0], 'delay_enabled' => false, 'parent_id' => 0, 'delay_start_at' => NULL, 'playlist' => STREAMS_PATH . $rStreamID . '_.m3u8', 'transcode' => false, 'offset' => 0];
	}

	static public function startLoopback($rStreamID)
	{
		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*.ts');

		if (file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
			unlink(STREAMS_PATH . $rStreamID . '_.pid');
		}

		$rStream = [];
		self::$db->query('SELECT * FROM `streams` WHERE direct_source = 0 AND id = ?', $rStreamID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['stream_info'] = self::$db->get_row();
		self::$db->query('SELECT * FROM `streams_servers` WHERE stream_id  = ? AND `server_id` = ?', $rStreamID, SERVER_ID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['server_info'] = self::$db->get_row();

		if ($rStream['server_info']['parent_id'] == 0) {
			return 0;
		}

		shell_exec(PHP_BIN . ' ' . CLI_PATH . 'loopback.php ' . (int) $rStreamID . ' ' . (int) $rStream['server_info']['parent_id'] . ' >/dev/null 2>/dev/null & echo $! > ' . STREAMS_PATH . (int) $rStreamID . '_.pid');
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
		$rKey = openssl_random_pseudo_bytes(16);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.key', $rKey);
		$rIVSize = openssl_cipher_iv_length('AES-128-CBC');
		$rIV = openssl_random_pseudo_bytes($rIVSize);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.iv', $rIV);
		self::$db->query('UPDATE `streams_servers` SET `delay_available_at` = ?,`to_analyze` = 0,`stream_started` = ?,`stream_info` = ?,`stream_status` = 2,`pid` = ?,`progress_info` = ?,`current_source` = ? WHERE `stream_id` = ? AND `server_id` = ?', NULL, time(), NULL, $rPID, json_encode([]), $rSources[0], $rStreamID, SERVER_ID);
		self::updateStream($rStreamID);
		$rLoopURL = (!is_null(self::$rServers[SERVER_ID]['private_url_ip']) && !is_null(self::$rServers[$rStream['server_info']['parent_id']]['private_url_ip']) ? self::$rServers[$rStream['server_info']['parent_id']]['private_url_ip'] : self::$rServers[$rStream['server_info']['parent_id']]['public_url_ip']);
		return ['main_pid' => $rPID, 'stream_source' => $rLoopURL . 'admin/live?stream=' . (int) $rStreamID . '&password=' . urlencode(self::$rSettings['live_streaming_pass']) . '&extension=ts', 'delay_enabled' => false, 'parent_id' => 0, 'delay_start_at' => NULL, 'playlist' => STREAMS_PATH . $rStreamID . '_.m3u8', 'transcode' => false, 'offset' => 0];
	}

	static public function startStream($rStreamID, $rFromCache = false, $rForceSource = NULL, $rLLOD = false, $rStartPos = 0)
	{
		if (file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
			unlink(STREAMS_PATH . $rStreamID . '_.pid');
		}

		$rStream = [];
		self::$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type AND t2.live = 1 LEFT JOIN `profiles` t4 ON t1.transcode_profile_id = t4.profile_id WHERE t1.direct_source = 0 AND t1.id = ?', $rStreamID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['stream_info'] = self::$db->get_row();
		self::$db->query('SELECT * FROM `streams_servers` WHERE stream_id  = ? AND `server_id` = ?', $rStreamID, SERVER_ID);

		if (self::$db->num_rows() <= 0) {
			return false;
		}

		$rStream['server_info'] = self::$db->get_row();
		self::$db->query('SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 WHERE t1.stream_id = ? AND t1.argument_id = t2.id', $rStreamID);
		$rStream['stream_arguments'] = self::$db->get_rows();

		if ($rStream['server_info']['on_demand'] == 1) {
			$rProbesize = (int) $rStream['stream_info']['probesize_ondemand'];
			$rAnalyseDuration = '10000000';
		}
		else {
			$rAnalyseDuration = abs((int) self::$rSettings['stream_max_analyze']);
			$rProbesize = abs((int) self::$rSettings['probesize']);
		}

		$rTimeout = (int) ($rAnalyseDuration / 1000000) + self::$rSettings['probe_extra_wait'];
		$rFFProbe = 'timeout ' . $rTimeout . ' ' . self::$rFFPROBE . (' {FETCH_OPTIONS} -probesize ' . $rProbesize . ' -analyzeduration ' . $rAnalyseDuration . ' {CONCAT} -i {STREAM_SOURCE} -v quiet -print_format json -show_streams -show_format');
		$rFetchOptions = [];
		$rLoopback = false;
		$rOffset = 0;

		if (!$rStream['server_info']['parent_id']) {
			if ($rStream['stream_info']['type_key'] == 'created_live') {
				$rSources = [CREATED_PATH . $rStreamID . '_.list'];

				if (0 < $rStartPos) {
					$rCCOutput = [];
					$rCCDuration = [];
					$rCCInfo = json_decode($rStream['server_info']['cc_info'], true);

					foreach ($rCCInfo as $rItem) {
						$rCCDuration[$rItem['path']] = (int) explode('.', $rItem['seconds'])[0];
					}

					$rTimer = 0;
					$rValid = true;

					foreach (explode("\n", file_get_contents(CREATED_PATH . $rStreamID . '_.list')) as $rItem) {
						$rPath = explode('\'', explode('file \'', $rItem)[1])[0];

						if ($rPath) {
							if (!$rCCDuration[$rPath]) {
								$rValid = false;
								continue;
							}

							$rDuration = $rCCDuration[$rPath];
							if (($rTimer <= $rStartPos) && ($rStartPos < ($rTimer + $rDuration))) {
								$rOffset = $rTimer;
								$rCCOutput[] = $rPath;
							}
							else if ($rStartPos < ($rTimer + $rDuration)) {
								$rCCOutput[] = $rPath;
							}

							$rTimer += $rDuration;
						}
					}

					if ($rValid) {
						$rSources = [CREATED_PATH . $rStreamID . '_.tlist'];
						$rTList = '';

						foreach ($rCCOutput as $rItem) {
							$rTList .= 'file \'' . $rItem . '\'' . "\n";
						}

						file_put_contents(CREATED_PATH . $rStreamID . '_.tlist', $rTList);
					}
				}
			}
			else {
				$rSources = json_decode($rStream['stream_info']['stream_source'], true);
			}

			if (0 < count($rSources)) {
				if (!empty($rForceSource)) {
					$rSources = [$rForceSource];
				}
				else if (self::$rSettings['priority_backup'] != 1) {
					if (!empty($rStream['server_info']['current_source'])) {
						$k = array_search($rStream['server_info']['current_source'], $rSources);

						if ($k !== false) {
							for ($i = 0; $i <= $k; $i++) {
								$rTemp = $rSources[$i];
								unset($rSources[$i]);
								array_push($rSources, $rTemp);
							}

							$rSources = array_values($rSources);
						}
					}
				}
			}
		}
		else {
			$rLoopback = true;

			if ($rStream['server_info']['on_demand']) {
				$rLLOD = true;
			}
			$rLoopURL = (!is_null(self::$rServers[SERVER_ID]['private_url_ip']) && !is_null(self::$rServers[$rStream['server_info']['parent_id']]['private_url_ip']) ? self::$rServers[$rStream['server_info']['parent_id']]['private_url_ip'] : self::$rServers[$rStream['server_info']['parent_id']]['public_url_ip']);
			$rSources = [$rLoopURL . 'admin/live?stream=' . (int) $rStreamID . '&password=' . urlencode(self::$rSettings['live_streaming_pass']) . '&extension=ts'];
		}

		if ($rStream['server_info']['on_demand']) {
			self::$rSegmentSettings['seg_type'] = 1;
		}
		if (($rStream['stream_info']['type_key'] == 'created_live') && file_exists(CREATED_PATH . $rStreamID . '_.info')) {
			self::$db->query('UPDATE `streams_servers` SET `cc_info` = ? WHERE `server_id` = ? AND `stream_id` = ?;', file_get_contents(CREATED_PATH . $rStreamID . '_.info'), SERVER_ID, $rStreamID);
		}

		if (!$rFromCache) {
			self::deleteCache($rSources);
		}

		foreach ($rSources as $rSource) {
			$rProcessed = false;
			$rRealSource = $rSource;
			$rStreamSource = self::parseStreamURL($rSource);
			echo 'Checking source: ' . $rSource . "\n";
			$rURLInfo = parse_url($rStreamSource);
			$rIsXCMS = ($rLoopback ? true : self::detectXCMS($rStreamSource));
			if ($rIsXCMS && !$rLoopback && self::$rSettings['send_xcms_header']) {
				foreach (array_keys($rStream['stream_arguments']) as $rID) {
					if ($rStream['stream_arguments'][$rID]['argument_key'] == 'headers') {
						$rStream['stream_arguments'][$rID]['value'] .= "\r\n" . 'X-XCMS-Detect:1';
						$rProcessed = true;
					}
				}

				if (!$rProcessed) {
					$rStream['stream_arguments'][] = ['value' => 'X-XCMS-Detect:1', 'argument_key' => 'headers', 'argument_cat' => 'fetch', 'argument_wprotocol' => 'http', 'argument_type' => 'text', 'argument_cmd' => '-headers \'%s' . "\r\n" . '\''];
				}
			}

			$rProbeArguments = $rStream['stream_arguments'];
			if ($rIsXCMS && ($rStream['server_info']['on_demand'] == 1) && (self::$rSettings['request_prebuffer'] == 1)) {
				foreach (array_keys($rStream['stream_arguments']) as $rID) {
					if ($rStream['stream_arguments'][$rID]['argument_key'] == 'headers') {
						$rStream['stream_arguments'][$rID]['value'] .= "\r\n" . 'X-XCMS-Prebuffer:1';
						$rProcessed = true;
					}
				}

				if (!$rProcessed) {
					$rStream['stream_arguments'][] = ['value' => 'X-XCMS-Prebuffer:1', 'argument_key' => 'headers', 'argument_cat' => 'fetch', 'argument_wprotocol' => 'http', 'argument_type' => 'text', 'argument_cmd' => '-headers \'%s' . "\r\n" . '\''];
				}
			}

			foreach (array_keys($rProbeArguments) as $rID) {
				if ($rProbeArguments[$rID]['argument_key'] == 'headers') {
					$rProbeArguments[$rID]['value'] .= "\r\n" . 'X-XCMS-Prebuffer:1';
					$rProcessed = true;
				}
			}

			if (!$rProcessed) {
				$rProbeArguments[] = ['value' => 'X-XCMS-Prebuffer:1', 'argument_key' => 'headers', 'argument_cat' => 'fetch', 'argument_wprotocol' => 'http', 'argument_type' => 'text', 'argument_cmd' => '-headers \'%s' . "\r\n" . '\''];
			}

			$rProtocol = strtolower(substr($rStreamSource, 0, strpos($rStreamSource, '://')));
			$rProbeOptions = implode(' ', self::getArguments($rProbeArguments, $rProtocol, 'fetch'));
			$rFetchOptions = implode(' ', self::getArguments($rStream['stream_arguments'], $rProtocol, 'fetch'));
			if ($rFromCache && file_exists(CACHE_TMP_PATH . md5($rSource)) && ((time() - filemtime(CACHE_TMP_PATH . md5($rSource))) <= 300)) {
				$rFFProbeOutput = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . md5($rStreamSource)));
				if ($rFFProbeOutput && (isset($rFFProbeOutput['streams']) || isset($rFFProbeOutput['codecs']))) {
					echo 'Got stream information via cache' . "\n";
					break;
				}
			}
			else if ($rFromCache && file_exists(CACHE_TMP_PATH . md5($rSource))) {
				$rFromCache = false;
			}
			if (!$rStream['server_info']['on_demand'] || !$rLLOD) {
				if ($rIsXCMS && self::$rSettings['api_probe']) {
					$rProbeURL = $rURLInfo['scheme'] . '://' . $rURLInfo['host'] . ':' . $rURLInfo['port'] . '/probe/' . base64_encode($rURLInfo['path']);
					$rFFProbeOutput = json_decode(self::getURL($rProbeURL), true);
					if ($rFFProbeOutput && isset($rFFProbeOutput['codecs'])) {
						echo 'Got stream information via API' . "\n";
						break;
					}
				}
				$rFFProbeOutput = json_decode(shell_exec(str_replace(['{FETCH_OPTIONS}', '{CONCAT}', '{STREAM_SOURCE}'], [$rProbeOptions, ($rStream['stream_info']['type_key'] == 'created_live') && !$rStream['server_info']['parent_id'] ? '-safe 0 -f concat' : '', escapeshellarg($rStreamSource)], $rFFProbe)), true);
				if ($rFFProbeOutput && isset($rFFProbeOutput['streams'])) {
					echo 'Got stream information via ffprobe' . "\n";
					break;
				}
			}
		}
		if (!$rStream['server_info']['on_demand'] || !$rLLOD) {
			if (!isset($rFFProbeOutput['codecs'])) {
				$rFFProbeOutput = self::parseFFProbe($rFFProbeOutput);
			}

			if (empty($rFFProbeOutput)) {
				self::$db->query('UPDATE `streams_servers` SET `progress_info` = \'\',`to_analyze` = 0,`pid` = -1,`stream_status` = 1 WHERE `server_id` = ? AND `stream_id` = ?', SERVER_ID, $rStreamID);
				return 0;
			}
			else if (!$rFromCache) {
				file_put_contents(CACHE_TMP_PATH . md5($rSource), igbinary_serialize($rFFProbeOutput));
			}
		}

		$rExternalPush = json_decode($rStream['stream_info']['external_push'], true);
		$rProgressURL = 'http://127.0.0.1:' . (int) self::$rServers[SERVER_ID]['http_broadcast_port'] . '/progress?stream_id=' . (int) $rStreamID;

		if (empty($rStream['stream_info']['custom_ffmpeg'])) {
			if ($rLoopback) {
				$rOptions = '{FETCH_OPTIONS}';
			}
			else {
				$rOptions = '{GPU} {FETCH_OPTIONS}';
			}

			if ($rStream['stream_info']['stream_all'] == 1) {
				$rMap = '-map 0 -copy_unknown ';
			}
			else if (!empty($rStream['stream_info']['custom_map'])) {
				$rMap = escapeshellcmd($rStream['stream_info']['custom_map']) . ' -copy_unknown ';
			}
			else if ($rStream['stream_info']['type_key'] == 'radio_streams') {
				$rMap = '-map 0:a? ';
			}
			else {
				$rMap = '';
			}
			if ((($rStream['stream_info']['gen_timestamps'] == 1) || empty($rProtocol)) && ($rStream['stream_info']['type_key'] != 'created_live')) {
				$rGenPTS = '-fflags +genpts -async 1';
			}
			else {
				if (in_array($rFFProbeOutput['codecs']['audio']['codec_name'], ['ac3' => true, 'eac3' => true]) && self::$rSettings['dts_legacy_ffmpeg']) {
					self::$rFFMPEG_CPU = FFMPEG_BIN_40;
					self::$rFFPROBE = FFPROBE_BIN_40;
				}

				$rNoFix = (self::$rFFMPEG_CPU == FFMPEG_BIN_40 ? '-nofix_dts' : '');
				$rGenPTS = $rNoFix . ' -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0';
			}
			if (!$rStream['server_info']['parent_id'] && (($rStream['stream_info']['read_native'] == 1) || (stristr($rFFProbeOutput['container'], 'hls') && self::$rSettings['read_native_hls']) || empty($rProtocol) || stristr($rFFProbeOutput['container'], 'mp4') || stristr($rFFProbeOutput['container'], 'matroska'))) {
				$rReadNative = '-re';
			}
			else {
				$rReadNative = '';
			}
			if (!$rStream['server_info']['parent_id'] && ($rStream['stream_info']['enable_transcode'] == 1) && ($rStream['stream_info']['type_key'] != 'created_live')) {
				if ($rStream['stream_info']['transcode_profile_id'] == -1) {
					$rStream['stream_info']['transcode_attributes'] = array_merge(self::getArguments($rStream['stream_arguments'], $rProtocol, 'transcode'), json_decode($rStream['stream_info']['transcode_attributes'], true));
				}
				else {
					$rStream['stream_info']['transcode_attributes'] = json_decode($rStream['stream_info']['profile_options'], true);
				}
			}
			else {
				$rStream['stream_info']['transcode_attributes'] = [];
			}

			$rFFMPEG = (isset($rStream['stream_info']['transcode_attributes']['gpu']) ? self::$rFFMPEG_GPU : self::$rFFMPEG_CPU) . ' -y -nostdin -hide_banner -loglevel ' . (self::$rSettings['ffmpeg_warnings'] ? 'warning' : 'error') . (' -err_detect ignore_err ' . $rOptions . ' {GEN_PTS} {READ_NATIVE} -probesize ' . $rProbesize . ' -analyzeduration ' . $rAnalyseDuration . ' -progress "' . $rProgressURL . '" {CONCAT} -i {STREAM_SOURCE} {LOGO} ');

			if (!array_key_exists('-acodec', $rStream['stream_info']['transcode_attributes'])) {
				$rStream['stream_info']['transcode_attributes']['-acodec'] = 'copy';
			}

			if (!array_key_exists('-vcodec', $rStream['stream_info']['transcode_attributes'])) {
				$rStream['stream_info']['transcode_attributes']['-vcodec'] = 'copy';
			}

			if (!array_key_exists('-scodec', $rStream['stream_info']['transcode_attributes'])) {
				if (self::$rSegmentSettings['seg_type'] == 0) {
					$rStream['stream_info']['transcode_attributes']['-sn'] = '';
				}
				else {
					$rStream['stream_info']['transcode_attributes']['-scodec'] = 'copy';
				}
			}
		}
		else {
			$rStream['stream_info']['transcode_attributes'] = [];
			$rFFMPEG = (stripos($rStream['stream_info']['custom_ffmpeg'], 'nvenc') !== false ? self::$rFFMPEG_GPU : self::$rFFMPEG_CPU) . ' -y -nostdin -hide_banner -loglevel ' . (self::$rSettings['ffmpeg_warnings'] ? 'warning' : 'error') . (' -progress "' . $rProgressURL . '" ') . $rStream['stream_info']['custom_ffmpeg'];
		}
		$rLLODOptions = ($rLLOD && !$rLoopback ? '-fflags nobuffer -flags low_delay -strict experimental' : '');
		$rOutputs = [];

		if ($rLoopback) {
			$rOptions = '{MAP}';
			$rFLVOptions = '{MAP}';
			$rMap = '-map 0 -copy_unknown ';
		}
		else {
			$rOptions = '{MAP} {LLOD}';
			$rFLVOptions = '{MAP} {AAC_FILTER}';
		}

		if (self::$rSegmentSettings['seg_type'] == 0) {
			$rKeyFrames = (self::$rSettings['ignore_keyframes'] ? '+split_by_time' : '');
			$rOutputs['mpegts'][] = $rOptions . ' -individual_header_trailer 0 -f hls -hls_time ' . (int) self::$rSegmentSettings['seg_time'] . ' -hls_list_size ' . (int) self::$rSegmentSettings['seg_list_size'] . ' -hls_delete_threshold ' . (int) self::$rSegmentSettings['seg_delete_threshold'] . (' -hls_flags delete_segments+discont_start+omit_endlist' . $rKeyFrames . ' -hls_segment_type mpegts -hls_segment_filename "') . STREAMS_PATH . (int) $rStreamID . '_%d.ts" "' . STREAMS_PATH . (int) $rStreamID . '_.m3u8" ';
		}
		else {
			$rKeyFrames = (self::$rSettings['ignore_keyframes'] ? ' -break_non_keyframes 1' : '');
			$rOutputs['mpegts'][] = $rOptions . ' -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time ' . (int) self::$rSegmentSettings['seg_time'] . ' -segment_list_size ' . (int) self::$rSegmentSettings['seg_list_size'] . (' -segment_format_options "mpegts_flags=+initial_discontinuity:mpegts_copyts=1" -segment_list_type m3u8 -segment_list_flags +live+delete' . $rKeyFrames . ' -segment_list "') . STREAMS_PATH . (int) $rStreamID . '_.m3u8" "' . STREAMS_PATH . (int) $rStreamID . '_%d.ts" ';
		}

		if ($rStream['stream_info']['rtmp_output'] == 1) {
			$rOutputs['flv'][] = $rFLVOptions . ' -f flv -flvflags no_duration_filesize rtmp://127.0.0.1:' . (int) self::$rServers[$rStream['server_info']['server_id']]['rtmp_port'] . '/live/' . (int) $rStreamID . '?password=' . urlencode(self::$rSettings['live_streaming_pass']) . ' ';
		}

		if (!empty($rExternalPush[SERVER_ID])) {
			foreach ($rExternalPush[SERVER_ID] as $rPushURL) {
				$rOutputs['flv'][] = $rFLVOptions . ' -f flv -flvflags no_duration_filesize ' . escapeshellarg($rPushURL) . ' ';
			}
		}
		$rLogoOptions = (isset($rStream['stream_info']['transcode_attributes'][16]) && !$rLoopback ? $rStream['stream_info']['transcode_attributes'][16]['cmd'] : '');
		$rGPUOptions = (isset($rStream['stream_info']['transcode_attributes']['gpu']) ? $rStream['stream_info']['transcode_attributes']['gpu']['cmd'] : '');
		$rInputCodec = '';
		if (!empty($rGPUOptions) && in_array($rFFProbeOutput['codecs']['video']['codec_name'], ['h264' => true, 'hevc' => true, 'mjpeg' => true, 'mpeg1' => true, 'mpeg2' => true, 'mpeg4' => true, 'vc1' => true, 'vp8' => true, 'vp9' => true])) {
			$rInputCodec = '-c:v ' . $rFFProbeOutput['codecs']['video']['codec_name'] . '_cuvid';
		}
		if (!((0 < $rStream['stream_info']['delay_minutes']) && !$rStream['server_info']['parent_id'])) {
			foreach ($rOutputs as $rOutputKey => $rOutputCommands) {
				foreach ($rOutputCommands as $rOutputCommand) {
					if (isset($rStream['stream_info']['transcode_attributes']['gpu'])) {
						$rFFMPEG .= '-gpu ' . (int) $rStream['stream_info']['transcode_attributes']['gpu']['device'] . ' ';
					}

					$rFFMPEG .= implode(' ', self::parseTranscode($rStream['stream_info']['transcode_attributes'])) . ' ';
					$rFFMPEG .= $rOutputCommand;
				}
			}
		}
		else {
			$rSegmentStart = 0;

			if (file_exists(DELAY_PATH . $rStreamID . '_.m3u8')) {
				$rFile = file(DELAY_PATH . $rStreamID . '_.m3u8');

				if (stristr($rFile[count($rFile) - 1], $rStreamID . '_')) {
					if (preg_match('/\\_(.*?)\\.ts/', $rFile[count($rFile) - 1], $rMatches)) {
						$rSegmentStart = (int) $rMatches[1] + 1;
					}
				}
				else {
					if (preg_match('/\\_(.*?)\\.ts/', $rFile[count($rFile) - 2], $rMatches)) {
						$rSegmentStart = (int) $rMatches[1] + 1;
					}
				}

				if (file_exists(DELAY_PATH . $rStreamID . '_.m3u8_old')) {
					file_put_contents(DELAY_PATH . $rStreamID . '_.m3u8_old', file_get_contents(DELAY_PATH . $rStreamID . '_.m3u8_old') . file_get_contents(DELAY_PATH . $rStreamID . '_.m3u8'));
					shell_exec('sed -i \'/EXTINF\\|.ts/!d\' ' . DELAY_PATH . (int) $rStreamID . '_.m3u8_old');
				}
				else {
					copy(DELAY_PATH . $rStreamID . '_.m3u8', DELAY_PATH . (int) $rStreamID . '_.m3u8_old');
				}
			}

			$rFFMPEG .= implode(' ', self::parseTranscode($rStream['stream_info']['transcode_attributes'])) . ' ';

			if (self::$rSegmentSettings['seg_type'] == 0) {
				$rFFMPEG .= '{MAP} -individual_header_trailer 0 -f hls -hls_time ' . (int) self::$rSegmentSettings['seg_time'] . ' -hls_list_size ' . ((int) $rStream['stream_info']['delay_minutes'] * 6) . (' -hls_delete_threshold 4 -start_number ' . $rSegmentStart . ' -hls_flags delete_segments+discont_start+omit_endlist -hls_segment_type mpegts -hls_segment_filename "') . DELAY_PATH . (int) $rStreamID . '_%d.ts" "' . DELAY_PATH . (int) $rStreamID . '_.m3u8" ';
			}
			else {
				$rFFMPEG .= '{MAP} -individual_header_trailer 0 -f segment -segment_format mpegts -segment_time ' . (int) self::$rSegmentSettings['seg_time'] . ' -segment_list_size ' . ((int) $rStream['stream_info']['delay_minutes'] * 6) . (' -segment_start_number ' . $rSegmentStart . ' -segment_format_options "mpegts_flags=+initial_discontinuity:mpegts_copyts=1" -segment_list_type m3u8 -segment_list_flags +live+delete -segment_list "') . DELAY_PATH . (int) $rStreamID . '_.m3u8" "' . DELAY_PATH . (int) $rStreamID . '_%d.ts" ';
			}

			$rSleepTime = $rStream['stream_info']['delay_minutes'] * 60;

			if (0 < $rSegmentStart) {
				$rSleepTime -= ($rSegmentStart - 1) * 10;

				if ($rSleepTime <= 0) {
					$rSleepTime = 0;
				}
			}
		}

		$rFFMPEG .= ' >/dev/null 2>>' . STREAMS_PATH . (int) $rStreamID . '.errors & echo $! > ' . STREAMS_PATH . (int) $rStreamID . '_.pid';
		$rFFMPEG = str_replace(['{FETCH_OPTIONS}', '{GEN_PTS}', '{STREAM_SOURCE}', '{MAP}', '{READ_NATIVE}', '{CONCAT}', '{AAC_FILTER}', '{GPU}', '{INPUT_CODEC}', '{LOGO}', '{LLOD}'], [empty($rStream['stream_info']['custom_ffmpeg']) ? $rFetchOptions : '', empty($rStream['stream_info']['custom_ffmpeg']) ? $rGenPTS : '', escapeshellarg($rStreamSource), empty($rStream['stream_info']['custom_ffmpeg']) ? $rMap : '', empty($rStream['stream_info']['custom_ffmpeg']) ? $rReadNative : '', ($rStream['stream_info']['type_key'] == 'created_live') && !$rStream['server_info']['parent_id'] ? '-safe 0 -f concat' : '', !stristr($rFFProbeOutput['container'], 'flv') && ($rFFProbeOutput['codecs']['audio']['codec_name'] == 'aac') && ($rStream['stream_info']['transcode_attributes']['-acodec'] == 'copy') ? '-bsf:a aac_adtstoasc' : '', $rGPUOptions, $rInputCodec, $rLogoOptions, $rLLODOptions], $rFFMPEG);
		shell_exec($rFFMPEG);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.ffmpeg', $rFFMPEG);
		$rKey = openssl_random_pseudo_bytes(16);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.key', $rKey);
		$rIVSize = openssl_cipher_iv_length('AES-128-CBC');
		$rIV = openssl_random_pseudo_bytes($rIVSize);
		file_put_contents(STREAMS_PATH . $rStreamID . '_.iv', $rIV);
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');

		if ($rStream['stream_info']['tv_archive_server_id'] == SERVER_ID) {
			shell_exec(PHP_BIN . ' ' . CLI_PATH . 'archive.php ' . (int) $rStreamID . ' >/dev/null 2>/dev/null & echo $!');
		}

		if ($rStream['stream_info']['vframes_server_id'] == SERVER_ID) {
			self::startThumbnail($rStreamID);
		}
		$rDelayEnabled = (0 < $rStream['stream_info']['delay_minutes']) && !$rStream['server_info']['parent_id'];
		$rDelayStartAt = ($rDelayEnabled ? time() + $rSleepTime : 0);

		if ($rStream['stream_info']['enable_transcode']) {
			$rFFProbeOutput = [];
		}

		$rCompatible = 0;
		$rAudioCodec = $rVideoCodec = $rResolution = NULL;

		if ($rFFProbeOutput) {
			$rCompatible = (int) self::checkCompatibility($rFFProbeOutput);
			$rAudioCodec = $rFFProbeOutput['codecs']['audio']['codec_name'] ?: NULL;
			$rVideoCodec = $rFFProbeOutput['codecs']['video']['codec_name'] ?: NULL;
			$rResolution = $rFFProbeOutput['codecs']['video']['height'] ?: NULL;

			if ($rResolution) {
				$rResolution = self::getNearest([240, 360, 480, 576, 720, 1080, 1440, 2160], $rResolution);
			}
		}

		self::$db->query('UPDATE `streams_servers` SET `delay_available_at` = ?,`to_analyze` = 0,`stream_started` = ?,`stream_info` = ?,`audio_codec` = ?, `video_codec` = ?, `resolution` = ?,`compatible` = ?,`stream_status` = 2,`pid` = ?,`progress_info` = ?,`current_source` = ? WHERE `stream_id` = ? AND `server_id` = ?', $rDelayStartAt, time(), json_encode($rFFProbeOutput), $rAudioCodec, $rVideoCodec, $rResolution, $rCompatible, $rPID, json_encode([]), $rSource, $rStreamID, SERVER_ID);
		self::updateStream($rStreamID);
		$rPlaylist = (!$rDelayEnabled ? STREAMS_PATH . $rStreamID . '_.m3u8' : DELAY_PATH . $rStreamID . '_.m3u8');
		return ['main_pid' => $rPID, 'stream_source' => $rRealSource, 'delay_enabled' => $rDelayEnabled, 'parent_id' => $rStream['server_info']['parent_id'], 'delay_start_at' => $rDelayStartAt, 'playlist' => $rPlaylist, 'transcode' => $rStream['stream_info']['enable_transcode'], 'offset' => $rOffset];
	}

	static public function customOrder($a, $b)
	{
		if (substr($a, 0, 3) == '-i ') {
			return -1;
		}
		else {
			return 1;
		}
	}

	static public function getArguments($rArguments, $rProtocol, $rType)
	{
		$rReturn = [];

		if (!empty($rArguments)) {
			foreach ($rArguments as $rArgument_id => $rArgument) {
				if ($rType != $rArgument['argument_cat']) {
					continue;
				}
				if (!is_null($rArgument['argument_wprotocol']) && !stristr($rProtocol, $rArgument['argument_wprotocol']) && !is_null($rProtocol)) {
					continue;
				}

				if ($rArgument['argument_key'] == 'cookie') {
					$rArgument['value'] = self::fixCookie($rArgument['value']);
				}

				if ($rArgument['argument_type'] == 'text') {
					$rReturn[] = sprintf($rArgument['argument_cmd'], $rArgument['value']);
				}
				else {
					$rReturn[] = $rArgument['argument_cmd'];
				}
			}
		}

		return $rReturn;
	}

	static public function parseTranscode($rArgs)
	{
		$rFitlerComplex = [];

		foreach ($rArgs as $rKey => $rArgument) {
			if (($rKey == 'gpu') || ($rKey == 'software_decoding') || ($rKey == '16')) {
				continue;
			}

			if (isset($rArgument['cmd'])) {
				$rArgs[$rKey] = $rArgument = $rArgument['cmd'];
			}

			if (preg_match('/-filter_complex "(.*?)"/', $rArgument, $rMatches)) {
				$rArgs[$rKey] = trim(str_replace($rMatches[0], '', $rArgs[$rKey]));
				$rFitlerComplex[] = $rMatches[1];
			}
		}

		if (!empty($rFitlerComplex)) {
			$rArgs[] = '-filter_complex "' . implode(',', $rFitlerComplex) . '"';
		}

		$rNewArgs = [];

		foreach ($rArgs as $rKey => $rArg) {
			if ($rKey == 'gpu') {
				continue;
			}

			if ($rKey == 'software_decoding') {
				continue;
			}

			if (is_numeric($rKey)) {
				$rNewArgs[] = $rArg;
			}
			else {
				$rNewArgs[] = $rKey . ' ' . $rArg;
			}
		}

		$rNewArgs = array_filter($rNewArgs);
		uasort($rNewArgs, [__CLASS__, 'customOrder']);
		return array_map('trim', array_values(array_filter($rNewArgs)));
	}

	static public function parseStreamURL($rURL)
	{
		$rProtocol = strtolower(substr($rURL, 0, 4));

		if ($rProtocol == 'rtmp') {
			if (stristr($rURL, '$OPT')) {
				$rPattern = 'rtmp://$OPT:rtmp-raw=';
				$rURL = trim(substr($rURL, stripos($rURL, $rPattern) + strlen($rPattern)));
			}

			$rURL .= ' live=1 timeout=10';
		}
		else if ($rProtocol == 'http') {
			$rPlatforms = ['livestream.com', 'ustream.tv', 'twitch.tv', 'vimeo.com', 'facebook.com', 'dailymotion.com', 'cnn.com', 'edition.cnn.com', 'youtube.com', 'youtu.be'];
			$rHost = str_ireplace('www.', '', parse_url($rURL, PHP_URL_HOST));

			if (in_array($rHost, $rPlatforms)) {
				$rURLs = trim(shell_exec(YOUTUBE_BIN . ' ' . escapeshellarg($rURL) . ' -q --get-url --skip-download -f best'));
				$rURL = explode("\n", $rURLs)[0];
			}
		}

		return $rURL;
	}

	static public function detectXCMS($rURL)
	{
		$rPath = parse_url($rURL)['path'];
		$rPathSize = count(explode('/', $rPath));
		$rRegex = ['/\\/auth\\/(.*)$/m' => 3, '/\\/play\\/(.*)$/m' => 3, '/\\/play\\/(.*)\\/(.*)$/m' => 4, '/\\/live\\/(.*)\\/(\\d+)$/m' => 4, '/\\/live\\/(.*)\\/(\\d+)\\.(.*)$/m' => 4, '/\\/(.*)\\/(.*)\\/(\\d+)\\.(.*)$/m' => 4, '/\\/(.*)\\/(.*)\\/(\\d+)$/m' => 4, '/\\/live\\/(.*)\\/(.*)\\/(\\d+)\\.(.*)$/m' => 5, '/\\/live\\/(.*)\\/(.*)\\/(\\d+)$/m' => 5];

		foreach ($rRegex as $rQuery => $rCount) {
			if ($rPathSize == $rCount) {
				preg_match($rQuery, $rPath, $rMatches);

				if (0 < count($rMatches)) {
					return true;
				}
			}
		}

		return false;
	}

	static public function getAllowedIPs($rForce = false)
	{
		if (!$rForce) {
			$rCache = self::getCache('allowed_ips', 60);

			if ($rCache !== false) {
				return $rCache;
			}
		}

		$rIPs = ['127.0.0.1', $_SERVER['SERVER_ADDR']];

		foreach (self::$rServers as $rServerID => $rServerInfo) {
			if (!empty($rServerInfo['whitelist_ips'])) {
				$rIPs = array_merge($rIPs, json_decode($rServerInfo['whitelist_ips'], true));
			}

			$rIPs[] = $rServerInfo['server_ip'];

			if ($rServerInfo['private_ip']) {
				$rIPs[] = $rServerInfo['private_ip'];
			}

			foreach (explode(',', $rServerInfo['domain_name']) as $rIP) {
				if (filter_var($rIP, FILTER_VALIDATE_IP)) {
					$rIPs[] = $rIP;
				}
			}
		}

		if (!empty(self::$rSettings['allowed_ips_admin'])) {
			$rIPs = array_merge($rIPs, explode(',', self::$rSettings['allowed_ips_admin']));
		}

		self::setCache('allowed_ips', $rIPs);
		return array_unique($rIPs);
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

	static public function getMAGInfo($rMAGID = NULL, $rMAC = NULL, $rGetChannelIDs = false, $rGetBouquetInfo = false, $rGetConnections = false)
	{
		if (empty($rMAGID)) {
			self::$db->query('SELECT * FROM `mag_devices` WHERE `mac` = ?', base64_encode($rMAC));
		}
		else {
			self::$db->query('SELECT * FROM `mag_devices` WHERE `mag_id` = ?', $rMAGID);
		}

		if (0 < self::$db->num_rows()) {
			$rMagInfo = [];
			$rMagInfo['mag_device'] = self::$db->get_row();
			$rMagInfo['mag_device']['mac'] = base64_decode($rMagInfo['mag_device']['mac']);
			$rMagInfo['user_info'] = [];

			if ($rUserInfo = self::getUserInfo($rMagInfo['mag_device']['user_id'], NULL, NULL, $rGetChannelIDs, $rGetConnections)) {
				$rMagInfo['user_info'] = $rUserInfo;
			}

			$rMagInfo['pair_line_info'] = [];

			if (!empty($rMagInfo['user_info'])) {
				$rMagInfo['pair_line_info'] = [];

				if (!is_null($rMagInfo['user_info']['pair_id'])) {
					if ($rUserInfo = self::getUserInfo($rMagInfo['user_info']['pair_id'], NULL, NULL, $rGetChannelIDs, $rGetConnections)) {
						$rMagInfo['pair_line_info'] = $rUserInfo;
					}
				}
			}

			return $rMagInfo;
		}

		return false;
	}

	static public function getE2Info($rDevice, $rGetChannelIDs = false, $rGetBouquetInfo = false, $rGetConnections = false)
	{
		if (empty($rDevice['device_id'])) {
			self::$db->query('SELECT * FROM `enigma2_devices` WHERE `mac` = ?', $rDevice['mac']);
		}
		else {
			self::$db->query('SELECT * FROM `enigma2_devices` WHERE `device_id` = ?', $rDevice['device_id']);
		}

		if (0 < self::$db->num_rows()) {
			$rReturn = [];
			$rReturn['enigma2'] = self::$db->get_row();
			$rReturn['user_info'] = [];

			if ($rUserInfo = self::getUserInfo($rReturn['enigma2']['user_id'], NULL, NULL, $rGetChannelIDs, $rGetConnections)) {
				$rReturn['user_info'] = $rUserInfo;
			}

			$rReturn['pair_line_info'] = [];

			if (!empty($rReturn['user_info'])) {
				$rReturn['pair_line_info'] = [];

				if (!is_null($rReturn['user_info']['pair_id'])) {
					if ($rUserInfo = self::getUserInfo($rReturn['user_info']['pair_id'], NULL, NULL, $rGetChannelIDs, $rGetConnections)) {
						$rReturn['pair_line_info'] = $rUserInfo;
					}
				}
			}

			return $rReturn;
		}

		return false;
	}

	static public function getRTMPStats()
	{
		$rURL = self::$rServers[SERVER_ID]['rtmp_mport_url'] . 'stat';
		$rContext = stream_context_create([
			'http' => ['timeout' => 1]
		]);
		$rXML = file_get_contents($rURL, false, $rContext);
		return json_decode(json_encode(simplexml_load_string($rXML, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
	}

	static public function closeConnection($rActivityInfo, $rRemove = true, $rEnd = true)
	{
		if (empty($rActivityInfo)) {
			return false;
		}
		if (self::$rSettings['redis_handler'] && !is_object(self::$redis)) {
			self::connectRedis();
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
			if (!$rRemove && $rEnd && ($rActivityInfo['hls_end'] == 0)) {
				if (self::$rSettings['redis_handler']) {
					self::updateConnection($rActivityInfo, [], 'close');
				}
				else {
					self::$db->query('UPDATE `lines_live` SET `hls_end` = 1 WHERE `activity_id` = ?', $rActivityInfo['activity_id']);
				}

				@unlink(CONS_TMP_PATH . $rActivityInfo['stream_id'] . '/' . $rActivityInfo['uuid']);
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

		if ($rActivityInfo['server_id'] == SERVER_ID) {
			@unlink(CONS_TMP_PATH . $rActivityInfo['uuid']);
		}

		if ($rRemove) {
			if ($rActivityInfo['server_id'] == SERVER_ID) {
				@unlink(CONS_TMP_PATH . $rActivityInfo['stream_id'] . '/' . $rActivityInfo['uuid']);
			}

			if (self::$rSettings['redis_handler']) {
				$rRedis = self::$redis->multi();
				$rRedis->zRem('LINE#' . $rActivityInfo['identity'], $rActivityInfo['uuid']);
				$rRedis->zRem('LINE_ALL#' . $rActivityInfo['identity'], $rActivityInfo['uuid']);
				$rRedis->zRem('STREAM#' . $rActivityInfo['stream_id'], $rActivityInfo['uuid']);
				$rRedis->zRem('SERVER#' . $rActivityInfo['server_id'], $rActivityInfo['uuid']);

				if ($rActivityInfo['user_id']) {
					$rRedis->zRem('SERVER_LINES#' . $rActivityInfo['server_id'], $rActivityInfo['uuid']);
				}

				if ($rActivityInfo['proxy_id']) {
					$rRedis->zRem('PROXY#' . $rActivityInfo['proxy_id'], $rActivityInfo['uuid']);
				}

				$rRedis->del($rActivityInfo['uuid']);
				$rRedis->zRem('CONNECTIONS', $rActivityInfo['uuid']);
				$rRedis->zRem('LIVE', $rActivityInfo['uuid']);
				$rRedis->sRem('ENDED', $rActivityInfo['uuid']);
				$rRedis->exec();
			}
			else {
				self::$db->query('DELETE FROM `lines_live` WHERE `activity_id` = ?', $rActivityInfo['activity_id']);
			}
		}

		self::writeOfflineActivity($rActivityInfo['server_id'], $rActivityInfo['proxy_id'], $rActivityInfo['user_id'], $rActivityInfo['stream_id'], $rActivityInfo['date_start'], $rActivityInfo['user_agent'], $rActivityInfo['user_ip'], $rActivityInfo['container'], $rActivityInfo['geoip_country_code'], $rActivityInfo['isp'], $rActivityInfo['external_device'], $rActivityInfo['divergence'], $rActivityInfo['hmac_id'], $rActivityInfo['hmac_identifier']);
		return true;
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

	static public function streamLog($rStreamID, $rServerID, $rAction, $rSource = '')
	{
		if (self::$rSettings['save_restart_logs'] == 0) {
			return NULL;
		}

		$rData = ['server_id' => $rServerID, 'stream_id' => $rStreamID, 'action' => $rAction, 'source' => $rSource, 'time' => time()];
		file_put_contents(LOGS_TMP_PATH . 'stream_log.log', base64_encode(json_encode($rData)) . "\n", FILE_APPEND);
	}

	static public function getPlaylistSegments($rPlaylist, $rPrebuffer = 0, $rSegmentDuration = 10)
	{
		if (file_exists($rPlaylist)) {
			$rSource = file_get_contents($rPlaylist);

			if (preg_match_all('/(.*?).ts/', $rSource, $rMatches)) {
				if (0 < $rPrebuffer) {
					$rTotalSegments = (int) ($rPrebuffer / ($rSegmentDuration ?: 1));
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

	static public function generateAdminHLS($rM3U8, $rPassword, $rStreamID, $rUIToken)
	{
		if (file_exists($rM3U8)) {
			$rSource = file_get_contents($rM3U8);

			if (preg_match_all('/(.*?)\\.ts/', $rSource, $rMatches)) {
				foreach ($rMatches[0] as $rMatch) {
					if ($rUIToken) {
						$rSource = str_replace($rMatch, '/admin/live?extension=m3u8&segment=' . $rMatch . '&uitoken=' . $rUIToken, $rSource);
					}
					else {
						$rSource = str_replace($rMatch, '/admin/live?password=' . $rPassword . '&extension=m3u8&segment=' . $rMatch . '&stream=' . $rStreamID, $rSource);
					}
				}

				return $rSource;
			}
		}

		return false;
	}

	static public function checkBlockedUAs($rUserAgent, $rReturn = false)
	{
		$rUserAgent = strtolower($rUserAgent);
		$rFoundID = false;

		foreach (self::$rBlockedUA as $rKey => $rBlocked) {
			if ($rBlocked['exact_match'] == 1) {
				if ($rUserAgent == $rBlocked['blocked_ua']) {
					$rFoundID = $rKey;
					break;
				}
			}
			else {
				if (stristr($rUserAgent, $rBlocked['blocked_ua'])) {
					$rFoundID = $rKey;
					break;
				}
			}
		}

		if (0 < $rFoundID) {
			self::$db->query('UPDATE `blocked_uas` SET `attempts_blocked` = `attempts_blocked`+1 WHERE `id` = ?', $rFoundID);

			if ($rReturn) {
				return true;
			}
			else {
				exit();
			}
		}
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

	static public function isThumbnailRunning($rPID, $rStreamID, $rEXE = PHP_BIN)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe') && (strpos(basename(readlink('/proc/' . $rPID . '/exe')), basename($rEXE)) === 0)) {
			$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));

			if ($rCommand == 'Thumbnail[' . $rStreamID . ']') {
				return true;
			}
		}

		return false;
	}

	static public function isArchiveRunning($rPID, $rStreamID, $rEXE = PHP_BIN)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe') && (strpos(basename(readlink('/proc/' . $rPID . '/exe')), basename($rEXE)) === 0)) {
			$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));

			if ($rCommand == 'TVArchive[' . $rStreamID . ']') {
				return true;
			}
		}

		return false;
	}

	static public function isDelayRunning($rPID, $rStreamID)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe')) {
			$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));

			if ($rCommand == 'XCMSDelay[' . $rStreamID . ']') {
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

	static public function isProcessRunning($rPID, $rEXE = NULL)
	{
		if (empty($rPID)) {
			return false;
		}

		clearstatcache(true);
		if ((file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe') && (strpos(basename(readlink('/proc/' . $rPID . '/exe')), basename($rEXE)) === 0)) || !$rEXE) {
			return true;
		}

		return false;
	}

	static public function isValidStream($rPlaylist, $rPID)
	{
		return (self::isProcessRunning($rPID, 'ffmpeg') || self::isProcessRunning($rPID, 'php')) && file_exists($rPlaylist);
	}

	static public function findKeyframe($rSegment)
	{
		$rPacketSize = 188;
		$rKeyframe = $rPosition = 0;
		$rFoundStart = false;

		if (file_exists($rSegment)) {
			$rFP = fopen($rSegment, 'rb');

			if ($rFP) {
				while (!feof($rFP)) {
					if (!$rFoundStart) {
						$rFirstPacket = fread($rFP, $rPacketSize);
						$rSecondPacket = fread($rFP, $rPacketSize);

						for ($i = 0; $i < strlen($rFirstPacket); $i++) {
							$rFirstHeader = unpack('N', substr($rFirstPacket, $i, 4))[1];
							$rSecondHeader = unpack('N', substr($rSecondPacket, $i, 4))[1];
							$rSync = ((($rFirstHeader >> 24) & 255) == 71) && ((($rSecondHeader >> 24) & 255) == 71);

							if ($rSync) {
								$rFoundStart = true;
								$rPosition = $i;
								fseek($rFP, $i);
								break;
							}
						}
					}

					$rBuffer .= fread($rFP, ($rPacketSize * 64) - strlen($rBuffer));

					if (!empty($rBuffer)) {
						foreach (str_split($rBuffer, $rPacketSize) as $rPacket) {
							$rHeader = unpack('N', substr($rPacket, 0, 4))[1];
							$rSync = ($rHeader >> 24) & 255;

							if ($rSync == 71) {
								if (substr($rPacket, 6, 4) == "\xb0" . '' . "\r" . '' . "\0" . '' . "\x1") {
									$rKeyframe = $rPosition;
								}
								else {
									$rAdaptationField = ($rHeader >> 4) & 3;

									if (($rAdaptationField & 2) === 2) {
										if ((0 < $rKeyframe) && ((unpack('C', $rPacket[4])[1] == 7) && (substr($rPacket, 4, 2) == "\x7" . 'P'))) {
											break 2;
										}
									}
								}
							}

							$rPosition += strlen($rPacket);
						}
					}

					$rBuffer = '';
				}

				fclose($rFP);
			}
		}

		return $rKeyframe;
	}

	static public function getUserIP()
	{
		return $_SERVER['REMOTE_ADDR'];
	}

	static public function getStreamBitrate($rType, $rPath, $rForceDuration = NULL)
	{
		clearstatcache();

		if (!file_exists($rPath)) {
			return false;
		}

		switch ($rType) {
		case 'movie':
			if (!is_null($rForceDuration)) {
				sscanf($rForceDuration, '%d:%d:%d', $rHours, $rMinutes, $rSeconds);
				$rTime = (isset($rSeconds) ? ($rHours * 3600) + ($rMinutes * 60) + $rSeconds : ($rHours * 60) + $rMinutes);
				$rBitrate = round((filesize($rPath) * 0.008) / ($rTime ?: 1));
			}

			break;
		case 'live':
			$rFP = fopen($rPath, 'r');
			$rBitrates = [];

			while (!feof($rFP)) {
				$rLine = trim(fgets($rFP));

				if (stristr($rLine, 'EXTINF')) {
					list($rTrash, $rSeconds) = explode(':', $rLine);
					$rSeconds = rtrim($rSeconds, ',');

					if ($rSeconds <= 0) {
						continue;
					}

					$rSegmentFile = trim(fgets($rFP));

					if (!file_exists(dirname($rPath) . '/' . $rSegmentFile)) {
						fclose($rFP);
						return false;
					}

					$rSize = filesize(dirname($rPath) . '/' . $rSegmentFile) * 0.008;
					$rBitrates[] = $rSize / ($rSeconds ?: 1);
				}
			}

			fclose($rFP);
			$rBitrate = (0 < count($rBitrates) ? round(array_sum($rBitrates) / count($rBitrates)) : 0);
			break;
		}

		return 0 < $rBitrate ? $rBitrate : false;
	}

	static public function getISP($rIP)
	{
		if (empty($rIP)) {
			return false;
		}

		if (file_exists(CONS_TMP_PATH . md5($rIP) . '_isp')) {
			return json_decode(file_get_contents(CONS_TMP_PATH . md5($rIP) . '_isp'), true);
		}

		$rGeoIP = new MaxMind\Db\Reader(GEOISP_BIN);
		$rResponse = $rGeoIP->get($rIP);
		$rGeoIP->close();

		if ($rResponse) {
			file_put_contents(CONS_TMP_PATH . md5($rIP) . '_isp', json_encode($rResponse));
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

	static public function getCertificateInfo($rCertificate = NULL)
	{
		$rReturn = ['serial' => NULL, 'expiration' => NULL, 'subject' => NULL, 'path' => NULL];

		if (!$rCertificate) {
			$rConfig = explode("\n", file_get_contents(BIN_PATH . 'nginx/conf/ssl.conf'));

			foreach ($rConfig as $rLine) {
				if (stripos($rLine, 'ssl_certificate ') !== false) {
					$rCertificate = rtrim(trim(explode('ssl_certificate ', $rLine)[1]), ';');
					break;
				}
			}
		}

		if ($rCertificate) {
			$rReturn['path'] = pathinfo($rCertificate)['dirname'];
			exec('openssl x509 -serial -enddate -subject -noout -in ' . escapeshellarg($rCertificate), $rOutput, $rReturnVar);

			foreach ($rOutput as $rLine) {
				if (stripos($rLine, 'serial=') !== false) {
					$rReturn['serial'] = trim(explode('serial=', $rLine)[1]);
				}
				else if (stripos($rLine, 'subject=') !== false) {
					$rReturn['subject'] = trim(explode('subject=', $rLine)[1]);
				}
				else if (stripos($rLine, 'notAfter=') !== false) {
					$rReturn['expiration'] = strtotime(trim(explode('notAfter=', $rLine)[1]));
				}
			}
		}

		return $rReturn;
	}

	static public function downloadImage($rImage, $rType = NULL)
	{
		if ((0 < strlen($rImage)) && (substr(strtolower($rImage), 0, 4) == 'http')) {
			$rPathInfo = pathinfo($rImage);
			$rExt = $rPathInfo['extension'];

			if (!$rExt) {
				$rImageInfo = getimagesize($rImage);

				if ($rImageInfo['mime']) {
					$rExt = explode('/', $rImageInfo['mime'])[1];
				}
			}

			if (in_array(strtolower($rExt), ['jpg' => true, 'jpeg' => true, 'png' => true])) {
				$rFilename = Xcms\Functions::encrypt($rImage, self::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
				$rPrevPath = IMAGES_PATH . $rFilename . '.' . $rExt;

				if (file_exists($rPrevPath)) {
					return 's:' . SERVER_ID . ':/images/' . $rFilename . '.' . $rExt;
				}
				else {
					$rCurl = curl_init();
					curl_setopt($rCurl, CURLOPT_URL, $rImage);
					curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($rCurl, CURLOPT_CONNECTTIMEOUT, 5);
					curl_setopt($rCurl, CURLOPT_TIMEOUT, 5);
					$rData = curl_exec($rCurl);

					if (0 < strlen($rData)) {
						$rPath = IMAGES_PATH . $rFilename . '.' . $rExt;
						file_put_contents($rPath, $rData);

						if (file_exists($rPath)) {
							return 's:' . SERVER_ID . ':/images/' . $rFilename . '.' . $rExt;
						}
					}
				}
			}
		}

		return $rImage;
	}

	static public function getImageSizeKeepAspectRatio($origWidth, $origHeight, $maxWidth, $maxHeight)
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

	static public function isAbsoluteUrl($rURL)
	{
		$rPattern = '/^(?:ftp|https?|feed)?:?\\/\\/(?:(?:(?:[\\w\\.\\-\\+!$&\'\\(\\)*\\+,;=]|%[0-9a-f]{2})+:)*' . "\n" . '        (?:[\\w\\.\\-\\+%!$&\'\\(\\)*\\+,;=]|%[0-9a-f]{2})+@)?(?:' . "\n" . '        (?:[a-z0-9\\-\\.]|%[0-9a-f]{2})+|(?:\\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\\]))(?::[0-9]+)?(?:[\\/|\\?]' . "\n" . '        (?:[\\w#!:\\.\\?\\+\\|=&@$\'~*,;\\/\\(\\)\\[\\]\\-]|%[0-9a-f]{2})*)?$/xi';
		return (bool) preg_match($rPattern, $rURL);
	}

	static public function generateThumbnail($rImage, $rType)
	{
		if (($rType == 1) || ($rType == 5) || ($rType == 4)) {
			$rMaxW = 96;
			$rMaxH = 32;
		}
		else if ($rType == 2) {
			$rMaxW = 58;
			$rMaxH = 32;
		}
		else if ($rType == 5) {
			$rMaxW = 32;
			$rMaxH = 64;
		}
		else {
			return false;
		}

		$rExtension = explode('.', strtolower(pathinfo($rImage)['extension']))[0];

		if (in_array($rExtension, ['png' => true, 'jpg' => true, 'jpeg' => true])) {
			$rImagePath = IMAGES_PATH . 'admin/' . md5($rImage) . '_' . $rMaxW . '_' . $rMaxH . '.' . $rExtension;

			if (!file_exists($rImagePath)) {
				if (self::isAbsoluteUrl($rImage)) {
					$rActURL = $rImage;
				}
				else {
					$rActURL = IMAGES_PATH . basename($rImage);
				}

				list($rWidth, $rHeight) = getimagesize($rActURL);
				$rImageSize = self::getImageSizeKeepAspectRatio($rWidth, $rHeight, $rMaxW, $rMaxH);
				if ($rImageSize['width'] && $rImageSize['height']) {
					$rImageP = imagecreatetruecolor($rImageSize['width'], $rImageSize['height']);

					if ($rExtension == 'png') {
						$rImage = imagecreatefrompng($rActURL);
					}
					else {
						$rImage = imagecreatefromjpeg($rActURL);
					}

					imagealphablending($rImageP, false);
					imagesavealpha($rImageP, true);
					imagecopyresampled($rImageP, $rImage, 0, 0, 0, 0, $rImageSize['width'], $rImageSize['height'], $rWidth, $rHeight);
					imagepng($rImageP, $rImagePath);
				}
			}

			if (file_exists($rImagePath)) {
				return true;
			}
		}

		return false;
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

	static public function getURL($rURL, $rWait = true)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_setopt($ch, CURLOPT_URL, $rURL);
		curl_setopt($ch, CURLOPT_USERAGENT, 'XCMS/' . XCMS_VERSION);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, $rWait);
		$rReturn = curl_exec($ch);
		curl_close($ch);
		return $rReturn;
	}

	static public function startDownload($rType, $rUser, $rDownloadPID)
	{
		$rFloodLimit = (int) self::$rSettings['max_simultaneous_downloads'];

		if ($rFloodLimit == 0) {
			return true;
		}

		if ($rUser['is_restreamer']) {
			return true;
		}

		$rFile = FLOOD_TMP_PATH . $rUser['id'] . '_downloads';
		if (file_exists($rFile) && ((time() - filemtime($rFile)) < 10)) {
			$rFloodRow[$rType] = [];

			foreach (json_decode(file_get_contents($rFile), true)[$rType] as $rPID) {
				if (self::isProcessRunning($rPID, 'php-fpm') && ($rPID != $rDownloadPID)) {
					$rFloodRow[$rType][] = $rPID;
				}
			}
		}
		else {
			$rFloodRow = [
				'epg'      => [],
				'playlist' => []
			];
		}

		$rAllow = false;

		if (count($rFloodRow[$rType]) < $rFloodLimit) {
			$rFloodRow[$rType][] = $rDownloadPID;
			$rAllow = true;
		}

		file_put_contents($rFile, json_encode($rFloodRow), LOCK_EX);
		return $rAllow;
	}

	static public function stopDownload($rType, $rUser, $rDownloadPID)
	{
		if ((int) self::$rSettings['max_simultaneous_downloads'] == 0) {
			return NULL;
		}

		if ($rUser['is_restreamer']) {
			return NULL;
		}

		$rFile = FLOOD_TMP_PATH . $rUser['id'] . '_downloads';

		if (file_exists($rFile)) {
			$rFloodRow[$rType] = [];

			foreach (json_decode(file_get_contents($rFile), true)[$rType] as $rPID) {
				if (self::isProcessRunning($rPID, 'php-fpm') && ($rPID != $rDownloadPID)) {
					$rFloodRow[$rType][] = $rPID;
				}
			}
		}
		else {
			$rFloodRow = [
				'epg'      => [],
				'playlist' => []
			];
		}

		file_put_contents($rFile, json_encode($rFloodRow), LOCK_EX);
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

	static public function getCapacity($rProxy = false)
	{
		$rFile = ($rProxy ? 'proxy_capacity' : 'servers_capacity');
		if (self::$rSettings['redis_handler'] && $rProxy && (self::$rSettings['split_by'] == 'maxclients')) {
			self::$rSettings['split_by'] == 'guar_band';
		}

		if (self::$rSettings['redis_handler']) {
			$rRows = [];
			$rMulti = self::$redis->multi();

			foreach (array_keys(self::$rServers) as $rServerID) {
				if (self::$rServers[$rServerID]['server_online']) {
					$rMulti->zCard(($rProxy ? 'PROXY#' : 'SERVER#') . $rServerID);
				}
			}

			$rResults = $rMulti->exec();
			$i = 0;

			foreach (array_keys(self::$rServers) as $rServerID) {
				if (self::$rServers[$rServerID]['server_online']) {
					$rRows[$rServerID] = ['online_clients' => $rResults[$i] ?: 0];
					$i++;
				}
			}
		}
		else if ($rProxy) {
			self::$db->query('SELECT `proxy_id`, COUNT(*) AS `online_clients` FROM `lines_live` WHERE `proxy_id` <> 0 AND `hls_end` = 0 GROUP BY `proxy_id`;');
			$rRows = self::$db->get_rows(true, 'proxy_id');
		}
		else {
			self::$db->query('SELECT `server_id`, COUNT(*) AS `online_clients` FROM `lines_live` WHERE `server_id` <> 0 AND `hls_end` = 0 GROUP BY `server_id`;');
			$rRows = self::$db->get_rows(true, 'server_id');
		}

		if (self::$rSettings['split_by'] == 'band') {
			$rServerSpeed = [];

			foreach (array_keys(self::$rServers) as $rServerID) {
				$rServerHardware = json_decode(self::$rServers[$rServerID]['server_hardware'], true);

				if (!empty($rServerHardware['network_speed'])) {
					$rServerSpeed[$rServerID] = (float) $rServerHardware['network_speed'];
				}
				else if (0 < self::$rServers[$rServerID]['network_guaranteed_speed']) {
					$rServerSpeed[$rServerID] = self::$rServers[$rServerID]['network_guaranteed_speed'];
				}
				else {
					$rServerSpeed[$rServerID] = 1000;
				}
			}

			foreach ($rRows as $rServerID => $rRow) {
				$rCurrentOutput = (int) (self::$rServers[$rServerID]['watchdog']['bytes_sent'] / 125000);
				$rRows[$rServerID]['capacity'] = (float) ($rCurrentOutput / ($rServerSpeed[$rServerID] ?: 1000));
			}
		}
		else if (self::$rSettings['split_by'] == 'maxclients') {
			foreach ($rRows as $rServerID => $rRow) {
				$rRows[$rServerID]['capacity'] = (float) ($rRow['online_clients'] / (self::$rServers[$rServerID]['total_clients'] ?: 1));
			}
		}
		else if (self::$rSettings['split_by'] == 'guar_band') {
			foreach ($rRows as $rServerID => $rRow) {
				$rCurrentOutput = (int) (self::$rServers[$rServerID]['watchdog']['bytes_sent'] / 125000);
				$rRows[$rServerID]['capacity'] = (float) ($rCurrentOutput / (self::$rServers[$rServerID]['network_guaranteed_speed'] ?: 1));
			}
		}
		else {
			foreach ($rRows as $rServerID => $rRow) {
				$rRows[$rServerID]['capacity'] = $rRow['online_clients'];
			}
		}

		file_put_contents(CACHE_TMP_PATH . $rFile, json_encode($rRows), LOCK_EX);
		return $rRows;
	}

	static public function getConnections($rServerID = NULL, $rUserID = NULL, $rStreamID = NULL)
	{
		if (self::$rSettings['redis_handler'] && !is_object(self::$redis)) {
			self::connectRedis();
		}

		if (self::$rSettings['redis_handler']) {
			if ($rServerID) {
				$rKeys = self::$redis->zRangeByScore('SERVER#' . $rServerID, '-inf', '+inf');
			}
			else if ($rUserID) {
				$rKeys = self::$redis->zRangeByScore('LINE#' . $rUserID, '-inf', '+inf');
			}
			else if ($rStreamID) {
				$rKeys = self::$redis->zRangeByScore('STREAM#' . $rStreamID, '-inf', '+inf');
			}
			else {
				$rKeys = self::$redis->zRangeByScore('LIVE', '-inf', '+inf');
			}

			if (0 < count($rKeys)) {
				return [$rKeys, array_map('igbinary_unserialize', self::$redis->mGet($rKeys))];
			}
		}
		else {
			$rWhere = [];

			if (!empty($rServerID)) {
				$rWhere[] = 't1.server_id = ' . (int) $rServerID;
			}

			if (!empty($rUserID)) {
				$rWhere[] = 't1.user_id = ' . (int) $rUserID;
			}

			$rExtra = '';

			if (0 < count($rWhere)) {
				$rExtra = 'WHERE ' . implode(' AND ', $rWhere);
			}

			$rQuery = 'SELECT t2.*,t3.*,t5.bitrate,t1.*,t1.uuid AS `uuid` FROM `lines_live` t1 LEFT JOIN `lines` t2 ON t2.id = t1.user_id LEFT JOIN `streams` t3 ON t3.id = t1.stream_id LEFT JOIN `streams_servers` t5 ON t5.stream_id = t1.stream_id AND t5.server_id = t1.server_id ' . $rExtra . ' ORDER BY t1.activity_id ASC';
			self::$db->query($rQuery);
			return self::$db->get_rows(true, 'user_id', false);
		}
	}

	static public function getEnded()
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rKeys = self::$redis->sMembers('ENDED');

		if (0 < count($rKeys)) {
			return array_map('igbinary_unserialize', self::$redis->mGet($rKeys));
		}
	}

	static public function getBouquetMap($rStreamID)
	{
		$rBouquetMap = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'bouquet_map'));
		$rReturn = $rBouquetMap[$rStreamID] ?: [];
		unset($rBouquetMap);
		return $rReturn;
	}

	static public function updateStream($rStreamID, $rForce = false)
	{
		if (!self::$rCached) {
			return false;
		}

		self::$db->query('SELECT COUNT(*) AS `count` FROM `signals` WHERE `server_id` = ? AND `cache` = 1 AND `custom_data` = ?;', self::getMainID(), json_encode(['type' => 'update_stream', 'id' => $rStreamID]));

		if (self::$db->get_row()['count'] == 0) {
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', self::getMainID(), time(), json_encode(['type' => 'update_stream', 'id' => $rStreamID]));
		}

		return true;
	}

	static public function updateStreams($rStreamIDs)
	{
		if (!self::$rCached) {
			return false;
		}

		self::$db->query('SELECT COUNT(*) AS `count` FROM `signals` WHERE `server_id` = ? AND `cache` = 1 AND `custom_data` = ?;', self::getMainID(), json_encode(['type' => 'update_streams', 'id' => $rStreamIDs]));

		if (self::$db->get_row()['count'] == 0) {
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', self::getMainID(), time(), json_encode(['type' => 'update_streams', 'id' => $rStreamIDs]));
		}

		return true;
	}

	static public function deleteLine($rUserID, $rForce = false)
	{
		self::updateLine($rUserID, $rForce);
	}

	static public function deleteLines($rUserIDs, $rForce = false)
	{
		self::updateLines($rUserIDs);
	}

	static public function updateLine($rUserID, $rForce = false)
	{
		if (!self::$rCached) {
			return false;
		}

		self::$db->query('SELECT COUNT(*) AS `count` FROM `signals` WHERE `server_id` = ? AND `cache` = 1 AND `custom_data` = ?;', self::getMainID(), json_encode(['type' => 'update_line', 'id' => $rUserID]));

		if (self::$db->get_row()['count'] == 0) {
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', self::getMainID(), time(), json_encode(['type' => 'update_line', 'id' => $rUserID]));
		}

		return true;
	}

	static public function updateLines($rUserIDs)
	{
		if (!self::$rCached) {
			return false;
		}

		self::$db->query('SELECT COUNT(*) AS `count` FROM `signals` WHERE `server_id` = ? AND `cache` = 1 AND `custom_data` = ?;', self::getMainID(), json_encode(['type' => 'update_lines', 'id' => $rUserIDs]));

		if (self::$db->get_row()['count'] == 0) {
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', self::getMainID(), time(), json_encode(['type' => 'update_lines', 'id' => $rUserIDs]));
		}

		return true;
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

	static public function getProxyFor($rServerID)
	{
		return array_rand(array_keys(self::getProxies($rServerID, false))) ?: NULL;
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

	static public function setSignal($rKey, $rData)
	{
		file_put_contents(SIGNALS_TMP_PATH . 'cache_' . md5($rKey), json_encode([$rKey, $rData]));
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

	static public function redisSignal($rPID, $rServerID, $rRTMP, $rCustomData = NULL)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rKey = 'SIGNAL#' . md5($rServerID . '#' . $rPID . '#' . $rRTMP);
		$rData = ['pid' => $rPID, 'server_id' => $rServerID, 'rtmp' => $rRTMP, 'time' => time(), 'custom_data' => $rCustomData, 'key' => $rKey];
		return self::$redis->multi()->sAdd('SIGNALS#' . $rServerID, $rKey)->set($rKey, igbinary_serialize($rData))->exec();
	}

	static public function getUserConnections($rUserIDs, $rCount = false, $rKeysOnly = false)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rRedis = self::$redis->multi();

		foreach ($rUserIDs as $rUserID) {
			$rRedis->zRevRangeByScore('LINE#' . $rUserID, '+inf', '-inf');
		}

		$rGroups = $rRedis->exec();
		$rConnectionMap = $rRedisKeys = [];

		foreach ($rGroups as $rGroupID => $rKeys) {
			if ($rCount) {
				$rConnectionMap[$rUserIDs[$rGroupID]] = count($rKeys);
			}
			else if (0 < count($rKeys)) {
				$rRedisKeys = array_merge($rRedisKeys, $rKeys);
			}
		}

		$rRedisKeys = array_unique($rRedisKeys);

		if ($rKeysOnly) {
			return $rRedisKeys;
		}

		if (!$rCount) {
			foreach (self::$redis->mGet($rRedisKeys) as $rRow) {
				$rRow = igbinary_unserialize($rRow);
				$rConnectionMap[$rRow['user_id']][] = $rRow;
			}
		}

		return $rConnectionMap;
	}

	static public function getServerConnections($rServerIDs, $rProxy = false, $rCount = false, $rKeysOnly = false)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rRedis = self::$redis->multi();

		foreach ($rServerIDs as $rServerID) {
			$rRedis->zRevRangeByScore($rProxy ? 'PROXY#' . $rServerID : 'SERVER#' . $rServerID, '+inf', '-inf');
		}

		$rGroups = $rRedis->exec();
		$rConnectionMap = $rRedisKeys = [];

		foreach ($rGroups as $rGroupID => $rKeys) {
			if ($rCount) {
				$rConnectionMap[$rServerIDs[$rGroupID]] = count($rKeys);
			}
			else if (0 < count($rKeys)) {
				$rRedisKeys = array_merge($rRedisKeys, $rKeys);
			}
		}

		$rRedisKeys = array_unique($rRedisKeys);

		if ($rKeysOnly) {
			return $rRedisKeys;
		}

		if (!$rCount) {
			foreach (self::$redis->mGet($rRedisKeys) as $rRow) {
				$rRow = igbinary_unserialize($rRow);
				$rConnectionMap[$rRow['server_id']][] = $rRow;
			}
		}

		return $rConnectionMap;
	}

	static public function getFirstConnection($rUserIDs)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rRedis = self::$redis->multi();

		foreach ($rUserIDs as $rUserID) {
			$rRedis->zRevRangeByScore('LINE#' . $rUserID, '+inf', '-inf', [
				'limit' => [0, 1]
			]);
		}

		$rGroups = $rRedis->exec();
		$rConnectionMap = $rRedisKeys = [];

		foreach ($rGroups as $rGroupID => $rKeys) {
			if (0 < count($rKeys)) {
				$rRedisKeys[] = $rKeys[0];
			}
		}

		foreach (self::$redis->mGet(array_unique($rRedisKeys)) as $rRow) {
			$rRow = igbinary_unserialize($rRow);
			$rConnectionMap[$rRow['user_id']] = $rRow;
		}

		return $rConnectionMap;
	}

	static public function getStreamConnections($rStreamIDs, $rGroup = true, $rCount = false)
	{
		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rRedis = self::$redis->multi();

		foreach ($rStreamIDs as $rStreamID) {
			$rRedis->zRevRangeByScore('STREAM#' . $rStreamID, '+inf', '-inf');
		}

		$rGroups = $rRedis->exec();
		$rConnectionMap = $rRedisKeys = [];

		foreach ($rGroups as $rGroupID => $rKeys) {
			if ($rCount) {
				$rConnectionMap[$rStreamIDs[$rGroupID]] = count($rKeys);
			}
			else if (0 < count($rKeys)) {
				$rRedisKeys = array_merge($rRedisKeys, $rKeys);
			}
		}

		if (!$rCount) {
			foreach (self::$redis->mGet(array_unique($rRedisKeys)) as $rRow) {
				$rRow = igbinary_unserialize($rRow);

				if ($rGroup) {
					$rConnectionMap[$rRow['stream_id']][] = $rRow;
				}
				else {
					$rConnectionMap[$rRow['stream_id']][$rRow['server_id']][] = $rRow;
				}
			}
		}

		return $rConnectionMap;
	}

	static public function getRedisConnections($rUserID = NULL, $rServerID = NULL, $rStreamID = NULL, $rOpenOnly = false, $rCountOnly = false, $rGroup = true, $rHLSOnly = false)
	{
		$rReturn = ($rCountOnly ? [0, 0] : []);

		if (!is_object(self::$redis)) {
			self::connectRedis();
		}

		$rUniqueUsers = [];
		$rUserID = (0 < (int) $rUserID ? (int) $rUserID : NULL);
		$rServerID = (0 < (int) $rServerID ? (int) $rServerID : NULL);
		$rStreamID = (0 < (int) $rStreamID ? (int) $rStreamID : NULL);

		if ($rUserID) {
			$rKeys = self::$redis->zRangeByScore('LINE#' . $rUserID, '-inf', '+inf');
		}
		else if ($rStreamID) {
			$rKeys = self::$redis->zRangeByScore('STREAM#' . $rStreamID, '-inf', '+inf');
		}
		else if ($rServerID) {
			$rKeys = self::$redis->zRangeByScore('SERVER#' . $rServerID, '-inf', '+inf');
		}
		else {
			$rKeys = self::$redis->zRangeByScore('LIVE', '-inf', '+inf');
		}

		if (0 < count($rKeys)) {
			foreach (self::$redis->mGet(array_unique($rKeys)) as $rRow) {
				$rRow = igbinary_unserialize($rRow);
				if ($rServerID && ($rServerID != $rRow['server_id'])) {
					continue;
				}
				if ($rStreamID && ($rStreamID != $rRow['stream_id'])) {
					continue;
				}
				if ($rUserID && ($rUserID != $rRow['user_id'])) {
					continue;
				}
				if ($rHLSOnly && ($rRow['container'] == 'hls')) {
					continue;
				}

				$rUUID = $rRow['user_id'] ?: ($rRow['hmac_id'] . '_' . $rRow['hmac_identifier']);

				if ($rCountOnly) {
					$rReturn[0]++;
					$rUniqueUsers[] = $rUUID;
				}
				else if ($rGroup) {
					if (!isset($rReturn[$rUUID])) {
						$rReturn[$rUUID] = [];
					}

					$rReturn[$rUUID][] = $rRow;
				}
				else {
					$rReturn[] = $rRow;
				}
			}
		}

		if ($rCountOnly) {
			$rReturn[1] = count(array_unique($rUniqueUsers));
		}

		return $rReturn;
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

	static public function checkCompatibility($rData)
	{
		if (!is_array($rData)) {
			$rData = json_decode($rData, true);
		}

		$rAudioCodecs = ['aac', 'libfdk_aac', 'opus', 'vorbis', 'pcm_s16le', 'mp2', 'mp3', 'flac', NULL];
		$rVideoCodecs = ['h264', 'vp8', 'vp9', 'ogg', 'av1', NULL];

		if (self::$rSettings['player_allow_hevc']) {
			$rVideoCodecs[] = 'hevc';
			$rVideoCodecs[] = 'h265';
			$rAudioCodecs[] = 'ac3';
		}
		return ($rData['codecs']['audio']['codec_name'] || $rData['codecs']['video']['codec_name']) && in_array(strtolower($rData['codecs']['audio']['codec_name']), $rAudioCodecs) && in_array(strtolower($rData['codecs']['video']['codec_name']), $rVideoCodecs);
	}

	static public function getNearest($arr, $search)
	{
		$closest = NULL;

		foreach ($arr as $item) {
			if (($closest === NULL) || (abs($item - $search) < abs($search - $closest))) {
				$closest = $item;
			}
		}

		return $closest;
	}

	static public function submitPanelLogs()
	{
		ini_set('default_socket_timeout', 60);
		self::$db->query('SELECT `type`, `log_message`, `log_extra`, `line`, `date` FROM `panel_logs` WHERE `type` <> \'epg\' GROUP BY CONCAT(`type`, `log_message`, `log_extra`) ORDER BY `date` DESC LIMIT 1000;');
		Xcms\Functions::sendlicenseerror(self::$db->get_rows());
		self::$db->query('TRUNCATE `panel_logs`;');
	}

	static public function confirmIDs($rIDs)
	{
		$rReturn = [];

		foreach ($rIDs as $rID) {
			if (0 < (int) $rID) {
				$rReturn[] = $rID;
			}
		}

		return $rReturn;
	}

	static public function getTSInfo($rFilename)
	{
		return json_decode(shell_exec(BIN_PATH . 'tsinfo ' . escapeshellarg($rFilename)), true);
	}

	static public function getEPG($rStreamID, $rStartDate = NULL, $rFinishDate = NULL, $rByID = false)
	{
		$rReturn = [];
		$rData = (file_exists(EPG_PATH . 'stream_' . $rStreamID) ? igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStreamID)) : []);

		foreach ($rData as $rItem) {
			if (!$rStartDate || (($rStartDate < $rItem['end']) && ($rItem['start'] < $rFinishDate))) {
				if ($rByID) {
					$rReturn[$rItem['id']] = $rItem;
				}
				else {
					$rReturn[] = $rItem;
				}
			}
		}

		return $rReturn;
	}

	static public function getEPGs($rStreamIDs, $rStartDate = NULL, $rFinishDate = NULL)
	{
		$rReturn = [];

		foreach ($rStreamIDs as $rStreamID) {
			$rReturn[$rStreamID] = self::getEPG($rStreamID, $rStartDate, $rFinishDate);
		}

		return $rReturn;
	}

	static public function getProgramme($rStreamID, $rProgrammeID)
	{
		$rData = self::getEPG($rStreamID, NULL, NULL, true);

		if (isset($rData[$rProgrammeID])) {
			return $rData[$rProgrammeID];
		}

		return NULL;
	}

	static public function getNetwork($rInterface = NULL)
	{
		$rReturn = [];

		if (file_exists(LOGS_TMP_PATH . 'network')) {
			$rNetwork = json_decode(file_get_contents(LOGS_TMP_PATH . 'network'), true);

			foreach ($rNetwork as $rLine) {
				if ($rInterface && ($rInterface != $rLine[0])) {
					continue;
				}
				if (($rLine[0] == 'lo') || (!$rInterface && (substr($rLine[0], 0, 4) == 'bond'))) {
					continue;
				}

				$rReturn[$rLine[0]] = ['in_bytes' => (int) ($rLine[1] / 2), 'in_packets' => $rLine[2], 'in_errors' => $rLine[3], 'out_bytes' => (int) ($rLine[4] / 2), 'out_packets' => $rLine[5], 'out_errors' => $rLine[6]];
			}
		}

		return $rReturn;
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
}

?>