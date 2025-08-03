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
	$rLog = LOGS_TMP_PATH . 'client_request.log';

	if (file_exists($rLog)) {
		$rQuery = rtrim(parseLog($rLog), ',');

		if (!empty($rQuery)) {
			$db->query('INSERT INTO `lines_logs` (`stream_id`,`user_id`,`client_status`,`query_string`,`user_agent`,`ip`,`extra_data`,`date`) VALUES ' . $rQuery . ';');
		}

		unlink($rLog);
	}
}

function parseLog($rLog)
{
	global $db;
	$rQuery = '';
	$rFP = fopen($rLog, 'r');

	while (!feof($rFP)) {
		$rLine = trim(fgets($rFP));

		if (empty($rLine)) {
			break;
		}

		$rLine = json_decode(base64_decode($rLine), true);
		$rLine = array_map([$db, 'escape'], $rLine);
		$rQuery .= '(' . $rLine['stream_id'] . ',' . $rLine['user_id'] . ',' . $rLine['action'] . ',' . $rLine['query_string'] . ',' . $rLine['user_agent'] . ',' . $rLine['user_ip'] . ',' . $rLine['extra_data'] . ',' . $rLine['time'] . '),';
	}

	fclose($rFP);
	return $rQuery;
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

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
cli_set_process_title('XCMS[Lines Logs]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();

?>