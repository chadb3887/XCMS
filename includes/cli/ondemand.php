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
shell_exec('kill -9 $(ps aux | grep ondemand | grep -v grep | grep -v ' . getmypid() . ' | awk \'{print $2}\')');

if (!XCMS::$rSettings['on_demand_instant_off']) {
	echo 'On-Demand - Instant Off setting is disabled.' . "\n";
	exit();
}

if (XCMS::$rSettings['redis_handler']) {
	XCMS::connectRedis();
}

$rMainID = XCMS::getMainID();
$rLastCheck = NULL;
$rInterval = 60;
$rMD5 = md5_file(__FILE__);

while (true) {
	if (!$db || !$db->ping()) {
		break;
	}
	if (XCMS::$rSettings['redis_handler'] && (!XCMS::$redis || !XCMS::$redis->ping())) {
		break;
	}
	if (!$rLastCheck || ($rInterval <= time() - $rLastCheck)) {
		if ($rMD5 != md5_file(__FILE__)) {
			echo 'File changed! Break.' . "\n";
			break;
		}

		XCMS::$rSettings = XCMS::getSettings(true);
		$rLastCheck = time();
	}

	$rRows = [];

	if (XCMS::$rSettings['redis_handler']) {
		$rStreamIDs = $rAttached = $rRows = [];

		if (!$db->query('SELECT t1.stream_id, servers_attached.attached FROM `streams_servers` t1 LEFT JOIN (SELECT `stream_id`, COUNT(*) AS `attached` FROM `streams_servers` WHERE `parent_id` = ? AND `pid` IS NOT NULL AND `pid` > 0 AND `monitor_pid` IS NOT NULL AND `monitor_pid` > 0) AS `servers_attached` ON `servers_attached`.`stream_id` = t1.`stream_id` WHERE t1.pid IS NOT NULL AND t1.pid > 0 AND t1.server_id = ? AND t1.`on_demand` = 1;', SERVER_ID, SERVER_ID)) {
			break;
		}

		foreach ($db->get_rows() as $rRow) {
			$rStreamIDs[] = $rRow['stream_id'];
			$rAttached[$rRow['stream_id']] = $rRow['attached'];
		}

		if (0 < count($rStreamIDs)) {
			$rConnections = XCMS::getStreamConnections($rStreamIDs, false, false);

			foreach ($rStreamIDs as $rStreamID) {
				$rRows[] = ['stream_id' => $rStreamID, 'online_clients' => count($rConnections[$rStreamID][SERVER_ID]) ?: 0, 'attached' => $rAttached[$rStreamID] ?: 0];
			}
		}
	}
	else {
		if (!$db->query('SELECT t1.stream_id, clients.online_clients, servers_attached.attached FROM `streams_servers` t1 LEFT JOIN (SELECT stream_id, COUNT(*) as online_clients FROM `lines_live` WHERE `server_id` = ? AND `hls_end` = 0 GROUP BY stream_id) AS clients ON clients.stream_id = t1.stream_id LEFT JOIN (SELECT `stream_id`, COUNT(*) AS `attached` FROM `streams_servers` WHERE `parent_id` = ? AND `pid` IS NOT NULL AND `pid` > 0 AND `monitor_pid` IS NOT NULL AND `monitor_pid` > 0) AS `servers_attached` ON `servers_attached`.`stream_id` = t1.`stream_id` WHERE t1.pid IS NOT NULL AND t1.pid > 0 AND t1.server_id = ? AND t1.`on_demand` = 1;', SERVER_ID, SERVER_ID, SERVER_ID)) {
			break;
		}

		if (0 < $db->num_rows()) {
			$rRows = $db->get_rows();
		}
	}

	if (0 < count($rRows)) {
		foreach ($rRows as $rRow) {
			if ((0 < $rRow['online_clients']) || (0 < $rRow['attached'])) {
				continue;
			}

			$rStreamID = $rRow['stream_id'];
			$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid');
			$rMonitorPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
			$rAdminQueue = $rQueue = 0;

			if (file_exists(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID)) {
				foreach (igbinary_unserialize(file_get_contents(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID)) ?: [] as $rPID) {
					if (XCMS::isProcessRunning($rPID, 'php-fpm')) {
						$rQueue++;
					}
				}
			}
			if (file_exists(SIGNALS_TMP_PATH . 'admin_' . (int) $rStreamID) && ((time() - filemtime(SIGNALS_TMP_PATH . 'admin_' . (int) $rStreamID)) <= 30)) {
				$rAdminQueue = 1;
			}

			echo 'Queue: ' . ($rQueue + $rAdminQueue) . "\n";
			if (($rQueue == 0) && ($rAdminQueue == 0) && XCMS::isMonitorRunning($rMonitorPID, $rStreamID)) {
				echo 'Killing ID: ' . $rStreamID . "\n";
				if (is_numeric($rMonitorPID) && (0 < $rMonitorPID)) {
					posix_kill($rMonitorPID, 9);
				}
				if (is_numeric($rPID) && (0 < $rPID)) {
					posix_kill($rPID, 9);
				}

				shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*');
				$db->query('UPDATE `streams_servers` SET `bitrate` = NULL,`current_source` = NULL,`to_analyze` = 0,`pid` = NULL,`stream_started` = NULL,`stream_info` = NULL,`audio_codec` = NULL,`video_codec` = NULL,`resolution` = NULL,`compatible` = 0,`stream_status` = 0,`monitor_pid` = NULL WHERE `stream_id` = ? AND `server_id` = ?', $rStreamID, SERVER_ID);
				$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', $rMainID, time(), json_encode(['type' => 'update_stream', 'id' => $rStreamID]));
				unlink(SIGNALS_TMP_PATH . 'queue_' . (int) $rStreamID);
				XCMS::updateStream($rStreamID);
			}
		}
	}

	usleep(1000000);
}

if (is_object($db)) {
	$db->close_mysql();
}

shell_exec('(sleep 1; ' . PHP_BIN . ' ' . __FILE__ . ' ) > /dev/null 2>/dev/null &');

?>