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
		self::$db->query('SELECT * FROM `users` LEFT JOIN `users_groups` ON `users_groups`.`group_id` = `users`.`member_group_id` WHERE `api_key` = ? AND LENGTH(`api_key`) > 0 AND `is_reseller` = 1 AND `status` = 1;', self::$rKey);

		if (0 < self::$db->num_rows()) {
			ResellerAPI::$db = & self::$db;
			ResellerAPI::init(self::$db->get_row()['id']);
			unset(unset(ResellerAPI::$rUserInfo)['password']);
			$rUserInfo = ResellerAPI::$rUserInfo;
			$rPermissions = ResellerAPI::$rPermissions;

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

	static public function getPackages()
	{
		global $rUserInfo;

		if ($rUserInfo) {
			$rPackages = [];
			$rOverride = json_decode($rUserInfo['override_packages'], true);

			foreach (getPackages($rUserInfo['member_group_id']) as $rPackage) {
				if (isset($rOverride[$rPackage['id']]['official_credits']) && (0 < strlen($rOverride[$rPackage['id']]['official_credits']))) {
					$rPackage['official_credits'] = (int) $rOverride[$rPackage['id']]['official_credits'];
				}
				else {
					$rPackage['official_credits'] = (int) $rPackage['official_credits'];
				}

				$rPackages[] = $rPackage;
			}

			return ['status' => 'STATUS_SUCCESS', 'data' => $rPackages];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getLine($rID)
	{
		if (($rLine = getUser($rID)) && hasPermissions('line', $rID)) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rLine];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createLine($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(ResellerAPI::processLine($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getLine($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editLine($rID, $rData)
	{
		if (getUser($rID)) {
			$rData['edit'] = $rID;

			if (isset($rData['isp_clear'])) {
				$rData['isp_clear'] = '';
			}

			$rReturn = parseerror(ResellerAPI::processLine($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getLine($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteLine($rID)
	{
		if (getUser($rID)) {
			if (deleteLine($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableLine($rID)
	{
		if (getUser($rID)) {
			self::$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableLine($rID)
	{
		if (getUser($rID)) {
			self::$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rID);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getMAG($rID)
	{
		if ($rDevice = getMag($rID)) {
			if (hasPermissions('line', $rDevice['user_id'])) {
				return ['status' => 'STATUS_SUCCESS', 'data' => $rDevice];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createMAG($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(ResellerAPI::processMAG($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getMAG($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editMAG($rID, $rData)
	{
		if (getMag($rID)) {
			$rData['edit'] = $rID;

			if (isset($rData['isp_clear'])) {
				$rData['isp_clear'] = '';
			}

			$rReturn = parseerror(ResellerAPI::processMAG($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getMAG($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteMAG($rID)
	{
		if (getMag($rID)) {
			if (deleteMAG($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableMAG($rID)
	{
		if ($rDevice = getMag($rID)) {
			self::$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableMAG($rID)
	{
		if ($rDevice = getMag($rID)) {
			self::$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function convertMAG($rID)
	{
		if ($rDevice = getMag($rID)) {
			deleteMAG($rID, false, false, true);
			return ['status' => 'STATUS_SUCCESS', 'data' => getUser($rDevice['user_id'])];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getEnigma($rID)
	{
		if ($rDevice = getEnigma($rID)) {
			if (hasPermissions('line', $rDevice['user_id'])) {
				return ['status' => 'STATUS_SUCCESS', 'data' => $rDevice];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createEnigma($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(ResellerAPI::processEnigma($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getEnigma($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editEnigma($rID, $rData)
	{
		if (getEnigma($rID)) {
			$rData['edit'] = $rID;

			if (isset($rData['isp_clear'])) {
				$rData['isp_clear'] = '';
			}

			$rReturn = parseerror(ResellerAPI::processEnigma($rData));

			if (isset($rReturn['data']['insert_id'])) {
				$rReturn['data'] = self::getEnigma($rReturn['data']['insert_id'])['data'];
			}

			return $rReturn;
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function deleteEnigma($rID)
	{
		if (getEnigma($rID)) {
			if (deleteEnigma($rID)) {
				return ['status' => 'STATUS_SUCCESS'];
			}
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function disableEnigma($rID)
	{
		if ($rDevice = getEnigma($rID)) {
			self::$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function enableEnigma($rID)
	{
		if ($rDevice = getEnigma($rID)) {
			self::$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rDevice['user_id']);
			return ['status' => 'STATUS_SUCCESS'];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function convertEnigma($rID)
	{
		if ($rDevice = getEnigma($rID)) {
			deleteEnigma($rID, false, false, true);
			return ['status' => 'STATUS_SUCCESS', 'data' => getUser($rDevice['user_id'])];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function getUser($rID)
	{
		if (($rUser = getRegisteredUser($rID)) && hasPermissions('user', $rUser['id'])) {
			return ['status' => 'STATUS_SUCCESS', 'data' => $rUser];
		}

		return ['status' => 'STATUS_FAILURE'];
	}

	static public function createUser($rData)
	{
		if (isset($rData['edit'])) {
			unset($rData['edit']);
		}

		$rReturn = parseerror(ResellerAPI::processUser($rData));

		if (isset($rReturn['data']['insert_id'])) {
			$rReturn['data'] = self::getUser($rReturn['data']['insert_id'])['data'];
		}

		return $rReturn;
	}

	static public function editUser($rID, $rData)
	{
		if (($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			$rData['edit'] = $rID;
			$rReturn = parseerror(ResellerAPI::processUser($rData));

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

	static public function adjustCredits($rID, $rCredits, $rNote)
	{
		global $rUserInfo;

		if (strlen($rNote) == 0) {
			$rNote = 'Reseller API Adjustment';
		}
		if (($rUser = self::getUser($rID)) && isset($rUser['data'])) {
			if (is_numeric($rCredits)) {
				$rOwnerCredits = (int) $rUserInfo['credits'] - (int) $rCredits;
				$rNewCredits = (int) $rUser['data']['credits'] + (int) $rCredits;
				if ((0 <= $rNewCredits) && (0 <= $rOwnerCredits)) {
					self::$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rOwnerCredits, $rUserInfo['id']);
					self::$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rNewCredits, $rUser['data']['id']);
					self::$db->query('INSERT INTO `users_credits_logs`(`target_id`, `admin_id`, `amount`, `date`, `reason`) VALUES(?, ?, ?, ?, ?);', $rUser['data']['id'], $rUserInfo['id'], $rCredits, time(), $rNote);
					self::$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'adjust_credits', $rID, (int) $rCredits, $rOwnerCredits, time(), json_encode($rUser['data']));
					return ['status' => 'STATUS_SUCCESS'];
				}
			}
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
	case 'packages':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getPackages(), $rShowColumns, $rHideColumns));
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
	case 'activity_logs':
		echo json_encode(APIWrapper::TableAPI('line_activity', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'live_connections':
		echo json_encode(APIWrapper::TableAPI('live_connections', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'user_logs':
		echo json_encode(APIWrapper::TableAPI('reg_user_logs', $rStart, $rLimit, $rData, $rShowColumns, $rHideColumns));
		break;
	case 'get_line':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getLine(XCMS::$rRequest['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_line':
		echo json_encode(APIWrapper::createLine(XCMS::$rRequest));
		break;
	case 'edit_line':
		$rData = XCMS::$rRequest;
		unset($rData['id']);
		echo json_encode(APIWrapper::editLine(XCMS::$rRequest['id'], $rData));
		break;
	case 'delete_line':
		echo json_encode(APIWrapper::deleteLine(XCMS::$rRequest['id']));
		break;
	case 'disable_line':
		echo json_encode(APIWrapper::disableLine(XCMS::$rRequest['id']));
		break;
	case 'enable_line':
		echo json_encode(APIWrapper::enableLine(XCMS::$rRequest['id']));
		break;
	case 'get_mag':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getMAG(XCMS::$rRequest['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_mag':
		echo json_encode(APIWrapper::createMAG(XCMS::$rRequest));
		break;
	case 'edit_mag':
		$rData = XCMS::$rRequest;
		unset($rData['id']);
		echo json_encode(APIWrapper::editMAG(XCMS::$rRequest['id'], $rData));
		break;
	case 'delete_mag':
		echo json_encode(APIWrapper::deleteMAG(XCMS::$rRequest['id']));
		break;
	case 'disable_mag':
		echo json_encode(APIWrapper::disableMAG(XCMS::$rRequest['id']));
		break;
	case 'enable_mag':
		echo json_encode(APIWrapper::enableMAG(XCMS::$rRequest['id']));
		break;
	case 'convert_mag':
		echo json_encode(APIWrapper::convertMAG(XCMS::$rRequest['id']));
		break;
	case 'get_enigma':
		echo json_encode(APIWrapper::filterRow(APIWrapper::getEnigma(XCMS::$rRequest['id']), $rShowColumns, $rHideColumns));
		break;
	case 'create_enigma':
		echo json_encode(APIWrapper::createEnigma(XCMS::$rRequest));
		break;
	case 'edit_enigma':
		$rData = XCMS::$rRequest;
		unset($rData['id']);
		echo json_encode(APIWrapper::editEnigma(XCMS::$rRequest['id'], $rData));
		break;
	case 'delete_enigma':
		echo json_encode(APIWrapper::deleteEnigma(XCMS::$rRequest['id']));
		break;
	case 'disable_enigma':
		echo json_encode(APIWrapper::disableEnigma(XCMS::$rRequest['id']));
		break;
	case 'enable_enigma':
		echo json_encode(APIWrapper::enableEnigma(XCMS::$rRequest['id']));
		break;
	case 'convert_enigma':
		echo json_encode(APIWrapper::convertEnigma(XCMS::$rRequest['id']));
		break;
	case 'get_user':
		if (!in_array('password', $rHideColumns)) {
			$rHideColumns[] = 'password';
		}

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
	case 'adjust_credits':
		echo json_encode(APIWrapper::adjustCredits($rData['id'], $rData['credits'], $rData['note'] ?: ''));
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