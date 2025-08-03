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

include 'functions.php';
session_write_close();

if (!isset($_SESSION['reseller'])) {
	echo json_encode(['result' => false, 'error' => 'Not logged in']);
	exit();
}

if (!PHP_ERRORS) {
	if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest')) {
		exit();
	}
}

if (XCMS::$rSettings['redis_handler']) {
	XCMS::connectRedis();
}

if (!$rUserInfo['id']) {
	echo json_encode(['result' => false]);
}
else if (!isset($rUserInfo['reports'])) {
	echo json_encode(['result' => false]);
}

if (isset(XCMS::$rRequest['action'])) {
	if (XCMS::$rRequest['action'] == 'dashboard') {
		$rReturn = ['open_connections' => 0, 'online_users' => 0, 'active_accounts' => 0, 'credits' => 0, 'credits_assigned' => 0];

		if (XCMS::$rSettings['redis_handler']) {
			$rReports = [];
			$db->query('SELECT `id` FROM `lines` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');

			foreach ($db->get_rows() as $rRow) {
				$rReports[] = $rRow['id'];
			}

			if (0 < count($rReports)) {
				foreach (XCMS::getUserConnections($rReports, true) as $rUserID => $rConnections) {
					$rReturn['open_connections'] += $rConnections;

					if (0 < $rConnections) {
						$rReturn['online_users']++;
					}
				}
			}
		}
		else {
			$db->query('SELECT COUNT(`activity_id`) AS `count` FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');
			$rReturn['open_connections'] = $db->get_row()['count'] ?: 0;
			$db->query('SELECT `activity_id` FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ') GROUP BY `lines_live`.`user_id`;');
			$rReturn['online_users'] = $db->num_rows();
		}

		$db->query('SELECT COUNT(`id`) AS `count` FROM `lines` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');
		$rReturn['active_accounts'] = $db->get_row()['count'] ?: 0;
		$db->query('SELECT SUM(`credits`) AS `credits` FROM `users` WHERE `id` IN (' . implode(',', $rUserInfo['reports']) . ');');
		$rReturn['credits'] = $db->get_row()['credits'] ?: 0;
		$rReturn['credits_assigned'] = ($rReturn['credits'] - (int) $rUserInfo['credits']) ?: 0;
		echo json_encode($rReturn);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'connections') {
		if (!$rPermissions['reseller_client_connection_logs']) {
			exit();
		}

		$rStreamID = XCMS::$rRequest['stream_id'];
		$rSub = XCMS::$rRequest['sub'];

		if ($rSub == 'purge') {
			if (XCMS::$rSettings['redis_handler']) {
				$rReports = [];
				$db->query('SELECT `id` FROM `lines` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');

				foreach ($db->get_rows() as $rRow) {
					$rReports[] = $rRow['id'];
				}

				$rConnections = XCMS::getRedisConnections(NULL, NULL, $rStreamID, true, false, false, false);

				foreach ($rConnections as $rConnection) {
					if (in_array($rConnection['user_id'], $rReports)) {
						XCMS::closeConnection($rConnection);
					}
				}
			}
			else {
				$db->query('SELECT `lines_live`.* FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `lines_live`.`stream_id` = ? AND `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ');', $rStreamID);

				foreach ($db->get_rows() as $rRow) {
					XCMS::closeConnection($rRow);
				}
			}

			echo json_encode(['result' => true]);
			exit();
		}
		else {
			echo json_encode(['result' => false]);
			exit();
		}
	}
	else if (XCMS::$rRequest['action'] == 'line') {
		if (!$rPermissions['create_line']) {
			exit();
		}

		$rSub = XCMS::$rRequest['sub'];
		$rUserID = (int) XCMS::$rRequest['user_id'];
		$rLine = getUser($rUserID);
		if (!hasPermissions('line', $rUserID) || !$rLine) {
			echo json_encode(['result' => false, 'error' => 'No permissions.']);
			exit();
		}

		if ($rSub == 'delete') {
			deleteLine($rUserID);
			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'delete', XCMS::$rRequest['user_id'], 0, $rUserInfo['credits'], time(), json_encode($rLine));
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'enable') {
			$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rUserID);
			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'enable', XCMS::$rRequest['user_id'], 0, $rUserInfo['credits'], time(), json_encode($rLine));
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'disable') {
			$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rUserID);
			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'disable', XCMS::$rRequest['user_id'], 0, $rUserInfo['credits'], time(), json_encode($rLine));
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'enable_adult') {
			$db->query('SELECT `bouquet` FROM `lines` WHERE `id` = ? LIMIT 1;', $rUserID);

			foreach ($db->get_rows() as $rRow) {
				$rIDs = [];

				foreach (json_decode($rRow['bouquet']) as $rArr) {
					$rIDs[] = $rArr;
				}

				$rIDs[] = $rPermissions['adult_bouquet'];
			}

			$db->query('UPDATE `lines` SET `bouquet` = \'[' . implode(',', array_map('intval', $rIDs)) . ']\' WHERE `id` = ?;', $rUserID);
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'disable_adult') {
			$db->query('SELECT `bouquet` FROM `lines` WHERE `id` = ? LIMIT 1;', $rUserID);
			$rIDs = [];

			foreach ($db->get_rows() as $rRow) {
				foreach (json_decode($rRow['bouquet']) as $rArr) {
					if ($rArr != $rPermissions['adult_bouquet']) {
						$rIDs[] = $rArr;
					}
				}
			}

			$db->query('UPDATE `lines` SET `bouquet` = \'' . json_encode($rIDs) . '\' WHERE `id` = ?;', $rUserID);
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'reset_isp') {
			$db->query('UPDATE `lines` SET `isp_desc` = \'\', `as_number` = NULL WHERE `id` = ?;', $rUserID);
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'kill_line') {
			if (!$rPermissions['reseller_client_connection_logs']) {
				exit();
			}

			if (XCMS::$rSettings['redis_handler']) {
				foreach (XCMS::getUserConnections([$rUserID], false)[$rUserID] as $rConnection) {
					XCMS::closeConnection($rConnection);
				}
			}
			else {
				$db->query('SELECT * FROM `lines_live` WHERE `user_id` = ?;', $rUserID);

				if (0 < $db->num_rows()) {
					foreach ($db->get_rows() as $rRow) {
						XCMS::closeConnection($rRow);
					}
				}
			}

			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'increase_conns') {
			$rCarry = false;
			$db->query('SELECT `exp_date`, `max_connections` FROM `lines` WHERE `id` = ? LIMIT 1;', $rUserID);

			foreach ($db->get_rows() as $rRow) {
				if ($rPermissions['connection_limit']) {
					if ($rPermissions['connection_limit'] <= $rRow['max_connections']) {
						echo json_encode(['result' => false]);
						exit();
					}
				}

				$rexp = $rRow['exp_date'];
			}

			$date_6_months = strtotime('+6 month');
			$date_14_months = strtotime('+14 month');

			if ($date_14_months < $rexp) {
				echo json_encode(['result' => false]);
				exit();
			}

			if ($date_6_months <= $rexp) {
				$creditCost = $rPermissions['cost_over_6_months'];
			}
			else {
				$creditCost = $rPermissions['cost_under_6_months'];
			}

			if ($creditCost == $rUserInfo['credits']) {
				$rCarry = false;
			}

			if ($rUserInfo['credits'] < $creditCost) {
				$rCarry = true;
			}

			if ($rCarry) {
				echo json_encode(['result' => false]);
				exit();
			}

			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'connection', XCMS::$rRequest['user_id'], $creditCost, $rUserInfo['credits'] - $creditCost, time(), json_encode($rLine));
			$db->query('UPDATE `lines` SET `max_connections` = `max_connections` + 1 WHERE `id` = ?;', $rUserID);
			$db->query('UPDATE `users` SET `credits` = `credits` - ' . $creditCost . ' WHERE `id` = ?;', $rUserInfo['id']);
			echo json_encode(['result' => true]);
			exit();
		}
	}
	else if (XCMS::$rRequest['action'] == 'line_activity') {
		if (!$rPermissions['reseller_client_connection_logs']) {
			exit();
		}

		$rSub = XCMS::$rRequest['sub'];

		if ($rSub == 'kill') {
			if (XCMS::$rSettings['redis_handler']) {
				if ($rActivityInfo = igbinary_unserialize(XCMS::$redis->get(XCMS::$rRequest['uuid']))) {
					if (!hasPermissions('line', $rActivityInfo['user_id'])) {
						echo json_encode(['result' => false, 'error' => 'No permissions.']);
						exit();
					}

					XCMS::closeConnection($rActivityInfo);
					echo json_encode(['result' => true]);
					exit();
				}
			}
			else {
				$db->query('SELECT * FROM `lines_live` WHERE `uuid` = ? LIMIT 1;', XCMS::$rRequest['uuid']);

				if ($db->num_rows() == 1) {
					$rRow = $db->get_row();

					if (!hasPermissions('line', $rRow['user_id'])) {
						echo json_encode(['result' => false, 'error' => 'No permissions.']);
						exit();
					}

					XCMS::closeConnection($rRow);
					echo json_encode(['result' => true]);
					exit();
				}
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'adjust_credits') {
		if (!$rPermissions['create_sub_resellers']) {
			exit();
		}

		if (!hasPermissions('user', XCMS::$rRequest['id'])) {
			echo json_encode(['result' => false, 'error' => 'No permissions.']);
			exit();
		}

		$rUser = getRegisteredUser(XCMS::$rRequest['id']);
		if ($rUser && is_numeric(XCMS::$rRequest['credits'])) {
			if (strpos(XCMS::$rRequest['credits'], '-') !== false) {
				echo json_encode(['result' => false, 'error' => 'No permissions.']);
				exit();
			}

			$rOwnerCredits = (int) $rUserInfo['credits'] - (int) XCMS::$rRequest['credits'];
			$rCredits = (int) $rUser['credits'] + (int) XCMS::$rRequest['credits'];
			if ((0 <= $rCredits) && (0 <= $rOwnerCredits)) {
				$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rOwnerCredits, $rUserInfo['id']);
				$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rCredits, $rUser['id']);
				$db->query('INSERT INTO `users_credits_logs`(`target_id`, `admin_id`, `amount`, `date`, `reason`) VALUES(?, ?, ?, ?, ?);', $rUser['id'], $rUserInfo['id'], XCMS::$rRequest['credits'], time(), XCMS::$rRequest['reason']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'adjust_credits', XCMS::$rRequest['id'], (int) XCMS::$rRequest['credits'], $rOwnerCredits, time(), json_encode($rUser));
				echo json_encode(['result' => true]);
				exit();
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'reg_user') {
		if (!$rPermissions['create_sub_resellers']) {
			exit();
		}

		if (!hasPermissions('user', XCMS::$rRequest['user_id'])) {
			echo json_encode(['result' => false, 'error' => 'No permissions.']);
			exit();
		}

		$rSub = XCMS::$rRequest['sub'];
		$rUser = getRegisteredUser(XCMS::$rRequest['user_id']);

		if ($rSub == 'delete') {
			if (!$rPermissions['delete_users']) {
				exit();
			}

			$rOwnerCredits = (int) $rUserInfo['credits'] + (int) $rUser['credits'];
			$db->query('UPDATE `users` SET `credits` = ? WHERE `id` = ?;', $rOwnerCredits, $rUserInfo['id']);
			deleteUser(XCMS::$rRequest['user_id'], false, false, $rUserInfo['id']);
			$db->query('INSERT INTO `users_credits_logs`(`target_id`, `admin_id`, `amount`, `date`, `reason`) VALUES(?, ?, ?, ?, ?);', $rUserInfo['id'], $rUserInfo['id'], (int) $rUser['credits'], time(), 'Deleted user: ' . $rUser['username']);
			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'delete', XCMS::$rRequest['user_id'], (int) $rUser['credits'], $rOwnerCredits, time(), json_encode($rUser));
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'enable') {
			$db->query('UPDATE `users` SET `status` = 1 WHERE `id` = ?;', XCMS::$rRequest['user_id']);
			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'enable', XCMS::$rRequest['user_id'], 0, $rUserInfo['credits'], time(), json_encode($rUser));
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'disable') {
			$db->query('UPDATE `users` SET `status` = 0 WHERE `id` = ?;', XCMS::$rRequest['user_id']);
			$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'user\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'disable', XCMS::$rRequest['user_id'], 0, $rUserInfo['credits'], time(), json_encode($rUser));
			echo json_encode(['result' => true]);
			exit();
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'ticket') {
		$rTicket = getTicket(XCMS::$rRequest['ticket_id']);

		if ($rTicket) {
			if (!hasPermissions('user', $rTicket['member_id'])) {
				echo json_encode(['result' => false, 'error' => 'No permissions.']);
				exit();
			}

			$rSub = XCMS::$rRequest['sub'];

			if ($rSub == 'close') {
				$db->query('UPDATE `tickets` SET `status` = 0 WHERE `id` = ?;', XCMS::$rRequest['ticket_id']);
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'reopen') {
				if ($rTicket['member_id'] == $rUserInfo['id']) {
					exit();
				}

				$db->query('UPDATE `tickets` SET `status` = 1 WHERE `id` = ?;', XCMS::$rRequest['ticket_id']);
				echo json_encode(['result' => true]);
				exit();
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'mag') {
		if (!$rPermissions['create_mag']) {
			exit();
		}

		$rSub = XCMS::$rRequest['sub'];
		$rMagDetails = getMag((int) XCMS::$rRequest['mag_id']);

		if ($rSub == 'enable_adult') {
			$rUserIDMAG = (int) XCMS::$rRequest['mag_id'];
			$db->query('SELECT `bouquet` FROM `lines` WHERE `id` = ? LIMIT 1;', $rUserIDMAG);
			$rIDs = [];

			foreach ($db->get_rows() as $rRow) {
				foreach (json_decode($rRow['bouquet']) as $rArr) {
					$rIDs[] = $rArr;
				}

				$rIDs[] = $rPermissions['adult_bouquet'];
			}

			$db->query('UPDATE `lines` SET `bouquet` = \'[' . implode(',', array_map('intval', $rIDs)) . ']\' WHERE `id` = ?;', $rUserIDMAG);
			echo json_encode(['result' => true]);
			exit();
		}
		else if ($rSub == 'disable_adult') {
			$rUserIDMAG = (int) XCMS::$rRequest['mag_id'];
			$db->query('SELECT `bouquet` FROM `lines` WHERE `id` = ? LIMIT 1;', $rUserIDMAG);
			$rIDs = [];

			foreach ($db->get_rows() as $rRow) {
				foreach (json_decode($rRow['bouquet']) as $rArr) {
					if ($rArr != $rPermissions['adult_bouquet']) {
						$rIDs[] = $rArr;
					}
				}
			}

			$db->query('UPDATE `lines` SET `bouquet` = \'' . json_encode($rIDs) . '\' WHERE `id` = ?;', $rUserIDMAG);
			echo json_encode(['result' => true]);
			exit();
		}

		if ($rMagDetails) {
			if (!hasPermissions('line', $rMagDetails['user_id'])) {
				echo json_encode(['result' => false, 'error' => 'No permissions.']);
				exit();
			}

			if ($rSub == 'delete') {
				deleteMAG(XCMS::$rRequest['mag_id']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'mag\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'delete', XCMS::$rRequest['mag_id'], 0, $rUserInfo['credits'], time(), json_encode($rMagDetails));
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'enable') {
				$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rMagDetails['user_id']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'mag\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'enable', XCMS::$rRequest['mag_id'], 0, $rUserInfo['credits'], time(), json_encode($rMagDetails));
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'disable') {
				$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rMagDetails['user_id']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'mag\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'disable', XCMS::$rRequest['mag_id'], 0, $rUserInfo['credits'], time(), json_encode($rMagDetails));
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'convert') {
				deleteMAG(XCMS::$rRequest['mag_id'], false, false, true);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'convert', $rMagDetails['user']['id'], 0, $rUserInfo['credits'], time(), json_encode($rMagDetails['user']));
				echo json_encode(['result' => true, 'line_id' => $rMagDetails['user']['id']]);
				exit();
			}
			else if ($rSub == 'reset_isp') {
				$db->query('UPDATE `lines` SET `isp_desc` = \'\', `as_number` = NULL WHERE `id` = ?;', $rMagDetails['user']['id']);
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'kill_line') {
				if (!$rPermissions['reseller_client_connection_logs']) {
					exit();
				}

				if (XCMS::$rSettings['redis_handler']) {
					foreach (XCMS::getUserConnections([$rMagDetails['user_id']], false)[$rMagDetails['user_id']] as $rConnection) {
						XCMS::closeConnection($rConnection);
					}
				}
				else {
					$db->query('SELECT * FROM `lines_live` WHERE `user_id` = ?;', $rMagDetails['user_id']);

					if (0 < $db->num_rows()) {
						foreach ($db->get_rows() as $rRow) {
							XCMS::closeConnection($rRow);
						}
					}
				}

				echo json_encode(['result' => true]);
				exit();
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'enigma') {
		if (!$rPermissions['create_enigma']) {
			exit();
		}

		$rSub = XCMS::$rRequest['sub'];
		$rE2Details = getEnigma((int) XCMS::$rRequest['e2_id']);

		if ($rE2Details) {
			if (!hasPermissions('line', $rE2Details['user_id'])) {
				echo json_encode(['result' => false, 'error' => 'No permissions.']);
				exit();
			}

			if ($rSub == 'delete') {
				deleteEnigma(XCMS::$rRequest['e2_id']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'enigma\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'delete', XCMS::$rRequest['e2_id'], 0, $rUserInfo['credits'], time(), json_encode($rE2Details));
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'enable') {
				$db->query('UPDATE `lines` SET `enabled` = 1 WHERE `id` = ?;', $rE2Details['user_id']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'enigma\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'enable', XCMS::$rRequest['e2_id'], 0, $rUserInfo['credits'], time(), json_encode($rE2Details));
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'disable') {
				$db->query('UPDATE `lines` SET `enabled` = 0 WHERE `id` = ?;', $rE2Details['user_id']);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'enigma\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'disable', XCMS::$rRequest['e2_id'], 0, $rUserInfo['credits'], time(), json_encode($rE2Details));
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'convert') {
				deleteEnigma(XCMS::$rRequest['e2_id'], false, false, true);
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'line\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'convert', $rE2Details['user']['id'], 0, $rUserInfo['credits'], time(), json_encode($rE2Details['user']));
				echo json_encode(['result' => true, 'line_id' => $rE2Details['user']['id']]);
				exit();
			}
			else if ($rSub == 'reset_isp') {
				$db->query('UPDATE `lines` SET `isp_desc` = \'\', `as_number` = NULL WHERE `id` = ?;', $rE2Details['user']['id']);
				echo json_encode(['result' => true]);
				exit();
			}
			else if ($rSub == 'kill_line') {
				if (!$rPermissions['reseller_client_connection_logs']) {
					exit();
				}

				if (XCMS::$rSettings['redis_handler']) {
					foreach (XCMS::getUserConnections([$rMagDetails['user_id']], false)[$rE2Details['user_id']] as $rConnection) {
						XCMS::closeConnection($rConnection);
					}
				}
				else {
					$db->query('SELECT * FROM `lines_live` WHERE `user_id` = ?;', $rE2Details['user_id']);

					if (0 < $db->num_rows()) {
						foreach ($db->get_rows() as $rRow) {
							XCMS::closeConnection($rRow);
						}
					}
				}

				echo json_encode(['result' => true]);
				exit();
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'get_package') {
		$rReturn = [];
		$rOverride = json_decode($rUserInfo['override_packages'], true);
		$db->query('SELECT `id`, `bouquets`, `official_credits` AS `cost_credits`, `official_duration`, `official_duration_in`, `max_connections`, `check_compatible`, `is_isplock` FROM `users_packages` WHERE `id` = ?;', XCMS::$rRequest['package_id']);

		if ($db->num_rows() == 1) {
			$rData = $db->get_row();
			if (isset($rOverride[$rData['id']]['official_credits']) && (0 < strlen($rOverride[$rData['id']]['official_credits']))) {
				$rData['cost_credits'] = $rOverride[$rData['id']]['official_credits'];
			}
			if (isset(XCMS::$rRequest['orig_id']) && $rData['check_compatible']) {
				$rData['compatible'] = checkCompatible(XCMS::$rRequest['package_id'], XCMS::$rRequest['orig_id']);
			}
			else {
				$rData['compatible'] = true;
			}

			$rData['exp_date'] = date('Y-m-d H:i', strtotime('+' . (int) $rData['official_duration'] . ' ' . $rData['official_duration_in']));
			if (isset(XCMS::$rRequest['user_id']) && $rData['compatible']) {
				if ($rUser = getUser(XCMS::$rRequest['user_id'])) {
					if (time() < $rUser['exp_date']) {
						$rData['exp_date'] = date('Y-m-d H:i', strtotime('+' . (int) $rData['official_duration'] . ' ' . $rData['official_duration_in'], $rUser['exp_date']));
					}
					else {
						$rData['exp_date'] = date('Y-m-d H:i', strtotime('+' . (int) $rData['official_duration'] . ' ' . $rData['official_duration_in']));
					}
				}
			}

			foreach (json_decode($rData['bouquets'], true) as $rBouquet) {
				$db->query('SELECT * FROM `bouquets` WHERE `id` = ?;', $rBouquet);

				if ($db->num_rows() == 1) {
					$rRow = $db->get_row();
					$rReturn[] = ['id' => $rRow['id'], 'bouquet_name' => str_replace('\'', '\\\'', $rRow['bouquet_name']), 'bouquet_channels' => json_decode($rRow['bouquet_channels'], true), 'bouquet_radios' => json_decode($rRow['bouquet_radios'], true), 'bouquet_movies' => json_decode($rRow['bouquet_movies'], true), 'bouquet_series' => json_decode($rRow['bouquet_series'], true)];
				}
			}

			$rData['duration'] = $rData['official_duration'] . ' ' . $rData['official_duration_in'];
			echo json_encode(['result' => true, 'bouquets' => $rReturn, 'data' => $rData]);
		}
		else {
			echo json_encode(['result' => false]);
		}

		exit();
	}
	else if (XCMS::$rRequest['action'] == 'get_package_trial') {
		$rReturn = [];
		$db->query('SELECT `bouquets`, `trial_credits` AS `cost_credits`, `trial_duration`, `trial_duration_in`, `max_connections`, `is_isplock` FROM `users_packages` WHERE `id` = ?;', XCMS::$rRequest['package_id']);

		if ($db->num_rows() == 1) {
			$rData = $db->get_row();
			$rData['exp_date'] = date('Y-m-d H:i', strtotime('+' . (int) $rData['trial_duration'] . ' ' . $rData['trial_duration_in']));

			foreach (json_decode($rData['bouquets'], true) as $rBouquet) {
				$db->query('SELECT * FROM `bouquets` WHERE `id` = ?;', $rBouquet);

				if ($db->num_rows() == 1) {
					$rRow = $db->get_row();
					$rReturn[] = ['id' => $rRow['id'], 'bouquet_name' => str_replace('\'', '\\\'', $rRow['bouquet_name']), 'bouquet_channels' => json_decode($rRow['bouquet_channels'], true), 'bouquet_radios' => json_decode($rRow['bouquet_radios'], true), 'bouquet_movies' => json_decode($rRow['bouquet_movies'], true), 'bouquet_series' => json_decode($rRow['bouquet_series'], true)];
				}
			}

			$rData['duration'] = $rData['trial_duration'] . ' ' . $rData['trial_duration_in'];
			$rData['compatible'] = true;
			echo json_encode(['result' => true, 'bouquets' => $rReturn, 'data' => $rData]);
		}
		else {
			echo json_encode(['result' => false]);
		}

		exit();
	}
	else if (XCMS::$rRequest['action'] == 'header_stats') {
		$rReturn = ['total_connections' => 0, 'total_users' => 0];

		if (XCMS::$rSettings['redis_handler']) {
			$rReports = [];
			$db->query('SELECT `id` FROM `lines` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');

			foreach ($db->get_rows() as $rRow) {
				$rReports[] = $rRow['id'];
			}

			if (0 < count($rReports)) {
				foreach (XCMS::getUserConnections($rReports, true) as $rUserID => $rConnections) {
					$rReturn['total_connections'] += $rConnections;

					if (0 < $rConnections) {
						$rReturn['total_users']++;
					}
				}
			}
		}
		else {
			$db->query('SELECT COUNT(`activity_id`) AS `count` FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');
			$rReturn['total_connections'] = $db->get_row()['count'] ?: 0;
			$db->query('SELECT `activity_id` FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ') GROUP BY `lines_live`.`user_id`;');
			$rReturn['total_users'] = $db->num_rows();
		}

		echo json_encode($rReturn, JSON_PARTIAL_OUTPUT_ON_ERROR);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'stats') {
		$rReturn = ['open_connections' => 0, 'online_users' => 0, 'total_lines' => 0, 'total_users' => 0, 'owner_credits' => 0, 'user_credits' => 0, 'total_credits' => 0];
		$rUptime = 0;

		if (XCMS::$rSettings['redis_handler']) {
			$rReports = [];
			$db->query('SELECT `id` FROM `lines` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');

			foreach ($db->get_rows() as $rRow) {
				$rReports[] = $rRow['id'];
			}

			if (0 < count($rReports)) {
				foreach (XCMS::getUserConnections($rReports, true) as $rUserID => $rConnections) {
					$rReturn['open_connections'] += $rConnections;

					if (0 < $rConnections) {
						$rReturn['online_users']++;
					}
				}
			}
		}
		else {
			$db->query('SELECT COUNT(`activity_id`) AS `count` FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');
			$rReturn['open_connections'] = $db->get_row()['count'] ?: 0;
			$db->query('SELECT `activity_id` FROM `lines_live` LEFT JOIN `lines` ON `lines`.`id` = `lines_live`.`user_id` WHERE `hls_end` = 0 AND `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ') GROUP BY `lines_live`.`user_id`;');
			$rReturn['online_users'] = $db->num_rows();
		}

		$db->query('SELECT COUNT(*) AS `count` FROM `lines` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ');');
		$rReturn['total_lines'] = $db->get_row()['count'];
		$db->query('SELECT COUNT(*) AS `count`, SUM(`credits`) AS `credits` FROM `users` WHERE `owner_id` IN (' . implode(',', $rUserInfo['reports']) . ');');
		$rRow = $db->get_row();
		$rReturn['total_users'] = $rRow['count'];
		$rReturn['user_credits'] = $rRow['credits'];
		$rReturn['owner_credits'] = $rUserInfo['credits'];
		$rReturn['total_credits'] = $rReturn['owner_credits'] + $rReturn['user_credits'];
		echo json_encode($rReturn);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'userlist') {
		$rReturn = [
			'total_count' => 0,
			'items'       => [],
			'result'      => true
		];

		if (isset(XCMS::$rRequest['search'])) {
			if (isset(XCMS::$rRequest['page'])) {
				$rPage = (int) XCMS::$rRequest['page'];
			}
			else {
				$rPage = 1;
			}

			$db->query('SELECT COUNT(`id`) AS `id` FROM `lines` LEFT JOIN `mag_devices` ON `mag_devices`.`user_id` = `lines`.`id` LEFT JOIN `enigma2_devices` ON `enigma2_devices`.`user_id` = `lines`.`id` WHERE `lines`.`member_id` IN (' . implode(',', $rUserInfo['reports']) . ') AND (`lines`.`username` LIKE ? OR `mag_devices`.`mac` LIKE ? OR `enigma2_devices`.`mac` LIKE ?);', '%' . XCMS::$rRequest['search'] . '%', '%' . XCMS::$rRequest['search'] . '%', '%' . XCMS::$rRequest['search'] . '%');
			$rReturn['total_count'] = $db->get_row()['id'];
			$db->query('SELECT `id`, IF(`lines`.`is_mag`, `mag_devices`.`mac`, IF(`lines`.`is_e2`, `enigma2_devices`.`mac`, `lines`.`username`)) AS `username` FROM `lines` LEFT JOIN `mag_devices` ON `mag_devices`.`user_id` = `lines`.`id` LEFT JOIN `enigma2_devices` ON `enigma2_devices`.`user_id` = `lines`.`id` WHERE `member_id` IN (' . implode(',', $rUserInfo['reports']) . ') AND (`lines`.`username` LIKE ? OR `mag_devices`.`mac` LIKE ? OR `enigma2_devices`.`mac` LIKE ?) ORDER BY `username` ASC LIMIT ' . (($rPage - 1) * 100) . ', 100;', '%' . XCMS::$rRequest['search'] . '%', '%' . XCMS::$rRequest['search'] . '%', '%' . XCMS::$rRequest['search'] . '%');

			if (0 < $db->num_rows()) {
				foreach ($db->get_rows() as $rRow) {
					$rReturn['items'][] = ['id' => $rRow['id'], 'text' => $rRow['username']];
				}
			}
		}

		echo json_encode($rReturn);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'send_event') {
		if (!$rPermissions['create_mag']) {
			exit();
		}

		$rData = json_decode(XCMS::$rRequest['data'], true);
		$rMag = getMag($rData['id']);

		if ($rMag) {
			if (!hasPermissions('line', $rMag['user_id'])) {
				echo json_encode(['result' => false, 'error' => 'No permissions.']);
				exit();
			}

			if ($rData['type'] == 'send_msg') {
				$rData['need_confirm'] = 1;
			}
			else if ($rData['type'] == 'play_channel') {
				$rData['need_confirm'] = 0;
				$rData['reboot_portal'] = 0;
				$rData['message'] = (int) $rData['channel'];
			}
			else if ($rData['type'] == 'reset_stb_lock') {
				resetSTB($rData['id']);
				echo json_encode(['result' => true]);
				exit();
			}
			else {
				$rData['need_confirm'] = 0;
				$rData['reboot_portal'] = 0;
				$rData['message'] = '';
			}

			if ($db->query('INSERT INTO `mag_events`(`status`, `mag_device_id`, `event`, `need_confirm`, `msg`, `reboot_after_ok`, `send_time`) VALUES (0, ?, ?, ?, ?, ?, ?);', $rData['id'], $rData['type'], $rData['need_confirm'], $rData['message'], $rData['reboot_portal'], time())) {
				$db->query('INSERT INTO `users_logs`(`owner`, `type`, `action`, `log_id`, `package_id`, `cost`, `credits_after`, `date`, `deleted_info`) VALUES(?, \'mag\', ?, ?, null, ?, ?, ?, ?);', $rUserInfo['id'], 'send_event', $rMag['mag_id'], 0, $rUserInfo['credits'], time(), json_encode($rMag));
				echo json_encode(['result' => true]);
				exit();
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'streamlist') {
		if (!$rPermissions['create_mag'] && !$rPermissions['can_view_vod'] && !$rPermissions['reseller_client_connection_logs']) {
			exit();
		}

		$rReturn = [
			'total_count' => 0,
			'items'       => [],
			'result'      => true
		];

		if (isset(XCMS::$rRequest['search'])) {
			if (isset(XCMS::$rRequest['page'])) {
				$rPage = (int) XCMS::$rRequest['page'];
			}
			else {
				$rPage = 1;
			}

			$db->query('SELECT COUNT(`id`) AS `id` FROM `streams` WHERE `stream_display_name` LIKE ? AND `id` IN (' . implode(',', array_map('intval', $rPermissions['stream_ids'])) . ');', '%' . XCMS::$rRequest['search'] . '%');
			$rReturn['total_count'] = $db->get_row()['id'];
			$db->query('SELECT `id`, `stream_display_name` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rPermissions['stream_ids'])) . ') AND `stream_display_name` LIKE ? ORDER BY `stream_display_name` ASC LIMIT ' . (($rPage - 1) * 100) . ', 100;', '%' . XCMS::$rRequest['search'] . '%');

			if (0 < $db->num_rows()) {
				foreach ($db->get_rows() as $rRow) {
					$rReturn['items'][] = ['id' => $rRow['id'], 'text' => $rRow['stream_display_name']];
				}
			}
		}

		echo json_encode($rReturn);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'ip_whois') {
		$rIP = XCMS::$rRequest['ip'];
		$rReader = new MaxMind\Db\Reader(GEOLITE2C_BIN);
		$rResponse = $rReader->get($rIP);

		if (isset($rResponse['location']['time_zone'])) {
			$rDate = new DateTime('now', new DateTimeZone($rResponse['location']['time_zone']));
			$rResponse['location']['time'] = $rDate->format('Y-m-d H:i:s');
		}

		$rReader->close();

		if (isset(XCMS::$rRequest['isp'])) {
			$rReader = new MaxMind\Db\Reader(GEOISP_BIN);
			$rResponse['isp'] = $rReader->get($rIP);
			$rReader->close();
		}

		$rResponse['type'] = NULL;

		if ($rResponse['isp']['autonomous_system_number']) {
			$db->query('SELECT `type` FROM `blocked_asns` WHERE `asn` = ?;', $rResponse['isp']['autonomous_system_number']);

			if (0 < $db->num_rows()) {
				$rResponse['type'] = $db->get_row()['type'];
			}
		}

		echo json_encode(['result' => true, 'data' => $rResponse]);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'get_epg') {
		if (!$rPermissions['can_view_vod']) {
			exit();
		}

		if (count($rPermissions['stream_ids']) == 0) {
			exit();
		}

		$rTimezone = XCMS::$rRequest['timezone'] ?: 'Europe/London';
		date_default_timezone_set($rTimezone);
		$rReturn = [
			'Channels' => []
		];
		$rChannels = array_map('intval', explode(',', XCMS::$rRequest['channels']));

		if (count($rChannels) == 0) {
			echo json_encode($rReturn);
			exit();
		}

		$rHours = (int) XCMS::$rRequest['hours'] ?: 3;
		$rStartDate = (int) strtotime(XCMS::$rRequest['startdate']) ?: time();
		$rFinishDate = $rStartDate + ($rHours * 3600);
		$rPerUnit = (float) (100 / ($rHours * 60));
		$rChannelsSort = $rChannels;
		sort($rChannelsSort);
		$rListings = [];

		if (0 < count($rChannels)) {
			$rArchiveInfo = [];
			$db->query('SELECT `id`, `tv_archive_server_id`, `tv_archive_duration` FROM `streams` WHERE `id` IN (' . implode(',', $rChannels) . ');');

			if (0 < $db->num_rows()) {
				foreach ($db->get_rows() as $rRow) {
					$rArchiveInfo[$rRow['id']] = $rRow;
				}
			}

			$rEPG = XCMS::getEPGs($rChannels, $rStartDate, $rFinishDate);

			foreach ($rEPG as $rChannelID => $rEPGData) {
				$rFullSize = 0;

				foreach ($rEPGData as $rEPGItem) {
					$rCapStart = ($rEPGItem['start'] < $rStartDate ? $rStartDate : $rEPGItem['start']);
					$rCapEnd = ($rFinishDate < $rEPGItem['end'] ? $rFinishDate : $rEPGItem['end']);
					$rDuration = ($rCapEnd - $rCapStart) / 60;
					$rArchive = NULL;

					if (isset($rArchiveInfo[$rChannelID])) {
						if ((0 < $rArchiveInfo[$rChannelID]['tv_archive_server_id']) && (0 < $rArchiveInfo[$rChannelID]['tv_archive_duration'])) {
							if ((time() - ($rArchiveInfo[$rChannelID]['tv_archive_duration'] * 86400)) <= $rEPGItem['start']) {
								$rArchive = [$rEPGItem['start'], (int) (($rEPGItem['end'] - $rEPGItem['start']) / 60)];
							}
						}
					}

					$rRelativeSize = round($rDuration * $rPerUnit, 2);
					$rFullSize += $rRelativeSize;

					if (100 < $rFullSize) {
						$rRelativeSize -= $rFullSize - 100;
					}

					$rListings[$rChannelID][] = ['ListingId' => $rEPGItem['id'], 'ChannelId' => $rChannelID, 'Title' => $rEPGItem['title'], 'RelativeSize' => $rRelativeSize, 'StartTime' => date('h:iA', $rCapStart), 'EndTime' => date('h:iA', $rCapEnd), 'Start' => $rEPGItem['start'], 'End' => $rEPGItem['end'], 'Specialisation' => 'tv', 'Archive' => $rArchive];
				}
			}
		}

		$rDefaultEPG = ['ChannelId' => NULL, 'Title' => 'No Programme Information...', 'RelativeSize' => 100, 'StartTime' => 'Not Available', 'EndTime' => '', 'Specialisation' => 'tv', 'Archive' => NULL];
		$db->query('SELECT `id`, `stream_icon`, `stream_display_name`, `tv_archive_duration`, `tv_archive_server_id`, `category_id` FROM `streams` WHERE `id` IN (' . implode(',', $rChannels) . ') ORDER BY FIELD(`id`, ' . implode(',', $rChannels) . ') ASC;');

		foreach ($db->get_rows() as $rStream) {
			if ((0 < $rStream['tv_archive_duration']) && (0 < $rStream['tv_archive_server_id'])) {
				$rArchive = $rStream['tv_archive_duration'];
			}
			else {
				$rArchive = 0;
			}

			$rDefaultArray = $rDefaultEPG;
			$rDefaultArray['ChannelId'] = $rStream['id'];
			$rCategoryIDs = json_decode($rStream['category_id'], true);
			$rCategories = getCategories('live');

			if (0 < strlen(XCMS::$rRequest['category'])) {
				$rCategory = $rCategories[(int) XCMS::$rRequest['category']]['category_name'] ?: 'No Category';
			}
			else {
				$rCategory = $rCategories[$rCategoryIDs[0]]['category_name'] ?: 'No Category';
			}

			if (1 < count($rCategoryIDs)) {
				$rCategory .= ' (+' . (count($rCategoryIDs) - 1) . ' others)';
			}

			$rReturn['Channels'][] = ['Id' => $rStream['id'], 'DisplayName' => $rStream['stream_display_name'], 'CategoryName' => $rCategory, 'Archive' => $rArchive, 'Image' => XCMS::validateImage($rStream['stream_icon']) ?: '', 'TvListings' => $rListings[$rStream['id']] ?: [$rDefaultArray]];
		}

		echo json_encode($rReturn);
		exit();
	}
	else if (XCMS::$rRequest['action'] == 'get_programme') {
		if (!$rPermissions['can_view_vod']) {
			exit();
		}

		$rTimezone = XCMS::$rRequest['timezone'] ?: 'Europe/London';
		date_default_timezone_set($rTimezone);

		if (isset(XCMS::$rRequest['id'])) {
			$rRow = XCMS::getProgramme(XCMS::$rRequest['stream_id'], XCMS::$rRequest['id']);

			if ($rRow) {
				$rArchive = $rAvailable = false;

				if (time() < $rRow['end']) {
					$db->query('SELECT `server_id`, `direct_source`, `monitor_pid`, `pid`, `stream_status`, `on_demand` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams`.`id` = ? AND `server_id` IS NOT NULL;', XCMS::$rRequest['stream_id']);

					if (0 < $db->num_rows()) {
						foreach ($db->get_rows() as $rStreamRow) {
							if ($rStreamRow['server_id'] && !$rStreamRow['direct_source']) {
								$rAvailable = true;
								break;
							}
						}
					}
				}

				$rRow['date'] = date('H:i', $rRow['start']) . ' - ' . date('H:i', $rRow['end']);
				echo json_encode(['result' => true, 'data' => $rRow, 'available' => $rAvailable, 'archive' => $rArchive]);
				exit();
			}
		}

		echo json_encode(['result' => false]);
		exit();
	}
}

echo json_encode(['result' => false]);

?>