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

class Database
{
	public $result = null;
	public $dbh = null;
	public $connected = false;

	public function __construct()
	{
		$this->dbh = false;
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
		$this->dbh->exec('SET NAMES utf8;');
		return true;
	}

	public function db_explicit_connect($rHost, $rPort, $rDatabase, $rUsername, $rPassword)
	{
		try {
			$this->dbh = new PDO('mysql:host=' . $rHost . ';port=' . $rPort . ';dbname=' . $rDatabase, $rUsername, $rPassword);

			if (!$this->dbh) {
				return false;
			}
		}
		catch (PDOException $e) {
			return false;
		}

		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->connected = true;
		$this->dbh->exec('SET NAMES utf8;');
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
		return $this->dbh->quote($string);
	}

	public function num_fields()
	{
		$mysqli_num_fields = $this->result->columnCount();
		return empty($mysqli_num_fields) ? 0 : $mysqli_num_fields;
	}

	public function last_insert_id()
	{
		$mysql_insert_id = $this->dbh->lastInsertId();
		return empty($mysql_insert_id) ? 0 : $mysql_insert_id;
	}

	public function num_rows()
	{
		$mysqli_num_rows = $this->result->rowCount();
		return empty($mysqli_num_rows) ? 0 : $mysqli_num_rows;
	}
}

class XCMS
{
	static public $db = null;
	static public $rRequest = [];
	static public $rSettings = [];
	static public $rServers = [];
	static public $rBlockedISP = [];
	static public $rBouquets = [];
	static public $rCategories = [];
	static public $rAllowedIPs = [];
	static public $rCached = null;

	static public function init()
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

		if (self::$db->connected) {
			self::$rSettings = self::getSettings();
			self::$rBlockedISP = self::getBlockedISP();
			self::$rBouquets = self::getBouquets();
			self::$rCategories = self::getCategories();
			self::$rAllowedIPs = self::getAllowedIPs();

			if (PLATFORM == 'xcms') {
				self::$rCached = self::$rSettings['enable_cache'] ?: false;
			}
			else {
				self::$rCached = false;
			}

			if (!empty(self::$rSettings['default_timezone'])) {
				date_default_timezone_set(self::$rSettings['default_timezone']);
			}

			self::$rServers = self::getServers();
		}
	}

	static public function serialize($rData)
	{
		if (function_exists('igbinary_serialize') || (PLATFORM == 'xcms')) {
			return igbinary_serialize($rData);
		}
		else {
			return serialize($rData);
		}
	}

	static public function unserialize($rData)
	{
		if (function_exists('igbinary_unserialize') || (PLATFORM == 'xcms')) {
			return igbinary_unserialize($rData);
		}
		else {
			return unserialize($rData);
		}
	}

	static public function setCache($rCache, $rData)
	{
		$rData = self::serialize($rData);
		file_put_contents(TMP_PATH . $rCache, $rData, LOCK_EX);
	}

	static public function getCache($rCache, $rSeconds)
	{
		if (file_exists(TMP_PATH . $rCache)) {
			if ((time() - filemtime(TMP_PATH . $rCache)) < $rSeconds) {
				$rData = file_get_contents(TMP_PATH . $rCache);
				return self::unserialize($rData);
			}
		}

		return false;
	}

	static public function getBouquets()
	{
		$rCache = self::getCache('bouquets', 60);

		if (!empty($rCache)) {
			return $rCache;
		}

		$rOutput = [];

		if (PLATFORM != 'xcms') {
			$rStreamMap = [];
			self::$db->query('SELECT `id`, `type` FROM streams WHERE `type` IN (1,2,3,4);');

			foreach (self::$db->get_rows() as $rStream) {
				switch ($rStream['type']) {
				case '1':
				case '3':
					$rStreamMap[(int) $rStream['id']] = 'channels';
					break;
				case '2':
					$rStreamMap[(int) $rStream['id']] = 'movies';
					break;
				case '4':
					$rStreamMap[(int) $rStream['id']] = 'radios';
					break;
				}
			}
		}

		self::$db->query('SELECT *, IF(`bouquet_order` > 0, `bouquet_order`, 999) AS `order` FROM `bouquets` ORDER BY `order` ASC;');

		foreach (self::$db->get_rows(true, 'id') as $rID => $rChannels) {
			$rOutput[$rID]['id'] = $rID;
			$rOutput[$rID]['bouquet_name'] = $rChannels['bouquet_name'];
			$rOutput[$rID]['order'] = $rChannels['order'];

			if (PLATFORM == 'xcms') {
				$rOutput[$rID]['streams'] = array_merge(json_decode($rChannels['bouquet_channels'], true), json_decode($rChannels['bouquet_movies'], true), json_decode($rChannels['bouquet_radios'], true));
				$rOutput[$rID]['series'] = json_decode($rChannels['bouquet_series'], true);
				$rOutput[$rID]['channels'] = json_decode($rChannels['bouquet_channels'], true);
				$rOutput[$rID]['movies'] = json_decode($rChannels['bouquet_movies'], true);
				$rOutput[$rID]['radios'] = json_decode($rChannels['bouquet_radios'], true);
			}
			else {
				$rOutput[$rID]['streams'] = json_decode($rChannels['bouquet_channels'], true);
				$rOutput[$rID]['series'] = json_decode($rChannels['bouquet_series'], true);
				$rOutput[$rID]['channels'] = [];
				$rOutput[$rID]['movies'] = [];
				$rOutput[$rID]['radios'] = [];

				foreach ($rOutput[$rID]['streams'] as $rStreamID) {
					$rType = $rStreamMap[(int) $rStreamID] ?: 'channels';
					$rOutput[$rID][$rType][] = (int) $rStreamID;
				}
			}
		}

		self::setCache('bouquets', $rOutput);
		return $rOutput;
	}

	static public function getStream($rID)
	{
		if (PLATFORM == 'xcms') {
			self::$db->query('SELECT * FROM `streams` WHERE `id` = ?;', $rID);
		}
		else {
			self::$db->query('SELECT * FROM `streams` LEFT JOIN `webplayer_data` ON `webplayer_data`.`stream_id` = `streams`.`id` WHERE `streams`.`id` = ?;', $rID);
		}

		if (self::$db->num_rows() == 1) {
			$rRow = self::$db->get_row();
			if ((PLATFORM != 'xcms') && $rRow['title']) {
				$rRow['stream_display_name'] = $rRow['title'];
			}

			return $rRow;
		}

		return NULL;
	}

	static public function getCategories($rType = NULL)
	{
		if (is_string($rType)) {
			if (PLATFORM == 'xcms') {
				self::$db->query('SELECT t1.* FROM `streams_categories` t1 WHERE t1.category_type = ? GROUP BY t1.id ORDER BY t1.cat_order ASC', $rType);
			}
			else {
				self::$db->query('SELECT t1.* FROM `stream_categories` t1 WHERE t1.category_type = ? GROUP BY t1.id ORDER BY t1.cat_order ASC', $rType);
			}

			return 0 < self::$db->num_rows() ? self::$db->get_rows(true, 'id') : [];
		}
		else {
			$rCache = self::getCache('categories', 20);

			if (!empty($rCache)) {
				return $rCache;
			}

			if (PLATFORM == 'xcms') {
				self::$db->query('SELECT t1.* FROM `streams_categories` t1 ORDER BY t1.cat_order ASC');
			}
			else {
				self::$db->query('SELECT t1.* FROM `stream_categories` t1 ORDER BY t1.cat_order ASC');
			}

			$rCategories = (0 < self::$db->num_rows() ? self::$db->get_rows(true, 'id') : []);
			self::setCache('categories', $rCategories);
			return $rCategories;
		}
	}

	static public function getAllowedIPs()
	{
		if (!empty(self::$rAllowedIPs)) {
			return self::$rAllowedIPs;
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
		}

		if (!empty(self::$rSettings['allowed_ips_admin'])) {
			$rIPs = array_merge($rIPs, explode(',', self::$rSettings['allowed_ips_admin']));
		}

		return array_unique($rIPs);
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

	static public function getBlockedISP()
	{
		$rCache = self::getCache('blocked_isp', 20);

		if ($rCache !== false) {
			return $rCache;
		}

		if (PLATFORM == 'xcms') {
			self::$db->query('SELECT id, isp, blocked FROM `blocked_isps`');
		}
		else {
			self::$db->query('SELECT id, isp, blocked FROM `isp_addon`');
		}

		$rOutput = self::$db->get_rows();
		self::setCache('blocked_isp', $rOutput);
		return $rOutput;
	}

	static public function getServers()
	{
		$rCache = self::getCache('servers', 10);

		if (!empty($rCache)) {
			return $rCache;
		}

		if (empty($_SERVER['REQUEST_SCHEME'])) {
			$_SERVER['REQUEST_SCHEME'] = 'http';
		}

		if (PLATFORM == 'xcms') {
			self::$db->query('SELECT * FROM `servers`');
		}
		else {
			self::$db->query('SELECT * FROM `streaming_servers`');
			$rEnableHTTPS = json_decode(self::$rSettings['use_https'], true) ?: [];
		}

		$rServers = [];
		$rOnlineStatus = [1];

		foreach (self::$db->get_rows() as $rRow) {
			if (empty($rRow['domain_name'])) {
				$rURL = escapeshellcmd($rRow['server_ip']);
			}
			else {
				$rURL = str_replace(['http://', '/', 'https://'], '', escapeshellcmd(explode(',', $rRow['domain_name'])[0]));
			}

			if (PLATFORM == 'xcms') {
				if ($rRow['enable_https'] == 1) {
					$rProtocol = 'https';
				}
				else {
					$rProtocol = 'http';
				}
			}
			else {
				if (in_array($rRow['id'], $rEnableHTTPS)) {
					$rProtocol = 'https';
				}
				else {
					$rProtocol = 'http';
				}

				$rRow['enable_https'] = in_array($rRow['id'], $rEnableHTTPS);
			}

			$rPort = ($rProtocol == 'http' ? (int) $rRow['http_broadcast_port'] : (int) $rRow['https_broadcast_port']);
			$rRow['server_protocol'] = $rProtocol;
			$rRow['request_port'] = $rPort;
			$rRow['site_url'] = $rProtocol . '://' . $rURL . ':' . $rPort . '/';
			$rRow['http_url'] = 'http://' . $rURL . ':' . (int) $rRow['http_broadcast_port'] . '/';
			$rRow['https_url'] = 'https://' . $rURL . ':' . (int) $rRow['https_broadcast_port'] . '/';
			$rRow['domains'] = ['protocol' => $rProtocol, 'port' => $rPort, 'urls' => array_filter(array_map('escapeshellcmd', explode(',', $rRow['domain_name'])))];

			if (is_numeric($rRow['parent_id'])) {
				$rRow['parent_id'] = [(int) $rRow['parent_id']];
			}
			else {
				$rRow['parent_id'] = array_map('intval', json_decode($rRow['parent_id'], true));
			}

			$rServers[(int) $rRow['id']] = $rRow;
		}

		self::setCache('servers', $rServers);
		return $rServers;
	}

	static public function getSettings()
	{
		$rCache = self::getCache('settings', 20);

		if (!empty($rCache)) {
			return $rCache;
		}

		$rOutput = [];
		self::$db->query('SELECT * FROM `settings`');
		$rRows = self::$db->get_row();

		foreach ($rRows as $rKey => $rValue) {
			$rOutput[$rKey] = $rValue;
		}

		$rOutput['allow_countries'] = json_decode($rOutput['allow_countries'], true);
		self::setCache('settings', $rOutput);
		return $rOutput;
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

	static public function getPageName()
	{
		return strtolower(basename(get_included_files()[0], '.php'));
	}

	static public function getProxyFor($rServerID)
	{
		return array_rand(array_keys(self::getProxies($rServerID, false))) ?: NULL;
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

	static public function getDomainName($rForceSSL = false)
	{
		$rDomainName = NULL;
		$rKey = ($rForceSSL ? 'https_url' : 'site_url');

		if (self::$rSettings['use_mdomain_in_lists'] == 1) {
			if ((PLATFORM == 'xcms') && self::$rServers[SERVER_ID]['enable_proxy']) {
				$rProxyID = self::getProxyFor(SERVER_ID);

				if ($rProxyID) {
					$rDomainName = self::$rServers[$rProxyID][$rKey];
				}
			}
			else {
				$rDomainName = self::$rServers[SERVER_ID][$rKey];
			}
		}
		else {
			list($rRequestedHost, $rRequestedPort) = explode(':', $_SERVER['HTTP_HOST']);
			if ((PLATFORM == 'xcms') && ($rRequestedHost == self::$rServers[SERVER_ID]['server_ip']) && self::$rServers[SERVER_ID]['enable_proxy']) {
				$rProxyID = self::getProxyFor(SERVER_ID);

				if ($rProxyID) {
					$rDomainName = self::$rServers[$rProxyID][$rKey];
				}
			}
			else if ($rForceSSL) {
				$rDomainName = 'https://' . $rRequestedHost . ':' . self::$rServers[SERVER_ID]['https_broadcast_port'] . '/';
			}
			else {
				$rDomainName = self::$rServers[SERVER_ID]['server_protocol'] . '://' . $rRequestedHost . ':' . self::$rServers[SERVER_ID]['request_port'] . '/';
			}
		}

		return $rDomainName;
	}

	static public function getSubtitles($rStreamID, $rSubtitles)
	{
		if (PLATFORM != 'xcms') {
			return [];
		}

		global $rUserInfo;
		$rDomainName = self::getDomainName((!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) || ($_SERVER['SERVER_PORT'] == 443));
		$rReturn = [];

		if (is_array($rSubtitles)) {
			$i = 0;

			foreach ($rSubtitles as $rSubtitle) {
				$rLanguage = NULL;

				foreach (array_keys($rSubtitle['tags']) as $rKey) {
					if (in_array(strtoupper(explode('-', $rKey)[0]), ['BPS' => true, 'DURATION' => true, 'NUMBER_OF_FRAMES' => true, 'NUMBER_OF_BYTES' => true])) {
						$rLanguage = explode('-', $rKey, 2)[1];
						break;
					}

					if ($rKey == 'language') {
						$rLanguage = $rSubtitle['tags'][$rKey];
						break;
					}
				}

				if (!$rLanguage) {
					$rLanguage = 'Subtitle #' . ($i + 1);
				}

				$rReturn[] = ['label' => $rLanguage, 'file' => $rDomainName . 'subtitle/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rStreamID . '?sub_id=' . $i . '&webvtt=1', 'kind' => 'subtitles'];
				$i++;
			}
		}

		return $rReturn;
	}

	static public function convertTypes($rTypes)
	{
		$rReturn = [];
		$rTypeInt = ['live' => 1, 'movie' => 2, 'created_live' => 3, 'radio_streams' => 4, 'series' => 5];

		foreach ($rTypes as $rType) {
			$rReturn[] = $rTypeInt[$rType];
		}

		return $rReturn;
	}

	static public function getOrderedCategories($rCategories, $rType = 'movie')
	{
		$rReturn = [];

		foreach (self::getCategories($rType) as $rCategory) {
			if (in_array($rCategory['id'], $rCategories)) {
				$rReturn[] = ['title' => $rCategory['category_name'], 'id' => $rCategory['id'], 'cat_order' => $rCategory['cat_order']];
			}
		}

		$rTitle = array_column($rReturn, 'cat_order');
		array_multisort($rTitle, SORT_ASC, $rReturn);

		if ($rType != 'live') {
			array_unshift($rReturn, ['id' => '0', 'cat_order' => 0, 'title' => 'All Genres']);
		}
		else {
			array_unshift($rReturn, ['id' => '0', 'cat_order' => 0, 'title' => 'Most Popular']);
		}

		return $rReturn;
	}

	static public function sortChannels($rChannels)
	{
		if (PLATFORM != 'xcms') {
			return $rChannels;
		}
		if ((0 < count($rChannels)) && file_exists(CACHE_TMP_PATH . 'channel_order') && (self::$rSettings['channel_number_type'] != 'bouquet')) {
			$rOrder = self::unserialize(file_get_contents(CACHE_TMP_PATH . 'channel_order'));
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

	static public function getUserStreams($rUserInfo, $rTypes = [], $rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL, $rPicking = [], $rStart = 0, $rLimit = 10, $rIDs = false)
	{
		self::verifyLicense();
		global $db;
		$rAdded = false;
		$rChannels = [];

		foreach ($rTypes as $rType) {
			switch ($rType) {
			case 'live':
			case 'created_live':
				if ($rAdded) {
					break;
				}

				$rChannels = array_merge($rChannels, $rUserInfo['live_ids']);
				$rAdded = true;
				break;
			case 'movie':
				$rChannels = array_merge($rChannels, $rUserInfo['vod_ids']);
				break;
			case 'radio_streams':
				$rChannels = array_merge($rChannels, $rUserInfo['radio_ids']);
				break;
			case 'series':
				$rChannels = array_merge($rChannels, $rUserInfo['episode_ids']);
				break;
			}
		}

		$rStreams = [
			'count'   => 0,
			'streams' => []
		];
		$rKey = $rStart + 1;
		$rWhereV = $rWhere = [];

		if (self::$rSettings['player_hide_incompatible']) {
			$rWhere[] = '(SELECT MAX(`compatible`) FROM `streams_servers` WHERE `streams_servers`.`stream_id` = `streams`.`id` LIMIT 1) = 1';
		}

		if (0 < count($rTypes)) {
			$rWhere[] = '`type` IN (' . implode(',', self::convertTypes($rTypes)) . ')';
		}

		if (!empty($rCategoryID)) {
			if (PLATFORM == 'xcms') {
				$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
			}
			else {
				$rWhere[] = '`category_id` = ?';
			}

			$rWhereV[] = $rCategoryID;
		}
		else if (in_array('live', $rTypes) && empty($rSearchBy)) {
			$rStart = 0;
			$rLimit = 200;
			$rLiveIDs = self::unserialize(file_get_contents(CONTENT_PATH . 'live_popular'));
			if ($rLiveIDs && (0 < count($rLiveIDs))) {
				$rWhere[] = '`id` IN (' . implode(',', array_map('intval', $rLiveIDs)) . ')';
			}
		}

		if ($rPicking['filter']) {
			switch ($rPicking['filter']) {
			case 'all':
				break;
			case 'timeshift':
				$rWhere[] = '`tv_archive_duration` > 0 AND `tv_archive_server_id` > 0';
				break;
			}
		}

		$rChannels = self::sortChannels($rChannels);

		if (!empty($rFav)) {
			$rFavouriteIDs = [];

			foreach ($rTypes as $rType) {
				foreach ($rUserInfo['fav_channels'][$rType] as $rStreamID) {
					$rFavouriteIDs[] = (int) $rStreamID;
				}
			}

			$rChannels = array_intersect($rFavouriteIDs, $rChannels);
		}

		if (!empty($rSearchBy)) {
			$rWhere[] = '`stream_display_name` LIKE ?';
			$rWhereV[] = '%' . $rSearchBy . '%';
		}

		if (is_array($rPicking['year_range'])) {
			$rWhere[] = '(`year` >= ? AND `year` <= ?)';
			$rWhereV[] = $rPicking['year_range'][0];
			$rWhereV[] = $rPicking['year_range'][1];
		}

		if (is_array($rPicking['rating_range'])) {
			$rWhere[] = '(`rating` >= ? AND `rating` <= ?)';
			$rWhereV[] = $rPicking['rating_range'][0];
			$rWhereV[] = $rPicking['rating_range'][1];
		}

		$rChannels = self::confirmIDs($rChannels);

		if (count($rChannels) == 0) {
			return $rStreams;
		}

		$rWhere[] = '`id` IN (' . implode(',', array_map('intval', $rChannels)) . ')';
		$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);

		switch ($rOrderBy) {
		case 'name':
			uasort($rStreams['streams'], 'sortArrayStreamName');
			$rOrder = '`stream_display_name` ASC';
			break;
		case 'top':
		case 'rating':
			$rOrder = '`rating` DESC';
			break;
		case 'added':
			$rOrder = '`added` DESC';
			break;
		case 'release':
			$rOrder = '`year` DESC, `stream_display_name` ASC';
			break;
		case 'number':
		default:
			if ((self::$rSettings['channel_number_type'] != 'manual') && (0 < count($rChannels))) {
				$rOrder = 'FIELD(id,' . implode(',', $rChannels) . ')';
			}
			else {
				$rOrder = '`order` ASC';
			}

			break;
		}

		if (0 < count($rChannels)) {
			if (PLATFORM == 'xcms') {
				$db->query('SELECT COUNT(`id`) AS `count` FROM `streams` ' . $rWhereString . ';', ...$rWhereV);
			}
			else {
				$db->query('SELECT COUNT(`id`) AS `count` FROM `streams` LEFT JOIN `webplayer_data` ON `webplayer_data`.`stream_id` = `streams`.`id` ' . $rWhereString . ';', ...$rWhereV);
			}

			$rStreams['count'] = $db->get_row()['count'];

			if ($rLimit) {
				if ($rIDs) {
					if (PLATFORM == 'xcms') {
						$rQuery = 'SELECT `id` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
					}
					else {
						$rQuery = 'SELECT `id` FROM `streams` LEFT JOIN `webplayer_data` ON `webplayer_data`.`stream_id` = `streams`.`id` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
					}
				}
				else if (PLATFORM == 'xcms') {
					$rQuery = 'SELECT (SELECT `stream_info` FROM `streams_servers` WHERE `streams_servers`.`pid` IS NOT NULL AND `streams_servers`.`stream_id` = `streams`.`id` LIMIT 1) AS `stream_info`, `id`, `stream_display_name`, `movie_properties`, `target_container`, `added`, `year`, `category_id`, `channel_id`, `epg_id`, `tv_archive_duration`, `stream_icon`, `allow_record`, `type` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
				}
				else {
					$rQuery = 'SELECT (SELECT `stream_info` FROM `streams_sys` WHERE `streams_sys`.`pid` IS NOT NULL AND `streams_sys`.`stream_id` = `streams`.`id` LIMIT 1) AS `stream_info`, `id`, `stream_display_name`, `title`, `movie_propeties`, `target_container`, `added`, `year`, `category_id`, `channel_id`, `epg_id`, `tv_archive_duration`, `stream_icon`, `allow_record`, `type` FROM `streams` LEFT JOIN `webplayer_data` ON `webplayer_data`.`stream_id` = `streams`.`id` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
				}
			}
			else if ($rIDs) {
				if (PLATFORM == 'xcms') {
					$rQuery = 'SELECT `id` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
				}
				else {
					$rQuery = 'SELECT `id` FROM `streams` LEFT JOIN `webplayer_data` ON `webplayer_data`.`stream_id` = `streams`.`id` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
				}
			}
			else if (PLATFORM == 'xcms') {
				$rQuery = 'SELECT (SELECT `stream_info` FROM `streams_servers` WHERE `streams_servers`.`pid` IS NOT NULL AND `streams_servers`.`stream_id` = `streams`.`id` LIMIT 1) AS `stream_info`, `id`, `stream_display_name`, `movie_properties`, `target_container`, `added`, `year`, `category_id`, `channel_id`, `epg_id`, `tv_archive_duration`, `stream_icon`, `allow_record`, `type` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
			}
			else {
				$rQuery = 'SELECT (SELECT `stream_info` FROM `streams_sys` WHERE `streams_sys`.`pid` IS NOT NULL AND `streams_sys`.`stream_id` = `streams`.`id` LIMIT 1) AS `stream_info`, `id`, `stream_display_name`, `title`, `movie_propeties`, `target_container`, `added`, `year`, `category_id`, `channel_id`, `epg_id`, `tv_archive_duration`, `stream_icon`, `allow_record`, `type` FROM `streams` LEFT JOIN `webplayer_data` ON `webplayer_data`.`stream_id` = `streams`.`id` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
			}

			$db->query($rQuery, ...$rWhereV);
			$rRows = $db->get_rows();
		}
		else {
			$rRows = [];
		}

		if ($rIDs) {
			return $rRows;
		}
		else {
			foreach ($rRows as $rStream) {
				$rStream['number'] = $rKey;

				if (PLATFORM == 'xcms') {
					if (in_array($rCategoryID, json_decode($rStream['category_id'], true))) {
						$rStream['category_id'] = $rCategoryID;
					}
					else {
						$rStream['category_id'] = json_decode($rStream['category_id'], true)[0];
					}
				}

				$rStream['stream_info'] = json_decode($rStream['stream_info'], true);
				$rStreams['streams'][$rStream['id']] = $rStream;
				$rKey++;
			}

			return $rStreams;
		}
	}

	static public function getUserSeries($rUserInfo, $rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL, $rPicking = [], $rStart = 0, $rLimit = 10, $rLastID = NULL)
	{
		self::verifyLicense();
		global $db;
		$rSeries = $rUserInfo['series_ids'];
		$rStreams = [
			'count'   => 0,
			'streams' => []
		];
		$rKey = $rStart + 1;
		$rWhereV = $rWhere = [];

		if (self::$rSettings['player_hide_incompatible']) {
			$rWhere[] = '(SELECT MAX(`compatible`) FROM `streams_servers` LEFT JOIN `streams_episodes` ON `streams_episodes`.`stream_id` = `streams_servers`.`stream_id` WHERE `streams_episodes`.`series_id` = `streams_series`.`id`) = 1';
		}

		if (!empty($rCategoryID)) {
			if (PLATFORM == 'xcms') {
				$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
			}
			else {
				$rWhere[] = '`category_id` = ?';
			}

			$rWhereV[] = $rCategoryID;
		}

		if (is_array($rPicking['year_range'])) {
			if (PLATFORM == 'xcms') {
				$rWhere[] = '(`year` >= ? AND `year` <= ?)';
			}
			else {
				$rWhere[] = '(LEFT(`releaseDate`, 4) >= ? AND LEFT(`releaseDate`, 4) <= ?)';
			}

			$rWhereV[] = $rPicking['year_range'][0];
			$rWhereV[] = $rPicking['year_range'][1];
		}

		if (is_array($rPicking['rating_range'])) {
			$rWhere[] = '(`rating` >= ? AND `rating` <= ?)';
			$rWhereV[] = $rPicking['rating_range'][0];
			$rWhereV[] = $rPicking['rating_range'][1];
		}

		if (!empty($rSearchBy)) {
			$rWhere[] = '`title` LIKE ?';
			$rWhereV[] = '%' . $rSearchBy . '%';
		}

		$rSeries = self::confirmIDs($rSeries);

		if (count($rSeries) == 0) {
			return $rStreams;
		}

		$rWhere[] = '`id` IN (' . implode(',', array_map('intval', $rSeries)) . ')';
		$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);

		switch ($rOrderBy) {
		case 'name':
			uasort($rStreams['streams'], 'sortArrayStreamName');
			$rOrder = '`title` ASC';
			break;
		case 'top':
		case 'rating':
			$rOrder = '`rating` DESC';
			break;
		case 'added':
			if (PLATFORM == 'xcms') {
				$rOrder = '`last_modified` DESC';
			}
			else {
				$rOrder = '`id` DESC';
			}

			break;
		case 'release':
			if (PLATFORM == 'xcms') {
				$rOrder = '`release_date` DESC';
			}
			else {
				$rOrder = '`releaseDate` DESC';
			}

			break;
		case 'number':
		default:
			if ((PLATFORM == 'xcms') && XCMS::$rSettings['vod_sort_newest']) {
				$rOrder = '`last_modified` DESC';
			}
			else {
				$rOrder = 'FIELD(id,' . implode(',', $rSeries) . ')';
			}

			break;
		}

		if (0 < count($rSeries)) {
			if (PLATFORM == 'xcms') {
				$db->query('SELECT COUNT(`id`) AS `count` FROM `streams_series` ' . $rWhereString . ';', ...$rWhereV);
			}
			else {
				$db->query('SELECT COUNT(`id`) AS `count` FROM `series` ' . $rWhereString . ';', ...$rWhereV);
			}

			$rStreams['count'] = $db->get_row()['count'];

			if ($rLimit) {
				if (PLATFORM == 'xcms') {
					$rQuery = 'SELECT `id`, `title`, `category_id`, `cover`, `rating`, `release_date`, `last_modified`, `tmdb_id`, `seasons`, `backdrop_path`, `year` FROM `streams_series` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
				}
				else {
					$rQuery = 'SELECT `id`, `title`, `category_id`, `cover`, `rating`, `releaseDate`, `tmdb_id`, `seasons`, `backdrop_path` FROM `series` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
				}
			}
			else if (PLATFORM == 'xcms') {
				$rQuery = 'SELECT `id`, `title`, `category_id`, `cover`, `rating`, `release_date`, `last_modified`, `tmdb_id`, `seasons`, `backdrop_path`, `year` FROM `streams_series` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
			}
			else {
				$rQuery = 'SELECT `id`, `title`, `category_id`, `cover`, `rating`, `releaseDate`, `tmdb_id`, `seasons`, `backdrop_path` FROM `series` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
			}

			$db->query($rQuery, ...$rWhereV);
			$rRows = $db->get_rows();
		}
		else if ($rLastID) {
			return NULL;
		}
		else {
			$rRows = [];
		}

		foreach ($rRows as $rStream) {
			$rStream['number'] = $rKey;

			if (PLATFORM == 'xcms') {
				if (in_array($rCategoryID, json_decode($rStream['category_id'], true))) {
					$rStream['category_id'] = $rCategoryID;
				}
				else {
					$rStream['category_id'] = json_decode($rStream['category_id'], true)[0];
				}
			}

			$rStreams['streams'][$rStream['id']] = $rStream;
			$rKey++;
		}

		return $rStreams;
	}

	static public function getSerie($rID)
	{
		if (PLATFORM == 'xcms') {
			self::$db->query('SELECT * FROM `streams_series` WHERE `id` = ?;', $rID);
		}
		else {
			self::$db->query('SELECT `series`.*, `webplayer_data`.`similar` FROM `series` LEFT JOIN `webplayer_data` ON `webplayer_data`.`series_id` = `series`.`id` WHERE `id` = ?;', $rID);
		}

		if (self::$db->num_rows() == 1) {
			return self::$db->get_row();
		}

		return NULL;
	}

	static public function getIPInfo($rIP)
	{
		if (PLATFORM != 'xcms') {
			return false;
		}

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

	static public function getISP($rIP)
	{
		if (PLATFORM != 'xcms') {
			return false;
		}

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
		if (PLATFORM != 'xcms') {
			return false;
		}

		foreach (self::$rBlockedISP as $rISP) {
			if (strtolower($rConISP) == strtolower($rISP['isp'])) {
				return (int) $rISP['blocked'];
			}
		}

		return 0;
	}

	static public function getUserInfo($rUserID = NULL, $rUsername = NULL, $rPassword = NULL, $rGetChannelIDs = false, $rGetConnections = false, $rIP = '')
	{
		self::verifyLicense();
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
				$rUserInfo = self::unserialize(file_get_contents(LINES_TMP_PATH . 'line_i_' . $rUserID));
			}
		}
		else {
			if (empty($rPassword) && empty($rUserID) && (strlen($rUsername) == 32)) {
				if (PLATFORM == 'xcms') {
					self::$db->query('SELECT * FROM `lines` WHERE `is_mag` = 0 AND `is_e2` = 0 AND `access_token` = ? AND LENGTH(`access_token`) = 32', $rUsername);
				}
				else {
					return false;
				}
			}
			else if (!empty($rUsername) && !empty($rPassword)) {
				if (PLATFORM == 'xcms') {
					self::$db->query('SELECT * FROM `lines` WHERE `username` = ? AND `password` = ? LIMIT 1', $rUsername, $rPassword);
				}
				else {
					self::$db->query('SELECT * FROM `users` WHERE `username` = ? AND `password` = ? LIMIT 1', $rUsername, $rPassword);
				}
			}
			else if (!empty($rUserID)) {
				if (PLATFORM == 'xcms') {
					self::$db->query('SELECT * FROM `lines` WHERE `id` = ?', $rUserID);
				}
				else {
					self::$db->query('SELECT * FROM `users` WHERE `id` = ?', $rUserID);
				}
			}
			else {
				return false;
			}

			if (0 < self::$db->num_rows()) {
				$rUserInfo = self::$db->get_row();
			}
		}

		if ($rUserInfo) {
			if ((PLATFORM == 'xcms') && (self::$rSettings['county_override_1st'] == 1) && empty($rUserInfo['forced_country']) && !empty($rIP) && ($rUserInfo['max_connections'] == 1)) {
				$rUserInfo['forced_country'] = self::getIPInfo($rIP)['registered_country']['iso_code'];

				if (self::$rCached) {
					self::setSignal('forced_country/' . $rUserInfo['id'], $rUserInfo['forced_country']);
				}
				else if (PLATFORM == 'xcms') {
					self::$db->query('UPDATE `lines` SET `forced_country` = ? WHERE `id` = ?', $rUserInfo['forced_country'], $rUserInfo['id']);
				}
				else {
					self::$db->query('UPDATE `users` SET `forced_country` = ? WHERE `id` = ?', $rUserInfo['forced_country'], $rUserInfo['id']);
				}
			}

			$rUserInfo['bouquet'] = json_decode($rUserInfo['bouquet'], true);
			$rUserInfo['allowed_ips'] = @array_filter(array_map('trim', json_decode($rUserInfo['allowed_ips'], true)));
			$rUserInfo['allowed_ua'] = @array_filter(array_map('trim', json_decode($rUserInfo['allowed_ua'], true)));

			if (PLATFORM == 'xcms') {
				$rUserInfo['allowed_outputs'] = array_map('intval', json_decode($rUserInfo['allowed_outputs'], true));
			}
			else {
				$rUserInfo['allowed_outputs'] = [];
			}

			$rUserInfo['output_formats'] = [];

			if (self::$rCached) {
				foreach (self::unserialize(file_get_contents(CACHE_TMP_PATH . 'output_formats')) as $rRow) {
					if (in_array((int) $rRow['access_output_id'], $rUserInfo['allowed_outputs'])) {
						$rUserInfo['output_formats'][] = $rRow['output_key'];
					}
				}
			}
			else if (PLATFORM == 'xcms') {
				self::$db->query('SELECT `access_output_id`, `output_key` FROM `output_formats`;');

				foreach (self::$db->get_rows() as $rRow) {
					if (in_array((int) $rRow['access_output_id'], $rUserInfo['allowed_outputs'])) {
						$rUserInfo['output_formats'][] = $rRow['output_key'];
					}
				}
			}
			else {
				self::$db->query('SELECT `user_output`.`access_output_id`, `access_output`.`output_key` FROM `user_output` LEFT JOIN `access_output` ON `user_output`.`access_output_id` = `access_output`.`access_output_id` WHERE `user_output`.`user_id` = ?;', $rUserInfo['id']);

				foreach (self::$db->get_rows() as $rRow) {
					$rUserInfo['allowed_outputs'][] = $rRow['access_output_id'];
					$rUserInfo['output_formats'][] = $rRow['output_key'];
				}
			}

			$rUserInfo['con_isp_name'] = NULL;
			$rUserInfo['isp_violate'] = 0;
			$rUserInfo['isp_is_server'] = 0;
			if ((PLATFORM == 'xcms') && (self::$rSettings['show_isps'] == 1) && !empty($rIP)) {
				$rISPLock = self::getISP($rIP);

				if (is_array($rISPLock)) {
					if (!empty($rISPLock['isp'])) {
						$rUserInfo['con_isp_name'] = $rISPLock['isp'];
						$rUserInfo['isp_asn'] = $rISPLock['autonomous_system_number'];
						$rUserInfo['isp_violate'] = self::checkISP($rUserInfo['con_isp_name']);
					}
				}
				if (!empty($rUserInfo['con_isp_name']) && (self::$rSettings['enable_isp_lock'] == 1) && ($rUserInfo['is_stalker'] == 0) && ($rUserInfo['is_isplock'] == 1) && !empty($rUserInfo['isp_desc']) && (strtolower($rUserInfo['con_isp_name']) != strtolower($rUserInfo['isp_desc']))) {
					$rUserInfo['isp_violate'] = 1;
				}
				if (($rUserInfo['isp_violate'] == 0) && (strtolower($rUserInfo['con_isp_name']) != strtolower($rUserInfo['isp_desc']))) {
					if (self::$rCached) {
						self::setSignal('isp/' . $rUserInfo['id'], json_encode([$rUserInfo['con_isp_name'], $rUserInfo['isp_asn']]));
					}
					else if (PLATFORM == 'xcms') {
						self::$db->query('UPDATE `lines` SET `isp_desc` = ?, `as_number` = ? WHERE `id` = ?', $rUserInfo['con_isp_name'], $rUserInfo['isp_asn'], $rUserInfo['id']);
					}
					else {
						self::$db->query('UPDATE `users` SET `isp_desc` = ? WHERE `id` = ?', $rUserInfo['con_isp_name'], $rUserInfo['id']);
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

				if (self::$rCached) {
					$rLiveCategoryIDs = self::unserialize(file_get_contents(STREAMS_TMP_PATH . 'channels_categories'));
					$rSeriesCategoryIDs = self::unserialize(file_get_contents(SERIES_TMP_PATH . 'series_categories'));

					if (0 < count($rUserInfo['channel_ids'])) {
						foreach ($rUserInfo['channel_ids'] as $rStreamID) {
							if (isset($rLiveCategoryIDs[$rStreamID])) {
								foreach (array_values($rLiveCategoryIDs[$rStreamID]) as $rCategoryID) {
									if ($rCategoryID && !in_array($rCategoryID, $rCategoryIDs)) {
										$rCategoryIDs[] = $rCategoryID;
									}
								}
							}
						}
					}

					if (0 < count($rUserInfo['series_ids'])) {
						foreach ($rUserInfo['series_ids'] as $rSeriesID) {
							if (isset($rSeriesCategoryIDs[$rSeriesID])) {
								foreach (array_values($rSeriesCategoryIDs[$rSeriesID]) as $rCategoryID) {
									if ($rCategoryID && !in_array($rCategoryID, $rCategoryIDs)) {
										$rCategoryIDs[] = $rCategoryID;
									}
								}
							}
						}
					}
				}
				else {
					if (0 < count($rUserInfo['channel_ids'])) {
						self::$db->query('SELECT DISTINCT(`category_id`) FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['channel_ids'])) . ');');

						foreach (self::$db->get_rows(true, 'category_id') as $rGroup) {
							if (PLATFORM == 'xcms') {
								foreach (json_decode($rGroup['category_id'], true) as $rCategoryID) {
									if (!in_array($rCategoryID, $rCategoryIDs)) {
										$rCategoryIDs[] = $rCategoryID;
									}
								}
							}
							else {
								$rCategoryIDs[] = $rGroup['category_id'];
							}
						}
					}

					if (0 < count($rUserInfo['series_ids'])) {
						if (PLATFORM == 'xcms') {
							self::$db->query('SELECT DISTINCT(`category_id`) FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['series_ids'])) . ');');
						}
						else {
							self::$db->query('SELECT DISTINCT(`category_id`) FROM `series` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['series_ids'])) . ');');
						}

						foreach (self::$db->get_rows(true, 'category_id') as $rGroup) {
							if (PLATFORM == 'xcms') {
								foreach (json_decode($rGroup['category_id'], true) as $rCategoryID) {
									if (!in_array($rCategoryID, $rCategoryIDs)) {
										$rCategoryIDs[] = $rCategoryID;
									}
								}
							}
							else {
								$rCategoryIDs[] = $rGroup['category_id'];
							}
						}
					}
				}

				$rUserInfo['category_ids'] = array_map('intval', array_unique($rCategoryIDs));
			}

			return $rUserInfo;
		}

		return false;
	}

	static public function setSignal($rKey, $rData)
	{
		if (PLATFORM != 'xcms') {
			return false;
		}

		file_put_contents(SIGNALS_TMP_PATH . 'cache_' . md5($rKey), json_encode([$rKey, $rData]));
	}

	static public function getMainID()
	{
		foreach (self::$rServers as $rServerID => $rServer) {
			if ((isset($rServer['is_main']) && ($rServer['is_main'] == 1)) || (isset($rServer['can_delete']) && ($rServer['can_delete'] == 0))) {
				return $rServerID;
			}
		}

		return NULL;
	}

	static public function getUserIP()
	{
		return $_SERVER['REMOTE_ADDR'];
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

		$rIPFile = TMP_PATH . $rIP;

		if (file_exists($rIPFile)) {
			$rFloodRow = json_decode(file_get_contents($rIPFile), true);
			$rFloodSeconds = self::$rSettings['flood_seconds'];
			$rFloodLimit = self::$rSettings['flood_limit'];

			if ((time() - $rFloodRow['last_request']) <= $rFloodSeconds) {
				$rFloodRow['requests']++;
				$rFloodRow['last_request'] = time();
				file_put_contents($rIPFile, json_encode($rFloodRow), LOCK_EX);

				if ($rFloodLimit <= $rFloodRow['requests']) {
					sleep(10);
					exit();
				}
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

	static public function validateImage($rURL, $rForceProtocol = NULL)
	{
		if (PLATFORM != 'xcms') {
			return $rURL;
		}

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

	static public function getMovieTMDB($rID)
	{
		if (0 < strlen(self::$rSettings['tmdb_language'])) {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
		}
		else {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
		}

		return $rTMDB->getMovie($rID) ?: NULL;
	}

	static public function getSeriesTMDB($rID)
	{
		if (0 < strlen(self::$rSettings['tmdb_language'])) {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
		}
		else {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
		}

		return json_decode($rTMDB->getTVShow($rID)->getJSON(), true) ?: NULL;
	}

	static public function getSeasonTMDB($rID, $rSeason)
	{
		if (0 < strlen(self::$rSettings['tmdb_language'])) {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
		}
		else {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
		}

		return json_decode($rTMDB->getSeason($rID, (int) $rSeason)->getJSON(), true);
	}

	static public function getSimilarMovies($rID, $rPage = 1)
	{
		if (0 < strlen(self::$rSettings['tmdb_language'])) {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
		}
		else {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
		}

		return json_decode(json_encode($rTMDB->getSimilarMovies($rID, $rPage)), true);
	}

	static public function getSimilarSeries($rID, $rPage = 1)
	{
		if (0 < strlen(self::$rSettings['tmdb_language'])) {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
		}
		else {
			$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
		}

		return json_decode(json_encode($rTMDB->getSimilarSeries($rID, $rPage)), true);
	}

	static public function getYear($rTitle, $rProperties)
	{
		$rYear = NULL;

		if (isset($rProperties['release_date'])) {
			$rYear = substr($rProperties['release_date'], 0, 4);
		}

		if (isset($rProperties['releaseDate'])) {
			$rYear = substr($rProperties['releaseDate'], 0, 4);
		}

		$rRegex = '/\\(([0-9)]+)\\)/';
		preg_match($rRegex, $rTitle, $rMatches, PREG_OFFSET_CAPTURE, 0);
		$rTitleYear = NULL;
		$rMatchType = 0;

		if (count($rMatches) == 2) {
			$rTitleYear = (int) $rMatches[1][0];
			$rMatchType = 1;
		}
		else {
			$rSplit = explode('-', $rTitle);
			if ((1 < count($rSplit)) && is_numeric(trim(end($rSplit)))) {
				$rTitleYear = (int) trim(end($rSplit));
				$rMatchType = 2;
			}
		}

		if (0 < $rMatchType) {
			if ((1900 <= $rTitleYear) && ($rTitleYear <= (int) (date('Y') + 1))) {
				if (empty($rYear)) {
					$rYear = $rTitleYear;
				}

				if ($rMatchType == 1) {
					$rTitle = trim(preg_replace('!\\s+!', ' ', str_replace($rMatches[0][0], '', $rTitle)));
				}
				else {
					$rTitle = trim(implode('-', array_slice($rSplit, 0, -1)));
				}
			}
		}

		return ['title' => $rTitle, 'year' => $rYear];
	}

	static public function verifyLicense()
	{
		if (extension_loaded('xcms') && (PLATFORM == 'xcms')) {
			return true;
		}
		else {
			$rLicense = self::getLicense();
			if (!$rLicense || (self::getMAC() != $rLicense[5]) || ($rLicense[3] < time())) {
				exit('This server is unlicensed. Please check the billing panel for more information.');
			}
		}
	}

	static public function encrypt($rData, $rSeed, $rDeviceID)
	{
		return self::base64url_encode(openssl_encrypt($rData, 'aes-256-cbc', md5(sha1($rDeviceID) . $rSeed), OPENSSL_RAW_DATA, substr(md5(sha1($rSeed)), 0, 16)));
	}

	static public function decrypt($rData, $rSeed, $rDeviceID)
	{
		return openssl_decrypt(self::base64url_decode($rData), 'aes-256-cbc', md5(sha1($rDeviceID) . $rSeed), OPENSSL_RAW_DATA, substr(md5(sha1($rSeed)), 0, 16));
	}

	static private function base64url_encode($rData)
	{
		return rtrim(strtr(base64_encode($rData), '+/', '-_'), '=');
	}

	static private function base64url_decode($rData)
	{
		return base64_decode(strtr($rData, '-_', '+/'));
	}

	static private function getLicense()
	{
		global $rLicenseEnc;

		if ($rLicenseEnc) {
			return explode('::', self::decrypt($rLicenseEnc, '0d01d1dc470b0b3a4d676ec49fb42261', 'faa33a7d87219846e40b75dbf5bc7932'));
		}
		else {
			return NULL;
		}
	}

	static public function getMAC()
	{
		exec('ip --json address list', $rOutput);
		$rAddresses = json_decode(implode('', $rOutput), true);
		$rValidMAC = NULL;

		foreach ($rAddresses as $rAddress) {
			foreach ($rAddress['addr_info'] as $rInterface) {
				if (($rInterface['label'] != 'lo') && !empty($rInterface['local'])) {
					if (filter_var($rAddress['address'], FILTER_VALIDATE_MAC) && ($rAddress['address'] != '00:00:00:00:00:00')) {
						$rValidMAC = $rAddress['address'];
						break 2;
					}
				}
			}
		}

		return $rValidMAC;
	}

	/**
    static public function updateLicense($rLicense = null) {
        global $rConfig, $rBasePath;
        if (!$rLicense) {
            $rLicense = $rConfig["license"];
        }
        $rMAC = self::getMAC();
        if (empty($rMAC)) {
            return false;
        }

        $postdata = http_build_query(Array("data" => self::encrypt(json_encode(Array("license_key" => $rLicense, "mac" => $rMAC)), "0d01d1dc470b0b3a4d676ec49fb42261", "faa33a7d87219846e40b75dbf5bc7932")));
        $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata)
        );
        $context = stream_context_create($opts);
        file_get_contents('https://xcms.live/license/chk.php', false, $context);

    }

**/
	static public function updateLicense($rLicense = NULL)
	{
		global $rConfig;
		global $rBasePath;

		if (!$rLicense) {
			$rLicense = $rConfig['license'];
		}

		$rMAC = self::getMAC();

		if (empty($rMAC)) {
			return false;
		}

		$rCurl = curl_init();
		curl_setopt($rCurl, CURLOPT_URL, 'http://i.mytvservices.com/lic.php');
		curl_setopt($rCurl, CURLOPT_POST, true);
		curl_setopt($rCurl, CURLOPT_POSTFIELDS, http_build_query(['data' => self::encrypt(json_encode(['license_key' => $rLicense, 'mac' => $rMAC]), '0d01d1dc470b0b3a4d676ec49fb42261', 'faa33a7d87219846e40b75dbf5bc7932')]));
		curl_setopt($rCurl, CURLOPT_TIMEOUT, 5);
		curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
		$rLicenseReturn = json_decode(curl_exec($rCurl), true);

		if ($rLicenseReturn['status']) {
			file_put_contents($rBasePath . 'license.php', '<?php $rLicenseEnc = "' . str_replace('"', '\\"', $rLicenseReturn['license']) . '"; ?>');
			return NULL;
		}
		else {
			return $rLicenseReturn['error'] ?: 'Couldn\'t contact license server.';
		}
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
}

function sortArrayByArray($rArray, $rSort)
{
	if (empty($rArray) || empty($rSort)) {
		return [];
	}

	$rOrdered = [];

	foreach ($rSort as $rValue) {
		if (($rKey = array_search($rValue, $rArray)) !== false) {
			$rOrdered[] = $rValue;
			unset($rArray[$rKey]);
		}
	}

	return $rOrdered + $rArray;
}

function sortArrayStreamName($a, $b)
{
	$rColumn = (isset($a['stream_display_name']) ? 'stream_display_name' : 'title');
	return strcmp($a[$rColumn], $b[$rColumn]);
}

function destroySession()
{
	$_SESSION = &$_SESSION;

	foreach (['phash', 'pverify'] as $rKey) {
		if (isset($_SESSION[$rKey])) {
			unset($_SESSION[$rKey]);
		}
	}
}
if (!isset($rSkipVerify) && (php_sapi_name() == 'cli')) {
	exit();
}

session_start();
list($rBasePath) = get_included_files();
$rBasePath = pathinfo($rBasePath)['dirname'] . '/';

if (file_exists('config.php')) {
	require_once 'config.php';
}

if (file_exists('license.php')) {
	require_once 'license.php';
}

require_once 'libs/tmdb.php';

if (!@$argc) {
	define('HOST', trim(explode(':', $_SERVER['HTTP_HOST'])[0]));
}

if (isset($rConfig)) {
	define('PLATFORM', $rConfig['platform']);
	define('TMP_PATH', $rConfig['tmp_path']);
	define('CACHE_TMP_PATH', TMP_PATH);
	if (TMP_PATH && !file_exists(TMP_PATH)) {
		mkdir(TMP_PATH);
	}
}
else if (extension_loaded('xcms')) {
	define('XCMS_HOME', '/home/xcms/');
	define('BIN_PATH', XCMS_HOME . 'bin/');
	define('PLATFORM', 'xcms');
	define('TMP_PATH', XCMS_HOME . 'tmp/player/');
	define('CACHE_TMP_PATH', XCMS_HOME . 'tmp/cache/');
	define('EPG_PATH', XCMS_HOME . 'content/epg/');
}
else {
	echo 'No platform found.';
	exit();
}

$db = new Database();
if (extension_loaded('xcms') && (PLATFORM == 'xcms')) {
	$db->db_connect();
	define('STREAMS_TMP_PATH', XCMS_HOME . 'tmp/cache/streams/');
	define('SERIES_TMP_PATH', XCMS_HOME . 'tmp/cache/series/');
	define('LINES_TMP_PATH', XCMS_HOME . 'tmp/cache/lines/');
	define('CONS_TMP_PATH', XCMS_HOME . 'tmp/opened_cons/');
	define('SIGNALS_TMP_PATH', XCMS_HOME . 'tmp/signals/');
	define('GEOLITE2_BIN', BIN_PATH . 'maxmind/GeoLite2.mmdb');
	define('GEOISP_BIN', BIN_PATH . 'maxmind/GeoIP2-ISP.mmdb');
	define('CONTENT_PATH', XCMS_HOME . 'content/');
}
else if (!!PLATFORM) {
	$db->db_explicit_connect($rConfig['db_host'], $rConfig['db_port'], $rConfig['db_name'], $rConfig['db_user'], $rConfig['db_pass']);
	define('STREAMS_TMP_PATH', TMP_PATH);
	define('SERIES_TMP_PATH', TMP_PATH);
	define('LINES_TMP_PATH', TMP_PATH);
	define('CONS_TMP_PATH', TMP_PATH);
	define('CONTENT_PATH', TMP_PATH);
}

XCMS::$db = & $db;
XCMS::init();
define('SERVER_ID', XCMS::getMainID());

if (PLATFORM != 'xcms') {
	foreach (['player_allow_bouquet', 'player_allow_playlist', 'player_opacity', 'player_blur', 'tmdb_language'] as $rKey) {
		XCMS::$rSettings[$rKey] = $rConfig[$rKey];
	}

	foreach (['server_name', 'tmdb_api_key'] as $rKey) {
		if (!empty($rConfig[$rKey])) {
			XCMS::$rSettings[$rKey] = $rConfig[$rKey];
		}
	}

	XCMS::$rSettings['player_hide_incompatible'] = false;
	XCMS::$rSettings['disable_hls'] = false;
	XCMS::$rSettings['cloudflare'] = true;
	XCMS::$rSettings['custom_ip_header'] = NULL;
}

$_VERSION = '1.1.6';
$_PAGE = XCMS::getPageName();
XCMS::$rSettings['live_streaming_pass'] = md5(sha1(XCMS::$rServers[SERVER_ID]['server_name'] . XCMS::$rServers[SERVER_ID]['server_ip']) . '5f13a731fb85944e5c69ce863b0c990d');

if (!isset($rSkipVerify)) {
	if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on') && !XCMS::$rServers[SERVER_ID]['enable_https']) {
		header('Location: ' . XCMS::$rServers[SERVER_ID]['http_url'] . ltrim($_SERVER['REQUEST_URI'], '/'));
		exit();
	}

	if (isset($_SESSION['phash'])) {
		$rUserInfo = XCMS::getUserInfo($_SESSION['phash'], NULL, NULL, true);
		if (!$rUserInfo || (md5($rUserInfo['username'] . '||' . $rUserInfo['password']) != $_SESSION['pverify']) || (!is_null($rUserInfo['exp_date']) && ($rUserInfo['exp_date'] <= time())) || ($rUserInfo['admin_enabled'] == 0) || ($rUserInfo['enabled'] == 0)) {
			destroysession();
			header('Location: login.php');
			exit();
		}

		sort($rUserInfo['bouquet']);
	}
	else {
		header('Location: login.php');
		exit();
	}
}

?>