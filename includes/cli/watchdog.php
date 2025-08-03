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

require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
shell_exec('kill $(ps aux | grep watchdog | grep -v grep | grep -v ' . getmypid() . ' | awk \'{print $2}\')');
$rInterval = (int) XCMS::$rSettings['online_capacity_interval'] ?: 10;
$rLastRequests = $rLastRequestsTime = $rPrevStat = $rLastCheck = NULL;
$rMD5 = md5_file(__FILE__);

if (XCMS::$rSettings['redis_handler']) {
	XCMS::connectRedis();
}

$rWatchdog = json_decode(XCMS::$rServers[SERVER_ID]['watchdog_data'], true);
$rCPUAverage = $rWatchdog['cpu_average_array'] ?: [];

while (true) {
	if (!$db->ping()) {
		break;
	}
	if (XCMS::$rSettings['redis_handler'] && (!XCMS::$redis || !XCMS::$redis->ping())) {
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

		XCMS::$rServers = XCMS::getServers(true);
		XCMS::$rSettings = XCMS::getSettings(true);

		if ($rServers[SERVER_ID]['server_type'] == 0) {
			XCMS::getCapacity(false);
		}
		else {
			XCMS::getCapacity(true);
		}

		$rLastCheck = time();
	}

	$rNginx = explode("\n", file_get_contents('http://127.0.0.1:' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/nginx_status'));
	list($rAccepted, $rHandled, $rRequests) = explode(' ', trim($rNginx[2]));
	$rRequestsPerSecond = ($rLastRequests ? (int) ((float) ($rRequests - $rLastRequests) / (time() - $rLastRequestsTime)) : 0);
	$rLastRequests = $rRequests;
	$rLastRequestsTime = time();
	$rStats = XCMS::getStats();

	if (!$rPrevStat) {
		$rPrevStat = file('/proc/stat');
		sleep(2);
	}

	$rStat = file('/proc/stat');
	$rInfoA = explode(' ', preg_replace('!cpu +!', '', $rPrevStat[0]));
	$rInfoB = explode(' ', preg_replace('!cpu +!', '', $rStat[0]));
	$rPrevStat = $rStat;
	$rDiff = [];
	$rDiff['user'] = $rInfoB[0] - $rInfoA[0];
	$rDiff['nice'] = $rInfoB[1] - $rInfoA[1];
	$rDiff['sys'] = $rInfoB[2] - $rInfoA[2];
	$rDiff['idle'] = $rInfoB[3] - $rInfoA[3];
	$rTotal = array_sum($rDiff);
	$rCPU = [];

	foreach ($rDiff as $x => $y) {
		$rCPU[$x] = round(($y / $rTotal) * 100, 2);
	}

	$rStats['cpu'] = $rCPU['user'] + $rCPU['sys'];
	$rCPUAverage[] = $rStats['cpu'];

	if (30 < count($rCPUAverage)) {
		$rCPUAverage = array_slice($rCPUAverage, count($rCPUAverage) - 30, 30);
	}

	$rStats['cpu_average_array'] = $rCPUAverage;
	$rPHPPIDs = [];
	exec('ps -u xcms | grep php-fpm | awk {\'print $1\'}', $rPHPPIDs);
	$rConnections = $rUsers = 0;

	if (!XCMS::$rSettings['redis_handler']) {
		$db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `hls_end` = 0 AND `server_id` = ?;', SERVER_ID);
		$rConnections = $db->get_row()['count'];
		$db->query('SELECT `activity_id` FROM `lines_live` WHERE `hls_end` = 0 AND `server_id` = ? GROUP BY `user_id`;', SERVER_ID);
		$rUsers = $db->num_rows();
		$rResult = $db->query('UPDATE `servers` SET `watchdog_data` = ?, `last_check_ago` = UNIX_TIMESTAMP(), `requests_per_second` = ?, `php_pids` = ?, `connections` = ?, `users` = ? WHERE `id` = ?;', json_encode($rStats, JSON_PARTIAL_OUTPUT_ON_ERROR), $rRequestsPerSecond, json_encode($rPHPPIDs), $rConnections, $rUsers, SERVER_ID);
	}
	else {
		$rResult = $db->query('UPDATE `servers` SET `watchdog_data` = ?, `last_check_ago` = UNIX_TIMESTAMP(), `requests_per_second` = ?, `php_pids` = ? WHERE `id` = ?;', json_encode($rStats, JSON_PARTIAL_OUTPUT_ON_ERROR), $rRequestsPerSecond, json_encode($rPHPPIDs), SERVER_ID);
	}

	if (!$rResult) {
		echo 'Failed, break.' . "\n";
		break;
	}

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		if (XCMS::$rSettings['redis_handler']) {
			$rMulti = XCMS::$redis->multi();

			foreach (array_keys(XCMS::$rServers) as $rServerID) {
				if (XCMS::$rServers[$rServerID]['server_online']) {
					$rMulti->zCard('SERVER#' . $rServerID);
					$rMulti->zRangeByScore('SERVER_LINES#' . $rServerID, '-inf', '+inf', ['withscores' => true]);
				}
			}

			$rResults = $rMulti->exec();
			$rTotalUsers = [];
			$i = 0;

			foreach (array_keys(XCMS::$rServers) as $rServerID) {
				if (XCMS::$rServers[$rServerID]['server_online']) {
					$db->query('UPDATE `servers` SET `connections` = ?, `users` = ? WHERE `id` = ?;', $rResults[$i * 2], count(array_unique(array_values($rResults[($i * 2) + 1]))), $rServerID);
					$rTotalUsers = array_merge(array_values($rResults[($i * 2) + 1]), $rTotalUsers);
					$i++;
				}
			}

			$db->query('UPDATE `settings` SET `total_users` = ?;', count(array_unique($rTotalUsers)));
		}
		else {
			$db->query('SELECT `activity_id` FROM `lines_live` WHERE `hls_end` = 0 GROUP BY `user_id`;');
			$rTotalUsers = $db->num_rows();
			$db->query('UPDATE `settings` SET `total_users` = ?;', $rTotalUsers);
		}
	}

	sleep(2);
}

if (is_object($db)) {
	$db->close_mysql();
}

shell_exec('(sleep 1; ' . PHP_BIN . ' ' . __FILE__ . ' ) > /dev/null 2>/dev/null &');

?>