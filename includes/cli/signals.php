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
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
shell_exec('kill -9 $(ps aux | grep signals | grep -v grep | grep -v ' . getmypid() . ' | awk \'{print $2}\')');
$rLastCheck = NULL;
$rInterval = 60;
$rMD5 = md5_file(__FILE__);

if (XCMS::$rSettings['redis_handler']) {
	XCMS::connectRedis();
}

while (true) {
	if (!$db || !$db->ping()) {
		break;
	}
	if (!$rLastCheck || ($rInterval <= time() - $rLastCheck)) {
		if (!XCMS::isRunning()) {
			echo 'Not running! Break.' . "\n";
			break;
		}

		if ($rMD5 != md5_file(__FILE__)) {
			echo 'File changed! Break.' . "\n";
			break;
		}

		XCMS::$rSettings = XCMS::getSettings(true);
		XCMS::$rServers = XCMS::getServers(true);
		$rLastCheck = time();
	}
	if (XCMS::$rSettings['redis_handler'] && (!XCMS::$redis || !XCMS::$redis->ping())) {
		break;
	}

	if (!$db->query('SELECT `signal_id`, `pid`, `rtmp` FROM `signals` WHERE `server_id` = ? AND `pid` IS NOT NULL ORDER BY `signal_id` ASC LIMIT 100', SERVER_ID)) {
		break;
	}

	if (0 < $db->num_rows()) {
		$rIDs = [];

		foreach ($db->get_rows() as $rRow) {
			$rIDs[] = $rRow['signal_id'];
			$rPID = $rRow['pid'];

			if ($rRow['rtmp'] == 0) {
				if (!empty($rPID) && file_exists('/proc/' . $rPID) && is_numeric($rPID) && (0 < $rPID)) {
					shell_exec('kill -9 ' . (int) $rPID);
				}
			}
			else {
				shell_exec('wget --timeout=2 -O /dev/null -o /dev/null "' . XCMS::$rServers[SERVER_ID]['rtmp_mport_url'] . 'control/drop/client?clientid=' . (int) $rPID . '" >/dev/null 2>/dev/null &');
			}
		}

		if (0 < count($rIDs)) {
			$db->query('DELETE FROM `signals` WHERE `signal_id` IN (' . implode(',', $rIDs) . ')');
		}
	}

	if (!$db->query('SELECT `signal_id`, `custom_data` FROM `signals` WHERE `server_id` = ? AND `cache` = 1 ORDER BY `signal_id` ASC LIMIT 1000;', SERVER_ID)) {
		break;
	}

	if (0 < $db->num_rows()) {
		$rDeletedLines = $rUpdatedStreams = $rUpdatedLines = $rIDs = [];

		foreach ($db->get_rows() as $rRow) {
			$rCustomData = json_decode($rRow['custom_data'], true);
			$rIDs[] = $rRow['signal_id'];

			switch ($rCustomData['type']) {
			case 'update_stream':
				if (!in_array($rCustomData['id'], $rUpdatedStreams)) {
					$rUpdatedStreams[] = $rCustomData['id'];
				}

				break;
			case 'update_line':
				if (!in_array($rCustomData['id'], $rUpdatedLines)) {
					$rUpdatedLines[] = $rCustomData['id'];
				}

				break;
			case 'update_streams':
				foreach ($rCustomData['id'] as $rID) {
					if (!in_array($rID, $rUpdatedStreams)) {
						$rUpdatedStreams[] = $rID;
					}
				}

				break;
			case 'update_lines':
				foreach ($rCustomData['id'] as $rID) {
					if (!in_array($rID, $rUpdatedLines)) {
						$rUpdatedLines[] = $rID;
					}
				}

				break;
			case 'delete_con':
				unlink(CONS_TMP_PATH . $rCustomData['uuid']);
				break;
			case 'delete_vod':
				exec('rm ' . XCMS_HOME . 'content/vod/' . (int) $rCustomData['id'] . '.*');
				break;
			case 'delete_vods':
				foreach ($rCustomData['id'] as $rID) {
					exec('rm ' . XCMS_HOME . 'content/vod/' . (int) $rID . '.*');
				}

				break;
			}
		}

		if (0 < count($rUpdatedStreams)) {
			shell_exec(PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "streams_update" "' . implode(',', $rUpdatedStreams) . '"');
		}

		if (0 < count($rUpdatedLines)) {
			shell_exec(PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "lines_update" "' . implode(',', $rUpdatedLines) . '"');
		}

		if (0 < count($rIDs)) {
			$db->query('DELETE FROM `signals` WHERE `signal_id` IN (' . implode(',', $rIDs) . ')');
		}
	}

	if (XCMS::$rSettings['redis_handler']) {
		$rSignals = [];

		foreach (XCMS::$redis->sMembers('SIGNALS#' . SERVER_ID) as $rKey) {
			$rSignals[] = $rKey;
		}

		if (0 < count($rSignals)) {
			$rSignalData = XCMS::$redis->mGet($rSignals);
			$rIDs = [];

			foreach ($rSignalData as $rData) {
				$rRow = igbinary_unserialize($rData);
				$rIDs[] = $rRow['key'];
				$rPID = $rRow['pid'];

				if ($rRow['rtmp'] == 0) {
					if (!empty($rPID) && file_exists('/proc/' . $rPID) && is_numeric($rPID) && (0 < $rPID)) {
						shell_exec('kill -9 ' . (int) $rPID);
					}
				}
				else {
					shell_exec('wget --timeout=2 -O /dev/null -o /dev/null "' . XCMS::$rServers[SERVER_ID]['rtmp_mport_url'] . 'control/drop/client?clientid=' . (int) $rPID . '" >/dev/null 2>/dev/null &');
				}
			}

			XCMS::$redis->multi()->del($rIDs)->sRem('SIGNALS#' . SERVER_ID, ...$rSignals)->exec();
		}
	}

	usleep(250000);
}

if (is_object($db)) {
	$db->close_mysql();
}

shell_exec('(sleep 1; ' . PHP_BIN . ' ' . __FILE__ . ' ) > /dev/null 2>/dev/null &');

?>