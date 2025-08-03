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

class APIWrapper
{
	static public $db = null;
	static public $rKey = null;

	static public function filterRow($rData, $rShow, $rHide, $rSkipResult = false)
	{
		if (!$rShow && !$rHide) {
			return $rData;
		}

		if ($rSkipResult) {
			$rRow = $rData;
		}
		else {
			$rRow = $rData['data'];
		}

		$rReturn = [];

		if ($rRow) {
			foreach (array_keys($rRow) as $rKey) {
				if ($rShow) {
					if (in_array($rKey, $rShow)) {
						$rReturn[$rKey] = $rRow[$rKey];
					}
				}
				else if ($rHide) {
					if (!in_array($rKey, $rHide)) {
						$rReturn[$rKey] = $rRow[$rKey];
					}
				}
			}
		}

		if ($rSkipResult) {
			return $rReturn;
		}
		else {
			$rData['data'] = $rReturn;
			return $rData;
		}
	}

	static public function filterRows($rRows, $rShow, $rHide)
	{
		$rReturn = [];

		if ($rRows['data']) {
			foreach ($rRows['data'] as $rRow) {
				$rReturn[] = self::filterRow($rRow, $rShow, $rHide, true);
			}
		}

		return $rReturn;
	}

	static public function TableAPI($rID, $rStart = 0, $rLimit = 10, $rData = [], $rShowColumns = [], $rHideColumns = [])
	{
		$rTableAPI = 'http://127.0.0.1:' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/' . trim(dirname($_SERVER['PHP_SELF']), '/') . '/table.php';
		$rData['api_key'] = self::$rKey;
		$rData['id'] = $rID;
		$rData['start'] = $rStart;
		$rData['length'] = $rLimit;
		$rData['show_columns'] = $rShowColumns;
		$rData['hide_columns'] = $rHideColumns;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $rTableAPI);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($rData));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: xmlhttprequest']);
		$rReturn = json_decode(curl_exec($ch), true);
		curl_close($ch);
		return $rReturn;
	}

	static public function createSession()
	{
		global $rUserInfo;
		global $rPermissions;
		self::$db->query('SELECT * FROM `users` LEFT JOIN `users_groups` ON `users_groups`.`group_id` = `users`.`member_group_id` WHERE `api_key` = ? AND LENGTH(`api_key`) > 0 AND `is_admin` = 1 AND `status` = 1;', self::$rKey);

		if (0 < self::$db->num_rows()) {
			API::$db = & self::$db;
			API::init(self::$db->get_row()['id']);
			unset(unset(API::$rUserInfo)['password']);
			$rUserInfo = API::$rUserInfo;
			$rPermissions = getPermissions($rUserInfo['member_group_id']);
			$rPermissions['advanced'] = [];

			if (0 < strlen($rUserInfo['timezone'])) {
				date_default_timezone_set($rUserInfo['timezone']);
			}

			return true;
		}

		return false;
	}

	static public function getUserInfo()
	{
		global $rUserInfo;
		global $rPermissions;
		return ['status' => 'STATUS_SUCCESS', 'data' => $rUserInfo, 'permissions' => $rPermissions];
	}

	static public function getLine($rID)
	{
		if ($rLine = getUser($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rLine];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createLine($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processLine($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getLine($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editLine($rID, $rData)
	{
		if (($rLine = self::getLine($rID)) && isset($rLine['data'])) {
			$rData['edit'] = $rID;

			if (isset($rData['isp_clear'])) {
				$rData['isp_clear'] = '';
			}

			$rReturn = parseerror(API::processLine($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getLine($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteLine($rID)
	{
		if (($rLine = self::getLine($rID)) && isset($rLine['data'])) {
			if (deleteLine($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableLine($rID)
	{
		if (($rLine = self::getLine($rID)) && isset($rLine['data'])) {
			self::$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableLine($rID)
	{
		if (($rLine = self::getLine($rID)) && isset($rLine['data'])) {
			self::$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function banLine($rID)
	{
		if (($rLine = self::getLine($rID)) && isset($rLine['data'])) {
			self::$db->query('UPDATE `lines` SET `admin_enabled` = 0 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function unbanLine($rID)
	{
		if (($rLine = self::getLine($rID)) && isset($rLine['data'])) {
			self::$db->query('UPDATE `lines` SET `admin_enabled` = 1 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getUser($rID)
	{
		if ($rUser = getRegisteredUser($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rUser];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createUser($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processUser($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getUser($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editUser($rID, $rData)
	{
		if (($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processUser($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getUser($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteUser($rID)
	{
		if (($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			if (deleteUser($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableUser($rID)
	{
		if (($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			self::$db->query('UPDATE `users` SET `status` = 0 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableUser($rID)
	{
		if (($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			self::$db->query('UPDATE `users` SET `status` = 1 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getMAG($rID)
	{
		if ($rDevice = getMag($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rDevice];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createMAG($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processMAG($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getMAG($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editMAG($rID, $rData)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			$rData['edit'] = $rID;

			if (isset($rData['isp_clear'])) {
				$rData['isp_clear'] = '';
			}

			$rReturn = parseerror(API::processMAG($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getMAG($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteMAG($rID)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			if (deleteMAG($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableMAG($rID)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableMAG($rID)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function banMAG($rID)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `admin_enabled` = 0 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function unbanMAG($rID)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `admin_enabled` = 1 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function convertMAG($rID)
	{
		if (($rDevice = self::getMag($rID)) && isset($rDevice['data'])) {
			deleteMAG($rID, false, false, true);
			return ['status' => 'STATUS_SUCCESS', 'data' => self::getLine($rDevice['user_id'])];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getEnigma($rID)
	{
		if ($rDevice = getEnigma($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rDevice];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createEnigma($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processEnigma($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getMAG($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editEnigma($rID, $rData)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			$rData['edit'] = $rID;

			if (isset($rData['isp_clear'])) {
				$rData['isp_clear'] = '';
			}

			$rReturn = parseerror(API::processEnigma($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getMAG($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteEnigma($rID)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			if (deleteEnigma($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableEnigma($rID)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableEnigma($rID)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function banEnigma($rID)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `admin_enabled` = 0 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function unbanEnigma($rID)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			self::$db->query('UPDATE `lines` SET `admin_enabled` = 1 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function convertEnigma($rID)
	{
		if (($rDevice = self::getEnigma($rID)) && isset($rDevice['data'])) {
			deleteEnigma($rID, false, false, true);
			return ['status' => 'STATUS_SUCCESS', 'data' => self::getLine($rDevice['user_id'])];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getBouquets()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getBouquets()];
	}

	static public function getBouquet($rID)
	{
		if ($rBouquet = getBouquet($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rBouquet];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createBouquet($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processBouquet($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getBouquet($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editBouquet($rID, $rData)
	{
		if (($rBouquet = self::getBouquet($rID)) && isset($rBouquet['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processBouquet($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getBouquet($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteBouquet($rID)
	{
		if (($rBouquet = self::getBouquet($rID)) && isset($rBouquet['data'])) {
			if (deleteBouquet($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getAccessCodes()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getCodes()];
	}

	static public function getAccessCode($rID)
	{
		if ($rCode = getCode($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rCode];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createAccessCode($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processCode($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getAccessCode($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editAccessCode($rID, $rData)
	{
		if (($rCode = self::getAccessCode($rID)) && isset($rCode['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processCode($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getAccessCode($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteAccessCode($rID)
	{
		if (($rCode = self::getAccessCode($rID)) && isset($rCode['data'])) {
			if (deleteCode($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getHMACs()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getHMACTokens()];
	}

	static public function getHMAC($rID)
	{
		if ($rToken = getHMACToken($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rToken];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createHMAC($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processHMAC($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getHMAC($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editHMAC($rID, $rData)
	{
		if (($rToken = self::getHMAC($rID)) && isset($rToken['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processHMAC($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getHMAC($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteHMAC($rID)
	{
		if (($rToken = self::getHMAC($rID)) && isset($rToken['data'])) {
			if (deleteHMAC($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getEPGs()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getEPGs()];
	}

	static public function getEPG($rID)
	{
		if ($rEPG = getEPG($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rEPG];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createEPG($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processEPG($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getEPG($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editEPG($rID, $rData)
	{
		if (($rEPG = self::getEPG($rID)) && isset($rEPG['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processEPG($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getEPG($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteEPG($rID)
	{
		if (($rEPG = self::getEPG($rID)) && isset($rEPG['data'])) {
			if (deleteEPG($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function reloadEPG($rID = NULL)
	{
		if ($rID) {
			shell_exec(PHP_BIN . ' ' . CRON_PATH . 'epg.php "' . (int) $rID . '" > /dev/null 2>/dev/null &');
		}
		else {
			shell_exec(PHP_BIN . ' ' . CRON_PATH . 'epg.php > /dev/null 2>/dev/null &');
		}

		return ['status' => 'STATUS_SUCCESS'];
	}

	static public function getProviders()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getStreamProviders()];
	}

	static public function getProvider($rID)
	{
		if ($rProvider = getStreamProvider($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rProvider];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createProvider($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processProvider($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getProvider($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editProvider($rID, $rData)
	{
		if (($rProvider = self::getProvider($rID)) && isset($rProvider['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processProvider($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getProvider($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteProvider($rID)
	{
		if (($rProvider = self::getProvider($rID)) && isset($rProvider['data'])) {
			if (deleteProvider($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function reloadProvider($rID = NULL)
	{
		if ($rID) {
			shell_exec(PHP_BIN . ' ' . CRON_PATH . 'providers.php "' . (int) $rID . '" > /dev/null 2>/dev/null &');
		}
		else {
			shell_exec(PHP_BIN . ' ' . CRON_PATH . 'providers.php > /dev/null 2>/dev/null &');
		}

		return ['status' => 'STATUS_SUCCESS'];
	}

	static public function getGroups()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getMemberGroups()];
	}

	static public function getGroup($rID)
	{
		if ($rGroup = getMemberGroup($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rGroup];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createGroup($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processGroup($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getGroup($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editGroup($rID, $rData)
	{
		if (($rGroup = self::getGroup($rID)) && isset($rGroup['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processGroup($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getGroup($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteGroup($rID)
	{
		if (($rGroup = self::getGroup($rID)) && isset($rGroup['data'])) {
			if (deleteGroup($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getPackages()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getPackages()];
	}

	static public function getPackage($rID)
	{
		if ($rPackage = getPackage($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rPackage];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createPackage($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processPackage($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getPackage($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editPackage($rID, $rData)
	{
		if (($rPackage = self::getPackage($rID)) && isset($rPackage['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processPackage($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getPackage($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deletePackage($rID)
	{
		if (($rPackage = self::getPackage($rID)) && isset($rPackage['data'])) {
			if (deletePackage($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getTranscodeProfiles()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getTranscodeProfiles()];
	}

	static public function getTranscodeProfile($rID)
	{
		if ($rProfile = getTranscodeProfile($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rProfile];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createTranscodeProfile($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processProfile($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getTranscodeProfile($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editTranscodeProfile($rID, $rData)
	{
		if (($rProfile = self::getTranscodeProfile($rID)) && isset($rProfile['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processProfile($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getTranscodeProfile($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteTranscodeProfile($rID)
	{
		if (($rProfile = self::getTranscodeProfile($rID)) && isset($rProfile['data'])) {
			if (deleteProfile($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getRTMPIPs()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getRTMPIPs()];
	}

	static public function getRTMPIP($rID)
	{
		if ($rIP = getRTMPIP($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rIP];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function addRTMPIP($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processRTMPIP($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getRTMPIP($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editRTMPIP($rID, $rData)
	{
		if (($rIP = self::getRTMPIP($rID)) && isset($rIP['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processRTMPIP($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getRTMPIP($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteRTMPIP($rID)
	{
		if (($rIP = self::getRTMPIP($rID)) && isset($rIP['data'])) {
			if (deleteRTMPIP($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getCategories()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getCategories()];
	}

	static public function getCategory($rID)
	{
		if ($rCategory = getCategory($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rCategory];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createCategory($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processCategory($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getCategory($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editCategory($rID, $rData)
	{
		if (($rCategory = self::getCategory($rID)) && isset($rCategory['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processCategory($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getCategory($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteCategory($rID)
	{
		if (($rCategory = self::getCategory($rID)) && isset($rCategory['data'])) {
			if (deleteCategory($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getWatchFolders()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getWatchFolders()];
	}

	static public function getWatchFolder($rID)
	{
		if ($rFolder = getWatchFolder($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rFolder];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createWatchFolder($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processWatchFolder($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getWatchFolder($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editWatchFolder($rID, $rData)
	{
		if (($rFolder = self::getWatchFolder($rID)) && isset($rFolder['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processWatchFolder($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getWatchFolder($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteWatchFolder($rID)
	{
		if (($rFolder = self::getWatchFolder($rID)) && isset($rFolder['data'])) {
			if (deleteWatchFolder($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function reloadWatchFolder($rServerID, $rID)
	{
		forceWatch($rServerID, $rID);
		return ['status' => 'STATUS_SUCCESS'];
	}

	static public function getBlockedISPs()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getISPs()];
	}

	static public function addBlockedISP($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processISP($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = $rReturn['data']['insert_id'];
		}

		return $rReturn;
	}

	static public function deleteBlockedISP($rID)
	{
		if (deleteBlockedISP($rID)) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getBlockedUAs()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getUserAgents()];
	}

	static public function addBlockedUA($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processUA($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = $rReturn['data']['insert_id'];
		}

		return $rReturn;
	}

	static public function deleteBlockedUA($rID)
	{
		if (deleteBlockedUA($rID)) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getBlockedIPs()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getBlockedIPs()];
	}

	static public function addBlockedIP($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::blockIP($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = $rReturn['data']['insert_id'];
		}

		return $rReturn;
	}

	static public function deleteBlockedIP($rID)
	{
		if (deleteBlockedIP($rID)) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function flushBlockedIPs()
	{
		flushIPs();
		return ['status' => 'STATUS_SUCCESS'];
	}

	static public function getStream($rID)
	{
		if (($rStream = getStream($rID)) && ($rStream['type'] == 1)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rStream];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createStream($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processStream($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getStream($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editStream($rID, $rData)
	{
		if (($rStream = self::getStream($rID)) && isset($rStream['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processStream($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getStream($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteStream($rID, $rServerID = -1)
	{
		if (($rStream = self::getStream($rID)) && isset($rStream['data'])) {
			if (deleteStream($rID, $rServerID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function startStream($rID, $rServerID = -1)
	{
		if ($rServerID == -1) {
			$rData = json_decode(APIRequest([
				'action'     => 'stream',
				'sub'        => 'start',
				'stream_ids' => [$rID],
				'servers'    => array_keys(XCMS::$rServers)
			]), true);
		}
		else {
			$rData = json_decode(SystemAPIRequest($rServerID, [
				'action'     => 'stream',
				'stream_ids' => [$rID],
				'function'   => 'start'
			]), true);
		}

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function stopStream($rID, $rServerID = -1)
	{
		if ($rServerID == -1) {
			$rData = json_decode(APIRequest([
				'action'     => 'stream',
				'sub'        => 'stop',
				'stream_ids' => [$rID],
				'servers'    => array_keys(XCMS::$rServers)
			]), true);
		}
		else {
			$rData = json_decode(SystemAPIRequest($rServerID, [
				'action'     => 'stream',
				'stream_ids' => [$rID],
				'function'   => 'stop'
			]), true);
		}

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getChannel($rID)
	{
		if (($rStream = getStream($rID)) && ($rStream['type'] == 3)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rStream];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createChannel($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processChannel($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getChannel($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editChannel($rID, $rData)
	{
		if (($rStream = self::getChannel($rID)) && isset($rStream['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processChannel($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getChannel($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteChannel($rID, $rServerID = -1)
	{
		if (($rStream = self::getChannel($rID)) && isset($rStream['data'])) {
			if (deleteStream($rID, $rServerID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getStation($rID)
	{
		if (($rStream = getStream($rID)) && ($rStream['type'] == 4)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rStream];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createStation($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processRadio($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getStation($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editStation($rID, $rData)
	{
		if (($rStream = self::getStation($rID)) && isset($rStream['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processRadio($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getStation($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteStation($rID, $rServerID = -1)
	{
		if (($rStream = self::getStation($rID)) && isset($rStream['data'])) {
			if (deleteStream($rID, $rServerID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getMovie($rID)
	{
		if (($rStream = getStream($rID)) && ($rStream['type'] == 2)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rStream];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createMovie($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processMovie($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getMovie($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editMovie($rID, $rData)
	{
		if (($rStream = self::getMovie($rID)) && isset($rStream['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processMovie($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getMovie($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteMovie($rID, $rServerID = -1)
	{
		if (($rStream = self::getMovie($rID)) && isset($rStream['data'])) {
			if (deleteStream($rID, $rServerID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function startMovie($rID, $rServerID = -1)
	{
		if ($rServerID == -1) {
			$rData = json_decode(APIRequest([
				'action'     => 'vod',
				'sub'        => 'start',
				'stream_ids' => [$rID],
				'servers'    => array_keys(XCMS::$rServers)
			]), true);
		}
		else {
			$rData = json_decode(SystemAPIRequest($rServerID, [
				'action'     => 'vod',
				'stream_ids' => [$rID],
				'function'   => 'start'
			]), true);
		}

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function stopMovie($rID, $rServerID = -1)
	{
		if ($rServerID == -1) {
			$rData = json_decode(APIRequest([
				'action'     => 'vod',
				'sub'        => 'stop',
				'stream_ids' => [$rID],
				'servers'    => array_keys(XCMS::$rServers)
			]), true);
		}
		else {
			$rData = json_decode(SystemAPIRequest($rServerID, [
				'action'     => 'vod',
				'stream_ids' => [$rID],
				'function'   => 'stop'
			]), true);
		}

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getEpisode($rID)
	{
		if (($rStream = getStream($rID)) && ($rStream['type'] == 5)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rStream];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createEpisode($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processEpisode($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getEpisode($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editEpisode($rID, $rData)
	{
		if (($rStream = self::getEpisode($rID)) && isset($rStream['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processEpisode($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getEpisode($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteEpisode($rID, $rServerID = -1)
	{
		if (($rStream = self::getEpisode($rID)) && isset($rStream['data'])) {
			if (deleteStream($rID, $rServerID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getSeries($rID)
	{
		if ($rSeries = getSerie($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rSeries];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createSeries($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(API::processSeries($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getSeries($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editSeries($rID, $rData)
	{
		if (($rStream = self::getSeries($rID)) && isset($rStream['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processSeries($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getSeries($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteSeries($rID)
	{
		if (($rStream = self::getSeries($rID)) && isset($rStream['data'])) {
			if (deleteSeries($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getServers()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getStreamingServers()];
	}

	static public function getServer($rID)
	{
		if ($rServer = getStreamingServersByID($rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rServer];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function installServer($rData)
	{
		if (empty($rData['type']) || empty($rData['ssh_port']) || empty($rData['root_username']) || empty($rData['root_password'])) {
			return ['status' => 'STATUS_INVALID_INPUT'];
		}
		if (($rData['type'] == 1) && (empty($rData['type']) || empty($rData['ssh_port']))) {
			return ['status' => 'STATUS_INVALID_INPUT'];
		}

		$rReturn = parseerror(API::installServer($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getServer($rReturn['data']['insert_id']);
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function editServer($rID, $rData)
	{
		if (($rServer = self::getServer($rID)) && isset($rServer['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processServer($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getServer($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function editProxy($rID, $rData)
	{
		if (($rServer = self::getServer($rID)) && isset($rServer['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(API::processProxy($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getServer($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteServer($rID)
	{
		if (($rServer = self::getServer($rID)) && isset($rServer['data'])) {
			if (deleteServer($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getSettings()
	{
		return ['status' => 'STATUS_SUCCESS', 'data' => getSettings()];
	}

	static public function editSettings($rData)
	{
		$rReturn = parseerror(API::editSettings($rData));
		$rReturn['data'] = self::getSettings()['data'];
		return $rReturn;
	}

	static public function getStats($rServerID)
	{
		global $db;
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'stats']), true);

		if ($rData) {
			$rData['requests_per_second'] = XCMS::$rServers[$rServerID]['requests_per_second'];
			$db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `server_id` = ? AND `hls_end` = 0;', $rServerID);

			if (0 < $db->num_rows()) {
				$rData['open_connections'] = $db->get_row()['count'];
			}

			$db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `hls_end` = 0;');

			if (0 < $db->num_rows()) {
				$rData['total_connections'] = $db->get_row()['count'];
			}

			$db->query('SELECT `activity_id` FROM `lines_live` WHERE `server_id` = ? AND `hls_end` = 0 GROUP BY `user_id`;', $rServerID);

			if (0 < $db->num_rows()) {
				$rData['online_users'] = $db->num_rows();
			}

			$db->query('SELECT `activity_id` FROM `lines_live` WHERE `hls_end` = 0 GROUP BY `user_id`;');

			if (0 < $db->num_rows()) {
				$rData['total_users'] = $db->num_rows();
			}

			$db->query('SELECT COUNT(*) AS `count` FROM `streams_servers` LEFT JOIN `streams` ON `streams`.`id` = `streams_servers`.`stream_id` WHERE `server_id` = ? AND `stream_status` <> 2 AND `type` = 1;', $rServerID);

			if (0 < $db->num_rows()) {
				$rData['total_streams'] = $db->get_row()['count'];
			}

			$db->query('SELECT COUNT(*) AS `count` FROM `streams_servers` LEFT JOIN `streams` ON `streams`.`id` = `streams_servers`.`stream_id` WHERE `server_id` = ? AND `pid` > 0 AND `type` = 1;', $rServerID);

			if (0 < $db->num_rows()) {
				$rData['total_running_streams'] = $db->get_row()['count'];
			}

			$db->query('SELECT COUNT(*) AS `count` FROM `streams_servers` LEFT JOIN `streams` ON `streams`.`id` = `streams_servers`.`stream_id` WHERE `server_id` = ? AND `type` = 1 AND (`streams`.`direct_source` = 0 AND (`streams_servers`.`monitor_pid` IS NOT NULL AND `streams_servers`.`monitor_pid` > 0) AND (`streams_servers`.`pid` IS NULL OR `streams_servers`.`pid` <= 0) AND `streams_servers`.`stream_status` <> 0);', $rServerID);

			if (0 < $db->num_rows()) {
				$rData['offline_streams'] = $db->get_row()['count'];
			}

			$rData['network_guaranteed_speed'] = XCMS::$rServers[$rServerID]['network_guaranteed_speed'];
			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getFPMStatus($rServerID)
	{
		$rData = SystemAPIRequest($rServerID, ['action' => 'fpm_status']);

		if ($rData) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getRTMPStats($rServerID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'rtmp_stats']), true);

		if ($rData) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getFreeSpace($rServerID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'get_free_space']), true);

		if ($rData) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getPIDs($rServerID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'get_pids']), true);

		if ($rData) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getCertificateInfo($rServerID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'get_certificate_info']), true);

		if ($rData) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function reloadNGINX($rServerID)
	{
		SystemAPIRequest($rServerID, ['action' => 'reload_nginx']);
		return ['status' => 'STATUS_SUCCESS'];
	}

	static public function clearTemp($rServerID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'free_temp']), true);

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function clearStreams($rServerID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'free_streams']), true);

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getDirectory($rServerID, $rDirectory)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'scandir', 'dir' => $rDirectory]), true);

		if ($rData) {
			unset($rData['result']);
			if (isset($rData['result']) && !$rData['result']) {
				return ['status' => 'STATUS_FAILURE'];
			}

			return ['status' => 'STATUS_SUCCESS', 'data' => $rData];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function killPID($rServerID, $rPID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'kill_pid', 'pid' => (int) $rPID]), true);

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function killConnection($rServerID, $rActivityID)
	{
		$rData = json_decode(SystemAPIRequest($rServerID, ['action' => 'closeConnection', 'activity_id' => (int) $rActivityID]), true);

		if ($rData['result']) {
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function adjustCredits($rID, $rCredits, $rReason = '')
	{
		global $db;
		global $rUserInfo;
		if (is_numeric($rCredits) && ($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			$rCredits = (int) $rUser['data']['credits'] + (int) $rCredits;

			if (0 <= $rCredits) {
				$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rCredits, $rID);
				$db->query('INSERT INTO `users_credits_logs`(`target_id`, `admin_id`, `amount`, `date`, `reason`) VALUES(?, ?, ?, ?, ?);', $rID, $rUserInfo['id'], $rCredits, time(), $rReason);
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function reloadCache()
	{
		shell_exec(PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php > /dev/null 2>/dev/null &');
		return ['status' => 'STATUS_SUCCESS'];
	}

	static public function runQuery($rQuery)
	{
		global $db;

		if ($db->query($rQuery)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $db->get_rows(), 'insert_id' => $db->last_insert_id()];
		}

		return ['status' => 'STATUS_FAILURE'];
	}
}

function parseError($rArray)
{
	global $_ERRORS;
	if (isset($rArray['status']) && is_numeric($rArray['status'])) {
		$rArray['status'] = $_ERRORS[$rArray['status']];
	}

	if (!$rArray) {
		$rArray['status'] = 'STATUS_NO_PERMISSIONS';
	}

	return $rArray;
}

if (!defined('XCMS_HOME')) {
	define('XCMS_HOME', '/home/xcms/');
}

require_once XCMS_HOME . 'includes/admin.php';
$_ERRORS = [];

foreach (get_defined_constants(true)['user'] as $rKey => $rValue) {
	if (substr($rKey, 0, 7) == 'STATUS_') {
		$_ERRORS[(int) $rValue] = $rKey;
	}
}

$rData = XCMS::$rRequest;
APIWrapper::$db = & $db;
APIWrapper::$rKey = $rData['api_key'];
if (!empty(XCMS::$rRequest['api_key']) && APIWrapper::createSession()) {
	$rAction = $rData['action'];
	$rStart = (int) $rData['start'] ?: 0;
	$rLimit = (int) $rData['limit'] ?: 50;
	unset($rData['api_key']);
	unset($rData['action']);
	unset($rData['start']);
	unset($rData['limit']);

	if (isset(XCMS::$rRequest['show_columns'])) {
		$rShowColumns = explode(',', XCMS::$rRequest['show_columns']);
	}
	else {
		$rShowColumns = NULL;
	}

	if (isset(XCMS::$rRequest['hide_columns'])) {
		$rHideColumns = explode(',', XCMS::$rRequest['hide_columns']);
	}
	else {
		$rHideColumns = NULL;
	}

	switch ($rAction) {
	case 'mysql_query':
		echo json_encode(APIWrapper::runQuery($rData['query']));
		break;
	case 'user_info':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getUserInfo(), $rShowColumns, $rHideColumns));
		break;
	case 'get_lines':
		echo json_encode(APIWrapper::TableAPI('lines', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_mags':
		echo json_encode(APIWrapper::TableAPI('mags', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_enigmas':
		echo json_encode(APIWrapper::TableAPI('enigmas', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_users':
		echo json_encode(APIWrapper::TableAPI('reg_users', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_streams':
		echo json_encode(APIWrapper::TableAPI('streams', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_provider_streams':
		echo json_encode(APIWrapper::TableAPI('provider_streams', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_channels':
		$rData['created'] = true;
		echo json_encode(APIWrapper::TableAPI('streams', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_stations':
		echo json_encode(APIWrapper::TableAPI('radios', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_movies':
		echo json_encode(APIWrapper::TableAPI('movies', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_series_list':
		echo json_encode(APIWrapper::TableAPI('series', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_episodes':
		echo json_encode(APIWrapper::TableAPI('episodes', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'activity_logs':
		echo json_encode(APIWrapper::TableAPI('line_activity', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'live_connections':
		echo json_encode(APIWrapper::TableAPI('live_connections', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'credit_logs':
		echo json_encode(APIWrapper::TableAPI('credits_log', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'client_logs':
		echo json_encode(APIWrapper::TableAPI('client_logs', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'user_logs':
		echo json_encode(APIWrapper::TableAPI('reg_user_logs', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'stream_errors':
		echo json_encode(APIWrapper::TableAPI('stream_errors', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'watch_output':
		echo json_encode(APIWrapper::TableAPI('watch_output', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'system_logs':
		echo json_encode(APIWrapper::TableAPI('mysql_syslog', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'login_logs':
		echo json_encode(APIWrapper::TableAPI('login_logs', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'restream_logs':
		echo json_encode(APIWrapper::TableAPI('restream_logs', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'mag_events':
		echo json_encode(APIWrapper::TableAPI('mag_events', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_line':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getLine($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_line':
		echo json_encode(APIWrapper::createLine($rData));
		break;
	case 'edit_line':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editLine($rID, $rData));
		break;
	case 'delete_line':
		echo json_encode(APIWrapper::deleteLine($rData['id']));
		break;
	case 'disable_line':
		echo json_encode(APIWrapper::disableLine($rData['id']));
		break;
	case 'enable_line':
		echo json_encode(APIWrapper::enableLine($rData['id']));
		break;
	case 'unban_line':
		echo json_encode(APIWrapper::unbanLine($rData['id']));
		break;
	case 'ban_line':
		echo json_encode(APIWrapper::banLine($rData['id']));
		break;
	case 'get_user':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getUser($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_user':
		echo json_encode(APIWrapper::createUser($rData));
		break;
	case 'edit_user':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editUser($rID, $rData));
		break;
	case 'delete_user':
		echo json_encode(APIWrapper::deleteUser($rData['id']));
		break;
	case 'disable_user':
		echo json_encode(APIWrapper::disableUser($rData['id']));
		break;
	case 'enable_user':
		echo json_encode(APIWrapper::enableUser($rData['id']));
		break;
	case 'get_mag':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getMAG($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_mag':
		echo json_encode(APIWrapper::createMAG($rData));
		break;
	case 'edit_mag':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editMAG($rID, $rData));
		break;
	case 'delete_mag':
		echo json_encode(APIWrapper::deleteMAG($rData['id']));
		break;
	case 'disable_mag':
		echo json_encode(APIWrapper::disableMAG($rData['id']));
		break;
	case 'enable_mag':
		echo json_encode(APIWrapper::enableMAG($rData['id']));
		break;
	case 'unban_mag':
		echo json_encode(APIWrapper::unbanMAG($rData['id']));
		break;
	case 'ban_mag':
		echo json_encode(APIWrapper::banMAG($rData['id']));
		break;
	case 'convert_mag':
		echo json_encode(APIWrapper::convertMAG($rData['id']));
		break;
	case 'get_enigma':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getEnigma($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_enigma':
		echo json_encode(APIWrapper::createEnigma($rData));
		break;
	case 'edit_enigma':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editEnigma($rID, $rData));
		break;
	case 'delete_enigma':
		echo json_encode(APIWrapper::deleteEnigma($rData['id']));
		break;
	case 'disable_enigma':
		echo json_encode(APIWrapper::disableEnigma($rData['id']));
		break;
	case 'enable_enigma':
		echo json_encode(APIWrapper::enableEnigma($rData['id']));
		break;
	case 'unban_enigma':
		echo json_encode(APIWrapper::unbanEnigma($rData['id']));
		break;
	case 'ban_enigma':
		echo json_encode(APIWrapper::banEnigma($rData['id']));
		break;
	case 'convert_enigma':
		echo json_encode(APIWrapper::convertEnigma($rData['id']));
		break;
	case 'get_bouquets':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getBouquets(), $rShowColumns, $rHideColumns));
		break;
	case 'get_bouquet':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getBouquet($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_bouquet':
		echo json_encode(APIWrapper::createBouquet($rData));
		break;
	case 'edit_bouquet':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editBouquet($rID, $rData));
		break;
	case 'delete_bouquet':
		echo json_encode(APIWrapper::deleteBouquet($rData['id']));
		break;
	case 'get_access_codes':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getAccessCodes(), $rShowColumns, $rHideColumns));
		break;
	case 'get_access_code':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getAccessCode($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_access_code':
		echo json_encode(APIWrapper::createAccessCode($rData));
		break;
	case 'edit_access_code':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editAccessCode($rID, $rData));
		break;
	case 'delete_access_code':
		echo json_encode(APIWrapper::deleteAccessCode($rData['id']));
		break;
	case 'get_hmacs':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getHMACs(), $rShowColumns, $rHideColumns));
		break;
	case 'get_hmac':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getHMAC($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_hmac':
		echo json_encode(APIWrapper::createHMAC($rData));
		break;
	case 'edit_hmac':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editHMAC($rID, $rData));
		break;
	case 'delete_hmac':
		echo json_encode(APIWrapper::deleteHMAC($rData['id']));
		break;
	case 'get_epgs':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getEPGs(), $rShowColumns, $rHideColumns));
		break;
	case 'get_epg':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getEPG($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_epg':
		echo json_encode(APIWrapper::createEPG($rData));
		break;
	case 'edit_epg':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editEPG($rID, $rData));
		break;
	case 'delete_epg':
		echo json_encode(APIWrapper::deleteEPG($rData['id']));
		break;
	case 'reload_epg':
		echo json_encode(APIWrapper::reloadEPG(isset($rData['id']) ? (int) $rData['id'] : NULL));
		break;
	case 'get_providers':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getProviders(), $rShowColumns, $rHideColumns));
		break;
	case 'get_provider':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getProvider($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_provider':
		echo json_encode(APIWrapper::createProvider($rData));
		break;
	case 'edit_provider':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editProvider($rID, $rData));
		break;
	case 'delete_provider':
		echo json_encode(APIWrapper::deleteProvider($rData['id']));
		break;
	case 'reload_provider':
		echo json_encode(APIWrapper::reloadProvider(isset($rData['id']) ? (int) $rData['id'] : NULL));
		break;
	case 'get_groups':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getGroups(), $rShowColumns, $rHideColumns));
		break;
	case 'get_group':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getGroup($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_group':
		echo json_encode(APIWrapper::createGroup($rData));
		break;
	case 'edit_group':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editGroup($rID, $rData));
		break;
	case 'delete_group':
		echo json_encode(APIWrapper::deleteGroup($rData['id']));
		break;
	case 'get_packages':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getPackages(), $rShowColumns, $rHideColumns));
		break;
	case 'get_package':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getPackage($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_package':
		echo json_encode(APIWrapper::createPackage($rData));
		break;
	case 'edit_package':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editPackage($rID, $rData));
		break;
	case 'delete_package':
		echo json_encode(APIWrapper::deletePackage($rData['id']));
		break;
	case 'get_transcode_profiles':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getTranscodeProfiles(), $rShowColumns, $rHideColumns));
		break;
	case 'get_transcode_profile':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getTranscodeProfile($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_transcode_profile':
		echo json_encode(APIWrapper::createTranscodeProfile($rData));
		break;
	case 'edit_transcode_profile':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editTranscodeProfile($rID, $rData));
		break;
	case 'delete_transcode_profile':
		echo json_encode(APIWrapper::deleteTranscodeProfile($rData['id']));
		break;
	case 'get_rtmp_ips':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getRTMPIPs(), $rShowColumns, $rHideColumns));
		break;
	case 'get_rtmp_ip':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getRTMPIP($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_rtmp_ip':
		echo json_encode(APIWrapper::addRTMPIP($rData));
		break;
	case 'edit_rtmp_ip':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editRTMPIP($rID, $rData));
		break;
	case 'delete_rtmp_ip':
		echo json_encode(APIWrapper::deleteRTMPIP($rData['id']));
		break;
	case 'get_categories':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getCategories(), $rShowColumns, $rHideColumns));
		break;
	case 'get_category':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getCategory($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_category':
		echo json_encode(APIWrapper::createCategory($rData));
		break;
	case 'edit_category':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editCategory($rID, $rData));
		break;
	case 'delete_category':
		echo json_encode(APIWrapper::deleteCategory($rData['id']));
		break;
	case 'get_watch_folders':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getWatchFolders(), $rShowColumns, $rHideColumns));
		break;
	case 'get_watch_folder':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getWatchFolder($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_watch_folder':
		echo json_encode(APIWrapper::createWatchFolder($rData));
		break;
	case 'edit_watch_folder':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editWatchFolder($rID, $rData));
		break;
	case 'delete_watch_folder':
		echo json_encode(APIWrapper::deleteWatchFolder($rData['id']));
		break;
	case 'reload_watch_folder':
		echo json_encode(APIWrapper::reloadWatchFolder($rData['server_id'] ?? SERVER_ID, $rData['id']));
		break;
	case 'get_blocked_isps':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getBlockedISPs(), $rShowColumns, $rHideColumns));
		break;
	case 'add_blocked_isp':
		echo json_encode(APIWrapper::addBlockedISP($rData['id']));
		break;
	case 'delete_blocked_isp':
		echo json_encode(APIWrapper::deleteBlockedISP($rData['id']));
		break;
	case 'get_blocked_uas':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getBlockedUAs(), $rShowColumns, $rHideColumns));
		break;
	case 'add_blocked_ua':
		echo json_encode(APIWrapper::addBlockedUA($rData));
		break;
	case 'delete_blocked_ua':
		echo json_encode(APIWrapper::deleteBlockedUA($rData['id']));
		break;
	case 'get_blocked_ips':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getBlockedIPs(), $rShowColumns, $rHideColumns));
		break;
	case 'add_blocked_ip':
		echo json_encode(APIWrapper::addBlockedIP($rData['id']));
		break;
	case 'delete_blocked_ip':
		echo json_encode(APIWrapper::deleteBlockedIP($rData['id']));
		break;
	case 'flush_blocked_ips':
		echo json_encode(APIWrapper::flushBlockedIPs());
		break;
	case 'get_stream':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getStream($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_stream':
		echo json_encode(APIWrapper::createStream($rData));
		break;
	case 'edit_stream':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editStream($rID, $rData));
		break;
	case 'delete_stream':
		echo json_encode(APIWrapper::deleteStream($rData['id'], $rData['server_id'] ?? -1));
		break;
	case 'start_station':
	case 'start_channel':
	case 'start_stream':
		echo json_encode(APIWrapper::startStream($rData['id'], $rData['server_id']));
		break;
	case 'stop_station':
	case 'stop_channel':
	case 'stop_stream':
		echo json_encode(APIWrapper::stopStream($rData['id'], $rData['server_id']));
		break;
	case 'get_channel':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getChannel($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_channel':
		echo json_encode(APIWrapper::createChannel($rData));
		break;
	case 'edit_channel':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editChannel($rID, $rData));
		break;
	case 'delete_channel':
		echo json_encode(APIWrapper::deleteChannel($rData['id'], $rData['server_id'] ?? -1));
		break;
	case 'get_station':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getStation($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_station':
		echo json_encode(APIWrapper::createStation($rData));
		break;
	case 'edit_station':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editStation($rID, $rData));
		break;
	case 'delete_station':
		echo json_encode(APIWrapper::deleteStation($rData['id'], $rData['server_id'] ?? -1));
		break;
	case 'get_movie':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getMovie($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_movie':
		echo json_encode(APIWrapper::createMovie($rData));
		break;
	case 'edit_movie':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editMovie($rID, $rData));
		break;
	case 'delete_movie':
		echo json_encode(APIWrapper::deleteMovie($rData['id'], $rData['server_id'] ?? -1));
		break;
	case 'start_episode':
	case 'start_movie':
		echo json_encode(APIWrapper::startMovie($rData['id'], $rData['server_id']));
		break;
	case 'stop_episode':
	case 'stop_movie':
		echo json_encode(APIWrapper::stopMovie($rData['id'], $rData['server_id']));
		break;
	case 'get_episode':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getEpisode($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_episode':
		echo json_encode(APIWrapper::createEpisode($rData));
		break;
	case 'edit_episode':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editEpisode($rID, $rData));
		break;
	case 'delete_episode':
		echo json_encode(APIWrapper::deleteEpisode($rData['id'], $rData['server_id'] ?? -1));
		break;
	case 'get_series':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getSeries($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_series':
		echo json_encode(APIWrapper::createSeries($rData));
		break;
	case 'edit_series':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editSeries($rID, $rData));
		break;
	case 'delete_series':
		echo json_encode(APIWrapper::deleteSeries($rData['id']));
		break;
	case 'get_servers':
		echo json_encode(APIWrapper::filterRows(APIWrapper::getServers(), $rShowColumns, $rHideColumns));
		break;
	case 'get_server':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getServer($rData['id']), $rShowColumns, $rHideColumns));
		break;
	case 'install_server':
		$rData['type'] = 0;
		echo json_encode(APIWrapper::installServer($rData));
		break;
	case 'install_proxy':
		$rData['type'] = 1;
		echo json_encode(APIWrapper::installServer($rData));
		break;
	case 'edit_server':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editServer($rID, $rData));
		break;
	case 'edit_proxy':
		$rID = $rData['id'];
		unset($rData['id']);
		echo json_encode(APIWrapper::editProxy($rID, $rData));
		break;
	case 'delete_server':
		echo json_encode(APIWrapper::deleteServer($rData['id']));
		break;
	case 'get_settings':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getSettings(), $rShowColumns, $rHideColumns));
		break;
	case 'edit_settings':
		echo json_encode(APIWrapper::editSettings($rData));
		break;
	case 'get_server_stats':
		echo json_encode(APIWrapper::getStats($rData['server_id'] ?? SERVER_ID));
		break;
	case 'get_fpm_status':
		echo json_encode(APIWrapper::getFPMStatus($rData['server_id'] ?? SERVER_ID));
		break;
	case 'get_rtmp_stats':
		echo json_encode(APIWrapper::getRTMPStats($rData['server_id'] ?? SERVER_ID));
		break;
	case 'get_free_space':
		echo json_encode(APIWrapper::getFreeSpace($rData['server_id'] ?? SERVER_ID));
		break;
	case 'get_pids':
		echo json_encode(APIWrapper::getPIDs($rData['server_id'] ?? SERVER_ID));
		break;
	case 'get_certificate_info':
		echo json_encode(APIWrapper::getCertificateInfo($rData['server_id'] ?? SERVER_ID));
		break;
	case 'reload_nginx':
		echo json_encode(APIWrapper::reloadNGINX($rData['server_id'] ?? SERVER_ID));
		break;
	case 'clear_temp':
		echo json_encode(APIWrapper::clearTemp($rData['server_id'] ?? SERVER_ID));
		break;
	case 'clear_streams':
		echo json_encode(APIWrapper::clearStreams($rData['server_id'] ?? SERVER_ID));
		break;
	case 'get_directory':
		echo json_encode(APIWrapper::getDirectory($rData['server_id'] ?? SERVER_ID, $rData['dir']));
		break;
	case 'kill_pid':
		echo json_encode(APIWrapper::killPID($rData['server_id'] ?? SERVER_ID, $rData['pid']));
		break;
	case 'kill_connection':
		echo json_encode(APIWrapper::killConnection($rData['server_id'] ?? SERVER_ID, $rData['activity_id']));
		break;
	case 'adjust_credits':
		echo json_encode(APIWrapper::adjustCredits($rData['id'], $rData['credits'], $rData['reason'] ?? ''));
		break;
	case 'reload_cache':
		echo json_encode(APIWrapper::reloadCache());
		break;
	default:
		echo json_encode(['status' => 'STATUS_FAILURE', 'error' => 'Invalid action.']);
		break;
	}
}
else {
	echo json_encode(['status' => 'STATUS_FAILURE', 'error' => 'Invalid API key.']);
}

?>