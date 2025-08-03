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
	$rWrite = false;

	if (XCMS::$rServers[SERVER_ID]['is_main'] == 0) {
		if (XCMS::$rServers[SERVER_ID]['enable_protection'] == 1) {
			$rOutput = 'default 1;' . PHP_EOL . '127.0.0.1 0;' . PHP_EOL;
			$db->query('SELECT `server_ip` FROM servers;');

			foreach ($db->get_rows() as $rRow) {
				$rOutput .= $rRow['server_ip'] . ' 0;' . PHP_EOL;

				if (!exec('cat ' . XCMS_HOME . 'bin/nginx/conf/geo.conf | grep ' . escapeshellarg($rRow['server_ip']))) {
					$rWrite = true;
					$rWriteStatus = 'Protection Access Updated';
				}
			}
		}

		if (!exec('cat ' . XCMS_HOME . 'bin/nginx/conf/protection.conf | grep ' . escapeshellarg(XCMS::$rSettings['nginx_key']))) {
			$rOutputData = 'if ($request_uri ~ .' . XCMS::$rSettings['nginx_key'] . '.) { set $trusted 0; }';
			file_put_contents(XCMS_HOME . 'bin/nginx/conf/protection.conf', $rOutputData);
			$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, `UPDATED`, `Nginx Protection Access Updated`, `system`, `localhost`, NULL, ?);', SERVER_ID, time());
			exec(XCMS_HOME . 'bin/nginx/sbin/nginx -s reload');
		}

		if (XCMS::$rServers[SERVER_ID]['enable_protection'] == 0) {
			if (!exec('cat ' . XCMS_HOME . 'bin/nginx/conf/geo.conf | grep "default 0;" ')) {
				$rOutput = 'default 0;';
				$rWrite = true;
				$rWriteStatus = 'Protection Disabled';
			}
		}

		if ($rWrite) {
			file_put_contents(XCMS_HOME . 'bin/nginx/conf/geo.conf', $rOutput);
			$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, `UPDATED`, ' . $rWriteStatus . ', `system`, `localhost`, NULL, ?);', SERVER_ID, time());
			exec(XCMS_HOME . 'bin/nginx/sbin/nginx -s reload');
		}
	}

	$apt_updates = (int) shell_exec('apt-get upgrade -s |grep -P \'^\\d+ upgraded\'|cut -d" " -f1');
	$db->query('UPDATE `servers` SET `apt_updates`= ? WHERE id = ?', $apt_updates, SERVER_ID);
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
cli_set_process_title('XCMS[Protection]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rTimeout = 60;
set_time_limit($rTimeout);
ini_set('max_execution_time', $rTimeout);
XCMS::$rSettings = XCMS::getSettings(true);
$rNginxKey = XCMS::$rSettings['nginx_key'];
loadcron();

?>