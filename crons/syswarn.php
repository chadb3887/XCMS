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
	XCMS::$rSettings = XCMS::getSettings(true);
	$rServerList = XCMS::$rServers;

	if (XCMS::$rSettings['enable_telegram']) {
		foreach ($rServerList as $rServer) {
			$rWatchdog = json_decode($rServer['watchdog_data'], true);
			$rError = ['offline' => false, 'cpu' => false, 'memory' => false, 'disk' => false, 'clients' => false, 'output' => false, 'input' => false, 'offset' => false, 'protection' => false, 'streams' => false];
			if (!$rServer['server_online'] && $rServer['enabled'] && ($rServer['status'] == 1)) {
				shell_exec(XCMS_HOME . 'bin/php/bin/php ' . XCMS_HOME . 'includes/cli/telegram.php ' . base64_encode('Has Been offline for ' . XCMS::secondsToTime(time() - $rServer['last_check_ago'])) . ' ' . base64_encode($rServer['server_name']));
			}
			else if ($rServer['server_online']) {
				if (((int) XCMS::$rSettings['threshold_cpu'] <= $rWatchdog['cpu']) && in_array('CPU', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['cpu'] = true;
					$rSend = true;
				}
				if (((int) XCMS::$rSettings['threshold_mem'] < $rWatchdog['total_mem_used_percent']) && in_array('MEMORY', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['memory'] = true;
					$rSend = true;
				}
				if (($rServer['server_type'] == 0) && ($rServer['vod'] == 0) && (0 < $rWatchdog['total_disk_space']) && ((int) XCMS::$rSettings['threshold_disk'] <= (int) ((($rWatchdog['total_disk_space'] - $rWatchdog['free_disk_space']) / $rWatchdog['total_disk_space']) * 100)) && in_array('HDD', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['disk'] = true;
					$rSend = true;
				}
				if ((0 < $rServer['network_guaranteed_speed']) && (($rServer['network_guaranteed_speed'] * ((int) XCMS::$rSettings['threshold_network'] / 100)) <= $rWatchdog['bytes_sent'] / 125000) && in_array('OUTPUT', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['output'] = true;
					$rSend = true;
				}
				if ((0 < $rServer['network_guaranteed_speed']) && (($rServer['network_guaranteed_speed'] * ((int) XCMS::$rSettings['threshold_network'] / 100)) <= $rWatchdog['bytes_received'] / 125000) && in_array('INPUT', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['input'] = true;
					$rSend = true;
				}
				if ((0 < $rServer['total_clients']) && (($rServer['total_clients'] * ((int) XCMS::$rSettings['threshold_clients'] / 100)) <= $rTotalConnections) && in_array('CLIENTS', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['clients'] = true;
					$rSend = true;
				}
				if ((5 < $rServer['time_offset']) || (($rServer['time_offset'] < -5) && in_array('TIMEOFFSET', json_decode(XCMS::$rSettings['telegram_premisssions'])))) {
					$rError['offset'] = true;
					$rSend = true;
				}
				if (!$rServer['enable_protection'] && !$rServer['is_main'] && in_array('PROTECTION', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
					$rError['protection'] = true;
					$rSend = true;
				}
			}
			if ($rError['cpu'] || $rError['memory'] || $rError['disk'] || $rError['clients'] || $rError['output'] || $rError['input'] || $rError['offset'] || $rError['protection'] || $rError['streams'] || $rError['apt'] || $rServer['is_main']) {
				$rMsg = '';

				if ($rError['cpu']) {
					$rMsg .= '<pre>Abnormal CPU usage: ' . ceil($rWatchdog['cpu'] <= 100 ? $rWatchdog['cpu'] : 100) . '%</pre>';
				}

				if ($rError['memory']) {
					$rMsg .= '<pre>Memory usage is currently high at ' . ceil($rWatchdog['total_mem_used_percent'] <= 100 ? $rWatchdog['total_mem_used_percent'] : 100) . '%</pre>';
				}

				if ($rError['clients']) {
					$rMsg .= '<pre>Current connections is ' . number_format($rTotalConnections, 0) . ' / ' . number_format($rServer['total_clients'], 0) . '</pre>';
				}

				if ($rError['input']) {
					$rMsg .= '<pre>Network input is currently ' . number_format($rWatchdog['bytes_received'] / 125000, 0) . ' Mbps / ' . number_format($rServer['network_guaranteed_speed'], 0) . ' Mbps Your Max Port Speed is: ' . ($rServer['network_guaranteed_speed'] / 1000) . 'Mbps</pre>';
				}

				if ($rError['output']) {
					$rMsg .= '<pre>Network output is currently ' . number_format($rWatchdog['bytes_sent'] / 125000, 0) . ' Mbps / ' . number_format($rServer['network_guaranteed_speed'], 0) . ' Mbps Your Max Port Speed is: ' . ($rServer['network_guaranteed_speed'] / 1000) . 'Mbps</pre>';
				}

				if ($rError['offset']) {
					$rMsg .= '<pre>Server time is offset to main server time by ' . number_format(abs($rServer['time_offset']), 0) . '</pre>';
				}

				if ($rError['protection']) {
					$rMsg .= '<pre>Server Protection is DISABLED</pre>';
				}

				if ($rServer['is_main'] == 1) {
					if (in_array('MAIN', json_decode(XCMS::$rSettings['telegram_premisssions']))) {
						if (!file_exists(CONFIG_PATH . 'signals.last') || (600 < (time() - filemtime(CONFIG_PATH . 'signals.last')))) {
							$rMsg .= '<pre>Root cronjob has not run recently, please check root crontab or run /home/xcms/status</pre>';
							$rSend = true;
						}

						if (XCMS_VERSION < XCMS::$rSettings['update_version']) {
							$rMsg .= '<pre>There is a <b>NEW</b> Version of XCMS!!</pre>';
							$rSend = true;
						}
					}
				}

				if ($rSend) {
					shell_exec(XCMS_HOME . 'bin/php/bin/php ' . XCMS_HOME . 'includes/cli/telegram.php ' . base64_encode($rMsg) . ' ' . base64_encode($rServer['server_name']));
				}
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

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
define('XCMS_HOME', '/home/xcms/');
cli_set_process_title('XCMS[Warnings]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();

?>