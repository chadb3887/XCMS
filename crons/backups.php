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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(32757);

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

require str_replace('\\', '/', dirname($argv[0])) . '/../includes/admin.php';

if (!XCMS::$rServers[SERVER_ID]['is_main']) {
	exit('Please run on main server.');
}

cli_set_process_title('XCMS[Backups]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rForce = false;

if (1 < count($argv)) {
	if ((int) $argv[1] == 1) {
		$rForce = true;
	}
}

$rBackups = XCMS::$rSettings['automatic_backups'];
$rLastBackup = (int) XCMS::$rSettings['last_backup'];
$rPeriod = ['hourly' => 3600, 'daily' => 86400, 'weekly' => 604800, 'monthly' => 2419200];

if (!$rForce) {
	$rPID = getmypid();
	if (file_exists('/proc/' . XCMS::$rSettings['backups_pid']) && (0 < strlen(XCMS::$rSettings['backups_pid']))) {
		exit();
	}
	else {
		$db->query('UPDATE `settings` SET `backups_pid` = ?;', $rPID);
	}
}
if ((isset($rBackups) && ($rBackups != 'off')) || $rForce) {
	if ((($rLastBackup + $rPeriod[$rBackups]) <= time()) || $rForce) {
		if (!$rForce) {
			$db->query('UPDATE `settings` SET `last_backup` = ?;', time());
		}

		$db->close_mysql();
		$rFilename = XCMS_HOME . 'backups/backup_' . date('Y-m-d_H:i:s') . '.sql';
		$rRet = Xcms\Functions::backup($rFilename, true);

		if (0 < filesize($rFilename)) {
			if (XCMS::$rSettings['dropbox_remote']) {
				file_put_contents($rFilename . '.uploading', time());
				$rResponse = uploadRemoteBackup(basename($rFilename), $rFilename);

				if (!isset($rResponse->error)) {
					$rResponse = json_decode(json_encode($rResponse, JSON_UNESCAPED_UNICODE), true);
					if (!isset($rResponse['size']) || (filesize($rFilename) != (int) $rResponse['size'])) {
						$rError = 'Failed to upload';
						file_put_contents($rFilename . '.error', $rError);
					}
				}
				else {
					try {
						$rError = json_decode(explode(', in apiCall', $rResponse->error->getMessage())[0], true)['error_summary'];
					}
					catch (exception $e) {
						$rError = 'Unknown error';
					}

					file_put_contents($rFilename . '.error', $rError);
				}

				unlink($rFilename . '.uploading');
			}
		}
		else {
			unlink($rFilename);
		}
	}
}

$rBackups = getBackups();
if (((int) XCMS::$rSettings['backups_to_keep'] < count($rBackups)) && (0 < (int) XCMS::$rSettings['backups_to_keep'])) {
	$rDelete = array_slice($rBackups, 0, count($rBackups) - (int) XCMS::$rSettings['backups_to_keep']);

	foreach ($rDelete as $rItem) {
		if (file_exists(XCMS_HOME . 'backups/' . $rItem['filename'])) {
			unlink(XCMS_HOME . 'backups/' . $rItem['filename']);
		}
	}
}

if (XCMS::$rSettings['dropbox_remote']) {
	$rRemoteBackups = getRemoteBackups();
	if (((int) XCMS::$rSettings['dropbox_keep'] < count($rRemoteBackups)) && (0 < (int) XCMS::$rSettings['dropbox_keep'])) {
		$rDelete = array_slice($rRemoteBackups, 0, count($rRemoteBackups) - (int) XCMS::$rSettings['dropbox_keep']);

		foreach ($rDelete as $rItem) {
			try {
				deleteRemoteBackup($rItem['path']);
			}
			catch (exception $e) {
			}
		}
	}
}

@unlink($rIdentifier);

?>