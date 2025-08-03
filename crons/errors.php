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
	global $rIgnoreErrors;
	global $db;
	$rQuery = '';

	foreach ([STREAMS_PATH] as $rPath) {
		if ($rHandle = opendir($rPath)) {
			while (($rEntry = readdir($rHandle)) !== false) {
				if (($rEntry != '.') && ($rEntry != '..') && is_file($rPath . $rEntry)) {
					$rFile = $rPath . $rEntry;
					list($rStreamID, $rExtension) = explode('.', $rEntry);

					if ($rExtension == 'errors') {
						$rErrors = array_values(array_unique(array_map('trim', explode("\n", file_get_contents($rFile)))));

						foreach ($rErrors as $rError) {
							if (empty($rError) || inArray($rIgnoreErrors, $rError)) {
								continue;
							}

							if (XCMS::$rSettings['stream_logs_save']) {
								$rQuery .= '(' . $rStreamID . ',' . SERVER_ID . ',' . time() . ',' . $db->escape($rError) . '),';
							}
						}

						unlink($rFile);
					}
				}
			}

			closedir($rHandle);
		}
	}
	if (XCMS::$rSettings['stream_logs_save'] && !empty($rQuery)) {
		$rQuery = rtrim($rQuery, ',');
		$db->query('INSERT INTO `streams_errors` (`stream_id`,`server_id`,`date`,`error`) VALUES ' . $rQuery . ';');
	}

	if (XCMS::$rServers[SERVER_ID]['log_sql']) {
		$rLog = LOGS_TMP_PATH . 'error_log.log';

		if (file_exists($rLog)) {
			$rQuery = rtrim(parseLog($rLog), ',');
			$db->query('INSERT IGNORE INTO `panel_logs` (`server_id`,`type`,`log_message`,`log_extra`,`line`,`date`,`unique`) VALUES ' . $rQuery . ';');
			unlink($rLog);
		}
		else {
			$rLog = LOGS_TMP_PATH . 'error_log.log';
			unlink($rLog);
		}
	}
}

function parseLog($rLog)
{
	global $db;
	$rUniques = [];
	$rQuery = '';

	if (file_exists($rLog)) {
		$rFP = fopen($rLog, 'r');

		while (!feof($rFP)) {
			$rLine = trim(fgets($rFP));

			if (empty($rLine)) {
				break;
			}

			$rLine = json_decode(base64_decode($rLine), true);
			$rUnique = md5($rLine['type'] . $rLine['message'] . $rLine['extra'] . $rLine['line']);

			if (!in_array($rUnique, $rUniques)) {
				if ((stripos($rLine['message'], 'server has gone away') !== false) && (stripos($rLine['message'], 'socket error on read socket') !== false) && (stripos($rLine['message'], 'connection lost') !== false)) {
					continue;
				}

				$rLine = array_map([$db, 'escape'], $rLine);
				$rQuery .= '(' . SERVER_ID . (',' . $rLine['type'] . ',' . $rLine['message'] . ',' . $rLine['extra'] . ',' . $rLine['line'] . ',' . $rLine['time'] . ',\'' . $rUnique . '\'),');
				$rUniques[] = $rUnique;
			}
		}

		fclose($rFP);
	}

	return $rQuery;
}

function inArray($needles, $haystack)
{
	foreach ($needles as $needle) {
		if (stristr($haystack, $needle)) {
			return true;
		}
	}

	return false;
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
cli_set_process_title('XCMS[Errors]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
XCMS::$rSettings = XCMS::getSettings(true);
$rIgnoreErrors = ['the user-agent option is deprecated', 'last message repeated', 'deprecated', 'packets poorly interleaved', 'invalid timestamps', 'timescale not set', 'frame size not set', 'non-monotonous dts in output stream', 'invalid dts', 'no trailing crlf', 'failed to parse extradata', 'truncated', 'missing picture', 'non-existing pps', 'clipping', 'out of range', 'cannot use rename on non file protocol', 'end of file', 'stream ends prematurely'];
loadcron();

?>