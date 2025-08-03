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
	$rLogFile = LOGS_TMP_PATH . 'activity';
	$rUpdateQuery = $rQuery = '';
	$rUpdates = [];
	$rCount = 0;

	if (file_exists($rLogFile)) {
		list($rQuery, $rUpdates, $rCount) = parseLog($rLogFile);
		unlink($rLogFile);
	}

	if (0 < $rCount) {
		$rQuery = rtrim($rQuery, ',');

		if (!empty($rQuery)) {
			if ($db->query('INSERT INTO `lines_activity` (`server_id`,`proxy_id`,`user_id`,`isp`,`external_device`,`stream_id`,`date_start`,`user_agent`,`user_ip`,`date_end`,`container`,`geoip_country_code`,`divergence`,`hmac_id`,`hmac_identifier`) VALUES ' . $rQuery)) {
				$rFirstID = $db->last_insert_id();

				for ($i = 0; $i < $rCount; $i++) {
					$rUpdateQuery .= '(' . $rUpdates[$i][0] . ',' . $db->escape($rUpdates[$i][1]) . ',' . ($rFirstID + $i) . ',' . $db->escape($rUpdates[$i][2]) . '),';
				}
			}
		}
	}

	$rUpdateQuery = rtrim($rUpdateQuery, ',');

	if (!empty($rUpdateQuery)) {
		$db->query('INSERT INTO `lines`(`id`,`last_ip`,`last_activity`,`last_activity_array`) VALUES ' . $rUpdateQuery . ' ON DUPLICATE KEY UPDATE `id`=VALUES(`id`), `last_ip`=VALUES(`last_ip`), `last_activity`=VALUES(`last_activity`), `last_activity_array`=VALUES(`last_activity_array`);');
	}
}

function parseLog($rFile)
{
	global $db;
	$rQuery = '';
	$rUpdates = [];
	$rCount = 0;

	if (file_exists($rFile)) {
		$rFP = fopen($rFile, 'r');

		while (!feof($rFP)) {
			$rLine = trim(fgets($rFP));

			if (empty($rLine)) {
				break;
			}

			$rLine = json_decode(base64_decode($rLine), true);
			if ($rLine['server_id'] && $rLine['user_id'] && $rLine['stream_id'] && $rLine['user_ip']) {
				$rUpdates[] = [$rLine['user_id'], $rLine['user_ip'], json_encode(['date_end' => $rLine['date_end'], 'stream_id' => $rLine['stream_id']])];
				$rLine = array_map([$db, 'escape'], $rLine);
				$rQuery .= '(' . $rLine['server_id'] . ',' . $rLine['proxy_id'] . ',' . $rLine['user_id'] . ',' . $rLine['isp'] . ',' . $rLine['external_device'] . ',' . $rLine['stream_id'] . ',' . $rLine['date_start'] . ',' . $rLine['user_agent'] . ',' . $rLine['user_ip'] . ',' . $rLine['date_end'] . ',' . $rLine['container'] . ',' . $rLine['geoip_country_code'] . ',' . $rLine['divergence'] . ',' . $rLine['hmac_id'] . ',' . $rLine['hmac_identifier'] . '),';
				$rCount++;
			}
		}

		fclose($rFP);
	}

	return [$rQuery, $rUpdates, $rCount];
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
cli_set_process_title('XCMS[Activity]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();

?>