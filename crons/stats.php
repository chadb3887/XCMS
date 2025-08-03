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

function loadCron()
{
	global $db;

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		$rTime = time();
		$rDates = [
			'today' => [$rTime - 86400, $rTime],
			'week'  => [$rTime - 604800, $rTime],
			'month' => [$rTime - 2592000, $rTime],
			'all'   => [0, $rTime]
		];
		$db->query('TRUNCATE `streams_stats`;');

		foreach ($rDates as $rType => $rDate) {
			$rStats = [];
			$db->query('SELECT `stream_id`, COUNT(*) AS `connections`, SUM(`date_end` - `date_start`) AS `time`, COUNT(DISTINCT(`user_id`)) AS `users` FROM `lines_activity` LEFT JOIN `streams` ON `streams`.`id` = `lines_activity`.`stream_id` WHERE `date_start` > ? AND `date_end` <= ? GROUP BY `stream_id`;', $rDate[0], $rDate[1]);

			if (0 < $db->num_rows()) {
				foreach ($db->get_rows() as $rRow) {
					$rStats[$rRow['stream_id']] = ['rank' => 0, 'time' => (int) $rRow['time'], 'connections' => $rRow['connections'], 'users' => $rRow['users']];
				}
			}

			$db->query('SELECT `stream_id`, SUM(`date_end` - `date_start`) AS `time` FROM `lines_activity` LEFT JOIN `streams` ON `streams`.`id` = `lines_activity`.`stream_id` WHERE `date_start` > ? AND `date_end` <= ? GROUP BY `stream_id` ORDER BY `time` DESC, `stream_id` DESC;', $rDate[0], $rDate[1]);

			if (0 < $db->num_rows()) {
				$rRank = 1;

				foreach ($db->get_rows() as $rRow) {
					if (isset($rStats[$rRow['stream_id']])) {
						$rStats[$rRow['stream_id']]['rank'] = $rRank;
						$rRank++;
					}
				}
			}

			foreach ($rStats as $rStreamID => $rArray) {
				$db->query('INSERT INTO `streams_stats`(`stream_id`, `rank`, `time`, `connections`, `users`, `type`) VALUES(?, ?, ?, ?, ?, ?);', $rStreamID, $rArray['rank'], $rArray['time'], $rArray['connections'], $rArray['users'], $rType);
			}
		}
	}
}

function shutdown()
{
	global $db;
	global $rIdentifier;

	if (is_object($db)) {
		$db->close_mysql();
	}

	@unlink($rIdentifier);
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
cli_set_process_title('XCMS[Stats]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rTimeout = 60;
set_time_limit($rTimeout);
ini_set('max_execution_time', $rTimeout);
loadcron();

?>