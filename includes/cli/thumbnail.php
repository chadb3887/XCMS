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

function loadCLI()
{
	global $db;
	global $rStreamID;
	$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t1.id = t2.stream_id AND t2.server_id = t1.vframes_server_id WHERE t1.`id` = ? AND t1.`vframes_server_id` = ?', $rStreamID, SERVER_ID);

	if (0 < $db->num_rows()) {
		$rRow = $db->get_row();
		$db->query('UPDATE `streams` SET `vframes_pid` = ? WHERE `id` = ?', getmypid(), $rStreamID);
		XCMS::updateStream($rStreamID);
	}
	else {
		exit();
	}

	$db->close_mysql();

	while (XCMS::isStreamRunning($rRow['pid'], $rStreamID)) {
		shell_exec(XCMS::$rFFMPEG_CPU . ' -y -i "' . STREAMS_PATH . $rStreamID . '_.m3u8" -qscale:v 4 -frames:v 1 "' . STREAMS_PATH . $rStreamID . '_.jpg" >/dev/null 2>/dev/null &');
		sleep(5);
	}
}

function checkRunning($rStreamID)
{
	clearstatcache(true);

	if (file_exists(STREAMS_PATH . $rStreamID . '_.thumb')) {
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.thumb');
	}

	if (empty($rPID)) {
		shell_exec('kill -9 `ps -ef | grep \'Thumbnail\\[' . $rStreamID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
	}
	else if (file_exists('/proc/' . $rPID)) {
		$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
		if (($rCommand == 'Thumbnail[' . $rStreamID . ']') && is_numeric($rPID) && (0 < $rPID)) {
			posix_kill($rPID, 9);
		}
	}

	file_put_contents(STREAMS_PATH . $rStreamID . '_.thumb', getmypid());
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}
if (!@$argc || ($argc != 2)) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
$rStreamID = (int) $argv[1];
checkRunning($rStreamID);
set_time_limit(0);
cli_set_process_title('Thumbnail[' . $rStreamID . ']');
loadcli();

?>