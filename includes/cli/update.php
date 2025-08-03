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
	global $rCommand;

	switch ($rCommand) {
	case 'update':
		if (XCMS::$rServers[SERVER_ID]['is_main']) {
			$rUpdate = Xcms\Functions::checkUpdate(XCMS_VERSION);
		}
		else {
			$rVersion = NULL;
			$rURL = NULL;

			foreach (XCMS::$rServers as $rServer) {
				if ($rServer['is_main']) {
					$rURL = 'http://' . $rServer['server_ip'] . ':' . $rServer['http_broadcast_port'] . '/api?password=' . XCMS::$rSettings['live_streaming_pass'] . '&action=request_update&type=' . (int) XCMS::$rServers[SERVER_ID]['server_type'];
					$rVersion = $rServer['xcms_version'];
					break;
				}
			}

			if ($rURL) {
				$rUpdate = json_decode(file_get_contents($rURL), true);
			}
			else {
				exit(0);
			}
		}
		if ($rUpdate && (0 < strlen($rUpdate['url']))) {
			$rData = fopen($rUpdate['url'], 'rb');
			$rOutputDir = TMP_PATH . '.update.tar.gz';
			$rOutput = fopen($rOutputDir, 'wb');
			stream_copy_to_stream($rData, $rOutput);
			fclose($rData);
			fclose($rOutput);

			if (md5_file($rOutputDir) == $rUpdate['md5']) {
				$db->query('UPDATE `servers` SET `status` = 5 WHERE `id` = ?;', SERVER_ID);
				$rCommand = 'sudo /usr/bin/python3 ' . XCMS_HOME . 'update "' . $rOutputDir . '" "' . $rUpdate['md5'] . '" > /dev/null 2>&1 &';
				shell_exec($rCommand);
				exit(1);
			}
			else {
				exit(-1);
			}
		}
		else {
			exit(0);
		}
	case 'post-update':
		if (XCMS::$rServers[SERVER_ID]['is_main'] && XCMS::$rSettings['auto_update_lbs']) {
			foreach (XCMS::$rServers as $rServer) {
				if ($rServer['enabled'] && ($rServer['status'] == 1) && ((time() - $rServer['last_check_ago']) <= 180) && !$rServer['is_main']) {
					$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rServer['id'], time(), json_encode(['action' => 'update']));
				}
			}
		}

		$db->query('UPDATE `servers` SET `status` = 1, `xcms_version` = ?, `xcms_revision` = ? WHERE `id` = ?;', XCMS_VERSION, XCMS_REVISION, SERVER_ID);

		if (!XCMS::$rServers[SERVER_ID]['is_main']) {
			if (file_exists('/etc/init.d/xcms')) {
				unlink('/etc/init.d/xcms');
			}
		}

		foreach (['http', 'https'] as $rType) {
			$rPortConfig = file_get_contents(XCMS_HOME . 'bin/nginx/ports/' . $rType . '.conf');

			if (stripos($rPortConfig, ' reuseport') !== false) {
				file_put_contents(XCMS_HOME . 'bin/nginx/ports/' . $rType . '.conf', str_replace(' reuseport', '', $rPortConfig));
			}
		}

		exec('sudo chown -R xcms:xcms ' . XCMS_HOME);
		exec('sudo systemctl daemon-reload');
		exec('sudo echo \'net.ipv4.ip_unprivileged_port_start=0\' > /etc/sysctl.d/50-allports-nonroot.conf && sudo sysctl --system');
		exec('sudo ' . XCMS_HOME . 'status');
		break;
	}
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

set_time_limit(0);
if (!@$argc || (count($argv) != 2)) {
	exit(0);
}

if (Xcms\Functions::getLicense()[9] == 1) {
	exit('Not supported in Trial Mode.' . "\n");
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
$rCommand = $argv[1];
loadcli();

?>