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

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

set_time_limit(0);
$rPID = getmypid();
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
shell_exec('kill -9 $(ps aux | grep cache_handler | grep -v grep | grep -v ' . $rPID . ' | awk \'{print $2}\')');
$rLastCheck = NULL;
$rInterval = 60;
$rMD5 = md5_file(__FILE__);
XCMS::$rSettings = XCMS::getSettings(true);

if (!XCMS::$rSettings['enable_cache']) {
	echo 'Cache disabled.' . "\n";
	exit();
}

while (true) {
	try {
		if (!$db->ping()) {
			break;
		}
		if (!$rLastCheck || ($rInterval <= time() - $rLastCheck)) {
			XCMS::$rSettings = XCMS::getSettings(true);
			XCMS::$rServers = XCMS::getServers(true);

			if (!XCMS::$rSettings['enable_cache']) {
				echo 'Cache disabled! Break.' . "\n";
				break;
			}

			if ($rMD5 != md5_file(__FILE__)) {
				echo 'File changed! Break.' . "\n";
				break;
			}

			$rLastCheck = time();
		}

		$rUpdatedLines = [];

		foreach (glob(SIGNALS_TMP_PATH . 'cache_*') as $rFileMD5) {
			list($rKey, $rData) = json_decode(file_get_contents($rFileMD5), true);
			$rHeader = explode('/', $rKey)[0];

			switch ($rHeader) {
			case 'restream_block_user':
				list($rBlank, $rUserID, $rStreamID, $rIP) = explode('/', $rKey);
				$db->query('UPDATE `lines` SET `admin_enabled` = 0 WHERE `id` = ?;', $rUserID);
				$db->query('INSERT INTO `detect_restream_logs`(`user_id`, `stream_id`, `ip`, `time`) VALUES(?, ?, ?, ?);', $rUserID, $rStreamID, $rIP, time());
				$rUpdatedLines[] = $rUserID;
				break;
			case 'forced_country':
				$rUserID = (int) explode('/', $rKey)[1];
				$db->query('UPDATE `lines` SET `forced_country` = ? WHERE `id` = ?', $rData, $rUserID);
				$rUpdatedLines[] = $rUserID;
				break;
			case 'isp':
				$rUserID = (int) explode('/', $rKey)[1];
				$rISPInfo = json_decode($rData, true);
				$db->query('UPDATE `lines` SET `isp_desc` = ?, `as_number` = ? WHERE `id` = ?', $rISPInfo[0], $rISPInfo[1], $rUserID);
				$rUpdatedLines[] = $rUserID;
				break;
			case 'expiring':
				$rUserID = (int) explode('/', $rKey)[1];
				$db->query('UPDATE `lines` SET `last_expiration_video` = ? WHERE `id` = ?;', time(), $rUserID);
				$rUpdatedLines[] = $rUserID;
				break;
			case 'flood_attack':
				list($rBlank, $rIP) = explode('/', $rKey);
				$db->query('INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES(?,?,?)', $rIP, 'FLOOD ATTACK', time());
				touch(FLOOD_TMP_PATH . 'block_' . $rIP);
				break;
			case 'bruteforce_attack':
				list($rBlank, $rIP) = explode('/', $rKey);
				$db->query('INSERT INTO `blocked_ips` (`ip`,`notes`,`date`) VALUES(?,?,?)', $rIP, 'BRUTEFORCE ATTACK', time());
				touch(FLOOD_TMP_PATH . 'block_' . $rIP);
				break;
			}

			unlink($rFileMD5);
		}

		foreach (list($rBlank, $rUserID, $rStreamID, $rIP) = explode('/', $rKey)) {
		}

		$rUpdatedLines = array_unique($rUpdatedLines);

		foreach ($rUpdatedLines as $rUserID) {
			XCMS::updateLine($rUserID);
		}

		sleep(1);
	}
	catch (Exception $e) {
		echo 'Error!' . "\n";
		break;
	}
}

if (is_object($db)) {
	$db->close_mysql();
}

shell_exec('(sleep 1; ' . PHP_BIN . ' ' . __FILE__ . ' ) > /dev/null 2>/dev/null &');

?>