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

class ResellerAPI
{
	static public $db = null;
	static public $rSettings = [];
	static public $rServers = [];
	static public $rProxyServers = [];
	static public $rUserInfo = [];
	static public $rPermissions = [];

	static public function processData($rType, $rData)
	{
		$rArray = [
			'line'    => ['edit', 'trial', 'bouquets_selected', 'pair_id', 'username', 'password', 'member_id', 'package', 'contact', 'reseller_notes', 'allowed_ips', 'allowed_ua', 'bypass_ua', 'is_isplock', 'isp_clear'],
			'mag'     => ['edit', 'trial', 'bouquets_selected', 'pair_id', 'mac', 'member_id', 'package', 'parent_password', 'sn', 'stb_type', 'image_version', 'hw_version', 'device_id', 'device_id2', 'ver', 'reseller_notes', 'allowed_ips', 'is_isplock', 'isp_clear'],
			'enigma'  => ['edit', 'trial', 'bouquets_selected', 'pair_id', 'mac', 'member_id', 'package', 'modem_mac', 'local_ip', 'enigma_version', 'cpu', 'lversion', 'token', 'reseller_notes', 'allowed_ips', 'is_isplock', 'isp_clear'],
			'user'    => ['edit', 'username', 'password', 'owner_id', 'email', 'reseller_dns', 'notes', 'member_group_id'],
			'ticket'  => ['edit', 'message', 'title', 'respond'],
			'profile' => ['email', 'password', 'api_key', 'reseller_dns', 'theme', 'hue', 'timezone']
		];

		foreach ($rData as $rKey => $rValue) {
			if (!in_array($rKey, $rArray[$rType])) {
				unset($rData[$rKey]);
			}
		}

		return $rData;
	}

	static public function init($rUserID = NULL)
	{
		self::$rSettings = getSettings();
		self::$rServers = getStreamingServers();
		self::$rProxyServers = getProxyServers();
		if (!$rUserID && isset($_SESSION['reseller'])) {
			$rUserID = $_SESSION['reseller'];
		}

		if ($rUserID) {
			self::$rUserInfo = getRegisteredUser($rUserID);
			self::$rPermissions = array_merge(getPermissions(self::$rUserInfo['member_group_id']) ?: [], getGroupPermissions(self::$rUserInfo['id']) ?: []);
		}
	}

	static public function editResellerProfile($rData)
	{
		global $rHues;
		$rData = self::processData('profile', $rData);
		if ((0 < strlen($rData['email'])) && !filter_var($rData['email'], FILTER_VALIDATE_EMAIL)) {
			return ['status' => STATUS_INVALID_EMAIL];
		}

		if (0 < strlen($rData['password'])) {
			if ((strlen($rData['password']) < (int) self::$rPermissions['minimum_password_length']) && (0 < (int) self::$rPermissions['minimum_password_length'])) {
				return ['status' => STATUS_INVALID_PASSWORD];
			}

			$rPassword = cryptPassword($rData['password']);
		}
		else {
			$rPassword = self::$rUserInfo['password'];
		}
		if (!ctype_xdigit($rData['api_key']) || (strlen($rData['api_key']) != 32)) {
			$rData['api_key'] = '';
		}

		if (!in_array($rData['hue'], $rHues)) {
			$rData['hue'] = '';
		}

		if (!in_array($rData['theme'], [0, 1])) {
			$rData['theme'] = 0;
		}

		self::$db->query('UPDATE `users` SET `password` = ?, `email` = ?, `reseller_dns` = ?, `theme` = ?, `hue` = ?, `timezone` = ?, `api_key` = ? WHERE `id` = ?;', $rPassword, $rData['email'], $rData['reseller_dns'], $rData['theme'], $rData['hue'], $rData['timezone'], $rData['api_key'], self::$rUserInfo['id']);
		return ['status' => STATUS_SUCCESS];
	}

	static public function processLogin($rData)
	{
		if (self::$rSettings['recaptcha_enable']) {
			$rResponse = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . self::$rSettings['recaptcha_v2_secret_key'] . '&response=' . $rData['g-recaptcha-response']), true);

			if (!$rResponse['success']) {
				return ['status' => STATUS_INVALID_CAPTCHA];
			}
		}

		$rIP = getIP();
		$rUserInfo = getUserInfo($rData['username'], $rData['password']);
		$rAccessCode = getCurrentCode(true);

		if (isset($rUserInfo)) {
			if (in_array($rUserInfo['member_group_id'], json_decode($rAccessCode['groups'], true)) || (count(getActiveCodes()) == 0)) {
				$rPermissions = getPermissions($rUserInfo['member_group_id']);

				if ($rPermissions['is_reseller']) {
					if ($rUserInfo['status'] == 1) {
						$rCrypt = cryptPassword($rData['password']);

						if ($rCrypt != $rUserInfo['password']) {
							self::$db->query('UPDATE `users` SET `password` = ?, `last_login` = UNIX_TIMESTAMP(), `ip` = ? WHERE `id` = ?;', $rCrypt, $rIP, $rUserInfo['id']);
						}
						else {
							self::$db->query('UPDATE `users` SET `last_login` = UNIX_TIMESTAMP(), `ip` = ? WHERE `id` = ?;', $rIP, $rUserInfo['id']);
						}

						$_SESSION['reseller'] = $rUserInfo['id'];
						$_SESSION['rip'] = $rIP;
						$_SESSION['rcode'] = getCurrentCode();
						$_SESSION['rverify'] = md5($rUserInfo['username'] . '||' . $rCrypt);

						if (self::$rSettings['save_login_logs']) {
							self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'RESELLER\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'SUCCESS', $rIP, time());
						}

						return ['status' => STATUS_SUCCESS];
					}
					else if ($rPermissions && (($rPermissions['is_admin'] || $rPermissions['is_reseller']) && !$rUserInfo['status'])) {
						if (self::$rSettings['save_login_logs']) {
							self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'RESELLER\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'DISABLED', $rIP, time());
						}

						return ['status' => STATUS_DISABLED];
					}
				}
				else {
					if (self::$rSettings['save_login_logs']) {
						self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'RESELLER\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'NOT_ADMIN', $rIP, time());
					}

					return ['status' => STATUS_NOT_RESELLER];
				}
			}
			else {
				if (self::$rSettings['save_login_logs']) {
					self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'RESELLER\', ?, ?, ?, ?, ?);', $rAccessCode['id'], $rUserInfo['id'], 'INVALID_CODE', $rIP, time());
				}

				return ['status' => STATUS_INVALID_CODE];
			}
		}
		else {
			if (self::$rSettings['save_login_logs']) {
				self::$db->query('INSERT INTO `login_logs`(`type`, `access_code`, `user_id`, `status`, `login_ip`, `date`) VALUES(\'RESELLER\', ?, 0, ?, ?, ?);', $rAccessCode['id'], 'INVALID_LOGIN', $rIP, time());
			}

			return ['status' => STATUS_FAILURE];
		}
	}

	static public function processMAG($rData)
	{
		$rData = self::processData('mag', $rData);

		if (!self::$rPermissions['create_mag']) {
			return false;
		}

		if (isset($rData['edit'])) {
			$rArray = getMag($rData['edit']);
			if (!$rArray || !hasPermissions('line', $rArray['user_id'])) {
				return false;
			}

			$rUserArray = getUser($rArray['user_id']);
		}
		else {
			$rArray = verifyPostTable('mag_devices', $rData);
			$rArray['theme_type'] = self::$rSettings['mag_default_type'];
			$rUserArray = verifyPostTable('lines', $rData);
			$rUserArray['username'] = generateString(32);
			$rUserArray['password'] = generateString(32);
			$rUserArray['created_at'] = time();
			unset($rArray['mag_id']);
			unset($rUserArray['id']);
		}

		$rUserArray['is_mag'] = 1;
		$rUserArray['is_e2'] = 0;
		$rGenTrials = canGenerateTrials(self::$rUserInfo['id']);

		if (!empty($rData['package'])) {
			$rPackage = getPackage($rData['package']);

			if (!$rPackage['is_mag']) {
				return ['status' => STATUS_INVALID_TYPE, 'data' => $rData];
			}
			if ((0 < (int) $rUserArray['package_id']) && $rPackage['check_compatible']) {
				$rCompatible = checkCompatible($rUserArray['package_id'], $rPackage['id']);
			}
			else {
				$rCompatible = true;
			}
			if ($rPackage && in_array(self::$rUserInfo['member_group_id'], json_decode($rPackage['groups'], true))) {
				if ($rData['trial']) {
					if (!$rGenTrials) {
						return ['status' => STATUS_NO_TRIALS, 'data' => $rData];
					}

					$rCost = (int) $rPackage['trial_credits'];
				}
				else {
					$rOverride = json_decode(self::$rUserInfo['override_packages'], true);
					if (isset($rOverride[$rPackage['id']]['official_credits']) && (0 < strlen($rOverride[$rPackage['id']]['official_credits']))) {
						$rCost = (int) $rOverride[$rPackage['id']]['official_credits'];
					}
					else {
						$rCost = (int) $rPackage['official_credits'];
					}
				}

				if ($rCost <= (int) self::$rUserInfo['credits']) {
					if ($rData['trial']) {
						$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['trial_duration'] . ' ' . $rPackage['trial_duration_in']);
						$rUserArray['is_trial'] = 1;
					}
					else {
						if (isset($rUserArray['id']) && $rCompatible) {
							if (time() <= $rUserArray['exp_date']) {
								$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in'], (int) $rUserArray['exp_date']);
							}
							else {
								$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in']);
							}
						}
						else {
							$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in']);
						}

						$rUserArray['is_trial'] = 0;
					}

					$rBouquets = array_values(json_decode($rPackage['bouquets'], true));
					if (self::$rPermissions['allow_change_bouquets'] && (0 < count($rData['bouquets_selected']))) {
						$rNewBouquets = [];

						foreach ($rData['bouquets_selected'] as $rBouquetID) {
							if (in_array($rBouquetID, $rBouquets)) {
								$rNewBouquets[] = $rBouquetID;
							}
						}

						if (0 < count($rNewBouquets)) {
							$rBouquets = $rNewBouquets;
						}
					}

					$rUserArray['bouquet'] = sortArrayByArray($rBouquets, array_keys(getBouquetOrder()));
					$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
					$rUserArray['max_connections'] = $rPackage['max_connections'];
					$rUserArray['is_restreamer'] = $rPackage['is_restreamer'];
					$rUserArray['force_server_id'] = $rPackage['force_server_id'];
					$rUserArray['forced_country'] = $rPackage['forced_country'];
					$rUserArray['is_isplock'] = $rPackage['is_isplock'];
				}
				else {
					return ['status' => STATUS_INSUFFICIENT_CREDITS, 'data' => $rData];
				}
			}
			else {
				return ['status' => STATUS_INVALID_PACKAGE, 'data' => $rData];
			}

			$rOutputs = [];
			$rAccessOutput = json_decode($rPackage['output_formats'], true);

			foreach ($rAccessOutput as $rOutputID) {
				$rOutputs[] = $rOutputID;
			}

			$rUserArray['allowed_outputs'] = '[' . implode(',', array_map('intval', $rOutputs)) . ']';
			$rUserArray['package_id'] = $rPackage['id'];
			$rArray['lock_device'] = $rPackage['lock_device'];
		}
		else if (!isset($rUserArray['id'])) {
			return ['status' => STATUS_INVALID_PACKAGE, 'data' => $rData];
		}
		else if (isset($rData['edit']) && $rUserArray['package_id']) {
			$rPackage = getPackage($rUserArray['package_id']);
			$rBouquets = array_values(json_decode($rPackage['bouquets'], true));
			if (self::$rPermissions['allow_change_bouquets'] && (0 < count($rData['bouquets_selected']))) {
				$rNewBouquets = [];

				foreach ($rData['bouquets_selected'] as $rBouquetID) {
					if (in_array($rBouquetID, $rBouquets)) {
						$rNewBouquets[] = $rBouquetID;
					}
				}

				if (0 < count($rNewBouquets)) {
					$rBouquets = $rNewBouquets;
				}
			}

			$rUserArray['bouquet'] = sortArrayByArray($rBouquets, array_keys(getBouquetOrder()));
			$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
		}

		foreach (['parent_password', 'sn', 'stb_type', 'image_version', 'hw_version', 'device_id', 'device_id2', 'ver'] as $rKey) {
			$rArray[$rKey] = $rData[$rKey];
		}

		$rUserArray['reseller_notes'] = $rData['reseller_notes'];
		$rOwner = $rData['member_id'];

		if (hasPermissions('user', $rOwner)) {
			$rUserArray['member_id'] = $rOwner;
		}
		else {
			$rUserArray['member_id'] = self::$rUserInfo['id'];
		}

		if (self::$rPermissions['allow_restrictions']) {
			if (isset($rData['allowed_ips'])) {
				if (!is_array($rData['allowed_ips'])) {
					$rData['allowed_ips'] = [$rData['allowed_ips']];
				}

				$rUserArray['allowed_ips'] = json_encode($rData['allowed_ips']);
			}
			else {
				$rUserArray['allowed_ips'] = '[]';
			}

			if (isset($rData['is_isplock'])) {
				$rUserArray['is_isplock'] = 1;
			}
			else {
				$rUserArray['is_isplock'] = 0;
			}

			if (strlen($rData['isp_clear']) == 0) {
				$rUserArray['isp_desc'] = '';
				$rUserArray['as_number'] = NULL;
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

		$rArray['mac'] = $rData['mac'];
		if (isset($rData['pair_id']) && hasPermissions('line', $rData['pair_id'])) {
			$rUserArray['pair_id'] = (int) $rData['pair_id'];
		}
		else {
			$rUserArray['pair_id'] = NULL;
		}

		$rPrepare = prepareArray($rUserArray);
		$rQuery = 'REPLACE INTO `lines`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			syncDevices($rInsertID);
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', SERVER_ID, time(), json_encode(['type' => 'update_line', 'id' => $rInsertID]));
			$rArray['user_id'] = $rInsertID;
			unset($rArray['user']);
			unset($rArray['paired']);

			if (!isset($rData['edit'])) {
				$rArray['sn'] = $rArray['image_version'] = $rArray['stb_type'] = $rArray['hw_version'] = $rArray['device_id'] = $rArray['device_id2'] = $rArray['ver'] = '';
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `mag_devices`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();

				if (isset($rPackage)) {
					$rNewCredits = (int) self::$rUserInfo['credits'] - (int) $rCost;
					self::$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rNewCredits, self::$rUserInfo['id']);

					if (isset($rArray['id'])) {
						if ($rUserArray['package_id']) {
							$rType = 'extend';
						}
						else {
							$rType = 'edit';
						}
					}
					else {
						$rType = 'new';
					}

					$rData = getMag($rInsertID);
					self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'mag\', ?, ?, ?, ?, ?, ?, ?);', self::$rUserInfo['id'], $rType, $rInsertID, $rPackage['id'], $rCost, $rNewCredits, time(), json_encode($rData));
				}
				else {
					self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'mag\', ?, ?, null, ?, ?, ?, ?);', self::$rUserInfo['id'], 'edit', $rInsertID, 0, self::$rUserInfo['credits'], time(), json_encode($rData));
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
		$rData = self::processData('enigma', $rData);

		if (!self::$rPermissions['create_enigma']) {
			return false;
		}

		if (isset($rData['edit'])) {
			$rArray = getEnigma($rData['edit']);
			if (!$rArray || !hasPermissions('line', $rArray['user_id'])) {
				return false;
			}

			$rUserArray = getUser($rArray['user_id']);
		}
		else {
			$rArray = verifyPostTable('enigma2_devices', $rData);
			$rUserArray = verifyPostTable('lines', $rData);
			$rUserArray['username'] = generateString(32);
			$rUserArray['password'] = generateString(32);
			$rUserArray['created_at'] = time();
			unset($rArray['device_id']);
			unset($rUserArray['id']);
		}

		$rUserArray['is_mag'] = 0;
		$rUserArray['is_e2'] = 1;
		$rGenTrials = canGenerateTrials(self::$rUserInfo['id']);

		if (!empty($rData['package'])) {
			$rPackage = getPackage($rData['package']);

			if (!$rPackage['is_e2']) {
				return ['status' => STATUS_INVALID_TYPE, 'data' => $rData];
			}
			if ((0 < (int) $rUserArray['package_id']) && $rPackage['check_compatible']) {
				$rCompatible = checkCompatible($rUserArray['package_id'], $rPackage['id']);
			}
			else {
				$rCompatible = true;
			}
			if ($rPackage && in_array(self::$rUserInfo['member_group_id'], json_decode($rPackage['groups'], true))) {
				if ($rData['trial']) {
					if (!$rGenTrials) {
						return ['status' => STATUS_NO_TRIALS, 'data' => $rData];
					}

					$rCost = (int) $rPackage['trial_credits'];
				}
				else {
					$rOverride = json_decode(self::$rUserInfo['override_packages'], true);
					if (isset($rOverride[$rPackage['id']]['official_credits']) && (0 < strlen($rOverride[$rPackage['id']]['official_credits']))) {
						$rCost = (int) $rOverride[$rPackage['id']]['official_credits'];
					}
					else {
						$rCost = (int) $rPackage['official_credits'];
					}
				}

				if ($rCost <= (int) self::$rUserInfo['credits']) {
					if ($rData['trial']) {
						$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['trial_duration'] . ' ' . $rPackage['trial_duration_in']);
						$rUserArray['is_trial'] = 1;
					}
					else {
						if (isset($rUserArray['id']) && $rCompatible) {
							if (time() <= $rUserArray['exp_date']) {
								$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in'], (int) $rUserArray['exp_date']);
							}
							else {
								$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in']);
							}
						}
						else {
							$rUserArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in']);
						}

						$rUserArray['is_trial'] = 0;
					}

					$rBouquets = array_values(json_decode($rPackage['bouquets'], true));
					if (self::$rPermissions['allow_change_bouquets'] && (0 < count($rData['bouquets_selected']))) {
						$rNewBouquets = [];

						foreach ($rData['bouquets_selected'] as $rBouquetID) {
							if (in_array($rBouquetID, $rBouquets)) {
								$rNewBouquets[] = $rBouquetID;
							}
						}

						if (0 < count($rNewBouquets)) {
							$rBouquets = $rNewBouquets;
						}
					}

					$rUserArray['bouquet'] = sortArrayByArray($rBouquets, array_keys(getBouquetOrder()));
					$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
					$rUserArray['max_connections'] = $rPackage['max_connections'];
					$rUserArray['is_restreamer'] = $rPackage['is_restreamer'];
					$rUserArray['force_server_id'] = $rPackage['force_server_id'];
					$rUserArray['forced_country'] = $rPackage['forced_country'];
					$rUserArray['is_isplock'] = $rPackage['is_isplock'];
				}
				else {
					return ['status' => STATUS_INSUFFICIENT_CREDITS, 'data' => $rData];
				}
			}
			else {
				return ['status' => STATUS_INVALID_PACKAGE, 'data' => $rData];
			}

			$rOutputs = [];
			$rAccessOutput = json_decode($rPackage['output_formats'], true);

			foreach ($rAccessOutput as $rOutputID) {
				$rOutputs[] = $rOutputID;
			}

			$rUserArray['allowed_outputs'] = '[' . implode(',', array_map('intval', $rOutputs)) . ']';
			$rUserArray['package_id'] = $rPackage['id'];
			$rArray['lock_device'] = $rPackage['lock_device'];
		}
		else if (!isset($rUserArray['id'])) {
			return ['status' => STATUS_INVALID_PACKAGE, 'data' => $rData];
		}
		else if (isset($rData['edit']) && $rUserArray['package_id']) {
			$rPackage = getPackage($rUserArray['package_id']);
			$rBouquets = array_values(json_decode($rPackage['bouquets'], true));
			if (self::$rPermissions['allow_change_bouquets'] && (0 < count($rData['bouquets_selected']))) {
				$rNewBouquets = [];

				foreach ($rData['bouquets_selected'] as $rBouquetID) {
					if (in_array($rBouquetID, $rBouquets)) {
						$rNewBouquets[] = $rBouquetID;
					}
				}

				if (0 < count($rNewBouquets)) {
					$rBouquets = $rNewBouquets;
				}
			}

			$rUserArray['bouquet'] = sortArrayByArray($rBouquets, array_keys(getBouquetOrder()));
			$rUserArray['bouquet'] = '[' . implode(',', array_map('intval', $rUserArray['bouquet'])) . ']';
		}

		foreach (['modem_mac', 'local_ip', 'enigma_version', 'cpu', 'lversion', 'token'] as $rKey) {
			$rArray[$rKey] = $rData[$rKey];
		}

		$rUserArray['reseller_notes'] = $rData['reseller_notes'];
		$rOwner = $rData['member_id'];

		if (hasPermissions('user', $rOwner)) {
			$rUserArray['member_id'] = $rOwner;
		}
		else {
			$rUserArray['member_id'] = self::$rUserInfo['id'];
		}

		if (self::$rPermissions['allow_restrictions']) {
			if (isset($rData['allowed_ips'])) {
				if (!is_array($rData['allowed_ips'])) {
					$rData['allowed_ips'] = [$rData['allowed_ips']];
				}

				$rUserArray['allowed_ips'] = json_encode($rData['allowed_ips']);
			}
			else {
				$rUserArray['allowed_ips'] = '[]';
			}

			if (isset($rData['is_isplock'])) {
				$rUserArray['is_isplock'] = 1;
			}
			else {
				$rUserArray['is_isplock'] = 0;
			}

			if (strlen($rData['isp_clear']) == 0) {
				$rUserArray['isp_desc'] = '';
				$rUserArray['as_number'] = NULL;
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

		$rArray['mac'] = $rData['mac'];
		if (isset($rData['pair_id']) && hasPermissions('line', $rData['pair_id'])) {
			$rUserArray['pair_id'] = (int) $rData['pair_id'];
		}
		else {
			$rUserArray['pair_id'] = NULL;
		}

		$rPrepare = prepareArray($rUserArray);
		$rQuery = 'REPLACE INTO `lines`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			syncDevices($rInsertID);
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', SERVER_ID, time(), json_encode(['type' => 'update_line', 'id' => $rInsertID]));
			$rArray['user_id'] = $rInsertID;
			unset($rArray['user']);
			unset($rArray['paired']);

			if (!isset($rData['edit'])) {
				$rArray['modem_mac'] = $rArray['local_ip'] = $rArray['enigma_version'] = $rArray['cpu'] = $rArray['lversion'] = $rArray['token'] = '';
			}

			$rPrepare = prepareArray($rArray);
			$rQuery = 'REPLACE INTO `enigma2_devices`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if (self::$db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = self::$db->last_insert_id();

				if (isset($rPackage)) {
					$rNewCredits = (int) self::$rUserInfo['credits'] - (int) $rCost;
					self::$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rNewCredits, self::$rUserInfo['id']);

					if (isset($rArray['id'])) {
						if ($rArray['package_id']) {
							$rType = 'extend';
						}
						else {
							$rType = 'edit';
						}
					}
					else {
						$rType = 'new';
					}

					$rData = getEnigma($rInsertID);
					self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'enigma\', ?, ?, ?, ?, ?, ?, ?);', self::$rUserInfo['id'], $rType, $rInsertID, $rPackage['id'], $rCost, $rNewCredits, time(), json_encode($rData));
				}
				else {
					self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'enigma\', ?, ?, null, ?, ?, ?, ?);', self::$rUserInfo['id'], 'edit', $rInsertID, 0, self::$rUserInfo['credits'], time(), json_encode($rData));
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

	static public function processUser($rData)
	{
		$rData = self::processData('user', $rData);

		if (!self::$rPermissions['create_sub_resellers']) {
			return false;
		}

		if (isset($rData['edit'])) {
			$rArray = getRegisteredUser($rData['edit']);
			if (!$rArray || !hasPermissions('user', $rArray['id'])) {
				return false;
			}

			if ($rArray['id'] == self::$rUserInfo['id']) {
				return false;
			}
		}
		else {
			$rArray = verifyPostTable('users', $rData);
			$rArray['date_registered'] = time();
			unset($rArray['id']);
		}

		if (!self::$rPermissions['allow_change_username']) {
			if (isset($rArray['id'])) {
				$rData['username'] = $rArray['username'];
			}
			else {
				$rData['username'] = generateString(10 < self::$rPermissions['minimum_username_length'] ? self::$rPermissions['minimum_username_length'] : 10);
			}
		}

		if (!self::$rPermissions['allow_change_password']) {
			if (isset($rArray['id'])) {
				$rData['password'] = '';
			}
			else {
				$rData['password'] = generateString(10 < self::$rPermissions['minimum_password_length'] ? self::$rPermissions['minimum_password_length'] : 10);
			}
		}
		if ((strlen($rData['username']) < self::$rPermissions['minimum_username_length']) && !(isset($rData['edit']) && (strlen($rData['username']) == 0))) {
			return ['status' => STATUS_INVALID_USERNAME, 'data' => $rData];
		}
		if ((strlen($rData['password']) < self::$rPermissions['minimum_password_length']) && !(isset($rData['edit']) && (strlen($rData['password']) == 0))) {
			return ['status' => STATUS_INVALID_PASSWORD, 'data' => $rData];
		}

		if (checkExists('users', 'username', $rArray['username'], 'id', $rData['edit'])) {
			return ['status' => STATUS_EXISTS_USERNAME, 'data' => $rData];
		}

		$rArray['username'] = $rData['username'];

		if (0 < strlen($rData['password'])) {
			$rArray['password'] = cryptPassword($rData['password']);
		}
		if ((0 < count(self::$rPermissions['all_reports'])) && in_array((int) $rData['owner_id'], self::$rPermissions['all_reports']) && (!isset($rArray['id']) || ($rArray['id'] != $rData['owner_id']))) {
			$rArray['owner_id'] = (int) $rData['owner_id'];
		}
		else {
			$rArray['owner_id'] = self::$rUserInfo['id'];
		}

		if (!isset($rData['edit'])) {
			$rCost = (int) self::$rPermissions['create_sub_resellers_price'];

			if ((self::$rUserInfo['credits'] - $rCost) < 0) {
				return ['status' => STATUS_INSUFFICIENT_CREDITS, 'data' => $rData];
			}
		}
		if (isset($rData['member_group_id']) && in_array($rData['member_group_id'], self::$rPermissions['subresellers'])) {
			$rArray['member_group_id'] = $rData['member_group_id'];
		}
		else if (0 < count(self::$rPermissions['subresellers'])) {
			$rArray['member_group_id'] = self::$rPermissions['subresellers'][0];
		}
		else {
			return ['status' => STATUS_INVALID_SUBRESELLER, 'data' => $rData];
		}

		$rArray['email'] = $rData['email'];
		$rArray['reseller_dns'] = $rData['reseller_dns'];
		$rArray['notes'] = $rData['notes'];
		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `users`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			$rData = getRegisteredUser($rInsertID);

			if (isset($rCost)) {
				$rNewCredits = (int) self::$rUserInfo['credits'] - (int) $rCost;
				self::$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rNewCredits, self::$rUserInfo['id']);
				self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', self::$rUserInfo['id'], 'new', $rInsertID, $rCost, $rNewCredits, time(), json_encode($rData));
			}
			else {
				self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', self::$rUserInfo['id'], 'edit', $rInsertID, 0, self::$rUserInfo['credits'], time(), json_encode($rData));
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

	static public function submitTicket($rData)
	{
		$rData = self::processData('ticket', $rData);

		if (isset($rData['edit'])) {
			$rArray = getTicket($rData['edit']);
			if (!$rArray || !hasPermissions('user', $rArray['member_id'])) {
				return false;
			}
		}
		else {
			$rArray = verifyPostTable('tickets', $rData);
			unset($rArray['id']);
		}
		if (((strlen($rData['title']) == 0) && !isset($rData['respond'])) || (strlen($rData['message']) == 0)) {
			return ['status' => STATUS_INVALID_DATA, 'data' => $rData];
		}

		$rArray['member_id'] = self::$rUserInfo['id'];

		if (!isset($rData['respond'])) {
			$rArray['title'] = $rData['title'];
			$rArray['status'] = 1;
			$rArray['admin_read'] = 0;
			$rArray['user_read'] = 0;
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

	static public function processLine($rData)
	{
		$rData = self::processData('line', $rData);

		if (!self::$rPermissions['create_line']) {
			return false;
		}

		if (isset($rData['edit'])) {
			$rArray = getUser($rData['edit']);
			$rOrigCredentials = ['username' => $rArray['username'], 'password' => $rArray['password']];
			if (!$rArray || !hasPermissions('line', $rArray['id'])) {
				return false;
			}
		}
		else {
			$rArray = verifyPostTable('lines', $rData);
			$rArray['created_at'] = time();
			unset($rArray['id']);
		}

		$rArray['is_mag'] = 0;
		$rArray['is_e2'] = 0;
		$rGenTrials = canGenerateTrials(self::$rUserInfo['id']);

		if (!empty($rData['package'])) {
			$rPackage = getPackage($rData['package']);

			if (!$rPackage['is_line']) {
				return ['status' => STATUS_INVALID_TYPE, 'data' => $rData];
			}
			if ((0 < (int) $rArray['package_id']) && $rPackage['check_compatible']) {
				$rCompatible = checkCompatible($rArray['package_id'], $rPackage['id']);
			}
			else {
				$rCompatible = true;
			}
			if ($rPackage && in_array(self::$rUserInfo['member_group_id'], json_decode($rPackage['groups'], true))) {
				if ($rData['trial']) {
					if (!$rGenTrials) {
						return ['status' => STATUS_NO_TRIALS, 'data' => $rData];
					}

					$rCost = (int) $rPackage['trial_credits'];
				}
				else {
					$rOverride = json_decode(self::$rUserInfo['override_packages'], true);
					if (isset($rOverride[$rPackage['id']]['official_credits']) && (0 < strlen($rOverride[$rPackage['id']]['official_credits']))) {
						$rCost = (int) $rOverride[$rPackage['id']]['official_credits'];
					}
					else {
						$rCost = (int) $rPackage['official_credits'];
					}
				}

				if ($rCost <= (int) self::$rUserInfo['credits']) {
					if ($rData['trial']) {
						$rArray['exp_date'] = strtotime('+' . (int) $rPackage['trial_duration'] . ' ' . $rPackage['trial_duration_in']);
						$rArray['is_trial'] = 1;
					}
					else {
						if (isset($rArray['id']) && $rCompatible) {
							if (time() <= $rArray['exp_date']) {
								$rArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in'], (int) $rArray['exp_date']);
							}
							else {
								$rArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in']);
							}
						}
						else {
							$rArray['exp_date'] = strtotime('+' . (int) $rPackage['official_duration'] . ' ' . $rPackage['official_duration_in']);
						}

						$rArray['is_trial'] = 0;
					}

					$rBouquets = array_values(json_decode($rPackage['bouquets'], true));
					if (self::$rPermissions['allow_change_bouquets'] && (0 < count($rData['bouquets_selected']))) {
						$rNewBouquets = [];

						foreach ($rData['bouquets_selected'] as $rBouquetID) {
							if (in_array($rBouquetID, $rBouquets)) {
								$rNewBouquets[] = $rBouquetID;
							}
						}

						if (0 < count($rNewBouquets)) {
							$rBouquets = $rNewBouquets;
						}
					}

					$rArray['bouquet'] = sortArrayByArray($rBouquets, array_keys(getBouquetOrder()));
					$rArray['bouquet'] = '[' . implode(',', array_map('intval', $rArray['bouquet'])) . ']';
					$rArray['max_connections'] = $rPackage['max_connections'];
					$rArray['is_restreamer'] = $rPackage['is_restreamer'];
					$rArray['force_server_id'] = $rPackage['force_server_id'];
					$rArray['forced_country'] = $rPackage['forced_country'];
					$rArray['is_isplock'] = $rPackage['is_isplock'];
				}
				else {
					return ['status' => STATUS_INSUFFICIENT_CREDITS, 'data' => $rData];
				}
			}
			else {
				return ['status' => STATUS_INVALID_PACKAGE, 'data' => $rData];
			}

			$rArray['package_id'] = $rPackage['id'];
		}
		else if (!isset($rArray['id'])) {
			return ['status' => STATUS_INVALID_PACKAGE, 'data' => $rData];
		}
		else if (isset($rData['edit']) && $rArray['package_id']) {
			$rPackage = getPackage($rArray['package_id']);
			$rBouquets = array_values(json_decode($rPackage['bouquets'], true));
			if (self::$rPermissions['allow_change_bouquets'] && (0 < count($rData['bouquets_selected']))) {
				$rNewBouquets = [];

				foreach ($rData['bouquets_selected'] as $rBouquetID) {
					if (in_array($rBouquetID, $rBouquets)) {
						$rNewBouquets[] = $rBouquetID;
					}
				}

				if (0 < count($rNewBouquets)) {
					$rBouquets = $rNewBouquets;
				}
			}

			$rArray['bouquet'] = sortArrayByArray($rBouquets, array_keys(getBouquetOrder()));
			$rArray['bouquet'] = '[' . implode(',', array_map('intval', $rArray['bouquet'])) . ']';
		}

		$rArray['contact'] = $rData['contact'];
		$rArray['reseller_notes'] = $rData['reseller_notes'];
		$rOwner = $rData['member_id'];

		if (hasPermissions('user', $rOwner)) {
			$rArray['member_id'] = $rOwner;
		}
		else {
			$rArray['member_id'] = self::$rUserInfo['id'];
		}

		if (!self::$rPermissions['allow_change_username']) {
			if (isset($rArray['id'])) {
				$rData['username'] = $rArray['username'];
			}
			else {
				$rData['username'] = '';
			}
		}

		if (!self::$rPermissions['allow_change_password']) {
			if (isset($rArray['id'])) {
				$rData['password'] = $rArray['password'];
			}
			else {
				$rData['password'] = '';
			}
		}

		if (strlen($rData['username']) == 0) {
			if (!isset($rData['edit'])) {
				$rData['username'] = generateString(10 < self::$rPermissions['minimum_username_length'] ? self::$rPermissions['minimum_username_length'] : 10);
			}
			else {
				$rData['username'] = $rArray['username'];
			}
		}
		else if (strlen($rData['username']) < self::$rPermissions['minimum_username_length']) {
			if (!isset($rData['edit']) || ($rData['username'] != $rOrigCredentials['username'])) {
				return ['status' => STATUS_INVALID_USERNAME, 'data' => $rData];
			}
		}

		if (strlen($rData['password']) == 0) {
			if (!isset($rData['edit'])) {
				$rData['password'] = generateString(10 < self::$rPermissions['minimum_password_length'] ? self::$rPermissions['minimum_password_length'] : 10);
			}
			else {
				$rData['password'] = $rArray['password'];
			}
		}
		else if (strlen($rData['password']) < self::$rPermissions['minimum_password_length']) {
			if (!isset($rData['edit']) || ($rData['password'] != $rOrigCredentials['password'])) {
				return ['status' => STATUS_INVALID_PASSWORD, 'data' => $rData];
			}
		}

		if (!empty($rData['username'])) {
			$rArray['username'] = $rData['username'];
		}

		if (!empty($rData['password'])) {
			$rArray['password'] = $rData['password'];
		}

		if (checkExists('lines', 'username', $rArray['username'], 'id', $rData['edit'])) {
			return ['status' => STATUS_EXISTS_USERNAME, 'data' => $rData];
		}

		if (self::$rPermissions['allow_restrictions']) {
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

			if (isset($rData['bypass_ua'])) {
				$rArray['bypass_ua'] = 1;
			}
			else {
				$rArray['bypass_ua'] = 0;
			}

			if (isset($rData['is_isplock'])) {
				$rArray['is_isplock'] = 1;
			}
			else {
				$rArray['is_isplock'] = 0;
			}

			if (strlen($rData['isp_clear']) == 0) {
				$rArray['isp_desc'] = '';
				$rArray['as_number'] = NULL;
			}
		}

		if (isset($rPackage)) {
			$rOutputs = [];
			$rAccessOutput = json_decode($rPackage['output_formats'], true);

			foreach ($rAccessOutput as $rOutputID) {
				$rOutputs[] = $rOutputID;
			}

			$rArray['allowed_outputs'] = '[' . implode(',', array_map('intval', $rOutputs)) . ']';
		}

		$rPrepare = prepareArray($rArray);
		$rQuery = 'REPLACE INTO `lines`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

		if (self::$db->query($rQuery, ...$rPrepare['data'])) {
			$rInsertID = self::$db->last_insert_id();
			syncDevices($rInsertID);
			self::$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', SERVER_ID, time(), json_encode(['type' => 'update_line', 'id' => $rInsertID]));

			if (isset($rPackage)) {
				$rNewCredits = (int) self::$rUserInfo['credits'] - (int) $rCost;
				self::$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rNewCredits, self::$rUserInfo['id']);

				if (isset($rArray['id'])) {
					if ($rArray['package_id']) {
						$rType = 'extend';
					}
					else {
						$rType = 'edit';
					}
				}
				else {
					$rType = 'new';
				}

				$rData = getUser($rInsertID);
				self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, ?, ?, ?, ?, ?);', self::$rUserInfo['id'], $rType, $rInsertID, $rPackage['id'], $rCost, $rNewCredits, time(), json_encode($rData));
			}
			else {
				self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', self::$rUserInfo['id'], 'edit', $rInsertID, 0, self::$rUserInfo['credits'], time(), json_encode($rData));
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
}

?>