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

function parseLog($rLog)
{
	$rQuery = '';

	if (file_exists($rLog)) {
		$rFP = fopen($rLog, 'r');

		while (!feof($rFP)) {
			$rLine = trim(fgets($rFP));

			if (empty($rLine)) {
				break;
			}

			$rLine = json_decode(base64_decode($rLine), true);

			if ($rLine['stream_id']) {
				$rQuery .= '(' . $rLine['stream_id'] . ',' . SERVER_ID . (',\'' . $rLine['action'] . '\',\'' . $rLine['source'] . '\',\'' . $rLine['time'] . '\'),');
			}
		}

		fclose($rFP);
	}

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
cli_set_process_title('XCMS[Stream Logs]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rLog = LOGS_TMP_PATH . 'stream_log.log';

if (file_exists($rLog)) {
	$rQuery = rtrim(parseLog($rLog), ',');

	if (!empty($rQuery)) {
		$db->query('INSERT INTO `streams_logs` (`stream_id`,`server_id`,`action`,`source`,`date`) VALUES ' . $rQuery . ';');
	}

	unlink($rLog);
}

?>