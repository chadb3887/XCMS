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
shell_exec('kill -9 $(ps aux | grep queue | grep -v grep | grep -v ' . getmypid() . ' | awk \'{print $2}\')');
$rLastCheck = NULL;
$rInterval = 60;
$rMD5 = md5_file(__FILE__);

while (true) {
	if (!$db->ping()) {
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

	if (!$db->query('SELECT `id`, `pid` FROM `queue` WHERE `server_id` = ? AND `pid` IS NOT NULL AND `type` = \'movie\' ORDER BY `added` ASC;', SERVER_ID)) {
		break;
	}

	$rDelete = $rInProgress = [];

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			if ($rRow['pid'] && (XCMS::isProcessRunning($rRow['pid'], 'ffmpeg') || XCMS::isProcessRunning($rRow['pid'], PHP_BIN))) {
				$rInProgress[] = $rRow['pid'];
			}
			else {
				$rDelete[] = $rRow['id'];
			}
		}
	}

	$rFreeSlots = (0 < XCMS::$rSettings['max_encode_movies'] ? (int) XCMS::$rSettings['max_encode_movies'] - count($rInProgress) : 50);

	if (0 < $rFreeSlots) {
		if (!$db->query('SELECT `id`, `stream_id` FROM `queue` WHERE `server_id` = ? AND `pid` IS NULL AND `type` = \'movie\' ORDER BY `added` ASC LIMIT ' . $rFreeSlots . ';', SERVER_ID)) {
			break;
		}

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rRow) {
				$rPID = XCMS::startMovie($rRow['stream_id']);

				if ($rPID) {
					$db->query('UPDATE `queue` SET `pid` = ? WHERE `id` = ?;', $rPID, $rRow['id']);
				}
				else {
					$rDelete[] = $rRow['id'];
				}
			}
		}
	}

	if (!$db->query('SELECT `id`, `pid` FROM `queue` WHERE `server_id` = ? AND `pid` IS NOT NULL AND `type` = \'channel\' ORDER BY `added` ASC;', SERVER_ID)) {
		break;
	}

	$rInProgress = [];

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			if ($rRow['pid'] && XCMS::isProcessRunning($rRow['pid'], PHP_BIN)) {
				$rInProgress[] = $rRow['pid'];
			}
			else {
				$rDelete[] = $rRow['id'];
			}
		}
	}

	$rFreeSlots = (0 < XCMS::$rSettings['max_encode_cc'] ? (int) XCMS::$rSettings['max_encode_cc'] - count($rInProgress) : 1);

	if (0 < $rFreeSlots) {
		if (!$db->query('SELECT `id`, `stream_id` FROM `queue` WHERE `server_id` = ? AND `pid` IS NULL AND `type` = \'channel\' ORDER BY `added` ASC LIMIT ' . $rFreeSlots . ';', SERVER_ID)) {
			break;
		}

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rRow) {
				if (file_exists(CREATED_PATH . $rRow['stream_id'] . '_.create')) {
					unlink(CREATED_PATH . $rRow['stream_id'] . '_.create');
				}

				shell_exec(PHP_BIN . ' ' . CLI_PATH . 'created.php ' . (int) $rRow['stream_id'] . ' >/dev/null 2>/dev/null &');
				$rPID = NULL;

				foreach (range(1, 3) as $i) {
					if (file_exists(CREATED_PATH . $rRow['stream_id'] . '_.create')) {
						$rPID = (int) file_get_contents(CREATED_PATH . $rRow['stream_id'] . '_.create');
						break;
					}

					usleep(100000);
				}

				if ($rPID) {
					$db->query('UPDATE `queue` SET `pid` = ? WHERE `id` = ?;', $rPID, $rRow['id']);
				}
				else {
					$rDelete[] = $rRow['id'];
				}
			}
		}
	}

	if (0 < count($rDelete)) {
		$db->query('DELETE FROM `queue` WHERE `id` IN (' . implode(',', $rDelete) . ');');
	}

	sleep(0 < XCMS::$rSettings['queue_loop'] ? (int) XCMS::$rSettings['queue_loop'] : 5);
}

if (is_object($db)) {
	$db->close_mysql();
}

shell_exec('(sleep 1; ' . PHP_BIN . ' ' . __FILE__ . ' ) > /dev/null 2>/dev/null &');

?>