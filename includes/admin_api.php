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

class API
{
	static public $db = null;
	static public $rSettings = [];
	static public $rServers = [];
	static public $rProxyServers = [];
	static public $rUserInfo = [];

	static public function init($rUserID = NULL)
	{
		self::$rSettings = getSettings();
		self::$rServers = getStreamingServers();
		self::$rProxyServers = getProxyServers();
		if (!$rUserID && isset($_SESSION['hash'])) {
			$rUserID = $_SESSION['hash'];
		}

		if ($rUserID) {
			self::$rUserInfo = getRegisteredUser($rUserID);
		}
	}

	static private function checkMinimumRequirements($rData)
	{
		switch (debug_backtrace()[1]['function']) {
		case 'scheduleRecording':
			return !empty($rData['title']) && !empty($rData['source_id']);
		case 'processProvider':
			return !empty($rData['ip']) && !empty($rData['port']) && !empty($rData['username']) && !empty($rData['password']) && !empty($rData['name']);
		case 'processBouquet':
			return !empty($rData['bouquet_name']);
		case 'processGroup':
			return !empty($rData['group_name']);
		case 'processPackage':
			return !empty($rData['package_name']);
		case 'processCategory':
			return !empty($rData['category_name']) && !empty($rData['category_type']);
		case 'processCode':
			return !empty($rData['code']);
		case 'reorderBouquet':
		case 'setChannelOrder':
			return is_array(json_decode($rData['stream_order_array'], true));
		case 'sortBouquets':
			return is_array(json_decode($rData['bouquet_order_array'], true));
		case 'blockIP':
		case 'processRTMPIP':
			return !empty($rData['ip']);
		case 'processChannel':
		case 'processStream':
		case 'processMovie':
		case 'processRadio':
			return !empty($rData['stream_display_name']) || isset($rData['review']) || isset($_FILES['m3u_file']);
		case 'processEpisode':
			return !empty($rData['series']) && is_numeric($rData['season_num']) && is_numeric($rData['episode']);
		case 'processSeries':
			return !empty($rData['title']);
		case 'processEPG':
			return !empty($rData['epg_name']) && !empty($rData['epg_file']);
		case 'massEditEpisodes':
		case 'massEditMovies':
		case 'massEditRadios':
		case 'massEditStreams':
		case 'massEditChannels':
		case 'massDeleteStreams':
			return is_array(json_decode($rData['streams'], true));
		case 'massEditSeries':
		case 'massDeleteSeries':
			return is_array(json_decode($rData['series'], true));
		case 'massEditLines':
		case 'massEditUsers':
			return is_array(json_decode($rData['users_selected'], true));
		case 'massEditMags':
		case 'massEditEnigmas':
			return is_array(json_decode($rData['devices_selected'], true));
		case 'processISP':
			return !empty($rData['isp']);
		case 'massDeleteMovies':
			return is_array(json_decode($rData['movies'], true));
		case 'massDeleteLines':
			return is_array(json_decode($rData['lines'], true));
		case 'massDeleteUsers':
			return is_array(json_decode($rData['users'], true));
		case 'massDeleteStations':
			return is_array(json_decode($rData['radios'], true));
		case 'massDeleteMags':
			return is_array(json_decode($rData['mags'], true));
		case 'massDeleteEnigmas':
			return is_array(json_decode($rData['enigmas'], true));
		case 'massDeleteEpisodes':
			return is_array(json_decode($rData['episodes'], true));
		case 'processMAG':
		case 'processEnigma':
			return !empty($rData['mac']);
		case 'processProfile':
			return !empty($rData['profile_name']);
		case 'processProxy':
		case 'processServer':
			return !empty($rData['server_name']) && !empty($rData['server_ip']);
		case 'installServer':
			return !empty($rData['ssh_port']) && !empty($rData['root_password']);
		case 'orderCategories':
			return is_array(json_decode($rData['categories'], true));
		case 'orderServers':
			return is_array(json_decode($rData['server_order'], true));
		case 'moveStreams':
			return !empty($rData['content_type']) && !empty($rData['source_server']) && !empty($rData['replacement_server']);
		case 'replaceDNS':
			return !empty($rData['old_dns']) && !empty($rData['new_dns']);
		case 'processUA':
			return !empty($rData['user_agent']);
		case 'processWatchFolder':
			return !empty($rData['folder_type']) && !empty($rData['selected_path']) && !empty($rData['server_id']);
		}

		return true;
	}

	static public function processBouquet($rData)
	{
		global $_;

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_bouquet')) {
				exit();
			}

			$rArray = overwriteData(getBouquet($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_bouquet')) {
				exit();
			}

			$rArray = verifyPostTable('bouquets', $rData);
			unset($rArray['id']);
		}

		if (is_array(json_decode($rData['bouquet_data'], true))) {
			$rBouquetData = json_decode($rData['bouquet_data'], true);
			$rBouquetStreams = $rBouquetData['stream'];
			$rBouquetMovies = $rBouquetData['movies'];
			$rBouquetRadios = $rBouquetData['radios'];
			$rBouquetSeries = $rBouquetData['series'];
			$rRequiredIDs = confirmIDs(array_merge($rBouquetStreams, $rBouquetMovies, $rBouquetRadios));
			$rStreams = [];

			if (0 < count($rRequiredIDs)) {
				self::$db->query('SELECT `id`, `type` FROM `streams` WHERE `id` IN (' . implode(',', $rRequiredIDs) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					if ((int) $rRow['type'] == 3) {
						$rRow['type'] = 1;
					}

					$rStreams[(int) $rRow['type']][] = (int) $rRow['id'];
				}
			}

			if (0 < count($rBouquetSeries)) {
				self::$db->query('SELECT `id` FROM `streams_series` WHERE `id` IN (' . implode(',', $rBouquetSeries) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					$rStreams[5][] = (int) $rRow['id'];
				}
			}

			$rArray['bouquet_channels'] = array_intersect(array_map('intval', array_values($rBouquetStreams)), $rStreams[1]);
			$rArray['bouquet_movies'] = array_intersect(array_map('intval', array_values($rBouquetMovies)), $rStreams[2]);
			$rArray['bouquet_radios'] = array_intersect(array_map('intval', array_values($rBouquetRadios)), $rStreams[4]);
			$rArray['bouquet_series'] = array_intersect(array_map('intval', array_values($rBouquetSeries)), $rStreams[5]);
		}
		else if (isset($rData['edit'])) {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}

		if (!isset($rData['edit'])) {
			self::$db->query('SELECT MAX(`bouquet_order`) AS `max` FROM `bouquets`;');
			$rArray['bouquet_order'] = (int) self::$db->get_row()['max'] + 1;
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			scanBouquet($rInsertID);
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processCode($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getCode($rData['edit']), $rData);
			$rOrigCode = $rArray['code'];
		}
		else {
			$rArray = verifyPostTable('access_codes', $rData);
			$rOrigCode = NULL;
			unset($rArray['id']);
		}

		if (isset($rData['enabled'])) {
			$rArray['enabled'] = 1;
		}
		else {
			$rArray['enabled'] = 0;
		}

		if (isset($rData['groups'])) {
			$rArray['groups'] = [];

			foreach ($rData['groups'] as $rGroupID) {
				$rArray['groups'][] = (int) $rGroupID;
			}
		}

		if (in_array($rData['type'], [0, 1, 3, 4])) {
			$rArray['groups'] = '[' . implode(',', array_map('intval', $rArray['groups'])) . ']';
		}
		else {
			$rArray['groups'] = '[]';
		}

		if (!isset($rData['whitelist'])) {
			$rArray['whitelist'] = '[]';
		}
		if (($rData['type'] != 2) && (strlen($rData['code']) < 8)) {
			return ['status' => STATUS_CODE_LENGTH, 'data' => $rData];
		}
		else if (($rData['type'] == 2) && empty($rData['code'])) {
			return ['status' => STATUS_INVALID_CODE, 'data' => $rData];
		}
		else if (in_array($rData['code'], ['admin' => true, 'stream' => true, 'images' => true, 'player_api' => true, 'player' => true, 'playlist' => true, 'epg' => true, 'live' => true, 'movie' => true, 'series' => true, 'status' => true, 'nginx_status' => true, 'get' => true, 'panel_api' => true, 'xmltv' => true, 'probe' => true, 'thumb' => true, 'timeshift' => true, 'auth' => true, 'vauth' => true, 'tsauth' => true, 'hls' => true, 'play' => true, 'key' => true, 'api' => true, 'c' => true])) {
			return ['status' => STATUS_RESERVED_CODE, 'data' => $rData];
		}
		else {
			if (isset($rData['edit'])) {
				self::$db->query('SELECT `id` FROM `access_codes` WHERE `code` = ? AND `id` <> ?;', $rData['code'], $rData['edit']);
			}
			else {
				self::$db->query('SELECT `id` FROM `access_codes` WHERE `code` = ?;', $rData['code']);
			}

			if (0 < self::$db->num_rows()) {
				return ['status' => STATUS_EXISTS_CODE, 'data' => $rData];
			}
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `access_codes`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			updateCodes();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID, 'orig_code' => $rOrigCode, 'new_code' => $rData['code']]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processHMAC($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getHMACToken($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('hmac_keys', $rData);
			unset($rArray['id']);
		}

		if (isset($rData['enabled'])) {
			$rArray['enabled'] = 1;
		}
		else {
			$rArray['enabled'] = 0;
		}
		if (($rData['keygen'] != 'HMAC KEY HIDDEN') && (strlen($rData['keygen']) != 32)) {
			return ['status' => STATUS_NO_KEY, 'data' => $rData];
		}

		if (strlen($rData['notes']) == 0) {
			return ['status' => STATUS_NO_DESCRIPTION, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if ($rData['keygen'] != 'HMAC KEY HIDDEN') {
				self::$db->query('SELECT `id` FROM `hmac_keys` WHERE `key` = ? AND `id` <> ?;', Xcms\Functions::encrypt($rData['keygen'], OPENSSL_EXTRA), $rData['edit']);

				if (0 < self::$db->num_rows()) {
					return ['status' => STATUS_EXISTS_HMAC, 'data' => $rData];
				}
			}
		}
		else {
			self::$db->query('SELECT `id` FROM `hmac_keys` WHERE `key` = ?;', Xcms\Functions::encrypt($rData['keygen'], OPENSSL_EXTRA));

			if (0 < self::$db->num_rows()) {
				return ['status' => STATUS_EXISTS_HMAC, 'data' => $rData];
			}
		}

		if ($rData['keygen'] != 'HMAC KEY HIDDEN') {
			$rArray['key'] = Xcms\Functions::encrypt($rData['keygen'], OPENSSL_EXTRA);
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `hmac_keys`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function reorderBouquet($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rOrder = json_decode($rData['stream_order_array'], true);
		$rOrder['stream'] = confirmIDs($rOrder['stream']);
		$rOrder['series'] = confirmIDs($rOrder['series']);
		$rOrder['movie'] = confirmIDs($rOrder['movie']);
		$rOrder['radio'] = confirmIDs($rOrder['radio']);
		self::$db->query('UPDATE `bouquets` SET `bouquet_channels` = ?, `bouquet_series` = ?, `bouquet_movies` = ?, `bouquet_radios` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rOrder['stream'])) . ']', '[' . implode(',', array_map('intval', $rOrder['series'])) . ']', '[' . implode(',', array_map('intval', $rOrder['movie'])) . ']', '[' . implode(',', array_map('intval', $rOrder['radio'])) . ']', $rData['reorder']);
		return [
			'status' => STATUS_SUCCESS,
			'data'   => ['insert_id' => $rData['reorder']]
		];
	}

	static public function editAdminProfile($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}
		if ((0 < strlen($rData['email'])) && !filter_var($rData['email'], FILTER_VALIDATE_EMAIL)) {
			return ['status' => STATUS_INVALID_EMAIL];
		}

		if (0 < strlen($rData['password'])) {
			$rPassword = cryptPassword($rData['password']);
		}
		else {
			$rPassword = self::$rUserInfo['password'];
		}
		if (!ctype_xdigit($rData['api_key']) || (strlen($rData['api_key']) != 32)) {
			$rData['api_key'] = '';
		}

		self::$db->query('UPDATE `users` SET `password` = ?, `email` = ?, `theme` = ?, `hue` = ?, `timezone` = ?, `api_key` = ? WHERE `id` = ?;', $rPassword, $rData['email'], $rData['theme'], $rData['hue'], $rData['timezone'], $rData['api_key'], self::$rUserInfo['id']);
		return ['status' => STATUS_SUCCESS];
	}

	static public function blockIP($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (validateCIDR($rData['ip'])) {
			$rArray = ['ip' => $rData['ip'], 'notes' => $rData['notes'], 'date' => time()];
			touch(FLOOD_TMP_PATH . 'block_' . $rData['ip']);
			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `blocked_ips`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();
			}

			if (isset($rInsertID)) {
				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else {
				return ['status' => STATUS_FAILURE, 'data' => $rData];
			}
		}
		else {
			return ['status' => STATUS_INVALID_IP, 'data' => $rData];
		}
	}

	static public function sortBouquets($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);
		$rOrder = json_decode($rData['bouquet_order_array'], true);
		$rSort = 1;

		foreach ($rOrder as $rBouquetID) {
			self::$db->query('UPDATE `bouquets` SET `bouquet_order` = ? WHERE `id` = ?;', $rSort, $rBouquetID);
			$rSort++;
		}

		if (isset($rData['confirmReplace'])) {
			$rUsers = getUserBouquets();

			foreach ($rUsers as $rUser) {
				$rBouquet = json_decode($rUser['bouquet'], true);
				$rBouquet = array_map('intval', sortArrayByArray($rBouquet, $rOrder));
				self::$db->query('UPDATE `lines` SET `bouquet` = ? WHERE `id` = ?;', '[' . implode(',', $rBouquet) . ']', $rUser['id']);
				XCMS::updateLine($rUser['id']);
			}

			$rPackages = getPackages();

			foreach ($rPackages as $rPackage) {
				$rBouquet = json_decode($rPackage['bouquets'], true);
				$rBouquet = array_map('intval', sortArrayByArray($rBouquet, $rOrder));
				self::$db->query('UPDATE `users_packages` SET `bouquets` = ? WHERE `id` = ?;', '[' . implode(',', $rBouquet) . ']', $rPackage['id']);
			}

			return ['status' => STATUS_SUCCESS_REPLACE];
		}
		else {
			return ['status' => STATUS_SUCCESS];
		}
	}

	static public function setChannelOrder($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);
		$rOrder = json_decode($rData['stream_order_array'], true);
		$rSort = 0;

		foreach ($rOrder as $rStream) {
			self::$db->query('UPDATE `streams` SET `order` = ? WHERE `id` = ?;', $rSort, $rStream);
			$rSort++;
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processChannel($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_cchannel')) {
				exit();
			}

			$rArray = overwriteData(getStream($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'create_channel')) {
				exit();
			}

			$rArray = verifyPostTable('streams', $rData);
			$rArray['type'] = 3;
			$rArray['added'] = time();
			unset($rArray['id']);
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		if (isset($rData['reencode_on_edit'])) {
			$rReencode = true;
		}
		else {
			$rReencode = false;
		}

		foreach (['allow_record', 'rtmp_output'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		$rArray['movie_properties'] = ['type' => (int) $rData['channel_type']];

		if ((int) $rData['channel_type'] == 0) {
			$rPlaylist = generateSeriesPlaylist($rData['series_no']);
			$rArray['stream_source'] = $rPlaylist;
			$rArray['series_no'] = (int) $rData['series_no'];
		}
		else {
			$rArray['stream_source'] = $rData['video_files'];
			$rArray['series_no'] = 0;
		}

		if ($rData['transcode_profile_id'] == -1) {
			$rArray['movie_symlink'] = 1;
		}
		else {
			$rArray['movie_symlink'] = 0;
		}

		if (0 < count($rArray['stream_source'])) {
			$rBouquetCreate = [];

			foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
				$rPrepare = prepareArray([
					'bouquet_name'     => $rBouquet,
					'bouquet_channels' => [],
					'bouquet_movies'   => [],
					'bouquet_series'   => [],
					'bouquet_radios'   => []
				]);
				$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rBouquetID = self::$db->last_insert_id();
					$rBouquetCreate[$rBouquet] = $rBouquetID;
				}
			}

			$rCategoryCreate = [];

			foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
				$rPrepare = prepareArray(['category_type' => 'live', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
				$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rCategoryID = self::$db->last_insert_id();
					$rCategoryCreate[$rCategory] = $rCategoryID;
				}
			}

			$rBouquets = [];

			foreach ($rData['bouquets'] as $rBouquet) {
				if (isset($rBouquetCreate[$rBouquet])) {
					$rBouquets[] = $rBouquetCreate[$rBouquet];
				}
				else if (is_numeric($rBouquet)) {
					$rBouquets[] = (int) $rBouquet;
				}
			}

			$rCategories = [];

			foreach ($rData['category_id'] as $rCategory) {
				if (isset($rCategoryCreate[$rCategory])) {
					$rCategories[] = $rCategoryCreate[$rCategory];
				}
				else if (is_numeric($rCategory)) {
					$rCategories[] = (int) $rCategory;
				}
			}

			$rArray['category_id'] = '[' . implode(',', array_map('intval', $rCategories)) . ']';

			if (self::$rSettings['download_images']) {
				$rArray['stream_icon'] = XCMS::downloadImage($rArray['stream_icon'], 3);
			}

			if (!isset($rData['edit'])) {
				$rArray['order'] = getNextOrder();
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();
				$rStreamExists = [];

				if (isset($rData['edit'])) {
					self::$db->query('SELECT `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` = ?;', $rInsertID);

					foreach (self::$db->get_rows() as $rRow) {
						$rStreamExists[(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
					}
				}

				$rStreamsAdded = [];
				$rServerTree = json_decode($rData['server_tree_data'], true);

				foreach ($rServerTree as $rServer) {
					if ($rServer['parent'] != '#') {
						$rServerID = (int) $rServer['id'];
						$rStreamsAdded[] = $rServerID;
						$rOD = (int) in_array($rServerID, $rData['on_demand'] ?: []);

						if ($rServer['parent'] == 'source') {
							$rParent = NULL;
						}
						else {
							$rParent = (int) $rServer['parent'];
						}

						if (isset($rStreamExists[$rServerID])) {
							self::$db->query('UPDATE `streams_servers` SET `parent_id` = ?, `on_demand` = ? WHERE `server_stream_id` = ?;', $rParent, $rOD, $rStreamExists[$rServerID]);
						}
						else {
							self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `on_demand`, `pids_create_channel`, `cchannel_rsources`) VALUES(?, ?, ?, ?, \'[]\', \'[]\');', $rInsertID, $rServerID, $rParent, $rOD);
						}
					}
				}

				foreach ($rStreamExists as $rServerID => $rDBID) {
					if (!in_array($rServerID, $rStreamsAdded)) {
						deleteStream($rInsertID, $rServerID, false, false);
					}
				}

				if ($rReencode) {
					APIRequest([
						'action'     => 'stream',
						'sub'        => 'stop',
						'stream_ids' => [$rInsertID]
					]);
					self::$db->query('UPDATE `streams_servers` SET `pids_create_channel` = \'[]\', `cchannel_rsources` = \'[]\' WHERE `stream_id` = ?;', $rInsertID);
					XCMS::queueChannel($rInsertID);
				}

				if ($rRestart) {
					APIRequest([
						'action'     => 'stream',
						'sub'        => 'start',
						'stream_ids' => [$rInsertID]
					]);
				}

				foreach ($rBouquets as $rBouquet) {
					addToBouquet('stream', $rBouquet, $rInsertID);
				}

				if (isset($rData['edit'])) {
					foreach (getBouquets() as $rBouquet) {
						if (!in_array($rBouquet['id'], $rBouquets)) {
							removeFromBouquet('stream', $rBouquet['id'], $rInsertID);
						}
					}
				}

				XCMS::updateStream($rInsertID);
				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else {
				return ['status' => STATUS_FAILURE, 'data' => $rData];
			}
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rData];
		}
	}

	static public function processEPG($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'epg_edit')) {
				exit();
			}

			$rArray = overwriteData(getEPG($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_epg')) {
				exit();
			}

			$rArray = verifyPostTable('epg', $rData);
			unset($rArray['id']);
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `epg`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processProvider($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'streams')) {
				exit();
			}

			$rArray = overwriteData(getStreamProvider($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'streams')) {
				exit();
			}

			$rArray = verifyPostTable('providers', $rData);
			unset($rArray['id']);
		}

		foreach (['enabled', 'ssl', 'hls', 'legacy'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		if (isset($rData['edit'])) {
			self::$db->query('SELECT `id` FROM `providers` WHERE `ip` = ? AND `username` = ? AND `id` <> ? LIMIT 1;', $rArray['ip'], $rArray['username'], $rData['edit']);
		}
		else {
			self::$db->query('SELECT `id` FROM `providers` WHERE `ip` = ? AND `username` = ? LIMIT 1;', $rArray['ip'], $rArray['username']);
		}

		if (0 < self::$db->num_rows()) {
			return ['status' => STATUS_EXISTS_IP, 'data' => $rData];
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `providers`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processEpisode($rData)
	{
		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_episode')) {
				exit();
			}

			$rArray = overwriteData(getStream($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_episode')) {
				exit();
			}

			$rArray = verifyPostTable('streams', $rData);
			$rArray['type'] = 5;
			$rArray['added'] = time();
			$rArray['series_no'] = (int) $rData['series'];
			unset($rArray['id']);
		}

		$rArray['stream_source'] = [$rData['stream_source']];

		if (0 < strlen($rData['movie_subtitles'])) {
			$rSplit = explode(':', $rData['movie_subtitles']);
			$rArray['movie_subtitles'] = [
				'files'    => [$rSplit[2]],
				'names'    => ['Subtitles'],
				'charset'  => ['UTF-8'],
				'location' => (int) $rSplit[1]
			];
		}
		else {
			$rArray['movie_subtitles'] = NULL;
		}

		if (0 < $rArray['transcode_profile_id']) {
			$rArray['enable_transcode'] = 1;
		}

		foreach (['read_native', 'movie_symlink', 'direct_source', 'direct_proxy', 'remove_subtitles'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		$rProcessArray = [];

		if (isset($rData['multi'])) {
			if (!hasPermissions('adv', 'import_episodes')) {
				exit();
			}

			set_time_limit(0);
			include INCLUDES_PATH . 'libs/tmdb.php';
			$rSeries = getSerie((int) $rData['series']);

			if (0 < strlen(self::$rSettings['tmdb_language'])) {
				$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
			}
			else {
				$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
			}

			$rJSON = json_decode($rTMDB->getSeason($rData['tmdb_id'], (int) $rData['season_num'])->getJSON(), true);

			foreach ($rData as $rKey => $rFilename) {
				$rSplit = explode('_', $rKey);
				if (($rSplit[0] == 'episode') && ($rSplit[2] == 'name')) {
					if (0 < strlen($rData['episode_' . $rSplit[1] . '_num'])) {
						$rImportArray = [
							'filename'         => '',
							'properties'       => [],
							'name'             => '',
							'episode'          => 0,
							'target_container' => ''
						];
						$rEpisodeNum = (int) $rData['episode_' . $rSplit[1] . '_num'];
						$rImportArray['filename'] = 's:' . $rData['server'] . ':' . $rData['season_folder'] . $rFilename;
						$rImage = '';
						if (isset($rData['addName1']) && isset($rData['addName2'])) {
							$rImportArray['name'] = $rSeries['title'] . ' - S' . sprintf('%02d', (int) $rData['season_num']) . 'E' . sprintf('%02d', $rEpisodeNum) . ' - ';
						}
						else if (isset($rData['addName1'])) {
							$rImportArray['name'] = $rSeries['title'] . ' - ';
						}
						else if (isset($rData['addName2'])) {
							$rImportArray['name'] = 'S' . sprintf('%02d', (int) $rData['season_num']) . 'E' . sprintf('%02d', $rEpisodeNum) . ' - ';
						}

						$rImportArray['episode'] = $rEpisodeNum;

						foreach ($rJSON['episodes'] as $rEpisode) {
							if ($rEpisodeNum == (int) $rEpisode['episode_number']) {
								if (0 < strlen($rEpisode['still_path'])) {
									$rImage = 'https://image.tmdb.org/t/p/w600_and_h900_bestv2' . $rEpisode['still_path'];

									if (self::$rSettings['download_images']) {
										$rImage = XCMS::downloadImage($rImage, 5);
									}
								}

								$rImportArray['name'] .= $rEpisode['name'];
								$rSeconds = (int) $rSeries['episode_run_time'] * 60;
								$rImportArray['properties'] = [
									'tmdb_id'       => $rEpisode['id'],
									'release_date'  => $rEpisode['air_date'],
									'plot'          => $rEpisode['overview'],
									'duration_secs' => $rSeconds,
									'duration'      => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
									'movie_image'   => $rImage,
									'video'         => [],
									'audio'         => [],
									'bitrate'       => 0,
									'rating'        => $rEpisode['vote_average'],
									'season'        => $rData['season_num']
								];

								if (strlen($rImportArray['properties']['movie_image'][0]) == 0) {
									unset($rImportArray['properties']['movie_image']);
								}
							}
						}

						if (strlen($rImportArray['name']) == 0) {
							$rImportArray['name'] = 'No Episode Title';
						}

						$rPathInfo = pathinfo(explode('?', $rFilename)[0]);
						$rImportArray['target_container'] = $rPathInfo['extension'];
						$rProcessArray[] = $rImportArray;
					}
				}
			}
		}
		else {
			$rImportArray = [
				'filename'         => $rArray['stream_source'][0],
				'properties'       => [],
				'name'             => $rArray['stream_display_name'],
				'episode'          => $rData['episode'],
				'target_container' => $rData['target_container']
			];

			if (self::$rSettings['download_images']) {
				$rData['movie_image'] = XCMS::downloadImage($rData['movie_image'], 5);
			}

			$rSeconds = (int) $rData['episode_run_time'] * 60;
			$rImportArray['properties'] = [
				'release_date'  => $rData['release_date'],
				'plot'          => $rData['plot'],
				'duration_secs' => $rSeconds,
				'duration'      => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
				'movie_image'   => $rData['movie_image'],
				'video'         => [],
				'audio'         => [],
				'bitrate'       => 0,
				'rating'        => $rData['rating'],
				'season'        => $rData['season_num'],
				'tmdb_id'       => $rData['tmdb_id']
			];

			if (strlen($rImportArray['properties']['movie_image'][0]) == 0) {
				unset($rImportArray['properties']['movie_image']);
			}

			if ($rData['direct_proxy']) {
				$rExtension = pathinfo(explode('?', $rData['stream_source'])[0])['extension'];

				if ($rExtension) {
					$rImportArray['target_container'] = $rExtension;
				}
				else if (!$rImportArray['target_container']) {
					$rImportArray['target_container'] = 'mp4';
				}
			}

			$rProcessArray[] = $rImportArray;
		}

		$rRestartIDs = [];

		foreach ($rProcessArray as $rImportArray) {
			$rArray['stream_source'] = [$rImportArray['filename']];
			$rArray['movie_properties'] = $rImportArray['properties'];
			$rArray['stream_display_name'] = $rImportArray['name'];

			if (!empty($rImportArray['target_container'])) {
				$rArray['target_container'] = $rImportArray['target_container'];
			}
			else if (empty($rData['target_container'])) {
				$rArray['target_container'] = pathinfo(explode('?', $rImportArray['filename'])[0])['extension'];
			}
			else {
				$rArray['target_container'] = $rData['target_container'];
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();
				self::$db->query('DELETE FROM `streams_episodes` WHERE `stream_id` = ?;', $rInsertID);
				self::$db->query('INSERT INTO `streams_episodes`(`season_num`, `series_id`, `stream_id`, `episode_num`) VALUES(?, ?, ?, ?);', $rData['season_num'], $rData['series'], $rInsertID, $rImportArray['episode']);
				updateSeriesAsync((int) $rData['series']);
				$rStreamExists = [];

				if (isset($rData['edit'])) {
					self::$db->query('SELECT `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` = ?;', $rInsertID);

					foreach (self::$db->get_rows() as $rRow) {
						$rStreamExists[(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
					}
				}

				$rStreamsAdded = [];
				$rServerTree = json_decode($rData['server_tree_data'], true);

				foreach ($rServerTree as $rServer) {
					if ($rServer['parent'] != '#') {
						$rServerID = (int) $rServer['id'];
						$rStreamsAdded[] = $rServerID;

						if (!isset($rStreamExists[$rServerID])) {
							self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `on_demand`) VALUES(?, ?, 0);', $rInsertID, $rServerID);
						}
					}
				}

				foreach ($rStreamExists as $rServerID => $rDBID) {
					if (!in_array($rServerID, $rStreamsAdded)) {
						deleteStream($rInsertID, $rServerID, true, false);
					}
				}

				if ($rRestart) {
					$rRestartIDs[] = $rInsertID;
				}

				self::$db->query('UPDATE `streams_series` SET `last_modified` = ? WHERE `id` = ?;', time(), $rData['streams_series']);
				XCMS::updateStream($rInsertID);
			}
			else {
				return ['status' => STATUS_FAILURE];
			}
		}

		if ($rRestart) {
			APIRequest(['action' => 'vod', 'sub' => 'start', 'stream_ids' => $rRestartIDs]);
		}

		if (isset($rData['multi'])) {
			return [
				'status' => STATUS_SUCCESS_MULTI,
				0        => ['series_id' => $rData['series']]
			];
		}
		else {
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['series_id' => $rData['series'], 'insert_id' => $rInsertID]
			];
		}
	}

	static public function massEditEpisodes($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		if (isset($rData['c_movie_symlink'])) {
			if (isset($rData['movie_symlink'])) {
				$rArray['movie_symlink'] = 1;
			}
			else {
				$rArray['movie_symlink'] = 0;
			}
		}

		if (isset($rData['c_direct_source'])) {
			if (isset($rData['direct_source'])) {
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_source'] = 0;
				$rArray['direct_proxy'] = 0;
			}
		}

		if (isset($rData['c_direct_proxy'])) {
			if (isset($rData['direct_proxy'])) {
				$rArray['direct_proxy'] = 1;
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_proxy'] = 0;
			}
		}

		if (isset($rData['c_read_native'])) {
			if (isset($rData['read_native'])) {
				$rArray['read_native'] = 1;
			}
			else {
				$rArray['read_native'] = 0;
			}
		}

		if (isset($rData['c_remove_subtitles'])) {
			if (isset($rData['remove_subtitles'])) {
				$rArray['remove_subtitles'] = 1;
			}
			else {
				$rArray['remove_subtitles'] = 0;
			}
		}

		if (isset($rData['c_target_container'])) {
			$rArray['target_container'] = $rData['target_container'];
		}

		if (isset($rData['c_transcode_profile_id'])) {
			$rArray['transcode_profile_id'] = $rData['transcode_profile_id'];

			if (0 < $rArray['transcode_profile_id']) {
				$rArray['enable_transcode'] = 1;
			}
			else {
				$rArray['enable_transcode'] = 0;
			}
		}

		$rStreamIDs = confirmIDs(json_decode($rData['streams'], true));

		if (0 < count($rStreamIDs)) {
			if (isset($rData['c_serie_name'])) {
				self::$db->query('UPDATE `streams_episodes` SET `series_id` = ? WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');', $rData['serie_name']);
				self::$db->query('UPDATE `streams` SET `series_no` = ? WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');', $rData['serie_name']);
			}

			$rPrepare = prepareArray($rArray);

			if (0 < count($rPrepare['data'])) {
				$rQuery = 'UPDATE `streams` SET ' . $rPrepare['update'] . ' WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');';
				self::$db->query($rQuery, ...$rPrepare['data']);
			}

			$rDeleteServers = $rQueueMovies = $rProcessServers = $rStreamExists = [];
			self::$db->query('SELECT `stream_id`, `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

			foreach (self::$db->get_rows() as $rRow) {
				$rStreamExists[(int) $rRow['stream_id']][(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
				$rProcessServers[(int) $rRow['stream_id']][] = (int) $rRow['server_id'];
			}

			$rAddQuery = '';

			foreach ($rStreamIDs as $rStreamID) {
				if (isset($rData['c_server_tree'])) {
					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];

							if (in_array($rData['server_type'], ['ADD' => true, 'SET' => true])) {
								$rStreamsAdded[] = $rServerID;

								if (!isset($rStreamExists[$rStreamID][$rServerID])) {
									$rAddQuery .= '(' . (int) $rStreamID . ', ' . (int) $rServerID . '),';
									$rProcessServers[$rStreamID][] = $rServerID;
								}
							}
							else if (isset($rStreamExists[$rStreamID][$rServerID])) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}

					if ($rData['server_type'] == 'SET') {
						foreach ($rStreamExists[$rStreamID] as $rServerID => $rDBID) {
							if (!in_array($rServerID, $rStreamsAdded)) {
								$rDeleteServers[$rServerID][] = $rStreamID;

								if (($rKey = array_search($rServerID, $rProcessServers[$rStreamID])) !== false) {
									unset($rProcessServers[$rStreamID][$rKey]);
								}
							}
						}
					}
				}

				if (isset($rData['reencode_on_edit'])) {
					foreach ($rProcessServers[$rStreamID] as $rServerID) {
						$rQueueMovies[$rServerID][] = $rStreamID;
					}
				}

				foreach ($rDeleteServers as $rServerID => $rDeleteIDs) {
					deleteStreamsByServer($rDeleteIDs, $rServerID, true);
				}
			}

			if (!empty($rAddQuery)) {
				$rAddQuery = rtrim($rAddQuery, ',');
				self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`) VALUES ' . $rAddQuery . ';');
			}

			XCMS::updateStreams($rStreamIDs);

			if (isset($rData['reencode_on_edit'])) {
				foreach ($rQueueMovies as $rServerID => $rQueueIDs) {
					XCMS::queueMovies($rQueueIDs, $rServerID);
				}
			}

			if (isset($rData['reprocess_tmdb'])) {
				XCMS::refreshMovies($rStreamIDs, 3);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processGroup($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_group')) {
				exit();
			}

			$rArray = overwriteData(getMemberGroup($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_group')) {
				exit();
			}

			$rArray = verifyPostTable('users_groups', $rData);
			unset($rArray['id']);
		}

		foreach (['is_admin', 'is_reseller', 'allow_restrictions', 'create_sub_resellers', 'delete_users', 'allow_download', 'can_view_vod', 'reseller_client_connection_logs', 'allow_change_bouquets', 'allow_change_username', 'allow_change_password', 'can_add_connection'] as $rSelection) {
			if (isset($rData[$rSelection])) {
				$rArray[$rSelection] = 1;
			}
			else {
				$rArray[$rSelection] = 0;
			}
		}
		if (!$rArray['can_delete'] && isset($rData['edit'])) {
			$rGroup = getMemberGroup($rData['edit']);
			$rArray['is_admin'] = $rGroup['is_admin'];
			$rArray['is_reseller'] = $rGroup['is_reseller'];
		}

		$rArray['allowed_pages'] = array_values(json_decode($rData['permissions_selected'], true));

		if (strlen($rData['group_name']) == 0) {
			return ['status' => STATUS_INVALID_NAME, 'data' => $rData];
		}

		$rArray['subresellers'] = '[' . implode(',', array_map('intval', json_decode($rData['groups_selected'], true))) . ']';
		$rArray['notice_html'] = htmlentities($rData['notice_html']);
		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `users_groups`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			$rPackages = json_decode($rData['packages_selected'], true);

			foreach ($rPackages as $rPackage) {
				self::$db->query('SELECT `groups` FROM `users_packages` WHERE `id` = ?;', $rPackage);

				if (self::$db->num_rows() == 1) {
					$rGroups = json_decode(self::$db->get_row()['groups'], true);

					if (!in_array($rInsertID, $rGroups)) {
						$rGroups[] = $rInsertID;
						self::$db->query('UPDATE `users_packages` SET `groups` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rGroups)) . ']', $rPackage);
					}
				}
			}

			self::$db->query('SELECT `id`, `groups` FROM `users_packages` WHERE JSON_CONTAINS(`groups`, ?, \'$\');', $rInsertID);

			foreach (self::$db->get_rows() as $rRow) {
				if (!in_array($rRow['id'], $rPackages)) {
					$rGroups = json_decode($rRow['groups'], true);

					if (($rKey = array_search($rInsertID, $rGroups)) !== false) {
						unset($rGroups[$rKey]);
						self::$db->query('UPDATE `users_packages` SET `groups` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rGroups)) . ']', $rRow['id']);
					}
				}
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processISP($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'block_isps')) {
				exit();
			}

			$rArray = overwriteData(getISP($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'block_isps')) {
				exit();
			}

			$rArray = verifyPostTable('blocked_isps', $rData);
			unset($rArray['id']);
		}

		if (isset($rData['blocked'])) {
			$rArray['blocked'] = 1;
		}
		else {
			$rArray['blocked'] = 0;
		}

		if (strlen($rArray['isp']) == 0) {
			return ['status' => STATUS_INVALID_NAME, 'data' => $rData];
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `blocked_isps`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processLogin($rData, $rBypassRecaptcha = false)
	{
		if (self::$rSettings['recaptcha_enable'] && !$rBypassRecaptcha) {
			$rResponse = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . self::$rSettings['recaptcha_v2_secret_key'] . '&response=' . $rData['g-recaptcha-response']), true);

			if (!$rResponse['success']) {
				return ['status' => STATUS_INVALID_CAPTCHA];
			}
		}

		$rIP = getIP();
		$rUserInfo = getUserInfo($rData['username'], $rData['password']);
		$rAccessCode = getCurrentCode(true);

		if (isset($rUserInfo)) {
			self::$db->query('SELECT COUNT(*) AS `count` FROM `access_codes`;');
			$rCodeCount = self::$db->get_row()['count'];
			if (($rCodeCount == 0) || in_array($rUserInfo['member_group_id'], json_decode($rAccessCode['groups'], true))) {
				$rPermissions = getPermissions($rUserInfo['member_group_id']);

				if ($rPermissions['is_admin']) {
					if ($rUserInfo['status'] == 1) {
						$rCrypt = cryptPassword($rData['password']);

						if ($rCrypt != $rUserInfo['password']) {
							self::$db->query('UPDATE `users` SET `password` = ?, `last_login` = UNIX_TIMESTAMP(), `ip` = ? WHERE `id` = ?;', $rCrypt, $rIP, $rUserInfo['id']);
						}
						else {
							self::$db->query('UPDATE `users` SET `last_login` = UNIX_TIMESTAMP(), `ip` = ? WHERE `id` = ?;', $rIP, $rUserInfo['id']);
						}

						$_SESSION['hash'] = $rUserInfo['id'];
						$_SESSION['ip'] = $rIP;
						$_SESSION['code'] = getCurrentCode();
						$_SESSION['verify'] = md5($rUserInfo['username'] . '||' . $rCrypt);

						if (self::$rSettings['save_login_logs']) {
							self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'ADMIN\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'SUCCESS', $rIP, time());
						}

						return ['status' => STATUS_SUCCESS];
					}
					else if ($rPermissions && (($rPermissions['is_admin'] || $rPermissions['is_reseller']) && !$rUserInfo['status'])) {
						if (self::$rSettings['save_login_logs']) {
							self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'ADMIN\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'DISABLED', $rIP, time());
						}

						return ['status' => STATUS_DISABLED];
					}
				}
				else {
					if (self::$rSettings['save_login_logs']) {
						self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'ADMIN\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'NOT_ADMIN', $rIP, time());
					}

					return ['status' => STATUS_NOT_ADMIN];
				}
			}
			else {
				if (self::$rSettings['save_login_logs']) {
					self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'ADMIN\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'INVALID_CODE', $rIP, time());
				}

				return ['status' => STATUS_INVALID_CODE];
			}
		}
		else {
			if (self::$rSettings['save_login_logs']) {
				self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'ADMIN\', ?, 0, ?, ?, ?);', $rAccessCode['id'], 'INVALID_LOGIN', $rIP, time());
			}

			return ['status' => STATUS_FAILURE];
		}
	}

	static public function massDeleteStreams($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rStreams = json_decode($rData['streams'], true);
		deleteStreams($rStreams, false);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteMovies($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rMovies = json_decode($rData['movies'], true);
		deleteStreams($rMovies, true);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteLines($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rLines = json_decode($rData['lines'], true);
		deleteLines($rLines);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteUsers($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rUsers = json_decode($rData['users'], true);
		deleteUser($rUsers);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteStations($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rStreams = json_decode($rData['radios'], true);
		deleteStreams($rStreams, false);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteMags($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rMags = json_decode($rData['mags'], true);
		deleteMAGs($rMags);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteEnigmas($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rEnigmas = json_decode($rData['enigmas'], true);
		deleteEnigmas($rEnigmas);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteSeries($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rSeries = json_decode($rData['series'], true);
		deleteSeriesMass($rSeries);
		return ['status' => STATUS_SUCCESS];
	}

	static public function massDeleteEpisodes($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rEpisodes = json_decode($rData['episodes'], true);
		deleteStreams($rEpisodes, true);
		return ['status' => STATUS_SUCCESS];
	}

	static public function processMovie($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_movie')) {
				exit();
			}

			$rArray = overwriteData(getStream($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_movie')) {
				exit();
			}

			$rArray = verifyPostTable('streams', $rData);
			$rArray['added'] = time();
			$rArray['type'] = 2;
			unset($rArray['id']);
		}

		if (0 < strlen($rData['movie_subtitles'])) {
			$rSplit = explode(':', $rData['movie_subtitles']);
			$rArray['movie_subtitles'] = [
				'files'    => [$rSplit[2]],
				'names'    => ['Subtitles'],
				'charset'  => ['UTF-8'],
				'location' => (int) $rSplit[1]
			];
		}
		else {
			$rArray['movie_subtitles'] = NULL;
		}

		if (0 < $rArray['transcode_profile_id']) {
			$rArray['enable_transcode'] = 1;
		}
		if (!is_numeric($rArray['year']) || ($rArray['year'] < 1900) || ((int) (date('Y') + 1) < $rArray['year'])) {
			$rArray['year'] = NULL;
		}

		foreach (['read_native', 'movie_symlink', 'direct_source', 'direct_proxy', 'remove_subtitles'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		$rReview = false;
		$rImportStreams = [];

		if (isset($rData['review'])) {
			require_once XCMS_HOME . 'includes/libs/tmdb.php';

			if (0 < strlen(self::$rSettings['tmdb_language'])) {
				$rTMDB = new TMDB(self::$rSettings['tmdb_api_key'], self::$rSettings['tmdb_language']);
			}
			else {
				$rTMDB = new TMDB(self::$rSettings['tmdb_api_key']);
			}

			$rReview = true;

			foreach ($rData['review'] as $rImportStream) {
				if ($rImportStream['tmdb_id']) {
					$rMovie = $rTMDB->getMovie($rImportStream['tmdb_id']);

					if ($rMovie) {
						$rMovieData = json_decode($rMovie->getJSON(), true);
						$rMovieData['trailer'] = $rMovie->getTrailer();
						$rThumb = 'https://image.tmdb.org/t/p/w600_and_h900_bestv2' . $rMovieData['poster_path'];
						$rBG = 'https://image.tmdb.org/t/p/w1280' . $rMovieData['backdrop_path'];

						if (self::$rSettings['download_images']) {
							$rThumb = XCMS::downloadImage($rThumb, 2);
							$rBG = XCMS::downloadImage($rBG);
						}

						$rCast = [];

						foreach ($rMovieData['credits']['cast'] as $rMember) {
							if (count($rCast) < 5) {
								$rCast[] = $rMember['name'];
							}
						}

						$rDirectors = [];

						foreach ($rMovieData['credits']['crew'] as $rMember) {
							if ((count($rDirectors) < 5) && (($rMember['department'] == 'Directing') || ($rMember['known_for_department'] == 'Directing'))) {
								$rDirectors[] = $rMember['name'];
							}
						}

						$rCountry = '';

						if (isset($rMovieData['production_countries'][0]['name'])) {
							$rCountry = $rMovieData['production_countries'][0]['name'];
						}

						$rGenres = [];

						foreach ($rMovieData['genres'] as $rGenre) {
							if (count($rGenres) < 3) {
								$rGenres[] = $rGenre['name'];
							}
						}

						$rSeconds = (int) $rMovieData['runtime'] * 60;

						if (0 < strlen($rMovieData['release_date'])) {
							$rYear = (int) substr($rMovieData['release_date'], 0, 4);
						}
						else {
							$rYear = NULL;
						}

						$rImportStream['movie_properties'] = [
							'kinopoisk_url'          => 'https://www.themoviedb.org/movie/' . $rMovieData['id'],
							'tmdb_id'                => $rMovieData['id'],
							'name'                   => $rMovieData['title'],
							'year'                   => $rYear,
							'o_name'                 => $rMovieData['original_title'],
							'cover_big'              => $rThumb,
							'movie_image'            => $rThumb,
							'release_date'           => $rMovieData['release_date'],
							'episode_run_time'       => $rMovieData['runtime'],
							'youtube_trailer'        => $rMovieData['trailer'],
							'director'               => implode(', ', $rDirectors),
							'actors'                 => implode(', ', $rCast),
							'cast'                   => implode(', ', $rCast),
							'description'            => $rMovieData['overview'],
							'plot'                   => $rMovieData['overview'],
							'age'                    => '',
							'mpaa_rating'            => '',
							'rating_count_kinopoisk' => 0,
							'country'                => $rCountry,
							'genre'                  => implode(', ', $rGenres),
							'backdrop_path'          => [$rBG],
							'duration_secs'          => $rSeconds,
							'duration'               => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
							'video'                  => [],
							'audio'                  => [],
							'bitrate'                => 0,
							'rating'                 => $rMovieData['vote_average']
						];
					}
				}

				unset($rImportStream['tmdb_id']);
				$rImportStream['async'] = false;
				$rImportStream['target_container'] = pathinfo(explode('?', $rImportStream['stream_source'][0])[0])['extension'];

				if (empty($rImportStream['target_container'])) {
					$rImportStream['target_container'] = 'mp4';
				}

				$rImportStreams[] = $rImportStream;
			}
		}
		else {
			$rImportStreams = [];

			if (!empty($_FILES['m3u_file']['tmp_name'])) {
				if (!hasPermissions('adv', 'import_movies')) {
					exit();
				}

				$rStreamDatabase = [];
				self::$db->query('SELECT `stream_source` FROM `streams` WHERE `type` = 2;');

				foreach (self::$db->get_rows() as $rRow) {
					foreach (json_decode($rRow['stream_source'], true) as $rSource) {
						if (0 < strlen($rSource)) {
							$rStreamDatabase[] = $rSource;
						}
					}
				}

				$rFile = '';
				if (!empty($_FILES['m3u_file']['tmp_name']) && (strtolower(pathinfo(explode('?', $_FILES['m3u_file']['name'])[0], PATHINFO_EXTENSION)) == 'm3u')) {
					$rFile = file_get_contents($_FILES['m3u_file']['tmp_name']);
				}

				preg_match_all('/(?P<tag>#EXTINF:[-1,0])|(?:(?P<prop_key>[-a-z]+)=\\"(?P<prop_val>[^"]+)")|(?<name>,[^\\r\\n]+)|(?<url>http[^\\s]*:\\/\\/.*\\/.*)/', $rFile, $rMatches);
				$rResults = [];
				$rIndex = -1;

				for ($i = 0; $i < count($rMatches[0]); $i++) {
					$rItem = $rMatches[0][$i];

					if (!empty($rMatches['tag'][$i])) {
						++$rIndex;
						continue;
					}

					if (!empty($rMatches['prop_key'][$i])) {
						$rResults[$rIndex][$rMatches['prop_key'][$i]] = trim($rMatches['prop_val'][$i]);
						continue;
					}

					if (!empty($rMatches['name'][$i])) {
						$rResults[$rIndex]['name'] = trim(substr($rItem, 1));
						continue;
					}

					if (!empty($rMatches['url'][$i])) {
						$rResults[$rIndex]['url'] = str_replace(' ', '%20', trim($rItem));
					}
				}

				foreach ($rResults as $rResult) {
					if (!in_array($rResult['url'], $rStreamDatabase)) {
						$rPathInfo = pathinfo(explode('?', $rResult['url'])[0]);
						$rImportArray = [
							'stream_source'       => [$rResult['url']],
							'stream_icon'         => $rResult['tvg-logo'] ?: '',
							'stream_display_name' => $rResult['name'] ?: '',
							'movie_properties'    => [],
							'async'               => true,
							'target_container'    => $rPathInfo['extension']
						];
						$rImportStreams[] = $rImportArray;
					}
				}
			}
			else if (!empty($rData['import_folder'])) {
				if (!hasPermissions('adv', 'import_movies')) {
					exit();
				}

				$rStreamDatabase = [];
				self::$db->query('SELECT `stream_source` FROM `streams` WHERE `type` = 2;');

				foreach (self::$db->get_rows() as $rRow) {
					foreach (json_decode($rRow['stream_source'], true) as $rSource) {
						if (0 < strlen($rSource)) {
							$rStreamDatabase[] = $rSource;
						}
					}
				}

				$rParts = explode(':', $rData['import_folder']);

				if (is_numeric($rParts[1])) {
					if (isset($rData['scan_recursive'])) {
						$rFiles = scanRecursive((int) $rParts[1], $rParts[2], ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'wmv', 'mov', 'ts']);
					}
					else {
						$rFiles = [];

						foreach (listDir((int) $rParts[1], rtrim($rParts[2], '/'), ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'wmv', 'mov', 'ts'])['files'] as $rFile) {
							$rFiles[] = rtrim($rParts[2], '/') . '/' . $rFile;
						}
					}

					foreach ($rFiles as $rFile) {
						$rFilePath = 's:' . (int) $rParts[1] . ':' . $rFile;

						if (!in_array($rFilePath, $rStreamDatabase)) {
							$rPathInfo = pathinfo($rFile);
							$rImportArray = [
								'stream_source'       => [$rFilePath],
								'stream_icon'         => '',
								'stream_display_name' => $rPathInfo['filename'],
								'movie_properties'    => [],
								'async'               => true,
								'target_container'    => $rPathInfo['extension']
							];
							$rImportStreams[] = $rImportArray;
						}
					}
				}
			}
			else {
				$rImportArray = [
					'stream_source'       => [$rData['stream_source']],
					'stream_icon'         => $rArray['stream_icon'],
					'stream_display_name' => $rArray['stream_display_name'],
					'movie_properties'    => [],
					'async'               => false,
					'target_container'    => $rArray['target_container']
				];

				if (0 < strlen($rData['tmdb_id'])) {
					$rTMDBURL = 'https://www.themoviedb.org/movie/' . $rData['tmdb_id'];
				}
				else {
					$rTMDBURL = '';
				}

				if (self::$rSettings['download_images']) {
					$rData['movie_image'] = XCMS::downloadImage($rData['movie_image'], 2);
					$rData['backdrop_path'] = XCMS::downloadImage($rData['backdrop_path']);
				}

				$rSeconds = (int) $rData['episode_run_time'] * 60;
				$rImportArray['movie_properties'] = [
					'kinopoisk_url'          => $rTMDBURL,
					'tmdb_id'                => $rData['tmdb_id'],
					'name'                   => $rArray['stream_display_name'],
					'o_name'                 => $rArray['stream_display_name'],
					'cover_big'              => $rData['movie_image'],
					'movie_image'            => $rData['movie_image'],
					'release_date'           => $rData['release_date'],
					'episode_run_time'       => $rData['episode_run_time'],
					'youtube_trailer'        => $rData['youtube_trailer'],
					'director'               => $rData['director'],
					'actors'                 => $rData['cast'],
					'cast'                   => $rData['cast'],
					'description'            => $rData['plot'],
					'plot'                   => $rData['plot'],
					'age'                    => '',
					'mpaa_rating'            => '',
					'rating_count_kinopoisk' => 0,
					'country'                => $rData['country'],
					'genre'                  => $rData['genre'],
					'backdrop_path'          => [$rData['backdrop_path']],
					'duration_secs'          => $rSeconds,
					'duration'               => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
					'video'                  => [],
					'audio'                  => [],
					'bitrate'                => 0,
					'rating'                 => $rData['rating']
				];

				if (strlen($rImportArray['movie_properties']['backdrop_path'][0]) == 0) {
					unset($rImportArray['movie_properties']['backdrop_path']);
				}
				if ($rData['movie_symlink'] || $rData['direct_proxy']) {
					$rExtension = pathinfo(explode('?', $rData['stream_source'])[0])['extension'];

					if ($rExtension) {
						$rImportArray['target_container'] = $rExtension;
					}
					else if (!$rImportArray['target_container']) {
						$rImportArray['target_container'] = 'mp4';
					}
				}

				$rImportStreams[] = $rImportArray;
			}
		}

		if (0 < count($rImportStreams)) {
			$rBouquetCreate = [];
			$rCategoryCreate = [];

			if (!$rReview) {
				foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
					$rPrepare = prepareArray([
						'bouquet_name'     => $rBouquet,
						'bouquet_channels' => [],
						'bouquet_movies'   => [],
						'bouquet_series'   => [],
						'bouquet_radios'   => []
					]);
					$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

					if (self::$db->query($rQuery, ...$rPrepare['data'])) {
						$rBouquetID = self::$db->last_insert_id();
						$rBouquetCreate[$rBouquet] = $rBouquetID;
					}
				}

				foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
					$rPrepare = prepareArray(['category_type' => 'movie', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
					$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

					if (self::$db->query($rQuery, ...$rPrepare['data'])) {
						$rCategoryID = self::$db->last_insert_id();
						$rCategoryCreate[$rCategory] = $rCategoryID;
					}
				}
			}

			$rRestartIDs = [];

			foreach ($rImportStreams as $rImportStream) {
				$rImportArray = $rArray;

				if ($rReview) {
					$rImportArray['category_id'] = '[' . implode(',', array_map('intval', $rImportStream['category_id'])) . ']';
					$rBouquets = array_map('intval', $rImportStream['bouquets']);
					unset($rImportStream['bouquets']);
				}
				else {
					$rBouquets = [];

					foreach ($rData['bouquets'] as $rBouquet) {
						if (isset($rBouquetCreate[$rBouquet])) {
							$rBouquets[] = $rBouquetCreate[$rBouquet];
						}
						else if (is_numeric($rBouquet)) {
							$rBouquets[] = (int) $rBouquet;
						}
					}

					$rCategories = [];

					foreach ($rData['category_id'] as $rCategory) {
						if (isset($rCategoryCreate[$rCategory])) {
							$rCategories[] = $rCategoryCreate[$rCategory];
						}
						else if (is_numeric($rCategory)) {
							$rCategories[] = (int) $rCategory;
						}
					}

					$rImportArray['category_id'] = '[' . implode(',', array_map('intval', $rCategories)) . ']';
				}

				if (isset($rImportArray['movie_properties']['rating'])) {
					$rImportArray['rating'] = $rImportArray['movie_properties']['rating'];
				}

				foreach (array_keys($rImportStream) as $rKey) {
					$rImportArray[$rKey] = $rImportStream[$rKey];
				}

				if (!isset($rData['edit'])) {
					$rImportArray['order'] = getNextOrder();
				}

				$rImportArray['tmdb_id'] = $rImportStream['movie_properties']['tmdb_id'] ?: NULL;
				$rSync = $rImportArray['async'];
				unset($rImportArray['async']);
				$rPrepare = prepareArray($rImportArray);
				$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rInsertID = self::$db->last_insert_id();
					$rStreamExists = [];

					if (isset($rData['edit'])) {
						self::$db->query('SELECT `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` = ?;', $rInsertID);

						foreach (self::$db->get_rows() as $rRow) {
							$rStreamExists[(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
						}
					}

					$rPath = $rImportArray['stream_source'][0];

					if (substr($rPath, 0, 2) == 's:') {
						$rSplit = explode(':', $rPath, 3);
						$rPath = $rSplit[2];
					}

					self::$db->query('UPDATE `watch_logs` SET `status` = 1, `stream_id` = ? WHERE `filename` = ? AND `type` = 1;', $rInsertID, $rPath);
					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];
							$rStreamsAdded[] = $rServerID;

							if (!isset($rStreamExists[$rServerID])) {
								self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `on_demand`) VALUES(?, ?, 0);', $rInsertID, $rServerID);
							}
						}
					}

					foreach ($rStreamExists as $rServerID => $rDBID) {
						if (!in_array($rServerID, $rStreamsAdded)) {
							deleteStream($rInsertID, $rServerID, true, false);
						}
					}

					if ($rRestart) {
						$rRestartIDs[] = $rInsertID;
					}

					foreach ($rBouquets as $rBouquet) {
						addToBouquet('movie', $rBouquet, $rInsertID);
					}

					foreach (getBouquets() as $rBouquet) {
						if (!in_array($rBouquet['id'], $rBouquets)) {
							removeFromBouquet('movie', $rBouquet['id'], $rInsertID);
						}
					}

					if ($rSync) {
						self::$db->query('INSERT INTO `watch_refresh`(`type`, `stream_id`, `status`) VALUES(1, ?, 0);', $rInsertID);
					}

					XCMS::updateStream($rInsertID);
				}
				else {
					foreach ($rBouquetCreate as $rBouquet => $rID) {
						$db->query('DELETE FROM `bouquets` WHERE `id` = ?;', $rID);
					}

					foreach ($rCategoryCreate as $rCategory => $rID) {
						$db->query('DELETE FROM `streams_categories` WHERE `id` = ?;', $rID);
					}

					return ['status' => STATUS_FAILURE, 'data' => $rData];
				}
			}

			if ($rRestart) {
				APIRequest(['action' => 'vod', 'sub' => 'start', 'stream_ids' => $rRestartIDs]);
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rData];
		}
	}

	static public function massEditMovies($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		if (isset($rData['c_movie_symlink'])) {
			if (isset($rData['movie_symlink'])) {
				$rArray['movie_symlink'] = 1;
			}
			else {
				$rArray['movie_symlink'] = 0;
			}
		}

		if (isset($rData['c_direct_source'])) {
			if (isset($rData['direct_source'])) {
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_source'] = 0;
				$rArray['direct_proxy'] = 0;
			}
		}

		if (isset($rData['c_direct_proxy'])) {
			if (isset($rData['direct_proxy'])) {
				$rArray['direct_proxy'] = 1;
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_proxy'] = 0;
			}
		}

		if (isset($rData['c_read_native'])) {
			if (isset($rData['read_native'])) {
				$rArray['read_native'] = 1;
			}
			else {
				$rArray['read_native'] = 0;
			}
		}

		if (isset($rData['c_remove_subtitles'])) {
			if (isset($rData['remove_subtitles'])) {
				$rArray['remove_subtitles'] = 1;
			}
			else {
				$rArray['remove_subtitles'] = 0;
			}
		}

		if (isset($rData['c_target_container'])) {
			$rArray['target_container'] = $rData['target_container'];
		}

		if (isset($rData['c_transcode_profile_id'])) {
			$rArray['transcode_profile_id'] = $rData['transcode_profile_id'];

			if (0 < $rArray['transcode_profile_id']) {
				$rArray['enable_transcode'] = 1;
			}
			else {
				$rArray['enable_transcode'] = 0;
			}
		}

		$rStreamIDs = json_decode($rData['streams'], true);

		if (0 < count($rStreamIDs)) {
			$rCategoryMap = [];
			if (isset($rData['c_category_id']) && in_array($rData['category_id_type'], ['ADD' => true, 'DEL' => true])) {
				self::$db->query('SELECT `id`, `category_id` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					$rCategoryMap[$rRow['id']] = json_decode($rRow['category_id'], true) ?: [];
				}
			}

			$rDeleteServers = $rQueueMovies = $rProcessServers = $rStreamExists = [];
			self::$db->query('SELECT `stream_id`, `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

			foreach (self::$db->get_rows() as $rRow) {
				$rStreamExists[(int) $rRow['stream_id']][(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
				$rProcessServers[(int) $rRow['stream_id']][] = (int) $rRow['server_id'];
			}

			$rBouquets = getBouquets();
			$rAddBouquet = $rDelBouquet = [];
			$rAddQuery = '';

			foreach ($rStreamIDs as $rStreamID) {
				if (isset($rData['c_category_id'])) {
					$rCategories = array_map('intval', $rData['category_id']);

					if ($rData['category_id_type'] == 'ADD') {
						foreach ($rCategoryMap[$rStreamID] ?: [] as $rCategoryID) {
							if (!in_array($rCategoryID, $rCategories)) {
								$rCategories[] = $rCategoryID;
							}
						}
					}
					else if ($rData['category_id_type'] == 'DEL') {
						$rNewCategories = $rCategoryMap[$rStreamID];

						foreach ($rCategories as $rCategoryID) {
							if (($rKey = array_search($rCategoryID, $rNewCategories)) !== false) {
								unset($rNewCategories[$rKey]);
							}
						}

						$rCategories = $rNewCategories;
					}

					$rArray['category_id'] = '[' . implode(',', $rCategories) . ']';
				}

				$rPrepare = prepareArray($rArray);

				if (0 < count($rPrepare['data'])) {
					$rPrepare['data'][] = $rStreamID;
					$rQuery = 'UPDATE `streams` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
					self::$db->query($rQuery, ...$rPrepare['data']);
				}

				if (isset($rData['c_server_tree'])) {
					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];

							if (in_array($rData['server_type'], ['ADD' => true, 'SET' => true])) {
								$rStreamsAdded[] = $rServerID;

								if (!isset($rStreamExists[$rStreamID][$rServerID])) {
									$rAddQuery .= '(' . (int) $rStreamID . ', ' . (int) $rServerID . '),';
									$rProcessServers[$rStreamID][] = $rServerID;
								}
							}
							else if (isset($rStreamExists[$rStreamID][$rServerID])) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}

					if ($rData['server_type'] == 'SET') {
						foreach ($rStreamExists[$rStreamID] as $rServerID => $rDBID) {
							if (!in_array($rServerID, $rStreamsAdded)) {
								$rDeleteServers[$rServerID][] = $rStreamID;

								if (($rKey = array_search($rServerID, $rProcessServers[$rStreamID])) !== false) {
									unset($rProcessServers[$rStreamID][$rKey]);
								}
							}
						}
					}
				}

				if (isset($rData['c_bouquets'])) {
					if ($rData['bouquets_type'] == 'SET') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}

						foreach ($rBouquets as $rBouquet) {
							if (!in_array($rBouquet['id'], $rData['bouquets'])) {
								$rDelBouquet[$rBouquet['id']][] = $rStreamID;
							}
						}
					}
					else if ($rData['bouquets_type'] == 'ADD') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}
					}
					else if ($rData['bouquets_type'] == 'DEL') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rDelBouquet[$rBouquet][] = $rStreamID;
						}
					}
				}

				if (isset($rData['reencode_on_edit'])) {
					foreach ($rProcessServers[$rStreamID] as $rServerID) {
						$rQueueMovies[$rServerID][] = $rStreamID;
					}
				}

				foreach ($rDeleteServers as $rServerID => $rDeleteIDs) {
					deleteStreamsByServer($rDeleteIDs, $rServerID, true);
				}
			}

			foreach ($rAddBouquet as $rBouquetID => $rAddIDs) {
				addToBouquet('movie', $rBouquetID, $rAddIDs);
			}

			foreach ($rDelBouquet as $rBouquetID => $rRemIDs) {
				removeFromBouquet('movie', $rBouquetID, $rRemIDs);
			}

			if (!empty($rAddQuery)) {
				$rAddQuery = rtrim($rAddQuery, ',');
				self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`) VALUES ' . $rAddQuery . ';');
			}

			XCMS::updateStreams($rStreamIDs);

			if (isset($rData['reencode_on_edit'])) {
				foreach ($rQueueMovies as $rServerID => $rQueueIDs) {
					XCMS::queueMovies($rQueueIDs, $rServerID);
				}
			}

			if (isset($rData['reprocess_tmdb'])) {
				XCMS::refreshMovies($rStreamIDs, 1);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processPackage($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_package')) {
				exit();
			}

			$rArray = overwriteData(getPackage($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_packages')) {
				exit();
			}

			$rArray = verifyPostTable('users_packages', $rData);
			unset($rArray['id']);
		}

		if (strlen($rData['package_name']) == 0) {
			return ['status' => STATUS_INVALID_NAME, 'data' => $rData];
		}

		foreach (['is_trial', 'is_official', 'is_mag', 'is_e2', 'is_line', 'lock_device', 'is_restreamer', 'is_isplock', 'check_compatible'] as $rSelection) {
			if (isset($rData[$rSelection])) {
				$rArray[$rSelection] = 1;
			}
			else {
				$rArray[$rSelection] = 0;
			}
		}

		$rArray['groups'] = '[' . implode(',', array_map('intval', json_decode($rData['groups_selected'], true))) . ']';
		$rArray['bouquets'] = sortArrayByArray(array_values(json_decode($rData['bouquets_selected'], true)), array_keys(getBouquetOrder()));
		$rArray['bouquets'] = '[' . implode(',', array_map('intval', $rArray['bouquets'])) . ']';

		if (isset($rData['output_formats'])) {
			$rArray['output_formats'] = [];

			foreach ($rData['output_formats'] as $rOutput) {
				$rArray['output_formats'][] = $rOutput;
			}

			$rArray['output_formats'] = '[' . implode(',', array_map('intval', $rArray['output_formats'])) . ']';
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `users_packages`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processMAG($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_mag')) {
				exit();
			}

			$rArray = overwriteData(getMag($rData['edit']), $rData);
			$rUser = getUser($rArray['user_id']);

			if ($rUser) {
				$rUserArray = overwriteData($rUser, $rData);
			}
			else {
				$rUserArray = verifyPostTable('lines', $rData);
				$rUserArray['created_at'] = time();
				unset($rUserArray['id']);
			}
		}
		else {
			if (!hasPermissions('adv', 'add_mag')) {
				exit();
			}

			$rArray = verifyPostTable('mag_devices', $rData);
			$rArray['theme_type'] = XCMS::$rSettings['mag_default_type'];
			$rUserArray = verifyPostTable('lines', $rData);
			$rUserArray['created_at'] = time();
			unset($rArray['mag_id']);
			unset($rUserArray['id']);
		}

		if (strlen($rUserArray['username']) == 0) {
			$rUserArray['username'] = generateString(32);
		}

		if (strlen($rUserArray['password']) == 0) {
			$rUserArray['password'] = generateString(32);
		}

		if (strlen($rData['isp_clear']) == 0) {
			$rUserArray['isp_desc'] = '';
			$rUserArray['as_number'] = NULL;
		}

		$rUserArray['is_mag'] = 1;
		$rUserArray['is_e2'] = 0;
		$rUserArray['max_connections'] = 1;
		$rUserArray['is_restreamer'] = 0;

		if (isset($rData['is_trial'])) {
			$rUserArray['is_trial'] = 1;
		}
		else {
			$rUserArray['is_trial'] = 0;
		}

		if (isset($rData['is_isplock'])) {
			$rUserArray['is_isplock'] = 1;
		}
		else {
			$rUserArray['is_isplock'] = 0;
		}

		if (isset($rData['lock_device'])) {
			$rArray['lock_device'] = 1;
		}
		else {
			$rArray['lock_device'] = 0;
		}

		$rUserArray['bouquet'] = sortArrayByArray(array_values(json_decode($rData['bouquets_selected'], true)), array_keys(getBouquetOrder()));
		$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
		if (isset($rData['exp_date']) && !isset($rData['no_expire'])) {
			if ((0 < strlen($rData['exp_date'])) && ($rData['exp_date'] != '1970-01-01')) {
				try {
					$rDate = new DateTime($rData['exp_date']);
					$rUserArray['exp_date'] = $rDate->format('U');
				}
				catch (Exception $e) {
					return ['status' => STATUS_INVALID_DATE, 'data' => $rData];
				}
			}
		}
		else {
			$rUserArray['exp_date'] = NULL;
		}

		if (!$rUserArray['member_id']) {
			$rUserArray['member_id'] = self::$rUserInfo['id'];
		}

		if (isset($rData['allowed_ips'])) {
			if (!is_array($rData['allowed_ips'])) {
				$rData['allowed_ips'] = [$rData['allowed_ips']];
			}

			$rUserArray['allowed_ips'] = json_encode($rData['allowed_ips']);
		}
		else {
			$rUserArray['allowed_ips'] = '[]';
		}

		if (isset($rData['pair_id'])) {
			$rUserArray['pair_id'] = (int) $rData['pair_id'];
		}
		else {
			$rUserArray['pair_id'] = NULL;
		}

		$rUserArray['allowed_outputs'] = '[' . implode(',', [1, 2]) . ']';
		$rDevice = $rArray;
		$rDevice['user'] = $rUserArray;

		if (0 < $rDevice['user']['pair_id']) {
			$rUserCheck = getUser($rDevice['user']['pair_id']);

			if (!$rUserCheck) {
				return ['status' => STATUS_INVALID_USER, 'data' => $rData];
			}
		}

		if (!filter_var($rData['mac'], FILTER_VALIDATE_MAC)) {
			return ['status' => STATUS_INVALID_MAC, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			self::$db->query('SELECT `mag_id` FROM `mag_devices` WHERE mac = ? AND `mag_id` <> ? LIMIT 1;', $rArray['mac'], $rData['edit']);
		}
		else {
			self::$db->query('SELECT `mag_id` FROM `mag_devices` WHERE mac = ? LIMIT 1;', $rArray['mac']);
		}

		if (0 < self::$db->num_rows()) {
			return ['status' => STATUS_EXISTS_MAC, 'data' => $rData];
		}

		$rPrepare = prepareArray($rUserArray);
		$rQuery = 'REPLACE INTO `lines`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			$rArray['user_id'] = $rInsertID;
			XCMS::updateLine($rArray['user_id']);
			unset($rArray['user']);
			unset($rArray['paired']);

			if (!isset($rData['edit'])) {
				$rArray['sn'] = $rArray['image_version'] = $rArray['stb_type'] = $rArray['hw_version'] = $rArray['device_id'] = $rArray['device_id2'] = $rArray['ver'] = '';
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `mag_devices`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();

				if (0 < $rDevice['user']['pair_id']) {
					syncDevices($rDevice['user']['pair_id'], $rInsertID);
					XCMS::updateLine($rDevice['user']['pair_id']);
				}

				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else if (!isset($rData['edit'])) {
				self::$db->query('DELETE FROM `lines` WHERE `id` = ?;', $rInsertID);
			}
		}

		return ['status' => STATUS_FAILURE, 'data' => $rData];
	}

	static public function processEnigma($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_e2')) {
				exit();
			}

			$rArray = overwriteData(getEnigma($rData['edit']), $rData);
			$rUser = getUser($rArray['user_id']);

			if ($rUser) {
				$rUserArray = overwriteData($rUser, $rData);
			}
			else {
				$rUserArray = verifyPostTable('lines', $rData);
				$rUserArray['created_at'] = time();
				unset($rUserArray['id']);
			}
		}
		else {
			if (!hasPermissions('adv', 'add_e2')) {
				exit();
			}

			$rArray = verifyPostTable('enigma2_devices', $rData);
			$rUserArray = verifyPostTable('lines', $rData);
			$rUserArray['created_at'] = time();
			unset($rArray['device_id']);
			unset($rUserArray['id']);
		}

		if (strlen($rUserArray['username']) == 0) {
			$rUserArray['username'] = generateString(32);
		}

		if (strlen($rUserArray['password']) == 0) {
			$rUserArray['password'] = generateString(32);
		}

		if (strlen($rData['isp_clear']) == 0) {
			$rUserArray['isp_desc'] = '';
			$rUserArray['as_number'] = NULL;
		}

		$rUserArray['is_e2'] = 1;
		$rUserArray['is_mag'] = 0;
		$rUserArray['max_connections'] = 1;
		$rUserArray['is_restreamer'] = 0;

		if (isset($rData['is_trial'])) {
			$rUserArray['is_trial'] = 1;
		}
		else {
			$rUserArray['is_trial'] = 0;
		}

		if (isset($rData['is_isplock'])) {
			$rUserArray['is_isplock'] = 1;
		}
		else {
			$rUserArray['is_isplock'] = 0;
		}

		if (isset($rData['lock_device'])) {
			$rArray['lock_device'] = 1;
		}
		else {
			$rArray['lock_device'] = 0;
		}

		$rUserArray['bouquet'] = sortArrayByArray(array_values(json_decode($rData['bouquets_selected'], true)), array_keys(getBouquetOrder()));
		$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
		if (isset($rData['exp_date']) && !isset($rData['no_expire'])) {
			if ((0 < strlen($rData['exp_date'])) && ($rData['exp_date'] != '1970-01-01')) {
				try {
					$rDate = new DateTime($rData['exp_date']);
					$rUserArray['exp_date'] = $rDate->format('U');
				}
				catch (Exception $e) {
					return ['status' => STATUS_INVALID_DATE, 'data' => $rData];
				}
			}
		}
		else {
			$rUserArray['exp_date'] = NULL;
		}

		if (!$rUserArray['member_id']) {
			$rUserArray['member_id'] = self::$rUserInfo['id'];
		}

		if (isset($rData['allowed_ips'])) {
			if (!is_array($rData['allowed_ips'])) {
				$rData['allowed_ips'] = [$rData['allowed_ips']];
			}

			$rUserArray['allowed_ips'] = json_encode($rData['allowed_ips']);
		}
		else {
			$rUserArray['allowed_ips'] = '[]';
		}

		if (isset($rData['pair_id'])) {
			$rUserArray['pair_id'] = (int) $rData['pair_id'];
		}
		else {
			$rUserArray['pair_id'] = NULL;
		}

		$rUserArray['allowed_outputs'] = '[' . implode(',', [1, 2]) . ']';
		$rDevice = $rArray;
		$rDevice['user'] = $rUserArray;

		if (0 < $rDevice['user']['pair_id']) {
			$rUserCheck = getUser($rDevice['user']['pair_id']);

			if (!$rUserCheck) {
				return ['status' => STATUS_INVALID_USER, 'data' => $rData];
			}
		}

		if (!filter_var($rData['mac'], FILTER_VALIDATE_MAC)) {
			return ['status' => STATUS_INVALID_MAC, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			self::$db->query('SELECT `device_id` FROM `enigma2_devices` WHERE mac = ? AND `device_id` <> ? LIMIT 1;', $rArray['mac'], $rData['edit']);
		}
		else {
			self::$db->query('SELECT `device_id` FROM `enigma2_devices` WHERE mac = ? LIMIT 1;', $rArray['mac']);
		}

		if (0 < self::$db->num_rows()) {
			return ['status' => STATUS_EXISTS_MAC, 'data' => $rData];
		}

		$rPrepare = prepareArray($rUserArray);
		$rQuery = 'REPLACE INTO `lines`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			$rArray['user_id'] = $rInsertID;
			XCMS::updateLine($rArray['user_id']);
			unset($rArray['user']);
			unset($rArray['paired']);

			if (!isset($rData['edit'])) {
				$rArray['modem_mac'] = $rArray['local_ip'] = $rArray['enigma_version'] = $rArray['cpu'] = $rArray['lversion'] = $rArray['token'] = '';
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `enigma2_devices`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();

				if (0 < $rDevice['user']['pair_id']) {
					syncDevices($rDevice['user']['pair_id'], $rInsertID);
					XCMS::updateLine($rDevice['user']['pair_id']);
				}

				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else if (!isset($rData['edit'])) {
				self::$db->query('DELETE FROM `lines` WHERE `id` = ?;', $rInsertID);
			}
		}

		return ['status' => STATUS_FAILURE, 'data' => $rData];
	}

	static public function processProfile($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = ['profile_name' => $rData['profile_name'], 'profile_options' => NULL];
		$rProfileOptions = [];

		if ($rData['gpu_device'] != 0) {
			$rProfileOptions['software_decoding'] = (int) $rData['software_decoding'] ?: 0;
			$rProfileOptions['gpu'] = ['val' => $rData['gpu_device'], 'cmd' => ''];
			$rProfileOptions['gpu']['device'] = (int) explode('_', $rData['gpu_device'])[1];

			if (!$rData['software_decoding']) {
				$rCommand = [];
				$rCommand[] = '-hwaccel cuvid';
				$rCommand[] = '-hwaccel_device ' . $rProfileOptions['gpu']['device'];

				if (0 < strlen($rData['resize'])) {
					$rProfileOptions['gpu']['resize'] = $rData['resize'];
					$rCommand[] = '-resize ' . escapeshellcmd($rData['resize']);
				}

				if (0 < $rData['deint']) {
					$rProfileOptions['gpu']['deint'] = (int) $rData['deint'];
					$rCommand[] = '-deint ' . (int) $rData['deint'];
				}

				$rCodec = '';

				if (0 < strlen($rData['video_codec_gpu'])) {
					$rProfileOptions['-vcodec'] = escapeshellcmd($rData['video_codec_gpu']);
					$rCommand[] = '{INPUT_CODEC}';

					switch ($rData['video_codec_gpu']) {
					case 'hevc_nvenc':
						$rCodec = 'hevc';
						break;
					default:
						$rCodec = 'h264';
						break;
					}
				}

				if (0 < strlen($rData['preset_' . $rCodec])) {
					$rProfileOptions['-preset'] = escapeshellcmd($rData['preset_' . $rCodec]);
				}

				if (0 < strlen($rData['video_profile_' . $rCodec])) {
					$rProfileOptions['-profile:v'] = escapeshellcmd($rData['video_profile_' . $rCodec]);
				}

				$rCommand[] = '-gpu ' . $rProfileOptions['gpu']['device'];
				$rCommand[] = '-drop_second_field 1';
				$rProfileOptions['gpu']['cmd'] = implode(' ', $rCommand);
			}
			else {
				$rCodec = '';

				if (0 < strlen($rData['video_codec_gpu'])) {
					$rProfileOptions['-vcodec'] = escapeshellcmd($rData['video_codec_gpu']);

					switch ($rData['video_codec_gpu']) {
					case 'hevc_nvenc':
						$rCodec = 'hevc';
						break;
					default:
						$rCodec = 'h264';
						break;
					}
				}

				if (0 < strlen($rData['preset_' . $rCodec])) {
					$rProfileOptions['-preset'] = escapeshellcmd($rData['preset_' . $rCodec]);
				}

				if (0 < strlen($rData['video_profile_' . $rCodec])) {
					$rProfileOptions['-profile:v'] = escapeshellcmd($rData['video_profile_' . $rCodec]);
				}
			}
		}
		else {
			if (0 < strlen($rData['video_codec_cpu'])) {
				$rProfileOptions['-vcodec'] = escapeshellcmd($rData['video_codec_cpu']);
			}

			if (0 < strlen($rData['preset_cpu'])) {
				$rProfileOptions['-preset'] = escapeshellcmd($rData['preset_cpu']);
			}

			if (0 < strlen($rData['video_profile_cpu'])) {
				$rProfileOptions['-profile:v'] = escapeshellcmd($rData['video_profile_cpu']);
			}
		}

		if (0 < strlen($rData['audio_codec'])) {
			$rProfileOptions['-acodec'] = escapeshellcmd($rData['audio_codec']);
		}

		if (0 < strlen($rData['video_bitrate'])) {
			$rProfileOptions[3] = ['cmd' => '-b:v ' . (int) $rData['video_bitrate'] . 'k', 'val' => (int) $rData['video_bitrate']];
		}

		if (0 < strlen($rData['audio_bitrate'])) {
			$rProfileOptions[4] = ['cmd' => '-b:a ' . (int) $rData['audio_bitrate'] . 'k', 'val' => (int) $rData['audio_bitrate']];
		}

		if (0 < strlen($rData['min_tolerance'])) {
			$rProfileOptions[5] = ['cmd' => '-minrate ' . (int) $rData['min_tolerance'] . 'k', 'val' => (int) $rData['min_tolerance']];
		}

		if (0 < strlen($rData['max_tolerance'])) {
			$rProfileOptions[6] = ['cmd' => '-maxrate ' . (int) $rData['max_tolerance'] . 'k', 'val' => (int) $rData['max_tolerance']];
		}

		if (0 < strlen($rData['buffer_size'])) {
			$rProfileOptions[7] = ['cmd' => '-bufsize ' . (int) $rData['buffer_size'] . 'k', 'val' => (int) $rData['buffer_size']];
		}

		if (0 < strlen($rData['crf_value'])) {
			$rProfileOptions[8] = ['cmd' => '-crf ' . (int) $rData['crf_value'], 'val' => $rData['crf_value']];
		}

		if (0 < strlen($rData['aspect_ratio'])) {
			$rProfileOptions[10] = ['cmd' => '-aspect ' . escapeshellcmd($rData['aspect_ratio']), 'val' => $rData['aspect_ratio']];
		}

		if (0 < strlen($rData['framerate'])) {
			$rProfileOptions[11] = ['cmd' => '-r ' . (int) $rData['framerate'], 'val' => (int) $rData['framerate']];
		}

		if (0 < strlen($rData['samplerate'])) {
			$rProfileOptions[12] = ['cmd' => '-ar ' . (int) $rData['samplerate'], 'val' => (int) $rData['samplerate']];
		}

		if (0 < strlen($rData['audio_channels'])) {
			$rProfileOptions[13] = ['cmd' => '-ac ' . (int) $rData['audio_channels'], 'val' => (int) $rData['audio_channels']];
		}

		if (0 < strlen($rData['threads'])) {
			$rProfileOptions[15] = ['cmd' => '-threads ' . (int) $rData['threads'], 'val' => (int) $rData['threads']];
		}

		$rComplex = false;
		$rScale = $rOverlay = $rLogoInput = '';

		if (0 < strlen($rData['logo_path'])) {
			$rComplex = true;
			$rPos = array_map('intval', explode(':', $rData['logo_pos']));

			if (count($rPos) != 2) {
				$rPos = [10, 10];
			}

			$rLogoInput = '-i ' . escapeshellarg($rData['logo_path']);
			$rProfileOptions[16] = ['cmd' => '', 'val' => $rData['logo_path'], 'pos' => implode(':', $rPos)];
			if (($rData['gpu_device'] != 0) && !$rData['software_decoding']) {
				$rOverlay = '[0:v]hwdownload,format=nv12 [base]; [base][1:v] overlay=' . $rPos[0] . ':' . $rPos[1];
			}
			else {
				$rOverlay = 'overlay=' . $rPos[0] . ':' . $rPos[1];
			}
		}

		if ($rData['gpu_device'] == 0) {
			if (isset($rData['yadif_filter']) && (0 < strlen($rData['scaling']))) {
				$rComplex = true;
			}

			if ($rComplex) {
				if (isset($rData['yadif_filter']) && (0 < strlen($rData['scaling']))) {
					if (!$rData['software_decoding']) {
						$rScale = '[0:v]yadif,scale=' . escapeshellcmd($rData['scaling']) . '[bg];[bg][1:v]';
					}
					else {
						$rScale = 'yadif,scale=' . escapeshellcmd($rData['scaling']);
					}

					$rProfileOptions[9] = ['cmd' => '', 'val' => $rData['scaling']];
					$rProfileOptions[17] = ['cmd' => '', 'val' => 1];
				}
				else if (0 < strlen($rData['scaling'])) {
					$rScale = 'scale=' . escapeshellcmd($rData['scaling']);
					$rProfileOptions[9] = ['cmd' => '', 'val' => $rData['scaling']];
				}
				else if (isset($rData['yadif_filter'])) {
					if (!$rData['software_decoding']) {
						$rScale = '[0:v]yadif[bg];[bg][1:v]';
					}
					else {
						$rScale = 'yadif';
					}

					$rProfileOptions[17] = ['cmd' => '', 'val' => 1];
				}
			}
			else {
				if (0 < strlen($rData['scaling'])) {
					$rProfileOptions[9] = ['cmd' => '-vf scale=' . escapeshellcmd($rData['scaling']), 'val' => $rData['scaling']];
				}

				if (isset($rData['yadif_filter'])) {
					$rProfileOptions[17] = ['cmd' => '-vf yadif', 'val' => 1];
				}
			}
		}
		else if ($rData['software_decoding']) {
			if ((0 < (int) $rData['deint']) && (0 < strlen($rData['resize']))) {
				$rComplex = true;
			}

			if ($rComplex) {
				if ((0 < (int) $rData['deint']) && (0 < strlen($rData['resize']))) {
					if (!$rData['software_decoding']) {
						$rScale = '[0:v]yadif,scale=' . escapeshellcmd($rData['resize']) . '[bg];[bg][1:v]';
					}
					else {
						$rScale = 'yadif,scale=' . escapeshellcmd($rData['resize']);
					}

					$rProfileOptions[9] = ['cmd' => '', 'val' => $rData['resize']];
					$rProfileOptions[17] = ['cmd' => '', 'val' => 1];
				}
				else if (0 < strlen($rData['resize'])) {
					if (!$rData['software_decoding']) {
						$rScale = '[0:v]scale=' . escapeshellcmd($rData['resize']) . '[bg];[bg][1:v]';
					}
					else {
						$rScale = 'scale=' . escapeshellcmd($rData['resize']);
					}

					$rProfileOptions[9] = ['cmd' => '', 'val' => $rData['resize']];
				}
				else if (0 < (int) $rData['deint']) {
					if (!$rData['software_decoding']) {
						$rScale = '[0:v]yadif[bg];[bg][1:v]';
					}
					else {
						$rScale = 'yadif';
					}

					$rProfileOptions[17] = ['cmd' => '', 'val' => 1];
				}
			}
			else {
				if (0 < strlen($rData['resize'])) {
					$rProfileOptions[9] = ['cmd' => '-vf scale=' . escapeshellcmd($rData['resize']), 'val' => $rData['resize']];
				}

				if (0 < (int) $rData['deint']) {
					$rProfileOptions[17] = ['cmd' => '-vf yadif', 'val' => 1];
				}
			}
		}

		if ($rComplex) {
			if (!empty($rScale) && (substr($rScale, strlen($rScale) - 1, 1) != ']')) {
				$rOverlay = ',' . $rOverlay;
			}
			else if (!empty($rScale)) {
				$rOverlay = ' ' . $rOverlay;
			}

			$rProfileOptions[16]['cmd'] = str_replace(['{SCALE}', '{OVERLAY}', '{LOGO}'], [$rScale, $rOverlay, $rLogoInput], '{LOGO} -filter_complex "{SCALE}{OVERLAY}"');
		}

		$rArray['profile_options'] = json_encode($rProfileOptions, JSON_UNESCAPED_UNICODE);

		if (isset($rData['edit'])) {
			$rArray['profile_id'] = $rData['edit'];
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `profiles`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processRadio($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_radio')) {
				exit();
			}

			$rArray = overwriteData(getStream($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_radio')) {
				exit();
			}

			$rArray = verifyPostTable('streams', $rData);
			$rArray['type'] = 4;
			$rArray['added'] = time();
			unset($rArray['id']);
		}
		if (isset($rData['days_to_restart']) && preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $rData['time_to_restart'])) {
			$rTimeArray = [
				'days' => [],
				'at'   => $rData['time_to_restart']
			];

			foreach ($rData['days_to_restart'] as $rID => $rDay) {
				$rTimeArray['days'][] = $rDay;
			}

			$rArray['auto_restart'] = $rTimeArray;
		}
		else {
			$rArray['auto_restart'] = '';
		}

		if (isset($rData['direct_source'])) {
			$rArray['direct_source'] = 1;
		}
		else {
			$rArray['direct_source'] = 0;
		}

		if (isset($rData['probesize_ondemand'])) {
			$rArray['probesize_ondemand'] = (int) $rData['probesize_ondemand'];
		}
		else {
			$rArray['probesize_ondemand'] = 128000;
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		$rImportStreams = [];

		if (0 < strlen($rData['stream_source'][0])) {
			$rImportArray = ['stream_source' => $rData['stream_source'], 'stream_icon' => $rArray['stream_icon'], 'stream_display_name' => $rArray['stream_display_name']];
			$rImportStreams[] = $rImportArray;
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rData];
		}

		if (0 < count($rImportStreams)) {
			$rBouquetCreate = [];

			foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
				$rPrepare = prepareArray([
					'bouquet_name'     => $rBouquet,
					'bouquet_channels' => [],
					'bouquet_movies'   => [],
					'bouquet_series'   => [],
					'bouquet_radios'   => []
				]);
				$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rBouquetID = self::$db->last_insert_id();
					$rBouquetCreate[$rBouquet] = $rBouquetID;
				}
			}

			$rCategoryCreate = [];

			foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
				$rPrepare = prepareArray(['category_type' => 'radio', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
				$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rCategoryID = self::$db->last_insert_id();
					$rCategoryCreate[$rCategory] = $rCategoryID;
				}
			}

			foreach ($rImportStreams as $rImportStream) {
				$rBouquets = [];

				foreach ($rData['bouquets'] as $rBouquet) {
					if (isset($rBouquetCreate[$rBouquet])) {
						$rBouquets[] = $rBouquetCreate[$rBouquet];
					}
					else if (is_numeric($rBouquet)) {
						$rBouquets[] = (int) $rBouquet;
					}
				}

				$rCategories = [];

				foreach ($rData['category_id'] as $rCategory) {
					if (isset($rCategoryCreate[$rCategory])) {
						$rCategories[] = $rCategoryCreate[$rCategory];
					}
					else if (is_numeric($rCategory)) {
						$rCategories[] = (int) $rCategory;
					}
				}

				$rArray['category_id'] = '[' . implode(',', array_map('intval', $rCategories)) . ']';
				$rImportArray = $rArray;

				if (self::$rSettings['download_images']) {
					$rImportStream['stream_icon'] = XCMS::downloadImage($rImportStream['stream_icon'], 4);
				}

				foreach (array_keys($rImportStream) as $rKey) {
					$rImportArray[$rKey] = $rImportStream[$rKey];
				}

				if (!isset($rData['edit'])) {
					$rImportArray['order'] = getNextOrder();
				}

				$rPrepare = prepareArray($rImportArray);
				$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rInsertID = self::$db->last_insert_id();
					$rStationExists = [];

					if (isset($rData['edit'])) {
						self::$db->query('SELECT `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` = ?;', $rInsertID);

						foreach (self::$db->get_rows() as $rRow) {
							$rStationExists[(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
						}
					}

					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];
							$rStreamsAdded[] = $rServerID;
							$rOD = (int) in_array($rServerID, $rData['on_demand'] ?: []);

							if ($rServer['parent'] == 'source') {
								$rParent = NULL;
							}
							else {
								$rParent = (int) $rServer['parent'];
							}

							if (isset($rStationExists[$rServerID])) {
								self::$db->query('UPDATE `streams_servers` SET `parent_id` = ?, `on_demand` = ? WHERE `server_stream_id` = ?;', $rParent, $rOD, $rStationExists[$rServerID]);
							}
							else {
								self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES(?, ?, ?, ?);', $rInsertID, $rServerID, $rParent, $rOD);
							}
						}
					}

					foreach ($rStationExists as $rServerID => $rDBID) {
						if (!in_array($rServerID, $rStreamsAdded)) {
							deleteStream($rInsertID, $rServerID, false, false);
						}
					}

					self::$db->query('DELETE FROM `streams_options` WHERE `stream_id` = ?;', $rInsertID);
					if (isset($rData['user_agent']) && (0 < strlen($rData['user_agent']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 1, ?);', $rInsertID, $rData['user_agent']);
					}
					if (isset($rData['http_proxy']) && (0 < strlen($rData['http_proxy']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 2, ?);', $rInsertID, $rData['http_proxy']);
					}
					if (isset($rData['cookie']) && (0 < strlen($rData['cookie']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 17, ?);', $rInsertID, $rData['cookie']);
					}
					if (isset($rData['headers']) && (0 < strlen($rData['headers']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 19, ?);', $rInsertID, $rData['headers']);
					}

					if ($rRestart) {
						APIRequest([
							'action'     => 'stream',
							'sub'        => 'start',
							'stream_ids' => [$rInsertID]
						]);
					}

					foreach ($rBouquets as $rBouquet) {
						addToBouquet('radio', $rBouquet, $rInsertID);
					}

					if (isset($rData['edit'])) {
						foreach (getBouquets() as $rBouquet) {
							if (!in_array($rBouquet['id'], $rBouquets)) {
								removeFromBouquet('radio', $rBouquet['id'], $rInsertID);
							}
						}
					}

					XCMS::updateStream($rInsertID);
					return [
						'status' => STATUS_SUCCESS,
						'data'   => ['insert_id' => $rInsertID]
					];
				}
				else {
					foreach ($rBouquetCreate as $rBouquet => $rID) {
						$db->query('DELETE FROM `bouquets` WHERE `id` = ?;', $rID);
					}

					foreach ($rCategoryCreate as $rCategory => $rID) {
						$db->query('DELETE FROM `streams_categories` WHERE `id` = ?;', $rID);
					}

					return ['status' => STATUS_FAILURE, 'data' => $rData];
				}
			}
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rData];
		}
	}

	static public function massEditRadios($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		if (isset($rData['c_direct_source'])) {
			if (isset($rData['direct_source'])) {
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_source'] = 0;
			}
		}

		if (isset($rData['c_custom_sid'])) {
			$rArray['custom_sid'] = $rData['custom_sid'];
		}

		$rStreamIDs = json_decode($rData['streams'], true);

		if (0 < count($rStreamIDs)) {
			$rCategoryMap = [];
			if (isset($rData['c_category_id']) && in_array($rData['category_id_type'], ['ADD' => true, 'DEL' => true])) {
				self::$db->query('SELECT `id`, `category_id` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					$rCategoryMap[$rRow['id']] = json_decode($rRow['category_id'], true) ?: [];
				}
			}

			$rDeleteServers = $rStreamExists = [];
			self::$db->query('SELECT `stream_id`, `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

			foreach (self::$db->get_rows() as $rRow) {
				$rStreamExists[(int) $rRow['stream_id']][(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
			}

			$rBouquets = getBouquets();
			$rAddBouquet = $rDelBouquet = [];
			$rAddQuery = '';

			foreach ($rStreamIDs as $rStreamID) {
				if (isset($rData['c_category_id'])) {
					$rCategories = array_map('intval', $rData['category_id']);

					if ($rData['category_id_type'] == 'ADD') {
						foreach ($rCategoryMap[$rStreamID] ?: [] as $rCategoryID) {
							if (!in_array($rCategoryID, $rCategories)) {
								$rCategories[] = $rCategoryID;
							}
						}
					}
					else if ($rData['category_id_type'] == 'DEL') {
						$rNewCategories = $rCategoryMap[$rStreamID];

						foreach ($rCategories as $rCategoryID) {
							if (($rKey = array_search($rCategoryID, $rNewCategories)) !== false) {
								unset($rNewCategories[$rKey]);
							}
						}

						$rCategories = $rNewCategories;
					}

					$rArray['category_id'] = '[' . implode(',', $rCategories) . ']';
				}

				$rPrepare = prepareArray($rArray);

				if (0 < count($rPrepare['data'])) {
					$rPrepare['data'][] = $rStreamID;
					$rQuery = 'UPDATE `streams` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
					self::$db->query($rQuery, ...$rPrepare['data']);
				}

				if (isset($rData['c_server_tree'])) {
					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);
					$rODTree = json_decode($rData['od_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];

							if (in_array($rData['server_type'], ['ADD' => true, 'SET' => true])) {
								$rOD = (int) in_array($rServerID, $rData['on_demand'] ?: []);

								if ($rServer['parent'] == 'source') {
									$rParent = NULL;
								}
								else {
									$rParent = (int) $rServer['parent'];
								}

								$rStreamsAdded[] = $rServerID;

								if (isset($rStreamExists[$rStreamID][$rServerID])) {
									self::$db->query('UPDATE `streams_servers` SET `parent_id` = ?, `on_demand` = ? WHERE `server_stream_id` = ?;', $rParent, $rOD, $rStreamExists[$rStreamID][$rServerID]);
								}
								else {
									$rAddQuery .= '(' . (int) $rStreamID . ', ' . (int) $rServerID . ', ' . ($rParent ?: 'NULL') . ', ' . $rOD . '),';
								}
							}
							else if (isset($rStreamExists[$rStreamID][$rServerID])) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}

					if ($rData['server_type'] == 'SET') {
						foreach ($rStreamExists[$rStreamID] as $rServerID => $rDBID) {
							if (!in_array($rServerID, $rStreamsAdded)) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}
				}

				if (isset($rData['c_bouquets'])) {
					if ($rData['bouquets_type'] == 'SET') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}

						foreach ($rBouquets as $rBouquet) {
							if (!in_array($rBouquet['id'], $rData['bouquets'])) {
								$rDelBouquet[$rBouquet['id']][] = $rStreamID;
							}
						}
					}
					else if ($rData['bouquets_type'] == 'ADD') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}
					}
					else if ($rData['bouquets_type'] == 'DEL') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rDelBouquet[$rBouquet][] = $rStreamID;
						}
					}
				}

				foreach ($rDeleteServers as $rServerID => $rDeleteIDs) {
					deleteStreamsByServer($rDeleteIDs, $rServerID, false);
				}
			}

			foreach ($rAddBouquet as $rBouquetID => $rAddIDs) {
				addToBouquet('radio', $rBouquetID, $rAddIDs);
			}

			foreach ($rDelBouquet as $rBouquetID => $rRemIDs) {
				removeFromBouquet('radio', $rBouquetID, $rRemIDs);
			}

			if (!empty($rAddQuery)) {
				$rAddQuery = rtrim($rAddQuery, ',');
				self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES ' . $rAddQuery . ';');
			}

			XCMS::updateStreams($rStreamIDs);

			if (isset($rData['restart_on_edit'])) {
				APIRequest(['action' => 'stream', 'sub' => 'start', 'stream_ids' => array_values($rStreamIDs)]);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processUser($rData, $rBypassAuth = false)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_reguser') && !$rBypassAuth) {
				exit();
			}

			$rUser = getRegisteredUser($rData['edit']);
			$rArray = overwriteData($rUser, $rData, ['password']);
		}
		else {
			if (!hasPermissions('adv', 'add_reguser') && !$rBypassAuth) {
				exit();
			}

			$rArray = verifyPostTable('users', $rData);
			$rArray['date_registered'] = time();
			unset($rArray['id']);
		}

		if (empty($rData['member_group_id'])) {
			return ['status' => STATUS_INVALID_GROUP, 'data' => $rData];
		}

		if (strlen($rData['username']) == 0) {
			$rArray['username'] = generateString(10);
		}

		if (checkExists('users', 'username', $rArray['username'], 'id', $rData['edit'])) {
			return ['status' => STATUS_EXISTS_USERNAME, 'data' => $rData];
		}

		if (0 < strlen($rData['password'])) {
			$rArray['password'] = cryptPassword($rData['password']);
		}

		$rOverride = [];

		foreach ($rData as $rKey => $rCredits) {
			if (substr($rKey, 0, 9) == 'override_') {
				$rID = (int) explode('override_', $rKey)[1];

				if (0 < strlen($rCredits)) {
					$rCredits = (int) $rCredits;
				}
				else {
					$rCredits = NULL;
				}

				if ($rCredits) {
					$rOverride[$rID] = ['assign' => 1, 'official_credits' => $rCredits];
				}
			}
		}
		if (!ctype_xdigit($rArray['api_key']) || (strlen($rArray['api_key']) != 32)) {
			$rArray['api_key'] = '';
		}

		$rArray['override_packages'] = json_encode($rOverride);
		if (isset($rUser) && ($rUser['credits'] != $rData['credits'])) {
			$rCreditsAdjustment = $rData['credits'] - $rUser['credits'];
			$rReason = $rData['credits_reason'];
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `users`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();

			if (isset($rCreditsAdjustment)) {
				self::$db->query('INSERT INTO `users_credits_logs`(`target_id`, `admin_id`, `amount`, `date`, `reason`) VALUES(?, ?, ?, ?, ?);', $rInsertID, self::$rUserInfo['id'], $rCreditsAdjustment, time(), $rReason);
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processRTMPIP($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getRTMPIP($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('rtmp_ips', $rData);
			unset($rArray['id']);
		}

		foreach (['push', 'pull'] as $rSelection) {
			if (isset($rData[$rSelection])) {
				$rArray[$rSelection] = 1;
			}
			else {
				$rArray[$rSelection] = 0;
			}
		}

		if (!filter_var($rData['ip'], FILTER_VALIDATE_IP)) {
			return ['status' => STATUS_INVALID_IP, 'data' => $rData];
		}

		if (checkExists('rtmp_ips', 'ip', $rData['ip'], 'id', $rArray['id'])) {
			return ['status' => STATUS_EXISTS_IP, 'data' => $rData];
		}

		if (strlen($rData['password']) == 0) {
			$rArray['password'] = generateString(16);
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `rtmp_ips`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function importSeries($rData)
	{
		if (!hasPermissions('adv', 'import_movies')) {
			exit();
		}

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rPostData = $rData;

		foreach (['read_native', 'movie_symlink', 'direct_source', 'direct_proxy', 'remove_subtitles'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rData[$rKey] = 1;
			}
			else {
				$rData[$rKey] = 0;
			}
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		$rStreamDatabase = [];
		self::$db->query('SELECT `stream_source` FROM `streams` WHERE `type` = 5;');

		foreach (self::$db->get_rows() as $rRow) {
			foreach (json_decode($rRow['stream_source'], true) as $rSource) {
				if (0 < strlen($rSource)) {
					$rStreamDatabase[] = $rSource;
				}
			}
		}

		$rImportStreams = [];

		if (!empty($_FILES['m3u_file']['tmp_name'])) {
			$rFile = '';
			if (!empty($_FILES['m3u_file']['tmp_name']) && (strtolower(pathinfo(explode('?', $_FILES['m3u_file']['name'])[0], PATHINFO_EXTENSION)) == 'm3u')) {
				$rFile = file_get_contents($_FILES['m3u_file']['tmp_name']);
			}

			preg_match_all('/(?P<tag>#EXTINF:[-1,0])|(?:(?P<prop_key>[-a-z]+)=\\"(?P<prop_val>[^"]+)")|(?<name>,[^\\r\\n]+)|(?<url>http[^\\s]*:\\/\\/.*\\/.*)/', $rFile, $rMatches);
			$rResults = [];
			$rIndex = -1;

			for ($i = 0; $i < count($rMatches[0]); $i++) {
				$rItem = $rMatches[0][$i];

				if (!empty($rMatches['tag'][$i])) {
					++$rIndex;
					continue;
				}

				if (!empty($rMatches['prop_key'][$i])) {
					$rResults[$rIndex][$rMatches['prop_key'][$i]] = trim($rMatches['prop_val'][$i]);
					continue;
				}

				if (!empty($rMatches['name'][$i])) {
					$rResults[$rIndex]['name'] = trim(substr($rItem, 1));
					continue;
				}

				if (!empty($rMatches['url'][$i])) {
					$rResults[$rIndex]['url'] = str_replace(' ', '%20', trim($rItem));
				}
			}

			foreach ($rResults as $rResult) {
				if (!empty($rResult['url']) && !in_array($rResult['url'], $rStreamDatabase)) {
					$rPathInfo = pathinfo(explode('?', $rResult['url'])[0]);

					if (empty($rPathInfo['extension'])) {
						$rPathInfo['extension'] = $rData['target_container'] ?: 'mp4';
					}
					$rImportStreams[] = ['url' => $rResult['url'], 'title' => $rResult['name'] ?: '', 'container' => $rData['movie_symlink'] || $rData['direct_source'] ? $rPathInfo['extension'] : $rData['target_container']];
				}
			}
		}
		else if (!empty($rData['import_folder'])) {
			$rParts = explode(':', $rData['import_folder']);

			if (is_numeric($rParts[1])) {
				if (isset($rData['scan_recursive'])) {
					$rFiles = scanRecursive((int) $rParts[1], $rParts[2], ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'wmv', 'mov', 'ts']);
				}
				else {
					$rFiles = [];

					foreach (listDir((int) $rParts[1], rtrim($rParts[2], '/'), ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'wmv', 'mov', 'ts'])['files'] as $rFile) {
						$rFiles[] = rtrim($rParts[2], '/') . '/' . $rFile;
					}
				}

				foreach ($rFiles as $rFile) {
					$rFilePath = 's:' . (int) $rParts[1] . ':' . $rFile;
					if (!empty($rFilePath) && !in_array($rFilePath, $rStreamDatabase)) {
						$rPathInfo = pathinfo($rFile);

						if (empty($rPathInfo['extension'])) {
							$rPathInfo['extension'] = $rData['target_container'] ?: 'mp4';
						}
						$rImportStreams[] = ['url' => $rFilePath, 'title' => $rPathInfo['filename'], 'container' => $rData['movie_symlink'] || $rData['direct_source'] ? $rPathInfo['extension'] : $rData['target_container']];
					}
				}
			}
		}

		$rSeriesCategories = array_keys(getCategories('series'));

		if (0 < count($rImportStreams)) {
			$rBouquets = [];

			foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
				$rPrepare = prepareArray([
					'bouquet_name'     => $rBouquet,
					'bouquet_channels' => [],
					'bouquet_movies'   => [],
					'bouquet_series'   => [],
					'bouquet_radios'   => []
				]);
				$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rBouquets[] = self::$db->last_insert_id();
				}
			}

			foreach ($rData['bouquets'] as $rBouquetID) {
				if (is_numeric($rBouquetID) && in_array($rBouquetID, array_keys(XCMS::$rBouquets))) {
					$rBouquets[] = (int) $rBouquetID;
				}
			}

			unset($rData['bouquets']);
			unset($rData['bouquet_create_list']);
			$rCategories = [];

			foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
				$rPrepare = prepareArray(['category_type' => 'series', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
				$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rCategories[] = self::$db->last_insert_id();
				}
			}

			foreach ($rData['category_id'] as $rCategoryID) {
				if (is_numeric($rCategoryID) && in_array($rCategoryID, $rSeriesCategories)) {
					$rCategories[] = (int) $rCategoryID;
				}
			}

			unset($rData['category_id']);
			unset($rData['category_create_list']);
			$rServerIDs = [];

			foreach (json_decode($rData['server_tree_data'], true) as $rServer) {
				if ($rServer['parent'] != '#') {
					$rServerIDs[] = (int) $rServer['id'];
				}
			}

			$rWatchCategories = [1 => getWatchCategories(1), 2 => getWatchCategories(2)];

			foreach ($rImportStreams as $rImportStream) {
				$rData = [
					'import'               => true,
					'type'                 => 'series',
					'title'                => $rImportStream['title'],
					'file'                 => $rImportStream['url'],
					'subtitles'            => [],
					'servers'              => $rServerIDs,
					'fb_category_id'       => $rCategories,
					'fb_bouquets'          => $rBouquets,
					'disable_tmdb'         => false,
					'ignore_no_match'      => false,
					'bouquets'             => [],
					'category_id'          => [],
					'language'             => XCMS::$rSettings['tmdb_language'],
					'watch_categories'     => $rWatchCategories,
					'read_native'          => $rData['read_native'],
					'movie_symlink'        => $rData['movie_symlink'],
					'remove_subtitles'     => $rData['remove_subtitles'],
					'direct_source'        => $rData['direct_source'],
					'direct_proxy'         => $rData['direct_proxy'],
					'auto_encode'          => $rRestart,
					'auto_upgrade'         => false,
					'fallback_title'       => false,
					'ffprobe_input'        => false,
					'transcode_profile_id' => $rData['transcode_profile_id'],
					'target_container'     => $rImportStream['container'],
					'max_genres'           => (int) XCMS::$rSettings['max_genres'],
					'duplicate_tmdb'       => true
				];
				$rCommand = '/usr/bin/timeout 300 ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/watch_item.php "' . base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . '" > /dev/null 2>/dev/null &';
				shell_exec($rCommand);
			}

			return ['status' => STATUS_SUCCESS];
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rPostData];
		}
	}

	static public function importMovies($rData)
	{
		if (!hasPermissions('adv', 'import_movies')) {
			exit();
		}

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rPostData = $rData;

		foreach (['read_native', 'movie_symlink', 'direct_source', 'direct_proxy', 'remove_subtitles'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rData[$rKey] = 1;
			}
			else {
				$rData[$rKey] = 0;
			}
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		if (isset($rData['disable_tmdb'])) {
			$rDisableTMDB = true;
		}
		else {
			$rDisableTMDB = false;
		}

		if (isset($rData['ignore_no_match'])) {
			$rIgnoreMatch = true;
		}
		else {
			$rIgnoreMatch = false;
		}

		$rStreamDatabase = [];
		self::$db->query('SELECT `stream_source` FROM `streams` WHERE `type` = 2;');

		foreach (self::$db->get_rows() as $rRow) {
			foreach (json_decode($rRow['stream_source'], true) as $rSource) {
				if (0 < strlen($rSource)) {
					$rStreamDatabase[] = $rSource;
				}
			}
		}

		$rImportStreams = [];

		if (!empty($_FILES['m3u_file']['tmp_name'])) {
			$rFile = '';
			if (!empty($_FILES['m3u_file']['tmp_name']) && (strtolower(pathinfo(explode('?', $_FILES['m3u_file']['name'])[0], PATHINFO_EXTENSION)) == 'm3u')) {
				$rFile = file_get_contents($_FILES['m3u_file']['tmp_name']);
			}

			preg_match_all('/(?P<tag>#EXTINF:[-1,0])|(?:(?P<prop_key>[-a-z]+)=\\"(?P<prop_val>[^"]+)")|(?<name>,[^\\r\\n]+)|(?<url>http[^\\s]*:\\/\\/.*\\/.*)/', $rFile, $rMatches);
			$rResults = [];
			$rIndex = -1;

			for ($i = 0; $i < count($rMatches[0]); $i++) {
				$rItem = $rMatches[0][$i];

				if (!empty($rMatches['tag'][$i])) {
					++$rIndex;
					continue;
				}

				if (!empty($rMatches['prop_key'][$i])) {
					$rResults[$rIndex][$rMatches['prop_key'][$i]] = trim($rMatches['prop_val'][$i]);
					continue;
				}

				if (!empty($rMatches['name'][$i])) {
					$rResults[$rIndex]['name'] = trim(substr($rItem, 1));
					continue;
				}

				if (!empty($rMatches['url'][$i])) {
					$rResults[$rIndex]['url'] = str_replace(' ', '%20', trim($rItem));
				}
			}

			foreach ($rResults as $rResult) {
				if (!empty($rResult['url']) && !in_array($rResult['url'], $rStreamDatabase)) {
					$rPathInfo = pathinfo(explode('?', $rResult['url'])[0]);

					if (empty($rPathInfo['extension'])) {
						$rPathInfo['extension'] = $rData['target_container'] ?: 'mp4';
					}
					$rImportStreams[] = ['url' => $rResult['url'], 'title' => $rResult['name'] ?: '', 'container' => $rData['movie_symlink'] || $rData['direct_source'] ? $rPathInfo['extension'] : $rData['target_container']];
				}
			}
		}
		else if (!empty($rData['import_folder'])) {
			$rParts = explode(':', $rData['import_folder']);

			if (is_numeric($rParts[1])) {
				if (isset($rData['scan_recursive'])) {
					$rFiles = scanRecursive((int) $rParts[1], $rParts[2], ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'wmv', 'mov', 'ts']);
				}
				else {
					$rFiles = [];

					foreach (listDir((int) $rParts[1], rtrim($rParts[2], '/'), ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'wmv', 'mov', 'ts'])['files'] as $rFile) {
						$rFiles[] = rtrim($rParts[2], '/') . '/' . $rFile;
					}
				}

				foreach ($rFiles as $rFile) {
					$rFilePath = 's:' . (int) $rParts[1] . ':' . $rFile;
					if (!empty($rFilePath) && !in_array($rFilePath, $rStreamDatabase)) {
						$rPathInfo = pathinfo($rFile);

						if (empty($rPathInfo['extension'])) {
							$rPathInfo['extension'] = $rData['target_container'] ?: 'mp4';
						}
						$rImportStreams[] = ['url' => $rFilePath, 'title' => $rPathInfo['filename'], 'container' => $rData['movie_symlink'] || $rData['direct_source'] ? $rPathInfo['extension'] : $rData['target_container']];
					}
				}
			}
		}

		$rMovieCategories = array_keys(getCategories('movie'));

		if (0 < count($rImportStreams)) {
			$rBouquets = [];

			foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
				$rPrepare = prepareArray([
					'bouquet_name'     => $rBouquet,
					'bouquet_channels' => [],
					'bouquet_movies'   => [],
					'bouquet_series'   => [],
					'bouquet_radios'   => []
				]);
				$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rBouquets[] = self::$db->last_insert_id();
				}
			}

			foreach ($rData['bouquets'] as $rBouquetID) {
				if (is_numeric($rBouquetID) && in_array($rBouquetID, array_keys(XCMS::$rBouquets))) {
					$rBouquets[] = (int) $rBouquetID;
				}
			}

			unset($rData['bouquets']);
			unset($rData['bouquet_create_list']);
			$rCategories = [];

			foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
				$rPrepare = prepareArray(['category_type' => 'movie', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
				$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rCategories[] = self::$db->last_insert_id();
				}
			}

			foreach ($rData['category_id'] as $rCategoryID) {
				if (is_numeric($rCategoryID) && in_array($rCategoryID, $rMovieCategories)) {
					$rCategories[] = (int) $rCategoryID;
				}
			}

			unset($rData['category_id']);
			unset($rData['category_create_list']);
			$rServerIDs = [];

			foreach (json_decode($rData['server_tree_data'], true) as $rServer) {
				if ($rServer['parent'] != '#') {
					$rServerIDs[] = (int) $rServer['id'];
				}
			}

			$rWatchCategories = [1 => getWatchCategories(1), 2 => getWatchCategories(2)];

			foreach ($rImportStreams as $rImportStream) {
				$rData = [
					'import'               => true,
					'type'                 => 'movie',
					'title'                => $rImportStream['title'],
					'file'                 => $rImportStream['url'],
					'subtitles'            => [],
					'servers'              => $rServerIDs,
					'fb_category_id'       => $rCategories,
					'fb_bouquets'          => $rBouquets,
					'disable_tmdb'         => $rDisableTMDB,
					'ignore_no_match'      => $rIgnoreMatch,
					'bouquets'             => [],
					'category_id'          => [],
					'language'             => XCMS::$rSettings['tmdb_language'],
					'watch_categories'     => $rWatchCategories,
					'read_native'          => $rData['read_native'],
					'movie_symlink'        => $rData['movie_symlink'],
					'remove_subtitles'     => $rData['remove_subtitles'],
					'direct_source'        => $rData['direct_source'],
					'direct_proxy'         => $rData['direct_proxy'],
					'auto_encode'          => $rRestart,
					'auto_upgrade'         => false,
					'fallback_title'       => false,
					'ffprobe_input'        => false,
					'transcode_profile_id' => $rData['transcode_profile_id'],
					'target_container'     => $rImportStream['container'],
					'max_genres'           => (int) XCMS::$rSettings['max_genres'],
					'duplicate_tmdb'       => true
				];
				$rCommand = '/usr/bin/timeout 300 ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/watch_item.php "' . base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . '" > /dev/null 2>/dev/null &';
				shell_exec($rCommand);
			}

			return ['status' => STATUS_SUCCESS];
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rPostData];
		}
	}

	static public function processSeries($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_series')) {
				exit();
			}

			$rArray = overwriteData(getSerie($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_series')) {
				exit();
			}

			$rArray = verifyPostTable('streams_series', $rData);
			unset($rArray['id']);
		}

		if (self::$rSettings['download_images']) {
			$rData['cover'] = XCMS::downloadImage($rData['cover'], 2);
			$rData['backdrop_path'] = XCMS::downloadImage($rData['backdrop_path']);
		}

		if (strlen($rData['backdrop_path']) == 0) {
			$rArray['backdrop_path'] = [];
		}
		else {
			$rArray['backdrop_path'] = [$rData['backdrop_path']];
		}

		$rArray['last_modified'] = time();
		$rArray['cover'] = $rData['cover'];
		$rArray['cover_big'] = $rData['cover'];
		$rBouquetCreate = [];

		foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
			$rPrepare = prepareArray([
				'bouquet_name'     => $rBouquet,
				'bouquet_channels' => [],
				'bouquet_movies'   => [],
				'bouquet_series'   => [],
				'bouquet_radios'   => []
			]);
			$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rBouquetID = self::$db->last_insert_id();
				$rBouquetCreate[$rBouquet] = $rBouquetID;
			}
		}

		$rCategoryCreate = [];

		foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
			$rPrepare = prepareArray(['category_type' => 'series', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
			$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rCategoryID = self::$db->last_insert_id();
				$rCategoryCreate[$rCategory] = $rCategoryID;
			}
		}

		$rBouquets = [];

		foreach ($rData['bouquets'] as $rBouquet) {
			if (isset($rBouquetCreate[$rBouquet])) {
				$rBouquets[] = $rBouquetCreate[$rBouquet];
			}
			else if (is_numeric($rBouquet)) {
				$rBouquets[] = (int) $rBouquet;
			}
		}

		$rCategories = [];

		foreach ($rData['category_id'] as $rCategory) {
			if (isset($rCategoryCreate[$rCategory])) {
				$rCategories[] = $rCategoryCreate[$rCategory];
			}
			else if (is_numeric($rCategory)) {
				$rCategories[] = (int) $rCategory;
			}
		}

		$rArray['category_id'] = '[' . implode(',', array_map('intval', $rCategories)) . ']';
		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `streams_series`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			updateSeriesAsync($rInsertID);

			foreach ($rBouquets as $rBouquet) {
				addToBouquet('series', $rBouquet, $rInsertID);
			}

			foreach (getBouquets() as $rBouquet) {
				if (!in_array($rBouquet['id'], $rBouquets)) {
					removeFromBouquet('series', $rBouquet['id'], $rInsertID);
				}
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			foreach ($rBouquetCreate as $rBouquet => $rID) {
				$db->query('DELETE FROM `bouquets` WHERE `id` = ?;', $rID);
			}

			foreach ($rCategoryCreate as $rCategory => $rID) {
				$db->query('DELETE FROM `streams_categories` WHERE `id` = ?;', $rID);
			}

			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function massEditSeries($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];
		$rSeriesIDs = json_decode($rData['series'], true);

		if (0 < count($rSeriesIDs)) {
			$rCategoryMap = [];
			if (isset($rData['c_category_id']) && in_array($rData['category_id_type'], ['ADD' => true, 'DEL' => true])) {
				self::$db->query('SELECT `id`, `category_id` FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rSeriesIDs)) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					$rCategoryMap[$rRow['id']] = json_decode($rRow['category_id'], true) ?: [];
				}
			}

			$rBouquets = getBouquets();
			$rAddBouquet = $rDelBouquet = [];

			foreach ($rSeriesIDs as $rSeriesID) {
				if (isset($rData['c_category_id'])) {
					$rCategories = array_map('intval', $rData['category_id']);

					if ($rData['category_id_type'] == 'ADD') {
						foreach ($rCategoryMap[$rSeriesID] ?: [] as $rCategoryID) {
							if (!in_array($rCategoryID, $rCategories)) {
								$rCategories[] = $rCategoryID;
							}
						}
					}
					else if ($rData['category_id_type'] == 'DEL') {
						$rNewCategories = $rCategoryMap[$rSeriesID];

						foreach ($rCategories as $rCategoryID) {
							if (($rKey = array_search($rCategoryID, $rNewCategories)) !== false) {
								unset($rNewCategories[$rKey]);
							}
						}

						$rCategories = $rNewCategories;
					}

					$rArray['category_id'] = '[' . implode(',', $rCategories) . ']';
				}

				$rPrepare = prepareArray($rArray);

				if (0 < count($rPrepare['data'])) {
					$rPrepare['data'][] = $rSeriesID;
					$rQuery = 'UPDATE `streams_series` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
					self::$db->query($rQuery, ...$rPrepare['data']);
				}

				if (isset($rData['c_bouquets'])) {
					if ($rData['bouquets_type'] == 'SET') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rSeriesID;
						}

						foreach ($rBouquets as $rBouquet) {
							if (!in_array($rBouquet['id'], $rData['bouquets'])) {
								$rDelBouquet[$rBouquet['id']][] = $rSeriesID;
							}
						}
					}
					else if ($rData['bouquets_type'] == 'ADD') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rSeriesID;
						}
					}
					else if ($rData['bouquets_type'] == 'DEL') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rDelBouquet[$rBouquet][] = $rSeriesID;
						}
					}
				}
			}

			foreach ($rAddBouquet as $rBouquetID => $rAddIDs) {
				addToBouquet('series', $rBouquetID, $rAddIDs);
			}

			foreach ($rDelBouquet as $rBouquetID => $rRemIDs) {
				removeFromBouquet('series', $rBouquetID, $rRemIDs);
			}

			if (isset($rData['reprocess_tmdb'])) {
				foreach ($rSeriesIDs as $rSeriesID) {
					if (0 < (int) $rSeriesID) {
						self::$db->query('INSERT INTO `watch_refresh`(`type`, `stream_id`, `status`) VALUES(2, ?, 0);', $rSeriesID);
					}
				}
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processServer($rData)
	{
		if (!hasPermissions('adv', 'edit_server')) {
			exit();
		}

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rServer = getStreamingServersByID($rData['edit']);

		if (!$rServer) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = verifyPostTable('servers', $rData, true);
		$rPorts = [
			'http'  => [],
			'https' => []
		];

		foreach ($rData['http_broadcast_ports'] as $rPort) {
			if (is_numeric($rPort) && (80 <= $rPort) && ($rPort <= 65535) && !in_array($rPort, $rPorts['http'] ?: []) && ($rPort != $rData['rtmp_port'])) {
				$rPorts['http'][] = $rPort;
			}
		}

		$rPorts['http'] = array_unique($rPorts['http']);
		unset($rData['http_broadcast_ports']);

		foreach ($rData['https_broadcast_ports'] as $rPort) {
			if (is_numeric($rPort) && (80 <= $rPort) && ($rPort <= 65535) && !in_array($rPort, $rPorts['http'] ?: []) && !in_array($rPort, $rPorts['https'] ?: []) && ($rPort != $rData['rtmp_port'])) {
				$rPorts['https'][] = $rPort;
			}
		}

		$rPorts['https'] = array_unique($rPorts['https']);
		unset($rData['https_broadcast_ports']);
		$rArray['http_broadcast_port'] = NULL;
		$rArray['http_ports_add'] = NULL;

		if (0 < count($rPorts['http'])) {
			$rArray['http_broadcast_port'] = $rPorts['http'][0];

			if (1 < count($rPorts['http'])) {
				$rArray['http_ports_add'] = implode(',', array_slice($rPorts['http'], 1, count($rPorts['http']) - 1));
			}
		}

		$rArray['https_broadcast_port'] = NULL;
		$rArray['https_ports_add'] = NULL;

		if (0 < count($rPorts['https'])) {
			$rArray['https_broadcast_port'] = $rPorts['https'][0];

			if (1 < count($rPorts['https'])) {
				$rArray['https_ports_add'] = implode(',', array_slice($rPorts['https'], 1, count($rPorts['https']) - 1));
			}
		}

		foreach (['enable_gzip', 'timeshift_only', 'enable_https', 'random_ip', 'enable_geoip', 'enable_isp', 'enabled', 'enable_proxy', 'enable_protection', 'enable_rtmp', 'log_sql', 'vod'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		if ($rServer['is_main']) {
			$rArray['enabled'] = 1;
		}

		if (isset($rData['geoip_countries'])) {
			$rArray['geoip_countries'] = [];

			foreach ($rData['geoip_countries'] as $rCountry) {
				$rArray['geoip_countries'][] = $rCountry;
			}
		}
		else {
			$rArray['geoip_countries'] = [];
		}

		if (isset($rData['isp_names'])) {
			$rArray['isp_names'] = [];

			foreach ($rData['isp_names'] as $rISP) {
				$rArray['isp_names'][] = strtolower(trim(preg_replace('/[^A-Za-z0-9 ]/', '', $rISP)));
			}
		}
		else {
			$rArray['isp_names'] = [];
		}

		if (isset($rData['domain_name'])) {
			$rArray['domain_name'] = implode(',', $rData['domain_name']);
		}
		else {
			$rArray['domain_name'] = '';
		}
		if ((strlen($rData['server_ip']) == 0) || !filter_var($rData['server_ip'], FILTER_VALIDATE_IP)) {
			return ['status' => STATUS_INVALID_IP, 'data' => $rData];
		}
		if ((0 < strlen($rData['private_ip'])) && !filter_var($rData['private_ip'], FILTER_VALIDATE_IP)) {
			return ['status' => STATUS_INVALID_IP, 'data' => $rData];
		}

		$rArray['total_services'] = $rData['total_services'];
		$rPrepare = prepareArray($rArray);
		$rPrepare['data'][] = $rData['edit'];
		$rQuery = 'UPDATE `servers` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = $rData['edit'];
			$rPorts = [
				'http'  => [],
				'https' => []
			];

			foreach (array_merge([(int) $rArray['http_broadcast_port']], explode(',', $rArray['http_ports_add'])) as $rPort) {
				if (is_numeric($rPort) && (0 < $rPort) && ($rPort <= 65535)) {
					$rPorts['http'][] = (int) $rPort;
				}
			}

			foreach (array_merge([(int) $rArray['https_broadcast_port']], explode(',', $rArray['https_ports_add'])) as $rPort) {
				if (is_numeric($rPort) && (0 < $rPort) && ($rPort <= 65535)) {
					$rPorts['https'][] = (int) $rPort;
				}
			}

			changePort($rInsertID, 0, $rPorts['http'], false);
			changePort($rInsertID, 1, $rPorts['https'], false);
			changePort($rInsertID, 2, [$rArray['rtmp_port']], false);
			setServices($rInsertID, (int) $rArray['total_services'], true);

			if (!empty($rArray['governor'])) {
				setGovernor($rInsertID, $rArray['governor']);
			}

			if (!empty($rArray['sysctl'])) {
				setSysctl($rInsertID, $rArray['sysctl']);
			}

			if (file_exists(CACHE_TMP_PATH . 'servers')) {
				unlink(CACHE_TMP_PATH . 'servers');
			}

			$rFS = getFreeSpace($rInsertID);
			$rMounted = false;

			foreach ($rFS as $rMount) {
				if (rtrim(STREAMS_PATH, '/') == $rMount['mount']) {
					$rMounted = true;
					break;
				}
			}
			if ($rData['disable_ramdisk'] && $rMounted) {
				self::$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rInsertID, time(), json_encode(['action' => 'disable_ramdisk']));
			}
			else if (!$rData['disable_ramdisk'] && !$rMounted) {
				self::$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rInsertID, time(), json_encode(['action' => 'enable_ramdisk']));
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processProxy($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (!hasPermissions('adv', 'edit_server')) {
			exit();
		}

		$rArray = overwriteData(getStreamingServersByID($rData['edit']), $rData);

		foreach (['enable_https', 'random_ip', 'enable_geoip', 'enabled'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = true;
			}
			else {
				$rArray[$rKey] = false;
			}
		}

		if (isset($rData['geoip_countries'])) {
			$rArray['geoip_countries'] = [];

			foreach ($rData['geoip_countries'] as $rCountry) {
				$rArray['geoip_countries'][] = $rCountry;
			}
		}
		else {
			$rArray['geoip_countries'] = [];
		}

		if (isset($rData['domain_name'])) {
			$rArray['domain_name'] = implode(',', $rData['domain_name']);
		}
		else {
			$rArray['domain_name'] = '';
		}
		if ((strlen($rData['server_ip']) == 0) || !filter_var($rData['server_ip'], FILTER_VALIDATE_IP)) {
			return ['status' => STATUS_INVALID_IP, 'data' => $rData];
		}

		if (checkExists('servers', 'server_ip', $rData['server_ip'], 'id', $rArray['id'])) {
			return ['status' => STATUS_EXISTS_IP, 'data' => $rData];
		}

		$rArray['server_type'] = 1;
		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `servers`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();

			if (file_exists(CACHE_TMP_PATH . 'servers')) {
				unlink(CACHE_TMP_PATH . 'servers');
			}

			if (file_exists(CACHE_TMP_PATH . 'proxy_servers')) {
				unlink(CACHE_TMP_PATH . 'proxy_servers');
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function installServer($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (!hasPermissions('adv', 'add_server')) {
			exit();
		}

		if (isset($rData['update_sysctl'])) {
			$rUpdateSysctl = 1;
		}
		else {
			$rUpdateSysctl = 0;
		}

		if (isset($rData['use_private_ip'])) {
			$rPrivateIP = 1;
		}
		else {
			$rPrivateIP = 0;
		}

		if ($rData['type'] == 1) {
			$rParentIDs = [];

			foreach (json_decode($rData['parent_id'], true) as $rServerID) {
				if (self::$rServers[$rServerID]['server_type'] == 0) {
					$rParentIDs[] = (int) $rServerID;
				}
			}
		}

		if (isset($rData['edit'])) {
			if (isset($rData['update_only'])) {
				$rData['type'] = 3;
			}

			if ($rData['type'] == 1) {
				$rServer = self::$rProxyServers[$rData['edit']];
			}
			else {
				$rServer = self::$rServers[$rData['edit']];
			}

			if ($rServer) {
				self::$db->query('UPDATE `servers` SET `status` = 3, `parent_id` = ? WHERE `id` = ?;', '[' . implode(',', $rParentIDs) . ']', $rServer['id']);

				if ($rData['type'] == 1) {
					$rCommand = PHP_BIN . ' ' . CLI_PATH . 'balancer.php ' . (int) $rData['type'] . ' ' . (int) $rServer['id'] . ' ' . (int) $rData['ssh_port'] . ' ' . escapeshellarg($rData['root_username']) . ' ' . escapeshellarg($rData['root_password']) . ' ' . (int) $rData['http_broadcast_port'] . ' ' . (int) $rData['https_broadcast_port'] . ' ' . (int) $rUpdateSysctl . ' ' . (int) $rPrivateIP . ' "' . json_encode($rParentIDs) . '" > "' . BIN_PATH . 'install/' . (int) $rServer['id'] . '.install" 2>/dev/null &';
				}
				else {
					$rCommand = PHP_BIN . ' ' . CLI_PATH . 'balancer.php ' . (int) $rData['type'] . ' ' . (int) $rServer['id'] . ' ' . (int) $rData['ssh_port'] . ' ' . escapeshellarg($rData['root_username']) . ' ' . escapeshellarg($rData['root_password']) . ' 80 443 ' . (int) $rUpdateSysctl . ' > "' . BIN_PATH . 'install/' . (int) $rServer['id'] . '.install" 2>/dev/null &';
				}

				shell_exec($rCommand);
				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rServer['id']]
				];
			}

			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
		else {
			$rArray = verifyPostTable('servers', $rData);
			$rArray['status'] = 3;
			unset($rArray['id']);
			if ((strlen($rArray['server_ip']) == 0) || !filter_var($rArray['server_ip'], FILTER_VALIDATE_IP)) {
				return ['status' => STATUS_INVALID_IP, 'data' => $rData];
			}

			if ($rData['type'] == 1) {
				$rArray['server_type'] = 1;
				$rArray['parent_id'] = '[' . implode(',', $rParentIDs) . ']';
			}
			else {
				$rArray['server_type'] = 0;
			}

			$rArray['network_interface'] = 'auto';
			$rPrepare = prepareArray($rArray);
			$rQuery = 'INSERT INTO `servers`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();

				if ($rArray['server_type'] == 0) {
					Xcms\Functions::grantPrivileges($rArray['server_ip']);
				}

				if ($rData['type'] == 1) {
					$rCommand = PHP_BIN . ' ' . CLI_PATH . 'balancer.php ' . (int) $rData['type'] . ' ' . (int) $rInsertID . ' ' . (int) $rData['ssh_port'] . ' ' . escapeshellarg($rData['root_username']) . ' ' . escapeshellarg($rData['root_password']) . ' ' . (int) $rData['http_broadcast_port'] . ' ' . (int) $rData['https_broadcast_port'] . ' ' . (int) $rUpdateSysctl . ' ' . (int) $rPrivateIP . ' "' . json_encode($rParentIDs) . '" > "' . BIN_PATH . 'install/' . (int) $rInsertID . '.install" 2>/dev/null &';
				}
				else {
					$rCommand = PHP_BIN . ' ' . CLI_PATH . 'balancer.php ' . (int) $rData['type'] . ' ' . (int) $rInsertID . ' ' . (int) $rData['ssh_port'] . ' ' . escapeshellarg($rData['root_username']) . ' ' . escapeshellarg($rData['root_password']) . ' 80 443 ' . (int) $rUpdateSysctl . ' > "' . BIN_PATH . 'install/' . (int) $rInsertID . '.install" 2>/dev/null &';
				}

				shell_exec($rCommand);
				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else {
				return ['status' => STATUS_FAILURE, 'data' => $rData];
			}
		}
	}

	static public function editSettings($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		foreach (['user_agent', 'http_proxy', 'cookie', 'headers'] as $rKey) {
			self::$db->query('UPDATE `streams_arguments` SET `argument_default_value` = ? WHERE `argument_key` = ?;', $rData[$rKey] ?: NULL, $rKey);
			unset($rData[$rKey]);
		}

		$rArray = verifyPostTable('settings', $rData, true);

		foreach (['php_loopback', 'restreamer_bypass_proxy', 'request_prebuffer', 'modal_edit', 'group_buttons', 'enable_search', 'on_demand_checker', 'ondemand_balance_equal', 'disable_mag_token', 'allow_cdn_access', 'dts_legacy_ffmpeg', 'mag_load_all_channels', 'disable_xmltv_restreamer', 'disable_playlist_restreamer', 'ffmpeg_warnings', 'auto_send_logs', 'reseller_ssl_domain', 'extract_subtitles', 'show_category_duplicates', 'vod_sort_newest', 'header_stats', 'mag_keep_extension', 'keep_protocol', 'read_native_hls', 'player_allow_playlist', 'player_allow_bouquet', 'player_hide_incompatible', 'player_allow_hevc', 'force_epg_timezone', 'check_vod', 'ignore_keyframes', 'save_login_logs', 'save_restart_logs', 'mag_legacy_redirect', 'restrict_playlists', 'monitor_connection_status', 'kill_rogue_ffmpeg', 'show_images', 'on_demand_instant_off', 'on_demand_failure_exit', 'playlist_from_mysql', 'ignore_invalid_users', 'legacy_mag_auth', 'ministra_allow_blank', 'block_proxies', 'block_streaming_servers', 'ip_subnet_match', 'debug_show_errors', 'restart_php_fpm', 'restream_deny_unauthorised', 'api_probe', 'legacy_panel_api', 'hide_failures', 'verify_host', 'encrypt_playlist', 'encrypt_playlist_restreamer', 'mag_disable_ssl', 'legacy_get', 'legacy_xmltv', 'save_closed_connection', 'show_tickets', 'stream_logs_save', 'client_logs_save', 'streams_grouped', 'cloudflare', 'cleanup', 'dashboard_stats', 'dashboard_status', 'dashboard_map', 'dashboard_display_alt', 'enable_epg_api', 'recaptcha_enable', 'ip_logout', 'disable_player_api', 'disable_playlist', 'disable_xmltv', 'disable_enigma2', 'disable_ministra', 'enable_isp_lock', 'block_svp', 'disable_ts', 'disable_ts_allow_restream', 'disable_hls', 'disable_hls_allow_restream', 'disable_rtmp', 'disable_rtmp_allow_restream', 'case_sensitive_line', 'county_override_1st', 'disallow_2nd_ip_con', 'use_mdomain_in_lists', 'encrypt_hls', 'disallow_empty_user_agents', 'detect_restream_block_user', 'download_images', 'api_redirect', 'use_buffer', 'audio_restart_loss', 'show_isps', 'priority_backup', 'rtmp_random', 'show_connected_video', 'show_not_on_air_video', 'show_banned_video', 'show_expired_video', 'show_expiring_video', 'show_all_category_mag', 'always_enabled_subtitles', 'enable_connection_problem_indication', 'show_tv_channel_logo', 'show_channel_logo_in_preview', 'disable_trial', 'restrict_same_ip', 'js_navigate', 'enable_telegram'] as $rSetting) {
			if (isset($rData[$rSetting])) {
				$rArray[$rSetting] = 1;
			}
			else {
				$rArray[$rSetting] = 0;
			}
		}

		if (!isset($rData['allowed_stb_types_for_local_recording'])) {
			$rArray['allowed_stb_types_for_local_recording'] = [];
		}

		if (!isset($rData['allowed_stb_types'])) {
			$rArray['allowed_stb_types'] = [];
		}

		if (!isset($rData['allow_countries'])) {
			$rArray['allow_countries'] = ['ALL'];
		}

		if ($rArray['mag_legacy_redirect']) {
			if (!file_exists(XCMS_HOME . 'www/c/')) {
				self::$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', SERVER_ID, time(), json_encode(['action' => 'enable_ministra']));
			}
		}
		else if (file_exists(XCMS_HOME . 'www/c/')) {
			self::$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', SERVER_ID, time(), json_encode(['action' => 'disable_ministra']));
		}

		if (100 < $rArray['search_items']) {
			$rArray['search_items'] = 100;
		}

		if ($rArray['search_items'] <= 0) {
			$rArray['search_items'] = 1;
		}

		$rPrepare = prepareArray($rArray);

		if (0 < count($rPrepare['data'])) {
			$rQuery = 'UPDATE `settings` SET ' . $rPrepare['update'] . ';';

			if (!self::$db->query($rQuery, ...$rPrepare['data'])) {
				return ['status' => STATUS_FAILURE];
			}

			clearSettingsCache();
			return ['status' => STATUS_SUCCESS];
		}
	}

	static public function editBackupSettings($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = verifyPostTable('settings', $rData, true);

		foreach (['dropbox_remote'] as $rSetting) {
			if (isset($rData[$rSetting])) {
				$rArray[$rSetting] = 1;
			}
			else {
				$rArray[$rSetting] = 0;
			}
		}

		if (!isset($rData['allowed_stb_types_for_local_recording'])) {
			$rArray['allowed_stb_types_for_local_recording'] = [];
		}

		if (!isset($rData['allowed_stb_types'])) {
			$rArray['allowed_stb_types'] = [];
		}

		$rPrepare = prepareArray($rArray);

		if (0 < count($rPrepare['data'])) {
			$rQuery = 'UPDATE `settings` SET ' . $rPrepare['update'] . ';';

			if (!self::$db->query($rQuery, ...$rPrepare['data'])) {
				return ['status' => STATUS_FAILURE];
			}

			clearSettingsCache();
			return ['status' => STATUS_SUCCESS];
		}
	}

	static public function editCacheCron($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rCheck = [false, false];
		$rCron = ['*', '*', '*', '*', '*'];
		$rPattern = '/^[0-9\\/*,-]+$/';
		$rCron[0] = $rData['minute'];
		preg_match($rPattern, $rCron[0], $rMatches);
		$rCheck[0] = 0 < count($rMatches);
		$rCron[1] = $rData['hour'];
		preg_match($rPattern, $rCron[1], $rMatches);
		$rCheck[1] = 0 < count($rMatches);
		$rCronOutput = implode(' ', $rCron);

		if (isset($rData['cache_changes'])) {
			$rCacheChanges = true;
		}
		else {
			$rCacheChanges = false;
		}
		if ($rCheck[0] && $rCheck[1]) {
			self::$db->query('UPDATE `crontab` SET `time` = ? WHERE `filename` = \'cache_engine.php\';', $rCronOutput);
			self::$db->query('UPDATE `settings` SET `cache_thread_count` = ?, `cache_changes` = ?;', $rData['cache_thread_count'], $rCacheChanges);

			if (file_exists(TMP_PATH . 'crontab')) {
				unlink(TMP_PATH . 'crontab');
			}

			clearSettingsCache();
			return ['status' => STATUS_SUCCESS];
		}
		else {
			return ['status' => STATUS_FAILURE];
		}
	}

	static public function editPlexSettings($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		foreach ($rData as $rKey => $rValue) {
			$rSplit = explode('_', $rKey);

			if ($rSplit[0] == 'genre') {
				if (isset($rData['bouquet_' . $rSplit[1]])) {
					$rBouquets = '[' . implode(',', array_map('intval', $rData['bouquet_' . $rSplit[1]])) . ']';
				}
				else {
					$rBouquets = '[]';
				}

				self::$db->query('UPDATE `watch_categories` SET `category_id` = ?, `bouquets` = ? WHERE `genre_id` = ? AND `type` = 3;', $rValue, $rBouquets, $rSplit[1]);
			}
		}

		foreach ($rData as $rKey => $rValue) {
			$rSplit = explode('_', $rKey);

			if ($rSplit[0] == 'genretv') {
				if (isset($rData['bouquettv_' . $rSplit[1]])) {
					$rBouquets = '[' . implode(',', array_map('intval', $rData['bouquettv_' . $rSplit[1]])) . ']';
				}
				else {
					$rBouquets = '[]';
				}

				self::$db->query('UPDATE `watch_categories` SET `category_id` = ?, `bouquets` = ? WHERE `genre_id` = ? AND `type` = 4;', $rValue, $rBouquets, $rSplit[1]);
			}
		}

		self::$db->query('UPDATE `settings` SET `scan_seconds` = ?, `max_genres` = ?, `thread_count_movie` = ?, `thread_count_show` = ?;', $rData['scan_seconds'], $rData['max_genres'], $rData['thread_count_movie'], $rData['thread_count_show']);
		clearSettingsCache();
		return ['status' => STATUS_SUCCESS];
	}

	static public function editWatchSettings($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		foreach ($rData as $rKey => $rValue) {
			$rSplit = explode('_', $rKey);

			if ($rSplit[0] == 'genre') {
				if (isset($rData['bouquet_' . $rSplit[1]])) {
					$rBouquets = '[' . implode(',', array_map('intval', $rData['bouquet_' . $rSplit[1]])) . ']';
				}
				else {
					$rBouquets = '[]';
				}

				self::$db->query('UPDATE `watch_categories` SET `category_id` = ?, `bouquets` = ? WHERE `genre_id` = ? AND `type` = 1;', $rValue, $rBouquets, $rSplit[1]);
			}
		}

		foreach ($rData as $rKey => $rValue) {
			$rSplit = explode('_', $rKey);

			if ($rSplit[0] == 'genretv') {
				if (isset($rData['bouquettv_' . $rSplit[1]])) {
					$rBouquets = '[' . implode(',', array_map('intval', $rData['bouquettv_' . $rSplit[1]])) . ']';
				}
				else {
					$rBouquets = '[]';
				}

				self::$db->query('UPDATE `watch_categories` SET `category_id` = ?, `bouquets` = ? WHERE `genre_id` = ? AND `type` = 2;', $rValue, $rBouquets, $rSplit[1]);
			}
		}

		if (isset($rData['alternative_titles'])) {
			$rAltTitles = true;
		}
		else {
			$rAltTitles = false;
		}

		if (isset($rData['fallback_parser'])) {
			$rFallbackParser = true;
		}
		else {
			$rFallbackParser = false;
		}

		self::$db->query('UPDATE `settings` SET `percentage_match` = ?, `scan_seconds` = ?, `thread_count` = ?, `max_genres` = ?, `max_items` = ?, `alternative_titles` = ?, `fallback_parser` = ?;', $rData['percentage_match'], $rData['scan_seconds'], $rData['thread_count'], $rData['max_genres'], $rData['max_items'], $rAltTitles, $rFallbackParser);
		clearSettingsCache();
		return ['status' => STATUS_SUCCESS];
	}

	static public function massEditStreams($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		if (isset($rData['c_days_to_restart'])) {
			if (isset($rData['days_to_restart']) && preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $rData['time_to_restart'])) {
				$rTimeArray = [
					'days' => [],
					'at'   => $rData['time_to_restart']
				];

				foreach ($rData['days_to_restart'] as $rID => $rDay) {
					$rTimeArray['days'][] = $rDay;
				}

				$rArray['auto_restart'] = json_encode($rTimeArray);
			}
			else {
				$rArray['auto_restart'] = '';
			}
		}

		foreach (['gen_timestamps', 'allow_record', 'rtmp_output', 'fps_restart', 'stream_all', 'read_native'] as $rKey) {
			if (isset($rData['c_' . $rKey])) {
				if (isset($rData[$rKey])) {
					$rArray[$rKey] = 1;
				}
				else {
					$rArray[$rKey] = 0;
				}
			}
		}

		if (isset($rData['c_direct_source'])) {
			if (isset($rData['direct_source'])) {
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_source'] = 0;
				$rArray['direct_proxy'] = 0;
			}
		}

		if (isset($rData['c_direct_proxy'])) {
			if (isset($rData['direct_proxy'])) {
				$rArray['direct_proxy'] = 1;
				$rArray['direct_source'] = 1;
			}
			else {
				$rArray['direct_proxy'] = 0;
			}
		}

		foreach (['tv_archive_server_id', 'vframes_server_id', 'tv_archive_duration', 'delay_minutes', 'probesize_ondemand', 'fps_threshold', 'llod'] as $rKey) {
			if (isset($rData['c_' . $rKey])) {
				$rArray[$rKey] = (int) $rData[$rKey];
			}
		}

		if (isset($rData['c_custom_sid'])) {
			$rArray['custom_sid'] = $rData['custom_sid'];
		}

		if (isset($rData['c_transcode_profile_id'])) {
			$rArray['transcode_profile_id'] = $rData['transcode_profile_id'];

			if (0 < $rArray['transcode_profile_id']) {
				$rArray['enable_transcode'] = 1;
			}
			else {
				$rArray['enable_transcode'] = 0;
			}
		}

		$rStreamIDs = json_decode($rData['streams'], true);

		if (0 < count($rStreamIDs)) {
			$rCategoryMap = [];
			if (isset($rData['c_category_id']) && in_array($rData['category_id_type'], ['ADD' => true, 'DEL' => true])) {
				self::$db->query('SELECT `id`, `category_id` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					$rCategoryMap[$rRow['id']] = json_decode($rRow['category_id'], true) ?: [];
				}
			}

			$rDeleteServers = $rStreamExists = [];
			self::$db->query('SELECT `stream_id`, `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

			foreach (self::$db->get_rows() as $rRow) {
				$rStreamExists[(int) $rRow['stream_id']][(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
			}

			$rBouquets = getBouquets();
			$rDelOptions = $rAddBouquet = $rDelBouquet = [];
			$rOptQuery = $rAddQuery = '';

			foreach ($rStreamIDs as $rStreamID) {
				if (isset($rData['c_category_id'])) {
					$rCategories = array_map('intval', $rData['category_id']);

					if ($rData['category_id_type'] == 'ADD') {
						foreach ($rCategoryMap[$rStreamID] ?: [] as $rCategoryID) {
							if (!in_array($rCategoryID, $rCategories)) {
								$rCategories[] = $rCategoryID;
							}
						}
					}
					else if ($rData['category_id_type'] == 'DEL') {
						$rNewCategories = $rCategoryMap[$rStreamID];

						foreach ($rCategories as $rCategoryID) {
							if (($rKey = array_search($rCategoryID, $rNewCategories)) !== false) {
								unset($rNewCategories[$rKey]);
							}
						}

						$rCategories = $rNewCategories;
					}

					$rArray['category_id'] = '[' . implode(',', $rCategories) . ']';
				}

				$rPrepare = prepareArray($rArray);

				if (0 < count($rPrepare['data'])) {
					$rPrepare['data'][] = $rStreamID;
					$rQuery = 'UPDATE `streams` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
					self::$db->query($rQuery, ...$rPrepare['data']);
				}

				if (isset($rData['c_server_tree'])) {
					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];

							if (in_array($rData['server_type'], ['ADD' => true, 'SET' => true])) {
								$rOD = (int) in_array($rServerID, $rData['on_demand'] ?: []);

								if ($rServer['parent'] == 'source') {
									$rParent = NULL;
								}
								else {
									$rParent = (int) $rServer['parent'];
								}

								$rStreamsAdded[] = $rServerID;

								if (isset($rStreamExists[$rStreamID][$rServerID])) {
									self::$db->query('UPDATE `streams_servers` SET `parent_id` = ?, `on_demand` = ? WHERE `server_stream_id` = ?;', $rParent, $rOD, $rStreamExists[$rStreamID][$rServerID]);
								}
								else {
									$rAddQuery .= '(' . (int) $rStreamID . ', ' . (int) $rServerID . ', ' . ($rParent ?: 'NULL') . ', ' . $rOD . '),';
								}
							}
							else if (isset($rStreamExists[$rStreamID][$rServerID])) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}

					if ($rData['server_type'] == 'SET') {
						foreach ($rStreamExists[$rStreamID] as $rServerID => $rDBID) {
							if (!in_array($rServerID, $rStreamsAdded)) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}
				}

				if (isset($rData['c_user_agent'])) {
					if (isset($rData['user_agent']) && (0 < strlen($rData['user_agent']))) {
						$rDelOptions[1][] = $rStreamID;
						$rOptQuery .= '(' . (int) $rStreamID . ', 1, ' . self::$db->escape($rData['user_agent']) . '),';
					}
				}

				if (isset($rData['c_http_proxy'])) {
					if (isset($rData['http_proxy']) && (0 < strlen($rData['http_proxy']))) {
						$rDelOptions[2][] = $rStreamID;
						$rOptQuery .= '(' . (int) $rStreamID . ', 2, ' . self::$db->escape($rData['http_proxy']) . '),';
					}
				}

				if (isset($rData['c_cookie'])) {
					if (isset($rData['cookie']) && (0 < strlen($rData['cookie']))) {
						$rDelOptions[17][] = $rStreamID;
						$rOptQuery .= '(' . (int) $rStreamID . ', 17, ' . self::$db->escape($rData['cookie']) . '),';
					}
				}

				if (isset($rData['c_headers'])) {
					if (isset($rData['headers']) && (0 < strlen($rData['headers']))) {
						$rDelOptions[19][] = $rStreamID;
						$rOptQuery .= '(' . (int) $rStreamID . ', 19, ' . self::$db->escape($rData['headers']) . '),';
					}
				}

				if (isset($rData['c_bouquets'])) {
					if ($rData['bouquets_type'] == 'SET') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}

						foreach ($rBouquets as $rBouquet) {
							if (!in_array($rBouquet['id'], $rData['bouquets'])) {
								$rDelBouquet[$rBouquet['id']][] = $rStreamID;
							}
						}
					}
					else if ($rData['bouquets_type'] == 'ADD') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}
					}
					else if ($rData['bouquets_type'] == 'DEL') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rDelBouquet[$rBouquet][] = $rStreamID;
						}
					}
				}

				foreach ($rDeleteServers as $rServerID => $rDeleteIDs) {
					deleteStreamsByServer($rDeleteIDs, $rServerID, false);
				}
			}

			foreach ($rDelOptions as $rOptionID => $rDelIDs) {
				self::$db->query('DELETE FROM `streams_options` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ') AND `argument_id` = 1;', $rStreamID);
			}

			if (!empty($rOptQuery)) {
				$rOptQuery = rtrim($rOptQuery, ',');
				self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES ' . $rOptQuery . ';');
			}

			foreach ($rAddBouquet as $rBouquetID => $rAddIDs) {
				addToBouquet('stream', $rBouquetID, $rAddIDs);
			}

			foreach ($rDelBouquet as $rBouquetID => $rRemIDs) {
				removeFromBouquet('stream', $rBouquetID, $rRemIDs);
			}

			if (!empty($rAddQuery)) {
				$rAddQuery = rtrim($rAddQuery, ',');
				self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES ' . $rAddQuery . ';');
			}

			XCMS::updateStreams($rStreamIDs);

			if (isset($rData['restart_on_edit'])) {
				APIRequest(['action' => 'stream', 'sub' => 'start', 'stream_ids' => array_values($rStreamIDs)]);
			}

			if (isset($rData['stop_on_edit'])) {
				APIRequest(['action' => 'stream', 'sub' => 'stop', 'stream_ids' => array_values($rStreamIDs)]);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function massEditChannels($rData)
	{
		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		foreach (['allow_record', 'rtmp_output'] as $rKey) {
			if (isset($rData['c_' . $rKey])) {
				if (isset($rData[$rKey])) {
					$rArray[$rKey] = 1;
				}
				else {
					$rArray[$rKey] = 0;
				}
			}
		}

		if (isset($rData['c_transcode_profile_id'])) {
			$rArray['transcode_profile_id'] = $rData['transcode_profile_id'];

			if (0 < $rArray['transcode_profile_id']) {
				$rArray['enable_transcode'] = 1;
			}
			else {
				$rArray['enable_transcode'] = 0;
			}
		}

		$rStreamIDs = json_decode($rData['streams'], true);

		if (0 < count($rStreamIDs)) {
			$rCategoryMap = [];
			if (isset($rData['c_category_id']) && in_array($rData['category_id_type'], ['ADD' => true, 'DEL' => true])) {
				self::$db->query('SELECT `id`, `category_id` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

				foreach (self::$db->get_rows() as $rRow) {
					$rCategoryMap[$rRow['id']] = json_decode($rRow['category_id'], true) ?: [];
				}
			}

			$rDeleteServers = $rProcessServers = $rStreamExists = [];
			self::$db->query('SELECT `stream_id`, `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

			foreach (self::$db->get_rows() as $rRow) {
				$rStreamExists[(int) $rRow['stream_id']][(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
				$rProcessServers[(int) $rRow['stream_id']][] = (int) $rRow['server_id'];
			}

			$rBouquets = getBouquets();
			$rDelOptions = $rAddBouquet = $rDelBouquet = [];
			$rEncQuery = $rAddQuery = '';

			foreach ($rStreamIDs as $rStreamID) {
				if (isset($rData['c_category_id'])) {
					$rCategories = array_map('intval', $rData['category_id']);

					if ($rData['category_id_type'] == 'ADD') {
						foreach ($rCategoryMap[$rStreamID] ?: [] as $rCategoryID) {
							if (!in_array($rCategoryID, $rCategories)) {
								$rCategories[] = $rCategoryID;
							}
						}
					}
					else if ($rData['category_id_type'] == 'DEL') {
						$rNewCategories = $rCategoryMap[$rStreamID];

						foreach ($rCategories as $rCategoryID) {
							if (($rKey = array_search($rCategoryID, $rNewCategories)) !== false) {
								unset($rNewCategories[$rKey]);
							}
						}

						$rCategories = $rNewCategories;
					}

					$rArray['category_id'] = '[' . implode(',', $rCategories) . ']';
				}

				$rPrepare = prepareArray($rArray);

				if (0 < count($rPrepare['data'])) {
					$rPrepare['data'][] = $rStreamID;
					$rQuery = 'UPDATE `streams` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
					self::$db->query($rQuery, ...$rPrepare['data']);
				}

				if (isset($rData['c_server_tree'])) {
					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];

							if (in_array($rData['server_type'], ['ADD' => true, 'SET' => true])) {
								$rStreamsAdded[] = $rServerID;
								$rOD = (int) in_array($rServerID, $rData['on_demand'] ?: []);

								if ($rServer['parent'] == 'source') {
									$rParent = NULL;
								}
								else {
									$rParent = (int) $rServer['parent'];
								}

								if (isset($rStreamExists[$rServerID])) {
									self::$db->query('UPDATE `streams_servers` SET `parent_id` = ?, `on_demand` = ? WHERE `server_stream_id` = ?;', $rParent, $rOD, $rStreamExists[$rServerID]);
								}
								else {
									$rAddQuery .= '(' . (int) $rStreamID . ', ' . (int) $rServerID . ', ' . ($rParent ?: 'NULL') . ', ' . $rOD . '),';
								}

								$rProcessServers[$rStreamID][] = $rServerID;
							}
							else if (isset($rStreamExists[$rStreamID][$rServerID])) {
								$rDeleteServers[$rServerID][] = $rStreamID;
							}
						}
					}

					if ($rData['server_type'] == 'SET') {
						foreach ($rStreamExists as $rServerID => $rDBID) {
							if (!in_array($rServerID, $rStreamsAdded)) {
								$rDeleteServers[$rServerID][] = $rStreamID;

								if (($rKey = array_search($rServerID, $rProcessServers[$rStreamID])) !== false) {
									unset($rProcessServers[$rStreamID][$rKey]);
								}
							}
						}
					}
				}

				if (isset($rData['c_bouquets'])) {
					if ($rData['bouquets_type'] == 'SET') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}

						foreach ($rBouquets as $rBouquet) {
							if (!in_array($rBouquet['id'], $rData['bouquets'])) {
								$rDelBouquet[$rBouquet['id']][] = $rStreamID;
							}
						}
					}
					else if ($rData['bouquets_type'] == 'ADD') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rAddBouquet[$rBouquet][] = $rStreamID;
						}
					}
					else if ($rData['bouquets_type'] == 'DEL') {
						foreach ($rData['bouquets'] as $rBouquet) {
							$rDelBouquet[$rBouquet][] = $rStreamID;
						}
					}
				}

				if (isset($rData['reencode_on_edit'])) {
					foreach ($rProcessServers[$rStreamID] as $rServerID) {
						$rEncQuery .= '(\'channel\', ' . (int) $rStreamID . ', ' . (int) $rServerID . ', ' . time() . '),';
					}
				}

				foreach ($rDeleteServers as $rServerID => $rDeleteIDs) {
					deleteStreamsByServer($rDeleteIDs, $rServerID, false);
				}
			}

			foreach ($rAddBouquet as $rBouquetID => $rAddIDs) {
				addToBouquet('stream', $rBouquetID, $rAddIDs);
			}

			foreach ($rDelBouquet as $rBouquetID => $rRemIDs) {
				removeFromBouquet('stream', $rBouquetID, $rRemIDs);
			}

			if (!empty($rAddQuery)) {
				$rAddQuery = rtrim($rAddQuery, ',');
				self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES ' . $rAddQuery . ';');
			}

			XCMS::updateStreams($rStreamIDs);

			if (isset($rData['reencode_on_edit'])) {
				self::$db->query('UPDATE `streams_servers` SET `pids_create_channel` = \'[]\', `cchannel_rsources` = \'[]\' WHERE `stream_id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

				if (!empty($rEncQuery)) {
					$rEncQuery = rtrim($rEncQuery, ',');
					self::$db->query('INSERT INTO `queue`(`type`, `stream_id`, `server_id`, `added`) VALUES ' . $rEncQuery . ';');
				}

				APIRequest(['action' => 'stream', 'sub' => 'stop', 'stream_ids' => array_values($rStreamIDs)]);
			}
			else if (isset($rData['restart_on_edit'])) {
				APIRequest(['action' => 'stream', 'sub' => 'start', 'stream_ids' => array_values($rStreamIDs)]);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processStream($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		set_time_limit(0);
		ini_set('mysql.connect_timeout', 0);
		ini_set('max_execution_time', 0);
		ini_set('default_socket_timeout', 0);

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_stream')) {
				exit();
			}

			$rArray = overwriteData(getStream($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_stream')) {
				exit();
			}

			$rArray = verifyPostTable('streams', $rData);
			$rArray['type'] = 1;
			$rArray['added'] = time();
			unset($rArray['id']);
		}
		if (isset($rData['days_to_restart']) && preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $rData['time_to_restart'])) {
			$rTimeArray = [
				'days' => [],
				'at'   => $rData['time_to_restart']
			];

			foreach ($rData['days_to_restart'] as $rID => $rDay) {
				$rTimeArray['days'][] = $rDay;
			}

			$rArray['auto_restart'] = $rTimeArray;
		}
		else {
			$rArray['auto_restart'] = '';
		}

		foreach (['fps_restart', 'gen_timestamps', 'allow_record', 'rtmp_output', 'stream_all', 'direct_source', 'direct_proxy', 'read_native'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		if (!$rArray['transcode_profile_id']) {
			$rArray['transcode_profile_id'] = 0;
		}

		if (0 < $rArray['transcode_profile_id']) {
			$rArray['enable_transcode'] = 1;
		}

		if (isset($rData['restart_on_edit'])) {
			$rRestart = true;
		}
		else {
			$rRestart = false;
		}

		$rReview = false;
		$rImportStreams = [];

		if (isset($rData['review'])) {
			$rReview = true;

			foreach ($rData['review'] as $rImportStream) {
				if (!$rImportStream['channel_id'] && $rImportStream['tvg_id']) {
					$rEPG = findEPG($rImportStream['tvg_id']);

					if (isset($rEPG)) {
						$rImportStream['epg_id'] = $rEPG['epg_id'];
						$rImportStream['channel_id'] = $rEPG['channel_id'];

						if (!empty($rEPG['epg_lang'])) {
							$rImportStream['epg_lang'] = $rEPG['epg_lang'];
						}
					}
				}

				$rImportStreams[] = $rImportStream;
			}
		}
		else if (isset($_FILES['m3u_file'])) {
			if (!hasPermissions('adv', 'import_streams')) {
				exit();
			}
			if (empty($_FILES['m3u_file']['tmp_name']) || (strtolower(pathinfo(explode('?', $_FILES['m3u_file']['name'])[0], PATHINFO_EXTENSION)) != 'm3u')) {
				return ['status' => STATUS_INVALID_FILE, 'data' => $rData];
			}

			$rResults = parseM3U($_FILES['m3u_file']['tmp_name']);

			if (0 < count($rResults)) {
				$rEPGDatabase = $rSourceDatabase = $rStreamDatabase = [];
				self::$db->query('SELECT `id`, `stream_display_name`, `stream_source`, `channel_id` FROM `streams` WHERE `type` = 1;');

				foreach (self::$db->get_rows() as $rRow) {
					$rName = preg_replace('/[^A-Za-z0-9 ]/', '', strtolower($rRow['stream_display_name']));

					if (!empty($rName)) {
						$rStreamDatabase[$rName] = $rRow['id'];
					}

					$rEPGDatabase[$rRow['channel_id']] = $rRow['id'];

					foreach (json_decode($rRow['stream_source'], true) as $rSource) {
						if (!empty($rSource)) {
							$rSourceDatabase[md5(preg_replace('(^https?://)', '', str_replace(' ', '%20', $rSource)))] = $rRow['id'];
						}
					}
				}

				$rEPGMatch = $rEPGScan = [];
				$i = 0;

				foreach ($rResults as $rResult) {
					$rTag = $rResult->getExtTags()[0];

					if ($rTag) {
						if ($rTag->getAttribute('tvg-id')) {
							$rID = $rTag->getAttribute('tvg-id');
							$rEPGScan[$rID][] = $i;
						}
					}

					$i++;
				}

				if (0 < count($rEPGScan)) {
					self::$db->query('SELECT `id`, `data` FROM `epg`;');

					if (0 < self::$db->num_rows()) {
						foreach (self::$db->get_rows() as $rRow) {
							foreach (json_decode($rRow['data'], true) as $rChannelID => $rChannelData) {
								if (isset($rEPGScan[$rChannelID])) {
									if (0 < count($rChannelData['langs'])) {
										$rEPGLang = $rChannelData['langs'][0];
									}
									else {
										$rEPGLang = '';
									}

									foreach ($rEPGScan[$rChannelID] as $i) {
										$rEPGMatch[$i] = ['channel_id' => $rChannelID, 'epg_lang' => $rEPGLang, 'epg_id' => (int) $rRow['id']];
									}
								}
							}
						}
					}
				}

				$i = 0;

				foreach ($rResults as $rResult) {
					$rTag = $rResult->getExtTags()[0];

					if ($rTag) {
						$rURL = $rResult->getPath();
						$rImportArray = [
							'stream_source'       => [$rURL],
							'stream_icon'         => $rTag->getAttribute('tvg-logo') ?: '',
							'stream_display_name' => $rTag->getTitle() ?: '',
							'epg_id'              => NULL,
							'epg_lang'            => NULL,
							'channel_id'          => NULL
						];

						if ($rTag->getAttribute('tvg-id')) {
							$rEPG = $rEPGMatch[$i] ?: NULL;

							if (isset($rEPG)) {
								$rImportArray['epg_id'] = $rEPG['epg_id'];
								$rImportArray['channel_id'] = $rEPG['channel_id'];

								if (!empty($rEPG['epg_lang'])) {
									$rImportArray['epg_lang'] = $rEPG['epg_lang'];
								}
							}
						}

						$rBackupID = $rExistsID = NULL;
						$rSourceID = md5(preg_replace('(^https?://)', '', str_replace(' ', '%20', $rURL)));

						if (isset($rSourceDatabase[$rSourceID])) {
							$rExistsID = $rSourceDatabase[$rSourceID];
						}

						$rName = preg_replace('/[^A-Za-z0-9 ]/', '', strtolower($rTag->getTitle()));
						if (!empty($rName) && isset($rStreamDatabase[$rName])) {
							$rBackupID = $rStreamDatabase[$rName];
						}
						else if (!empty($rImportArray['channel_id']) && isset($rEPGDatabase[$rImportArray['channel_id']])) {
							$rBackupID = $rEPGDatabase[$rImportArray['channel_id']];
						}
						if ($rBackupID && !$rExistsID && isset($rData['add_source_as_backup'])) {
							self::$db->query('SELECT `stream_source` FROM `streams` WHERE `id` = ?;', $rBackupID);

							if (0 < self::$db->num_rows()) {
								$rSources = json_decode(self::$db->get_row()['stream_source'], true) ?: [];
								$rSources[] = $rURL;
								self::$db->query('UPDATE `streams` SET `stream_source` = ? WHERE `id` = ?;', json_encode($rSources), $rBackupID);
								$rImportStreams[] = ['update' => true, 'id' => $rBackupID];
							}
						}
						else if ($rExistsID && isset($rData['update_existing'])) {
							$rImportArray['id'] = $rExistsID;
							$rImportStreams[] = $rImportArray;
						}
						else if (!$rExistsID) {
							$rImportStreams[] = $rImportArray;
						}
					}

					$i++;
				}
			}
		}
		else {
			if ($rData['epg_api']) {
				$rArray['channel_id'] = $rData['epg_api_id'];
				$rArray['epg_id'] = 0;
				$rArray['epg_lang'] = NULL;
			}

			$rImportArray = [
				'stream_source'       => [],
				'stream_icon'         => $rArray['stream_icon'],
				'stream_display_name' => $rArray['stream_display_name'],
				'epg_id'              => $rArray['epg_id'],
				'epg_lang'            => $rArray['epg_lang'],
				'channel_id'          => $rArray['channel_id']
			];

			if (isset($rData['stream_source'])) {
				foreach ($rData['stream_source'] as $rID => $rURL) {
					if (0 < strlen($rURL)) {
						$rImportArray['stream_source'][] = $rURL;
					}
				}
			}

			$rImportStreams[] = $rImportArray;
		}

		if (0 < count($rImportStreams)) {
			$rBouquetCreate = [];
			$rCategoryCreate = [];

			if (!$rReview) {
				foreach (json_decode($rData['bouquet_create_list'], true) as $rBouquet) {
					$rPrepare = prepareArray([
						'bouquet_name'     => $rBouquet,
						'bouquet_channels' => [],
						'bouquet_movies'   => [],
						'bouquet_series'   => [],
						'bouquet_radios'   => []
					]);
					$rQuery = 'INSERT INTO `bouquets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

					if (self::$db->query($rQuery, ...$rPrepare['data'])) {
						$rBouquetID = self::$db->last_insert_id();
						$rBouquetCreate[$rBouquet] = $rBouquetID;
					}
				}

				foreach (json_decode($rData['category_create_list'], true) as $rCategory) {
					$rPrepare = prepareArray(['category_type' => 'live', 'category_name' => $rCategory, 'parent_id' => 0, 'cat_order' => 99, 'is_adult' => 0]);
					$rQuery = 'INSERT INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

					if (self::$db->query($rQuery, ...$rPrepare['data'])) {
						$rCategoryID = self::$db->last_insert_id();
						$rCategoryCreate[$rCategory] = $rCategoryID;
					}
				}
			}

			foreach ($rImportStreams as $rImportStream) {
				if ($rImportStream['update']) {
					continue;
				}

				$rImportArray = $rArray;

				if (self::$rSettings['download_images']) {
					$rImportStream['stream_icon'] = XCMS::downloadImage($rImportStream['stream_icon'], 1);
				}

				if ($rReview) {
					$rImportArray['category_id'] = '[' . implode(',', array_map('intval', $rImportStream['category_id'])) . ']';
					$rBouquets = array_map('intval', $rImportStream['bouquets']);
					unset($rImportStream['bouquets']);
				}
				else {
					$rBouquets = [];

					foreach ($rData['bouquets'] as $rBouquet) {
						if (isset($rBouquetCreate[$rBouquet])) {
							$rBouquets[] = $rBouquetCreate[$rBouquet];
						}
						else if (is_numeric($rBouquet)) {
							$rBouquets[] = (int) $rBouquet;
						}
					}

					$rCategories = [];

					foreach ($rData['category_id'] as $rCategory) {
						if (isset($rCategoryCreate[$rCategory])) {
							$rCategories[] = $rCategoryCreate[$rCategory];
						}
						else if (is_numeric($rCategory)) {
							$rCategories[] = (int) $rCategory;
						}
					}

					$rImportArray['category_id'] = '[' . implode(',', array_map('intval', $rCategories)) . ']';
					if (isset($rData['adaptive_link']) && (0 < count($rData['adaptive_link']))) {
						$rImportArray['adaptive_link'] = '[' . implode(',', array_map('intval', $rData['adaptive_link'])) . ']';
					}
					else {
						$rImportArray['adaptive_link'] = NULL;
					}
				}

				foreach (array_keys($rImportStream) as $rKey) {
					$rImportArray[$rKey] = $rImportStream[$rKey];
				}
				if (!isset($rData['edit']) && !isset($rImportStream['id'])) {
					$rImportArray['order'] = getNextOrder();
				}

				$rImportArray['title_sync'] = $rData['title_sync'] ?: NULL;

				if ($rImportArray['title_sync']) {
					list($rSyncID, $rSyncStream) = array_map('intval', explode('_', $rImportArray['title_sync']));
					self::$db->query('SELECT `stream_display_name` FROM `providers_streams` WHERE `provider_id` = ? AND `stream_id` = ?;', $rSyncID, $rSyncStream);

					if (self::$db->num_rows() == 1) {
						$rImportArray['stream_display_name'] = self::$db->get_row()['stream_display_name'];
					}
				}

				$rPrepare = prepareArray($rImportArray);
				$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if (self::$db->query($rQuery, ...$rPrepare['data'])) {
					$rInsertID = self::$db->last_insert_id();
					$rStreamExists = [];
					if (isset($rData['edit']) || isset($rImportStream['id'])) {
						self::$db->query('SELECT `server_stream_id`, `server_id` FROM `streams_servers` WHERE `stream_id` = ?;', $rInsertID);

						foreach (self::$db->get_rows() as $rRow) {
							$rStreamExists[(int) $rRow['server_id']] = (int) $rRow['server_stream_id'];
						}
					}

					$rStreamsAdded = [];
					$rServerTree = json_decode($rData['server_tree_data'], true);

					foreach ($rServerTree as $rServer) {
						if ($rServer['parent'] != '#') {
							$rServerID = (int) $rServer['id'];
							$rStreamsAdded[] = $rServerID;
							$rOD = (int) in_array($rServerID, $rData['on_demand'] ?: []);

							if ($rServer['parent'] == 'source') {
								$rParent = NULL;
							}
							else {
								$rParent = (int) $rServer['parent'];
							}

							if (isset($rStreamExists[$rServerID])) {
								self::$db->query('UPDATE `streams_servers` SET `parent_id` = ?, `on_demand` = ? WHERE `server_stream_id` = ?;', $rParent, $rOD, $rStreamExists[$rServerID]);
							}
							else {
								self::$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `on_demand`) VALUES(?, ?, ?, ?);', $rInsertID, $rServerID, $rParent, $rOD);
							}
						}
					}

					foreach ($rStreamExists as $rServerID => $rDBID) {
						if (!in_array($rServerID, $rStreamsAdded)) {
							deleteStream($rInsertID, $rServerID, false, false);
						}
					}

					self::$db->query('DELETE FROM `streams_options` WHERE `stream_id` = ?;', $rInsertID);
					if (isset($rData['user_agent']) && (0 < strlen($rData['user_agent']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 1, ?);', $rInsertID, $rData['user_agent']);
					}
					if (isset($rData['http_proxy']) && (0 < strlen($rData['http_proxy']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 2, ?);', $rInsertID, $rData['http_proxy']);
					}
					if (isset($rData['cookie']) && (0 < strlen($rData['cookie']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 17, ?);', $rInsertID, $rData['cookie']);
					}
					if (isset($rData['headers']) && (0 < strlen($rData['headers']))) {
						self::$db->query('INSERT INTO `streams_options`(`stream_id`, `argument_id`, `value`) VALUES(?, 19, ?);', $rInsertID, $rData['headers']);
					}

					if ($rRestart) {
						APIRequest([
							'action'     => 'stream',
							'sub'        => 'start',
							'stream_ids' => [$rInsertID]
						]);
					}

					foreach ($rBouquets as $rBouquet) {
						addToBouquet('stream', $rBouquet, $rInsertID);
					}
					if (isset($rData['edit']) || isset($rImportStream['id'])) {
						foreach (getBouquets() as $rBouquet) {
							if (!in_array($rBouquet['id'], $rBouquets)) {
								removeFromBouquet('stream', $rBouquet['id'], $rInsertID);
							}
						}
					}
					if (($rArray['epg_id'] == 0) && !empty($rArray['channel_id'])) {
						processEPGAPI($rInsertID, $rArray['channel_id']);
					}

					XCMS::updateStream($rInsertID);
				}
				else {
					foreach ($rBouquetCreate as $rBouquet => $rID) {
						$db->query('DELETE FROM `bouquets` WHERE `id` = ?;', $rID);
					}

					foreach ($rCategoryCreate as $rCategory => $rID) {
						$db->query('DELETE FROM `streams_categories` WHERE `id` = ?;', $rID);
					}

					return ['status' => STATUS_FAILURE, 'data' => $rData];
				}
			}

			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_NO_SOURCES, 'data' => $rData];
		}
	}

	static public function orderCategories($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rPostCategories = json_decode($rData['categories'], true);

		if (0 < count($rPostCategories)) {
			foreach ($rPostCategories as $rOrder => $rPostCategory) {
				self::$db->query('UPDATE `streams_categories` SET `cat_order` = ?, `parent_id` = 0 WHERE `id` = ?;', (int) $rOrder + 1, $rPostCategory['id']);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function orderServers($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rPostServers = json_decode($rData['server_order'], true);

		if (0 < count($rPostServers)) {
			foreach ($rPostServers as $rOrder => $rPostServer) {
				self::$db->query('UPDATE `servers` SET `order` = ? WHERE `id` = ?;', (int) $rOrder + 1, $rPostServer['id']);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processCategory($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getCategory($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('streams_categories', $rData);
			$rArray['cat_order'] = 99;
			unset($rArray['id']);
		}

		if (isset($rData['is_adult'])) {
			$rArray['is_adult'] = 1;
		}
		else {
			$rArray['is_adult'] = 0;
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `streams_categories`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function moveStreams($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rType = (int) $rData['content_type'];
		$rSource = (int) $rData['source_server'];
		$rReplacement = (int) $rData['replacement_server'];
		if ((0 < $rSource) && (0 < $rReplacement) && ($rSource != $rReplacement)) {
			$rExisting = [];

			if ($rType == 0) {
				self::$db->query('SELECT `stream_id` FROM `streams_servers` WHERE `server_id` = ?;', $rReplacement);

				foreach (self::$db->get_rows() as $rRow) {
					$rExisting[] = (int) $rRow['stream_id'];
				}
			}
			else {
				self::$db->query('SELECT `streams_servers`.`stream_id` FROM `streams_servers` LEFT JOIN `streams` ON `streams`.`id` = `streams_servers`.`stream_id` WHERE `streams_servers`.`server_id` = ? AND `streams`.`type` = ?;', $rReplacement, $rType);

				foreach (self::$db->get_rows() as $rRow) {
					$rExisting[] = (int) $rRow['stream_id'];
				}
			}

			self::$db->query('SELECT `stream_id` FROM `streams_servers` WHERE `server_id` = ?;', $rSource);

			foreach (self::$db->get_rows() as $rRow) {
				if (in_array((int) $rRow['stream_id'], $rExisting)) {
					self::$db->query('DELETE FROM `streams_servers` WHERE `stream_id` = ? AND `server_id` = ?;', $rRow['stream_id'], $rSource);
				}
			}

			if ($rType == 0) {
				self::$db->query('UPDATE `streams_servers` SET `server_id` = ? WHERE `server_id` = ?;', $rReplacement, $rSource);
			}
			else {
				self::$db->query('UPDATE `streams_servers` LEFT JOIN `streams` ON `streams`.`id` = `streams_servers`.`stream_id` SET `streams_servers`.`server_id` = ? WHERE `streams_servers`.`server_id` = ? AND `streams`.`type` = ?;', $rReplacement, $rSource, $rType);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function replaceDNS($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rOldDNS = str_replace('/', '\\/', $rData['old_dns']);
		$rNewDNS = str_replace('/', '\\/', $rData['new_dns']);
		self::$db->query('UPDATE `streams` SET `stream_source` = REPLACE(`stream_source`, ?, ?);', $rOldDNS, $rNewDNS);
		return ['status' => STATUS_SUCCESS];
	}

	static public function submitTicket($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getTicket($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('tickets', $rData);
			unset($rArray['id']);
		}
		if (((strlen($rData['title']) == 0) && !isset($rData['respond'])) || (strlen($rData['message']) == 0)) {
			return ['status' => STATUS_INVALID_DATA, 'data' => $rData];
		}

		if (!isset($rData['respond'])) {
			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `tickets`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();
				self::$db->query('INSERT INTO `tickets_replies`(`ticket_id`, `admin_reply`, `message`, `date`) VALUES(?, 0, ?, ?);', $rInsertID, $rData['message'], time());
				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else {
				return ['status' => STATUS_FAILURE, 'data' => $rData];
			}
		}
		else {
			$rTicket = getTicket($rData['respond']);

			if ($rTicket) {
				if ((int) self::$rUserInfo['id'] == (int) $rTicket['member_id']) {
					self::$db->query('UPDATE `tickets` SET `admin_read` = 0, `user_read` = 1 WHERE `id` = ?;', $rData['respond']);
					self::$db->query('INSERT INTO `tickets_replies`(`ticket_id`, `admin_reply`, `message`, `date`) VALUES(?, 0, ?, ?);', $rData['respond'], $rData['message'], time());
				}
				else {
					self::$db->query('UPDATE `tickets` SET `admin_read` = 0, `user_read` = 0 WHERE `id` = ?;', $rData['respond']);
					self::$db->query('INSERT INTO `tickets_replies`(`ticket_id`, `admin_reply`, `message`, `date`) VALUES(?, 1, ?, ?);', $rData['respond'], $rData['message'], time());
				}

				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rData['respond']]
				];
			}
			else {
				return ['status' => STATUS_FAILURE, 'data' => $rData];
			}
		}
	}

	static public function processUA($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getUserAgent($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('blocked_uas', $rData);
			unset($rArray['id']);
		}

		if (isset($rData['exact_match'])) {
			$rArray['exact_match'] = true;
		}
		else {
			$rArray['exact_match'] = false;
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `blocked_uas`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processPlexSync($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getWatchFolder($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('watch_folders', $rData);
			unset($rArray['id']);
		}

		if (isset($rData['edit'])) {
			self::$db->query('SELECT COUNT(*) AS `count` FROM `watch_folders` WHERE `directory` = ? AND `server_id` = ? AND `plex_ip` = ? AND `id` <> ?;', $rData['library_id'], $rData['server_id'], $rData['plex_ip'], $rArray['id']);
		}
		else {
			self::$db->query('SELECT COUNT(*) AS `count` FROM `watch_folders` WHERE `directory` = ? AND `server_id` = ? AND `plex_ip` = ?;', $rData['library_id'], $rData['server_id'], $rData['plex_ip']);
		}

		if (0 < self::$db->get_row()['count']) {
			return ['status' => STATUS_EXISTS_DIR, 'data' => $rData];
		}

		$rArray['type'] = 'plex';
		$rArray['directory'] = $rData['library_id'];

		if (is_array($rData['server_id'])) {
			$rServers = $rData['server_id'];
			$rArray['server_id'] = (int) array_shift($rServers);
			$rArray['server_add'] = '[' . implode(',', array_map('intval', $rServers)) . ']';
		}
		else {
			$rArray['server_id'] = (int) $rData['server_id'];
			$rArray['server_add'] = NULL;
		}

		$rArray['plex_ip'] = $rData['plex_ip'];
		$rArray['plex_port'] = $rData['plex_port'];
		$rArray['plex_libraries'] = $rData['libraries'];
		$rArray['plex_username'] = $rData['username'];

		if (isset($rData['direct_proxy'])) {
			$rArray['direct_proxy'] = 1;
		}
		else {
			$rArray['direct_proxy'] = 0;
		}

		if (0 < strlen($rData['password'])) {
			$rArray['plex_password'] = $rData['password'];
		}

		foreach (['remove_subtitles', 'check_tmdb', 'store_categories', 'scan_missing', 'auto_upgrade', 'read_native', 'movie_symlink', 'auto_encode', 'active'] as $rKey) {
			if (isset($rData[$rKey])) {
				$rArray[$rKey] = 1;
			}
			else {
				$rArray[$rKey] = 0;
			}
		}

		$rArray['category_id'] = (int) $rData['override_category'];
		$rArray['fb_category_id'] = (int) $rData['fallback_category'];
		$rArray['bouquets'] = '[' . implode(',', array_map('intval', $rData['override_bouquets'])) . ']';
		$rArray['fb_bouquets'] = '[' . implode(',', array_map('intval', $rData['fallback_bouquets'])) . ']';
		$rArray['target_container'] = ($rData['target_container'] == 'auto' ? NULL : $rData['target_container']);
		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `watch_folders`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function processWatchFolder($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			$rArray = overwriteData(getWatchFolder($rData['edit']), $rData);
		}
		else {
			$rArray = verifyPostTable('watch_folders', $rData);
			unset($rArray['id']);
		}

		$rPath = $rData['selected_path'];
		if ((0 < strlen($rPath)) && ($rPath != '/')) {
			if (isset($rData['edit'])) {
				self::$db->query('SELECT COUNT(*) AS `count` FROM `watch_folders` WHERE `directory` = ? AND `server_id` = ? AND `type` = ? AND `id` <> ?;', $rPath, $rArray['server_id'], $rData['folder_type'], $rArray['id']);
			}
			else {
				self::$db->query('SELECT COUNT(*) AS `count` FROM `watch_folders` WHERE `directory` = ? AND `server_id` = ? AND `type` = ?;', $rPath, $rArray['server_id'], $rData['folder_type']);
			}

			if (0 < self::$db->get_row()['count']) {
				return ['status' => STATUS_EXISTS_DIR, 'data' => $rData];
			}

			$rArray['type'] = $rData['folder_type'];
			$rArray['directory'] = $rPath;
			$rArray['bouquets'] = '[' . implode(',', array_map('intval', $rData['bouquets'])) . ']';
			$rArray['fb_bouquets'] = '[' . implode(',', array_map('intval', $rData['fb_bouquets'])) . ']';

			if (0 < count($rData['allowed_extensions'])) {
				$rArray['allowed_extensions'] = json_encode($rData['allowed_extensions']);
			}
			else {
				$rArray['allowed_extensions'] = '[]';
			}

			$rArray['target_container'] = ($rData['target_container'] == 'auto' ? NULL : $rData['target_container']);
			$rArray['category_id'] = (int) $rData['category_id_' . $rData['folder_type']];
			$rArray['fb_category_id'] = (int) $rData['fb_category_id_' . $rData['folder_type']];

			foreach (['remove_subtitles', 'duplicate_tmdb', 'extract_metadata', 'fallback_title', 'disable_tmdb', 'ignore_no_match', 'auto_subtitles', 'auto_upgrade', 'read_native', 'movie_symlink', 'auto_encode', 'ffprobe_input', 'active'] as $rKey) {
				if (isset($rData[$rKey])) {
					$rArray[$rKey] = 1;
				}
				else {
					$rArray[$rKey] = 0;
				}
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `watch_folders`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();
				return [
					'status' => STATUS_SUCCESS,
					'data'   => ['insert_id' => $rInsertID]
				];
			}
			else {
				return ['status' => STATUS_FAILURE, 'data' => $rData];
			}
		}
		else {
			return ['status' => STATUS_INVALID_DIR, 'data' => $rData];
		}
	}

	static public function massEditLines($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		foreach (['is_stalker', 'is_isplock', 'is_restreamer', 'is_trial'] as $rItem) {
			if (isset($rData['c_' . $rItem])) {
				if (isset($rData[$rItem])) {
					$rArray[$rItem] = 1;
				}
				else {
					$rArray[$rItem] = 0;
				}
			}
		}

		if (isset($rData['c_admin_notes'])) {
			$rArray['admin_notes'] = $rData['admin_notes'];
		}

		if (isset($rData['c_reseller_notes'])) {
			$rArray['reseller_notes'] = $rData['reseller_notes'];
		}

		if (isset($rData['c_forced_country'])) {
			$rArray['forced_country'] = $rData['forced_country'];
		}

		if (isset($rData['c_member_id'])) {
			$rArray['member_id'] = (int) $rData['member_id'];
		}

		if (isset($rData['c_force_server_id'])) {
			$rArray['force_server_id'] = (int) $rData['force_server_id'];
		}

		if (isset($rData['c_max_connections'])) {
			$rArray['max_connections'] = (int) $rData['max_connections'];
		}

		if (isset($rData['c_exp_date'])) {
			if (isset($rData['no_expire'])) {
				$rArray['exp_date'] = NULL;
			}
			else {
				try {
					$rDate = new DateTime($rData['exp_date']);
					$rArray['exp_date'] = $rDate->format('U');
				}
				catch (Exception $e) {
				}
			}
		}

		if (isset($rData['c_access_output'])) {
			$rOutputs = [];

			foreach ($rData['access_output'] as $rOutputID) {
				$rOutputs[] = $rOutputID;
			}

			$rArray['allowed_outputs'] = '[' . implode(',', array_map('intval', $rOutputs)) . ']';
		}

		if (isset($rData['c_bouquets'])) {
			$rArray['bouquet'] = [];

			foreach (json_decode($rData['bouquets_selected'], true) as $rBouquet) {
				if (is_numeric($rBouquet)) {
					$rArray['bouquet'][] = $rBouquet;
				}
			}

			$rArray['bouquet'] = sortArrayByArray($rArray['bouquet'], array_keys(getBouquetOrder()));
			$rArray['bouquet'] = '[' . implode(',', array_map('intval', $rArray['bouquet'])) . ']';
		}

		if (isset($rData['reset_isp_lock'])) {
			$rArray['as_number'] = $rArray['isp_desc'] = '';
		}

		$rUsers = confirmIDs(json_decode($rData['users_selected'], true));

		if (0 < count($rUsers)) {
			$rPrepare = prepareArray($rArray);

			if (0 < count($rPrepare['data'])) {
				$rQuery = 'UPDATE `lines` SET ' . $rPrepare['update'] . ' WHERE `id` IN (' . implode(',', $rUsers) . ');';
				self::$db->query($rQuery, ...$rPrepare['data']);
			}

			self::$db->query('SELECT `pair_id` FROM `lines` WHERE `pair_id` IN (' . implode(',', $rUsers) . ');');

			foreach (self::$db->get_rows() as $rRow) {
				syncDevices($rRow['pair_id']);
			}

			XCMS::updateLines($rUsers);
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function massEditMags($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];
		$rUserArray = [];

		foreach (['lock_device'] as $rItem) {
			if (isset($rData['c_' . $rItem])) {
				if (isset($rData[$rItem])) {
					$rArray[$rItem] = 1;
				}
				else {
					$rArray[$rItem] = 0;
				}
			}
		}

		foreach (['is_isplock', 'is_trial'] as $rItem) {
			if (isset($rData['c_' . $rItem])) {
				if (isset($rData[$rItem])) {
					$rUserArray[$rItem] = 1;
				}
				else {
					$rUserArray[$rItem] = 0;
				}
			}
		}

		if (isset($rData['c_modern_theme'])) {
			if (isset($rData['modern_theme'])) {
				$rArray['theme_type'] = 0;
			}
			else {
				$rArray['theme_type'] = 1;
			}
		}

		if (isset($rData['c_parent_password'])) {
			$rArray['parent_password'] = $rData['parent_password'];
		}

		if (isset($rData['c_admin_notes'])) {
			$rUserArray['admin_notes'] = $rData['admin_notes'];
		}

		if (isset($rData['c_reseller_notes'])) {
			$rUserArray['reseller_notes'] = $rData['reseller_notes'];
		}

		if (isset($rData['c_forced_country'])) {
			$rUserArray['forced_country'] = $rData['forced_country'];
		}

		if (isset($rData['c_member_id'])) {
			$rUserArray['member_id'] = (int) $rData['member_id'];
		}

		if (isset($rData['c_force_server_id'])) {
			$rUserArray['force_server_id'] = (int) $rData['force_server_id'];
		}

		if (isset($rData['c_exp_date'])) {
			if (isset($rData['no_expire'])) {
				$rUserArray['exp_date'] = NULL;
			}
			else {
				try {
					$rDate = new DateTime($rData['exp_date']);
					$rUserArray['exp_date'] = $rDate->format('U');
				}
				catch (Exception $e) {
				}
			}
		}

		if (isset($rData['c_bouquets'])) {
			$rUserArray['bouquet'] = [];

			foreach (json_decode($rData['bouquets_selected'], true) as $rBouquet) {
				if (is_numeric($rBouquet)) {
					$rUserArray['bouquet'][] = $rBouquet;
				}
			}

			$rUserArray['bouquet'] = sortArrayByArray($rUserArray['bouquet'], array_keys(getBouquetOrder()));
			$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
		}

		if (isset($rData['reset_isp_lock'])) {
			$rUserArray['as_number'] = $rUserArray['isp_desc'] = '';
		}

		if (isset($rData['reset_device_lock'])) {
			$rArray['sn'] = $rArray['stb_type'] = $rArray['image_version'] = $rArray['hw_version'] = $rArray['device_id'] = $rArray['device_id2'] = $rArray['ver'] = '';
		}

		if (!empty($rData['message_type'])) {
			$rEvent = ['event' => $rData['message_type'], 'need_confirm' => 0, 'msg' => '', 'reboot_after_ok' => (int) isset($rData['reboot_portal'])];

			if ($rData['message_type'] == 'send_msg') {
				$rEvent['need_confirm'] = 1;
				$rEvent['msg'] = $rData['message'];
			}
			else if ($rData['message_type'] == 'play_channel') {
				$rEvent['msg'] = (int) $rData['selected_channel'];
				$rEvent['reboot_after_ok'] = 0;
			}
			else {
				$rEvent['need_confirm'] = 0;
				$rEvent['reboot_after_ok'] = 0;
			}
		}

		$rDevices = json_decode($rData['devices_selected'], true);

		foreach ($rDevices as $rDevice) {
			$rDeviceInfo = getMag($rDevice);

			if ($rDeviceInfo) {
				if (!empty($rData['message_type'])) {
					self::$db->query('INSERT INTO `mag_events`(`status`, `mag_device_id`, `event`, `need_confirm`, `msg`, `reboot_after_ok`, `send_time`) VALUES (0, ?, ?, ?, ?, ?, ?);', $rDevice, $rEvent['event'], $rEvent['need_confirm'], $rEvent['msg'], $rEvent['reboot_after_ok'], time());
				}

				if (0 < count($rArray)) {
					$rPrepare = prepareArray($rArray);

					if (0 < count($rPrepare['data'])) {
						$rPrepare['data'][] = $rDevice;
						$rQuery = 'UPDATE `mag_devices` SET ' . $rPrepare['update'] . ' WHERE `mag_id` = ?;';
						self::$db->query($rQuery, ...$rPrepare['data']);
					}
				}

				if (0 < count($rUserArray)) {
					$rUserIDs = [];

					if (isset($rDeviceInfo['user']['id'])) {
						$rUserIDs[] = $rDeviceInfo['user']['id'];
					}

					if (isset($rDeviceInfo['user']['paired'])) {
						$rUserIDs[] = $rDeviceInfo['paired']['id'];
					}

					foreach ($rUserIDs as $rUserID) {
						$rPrepare = prepareArray($rUserArray);

						if (0 < count($rPrepare['data'])) {
							$rPrepare['data'][] = $rUserID;
							$rQuery = 'UPDATE `lines` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
							self::$db->query($rQuery, ...$rPrepare['data']);
							XCMS::updateLine($rUserID);
						}
					}
				}
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function massEditEnigmas($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];
		$rUserArray = [];

		foreach (['is_isplock', 'is_trial'] as $rItem) {
			if (isset($rData['c_' . $rItem])) {
				if (isset($rData[$rItem])) {
					$rUserArray[$rItem] = 1;
				}
				else {
					$rUserArray[$rItem] = 0;
				}
			}
		}

		if (isset($rData['c_admin_notes'])) {
			$rUserArray['admin_notes'] = $rData['admin_notes'];
		}

		if (isset($rData['c_reseller_notes'])) {
			$rUserArray['reseller_notes'] = $rData['reseller_notes'];
		}

		if (isset($rData['c_forced_country'])) {
			$rUserArray['forced_country'] = $rData['forced_country'];
		}

		if (isset($rData['c_member_id'])) {
			$rUserArray['member_id'] = (int) $rData['member_id'];
		}

		if (isset($rData['c_force_server_id'])) {
			$rUserArray['force_server_id'] = (int) $rData['force_server_id'];
		}

		if (isset($rData['c_exp_date'])) {
			if (isset($rData['no_expire'])) {
				$rUserArray['exp_date'] = NULL;
			}
			else {
				try {
					$rDate = new DateTime($rData['exp_date']);
					$rUserArray['exp_date'] = $rDate->format('U');
				}
				catch (Exception $e) {
				}
			}
		}

		if (isset($rData['c_bouquets'])) {
			$rUserArray['bouquet'] = [];

			foreach (json_decode($rData['bouquets_selected'], true) as $rBouquet) {
				if (is_numeric($rBouquet)) {
					$rUserArray['bouquet'][] = $rBouquet;
				}
			}

			$rUserArray['bouquet'] = sortArrayByArray($rUserArray['bouquet'], array_keys(getBouquetOrder()));
			$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
		}

		if (isset($rData['reset_isp_lock'])) {
			$rUserArray['as_number'] = $rUserArray['isp_desc'] = '';
		}

		if (isset($rData['reset_device_lock'])) {
			$rArray['local_ip'] = $rArray['modem_mac'] = $rArray['enigma_version'] = $rArray['cpu'] = $rArray['lversion'] = $rArray['token'] = '';
		}

		$rDevices = json_decode($rData['devices_selected'], true);

		foreach ($rDevices as $rDevice) {
			$rDeviceInfo = getEnigma($rDevice);

			if ($rDeviceInfo) {
				if (0 < count($rArray)) {
					$rPrepare = prepareArray($rArray);

					if (0 < count($rPrepare['data'])) {
						$rPrepare['data'][] = $rDevice;
						$rQuery = 'UPDATE `enigma2_devices` SET ' . $rPrepare['update'] . ' WHERE `device_id` = ?;';
						self::$db->query($rQuery, ...$rPrepare['data']);
					}
				}

				if (0 < count($rUserArray)) {
					$rUserIDs = [];

					if (isset($rDeviceInfo['user']['id'])) {
						$rUserIDs[] = $rDeviceInfo['user']['id'];
					}

					if (isset($rDeviceInfo['user']['paired'])) {
						$rUserIDs[] = $rDeviceInfo['paired']['id'];
					}

					foreach ($rUserIDs as $rUserID) {
						$rPrepare = prepareArray($rUserArray);

						if (0 < count($rPrepare['data'])) {
							$rPrepare['data'][] = $rUserID;
							$rQuery = 'UPDATE `lines` SET ' . $rPrepare['update'] . ' WHERE `id` = ?;';
							self::$db->query($rQuery, ...$rPrepare['data']);
							XCMS::updateLine($rUserID);
						}
					}
				}
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function massEditUsers($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		$rArray = [];

		foreach (['status'] as $rItem) {
			if (isset($rData['c_' . $rItem])) {
				if (isset($rData[$rItem])) {
					$rArray[$rItem] = 1;
				}
				else {
					$rArray[$rItem] = 0;
				}
			}
		}

		if (isset($rData['c_owner_id'])) {
			$rArray['owner_id'] = (int) $rData['owner_id'];
		}

		if (isset($rData['c_member_group_id'])) {
			$rArray['member_group_id'] = (int) $rData['member_group_id'];
		}

		if (isset($rData['c_reseller_dns'])) {
			$rArray['reseller_dns'] = $rData['reseller_dns'];
		}

		if (isset($rData['c_override'])) {
			$rOverride = [];

			foreach ($rData as $rKey => $rCredits) {
				if (substr($rKey, 0, 9) == 'override_') {
					$rID = (int) explode('override_', $rKey)[1];

					if (0 < strlen($rCredits)) {
						$rCredits = (int) $rCredits;
					}
					else {
						$rCredits = NULL;
					}

					if ($rCredits) {
						$rOverride[$rID] = ['assign' => 1, 'official_credits' => $rCredits];
					}
				}
			}

			$rArray['override_packages'] = json_encode($rOverride);
		}

		$rUsers = confirmIDs(json_decode($rData['users_selected'], true));

		if (0 < count($rUsers)) {
			if (isset($rData['c_owner_id']) && ($rUser == $rArray['owner_id'])) {
				unset($rArray['owner_id']);
			}

			$rPrepare = prepareArray($rArray);

			if (0 < count($rPrepare['data'])) {
				$rQuery = 'UPDATE `users` SET ' . $rPrepare['update'] . ' WHERE `id` IN (' . implode(',', $rUsers) . ');';
				self::$db->query($rQuery, ...$rPrepare['data']);
			}
		}

		return ['status' => STATUS_SUCCESS];
	}

	static public function processLine($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (isset($rData['edit'])) {
			if (!hasPermissions('adv', 'edit_user')) {
				exit();
			}

			$rArray = overwriteData(getUser($rData['edit']), $rData);
		}
		else {
			if (!hasPermissions('adv', 'add_user')) {
				exit();
			}

			$rArray = verifyPostTable('lines', $rData);
			$rArray['created_at'] = time();
			unset($rArray['id']);
		}

		if (strlen($rData['username']) == 0) {
			$rArray['username'] = generateString(10);
		}

		if (strlen($rData['password']) == 0) {
			$rArray['password'] = generateString(10);
		}

		foreach (['max_connections', 'enabled', 'admin_enabled'] as $rSelection) {
			if (isset($rData[$rSelection])) {
				$rArray[$rSelection] = (int) $rData[$rSelection];
			}
			else {
				$rArray[$rSelection] = 1;
			}
		}

		foreach (['is_stalker', 'is_restreamer', 'is_trial', 'is_isplock', 'bypass_ua'] as $rSelection) {
			if (isset($rData[$rSelection])) {
				$rArray[$rSelection] = 1;
			}
			else {
				$rArray[$rSelection] = 0;
			}
		}

		if (strlen($rData['isp_clear']) == 0) {
			$rArray['isp_desc'] = '';
			$rArray['as_number'] = NULL;
		}

		$rArray['bouquet'] = sortArrayByArray(array_values(json_decode($rData['bouquets_selected'], true)), array_keys(getBouquetOrder()));
		$rArray['bouquet'] = '[' . implode(',', array_map('intval', $rArray['bouquet'])) . ']';
		if (isset($rData['exp_date']) && !isset($rData['no_expire'])) {
			if ((0 < strlen($rData['exp_date'])) && ($rData['exp_date'] != '1970-01-01')) {
				try {
					$rDate = new DateTime($rData['exp_date']);
					$rArray['exp_date'] = $rDate->format('U');
				}
				catch (Exception $e) {
					return ['status' => STATUS_INVALID_DATE, 'data' => $rData];
				}
			}
		}
		else {
			$rArray['exp_date'] = NULL;
		}

		if (!$rArray['member_id']) {
			$rArray['member_id'] = self::$rUserInfo['id'];
		}

		if (isset($rData['allowed_ips'])) {
			if (!is_array($rData['allowed_ips'])) {
				$rData['allowed_ips'] = [$rData['allowed_ips']];
			}

			$rArray['allowed_ips'] = json_encode($rData['allowed_ips']);
		}
		else {
			$rArray['allowed_ips'] = '[]';
		}

		if (isset($rData['allowed_ua'])) {
			if (!is_array($rData['allowed_ua'])) {
				$rData['allowed_ua'] = [$rData['allowed_ua']];
			}

			$rArray['allowed_ua'] = json_encode($rData['allowed_ua']);
		}
		else {
			$rArray['allowed_ua'] = '[]';
		}

		$rOutputs = [];

		if (isset($rData['access_output'])) {
			foreach ($rData['access_output'] as $rOutputID) {
				$rOutputs[] = $rOutputID;
			}
		}

		$rArray['allowed_outputs'] = '[' . implode(',', array_map('intval', $rOutputs)) . ']';

		if (checkExists('lines', 'username', $rArray['username'], 'id', $rData['edit'])) {
			return ['status' => STATUS_EXISTS_USERNAME, 'data' => $rData];
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `lines`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			syncDevices($rInsertID);
			XCMS::updateLine($rInsertID);
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}

	static public function scheduleRecording($rData)
	{
		if (!self::checkMinimumRequirements($rData)) {
			return ['status' => STATUS_INVALID_INPUT, 'data' => $rData];
		}

		if (!hasPermissions('adv', 'add_stream')) {
			exit();
		}

		if (empty($rData['title'])) {
			return ['status' => STATUS_NO_TITLE];
		}

		if (empty($rData['source_id'])) {
			return ['status' => STATUS_NO_SOURCE];
		}

		$rArray = verifyPostTable('recordings', $rData);
		$rArray['bouquets'] = '[' . implode(',', array_map('intval', $rData['bouquets'])) . ']';
		$rArray['category_id'] = '[' . implode(',', array_map('intval', $rData['category_id'])) . ']';
		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `recordings`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			return [
				'status' => STATUS_SUCCESS,
				'data'   => ['insert_id' => $rInsertID]
			];
		}
		else {
			return ['status' => STATUS_FAILURE, 'data' => $rData];
		}
	}
}

?>