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

function getBlockedIPs()
{
	$rReturn = [];
	exec('sudo iptables -nL --line-numbers -t filter', $rLines);

	foreach ($rLines as $rLine) {
		$rLine = explode(' ', preg_replace('!\\s+!', ' ', $rLine));

		if ($rLine[1] == 'DROP') {
			$rReturn[] = $rLine[4];
		}
	}

	$rLines = '';
	exec('sudo ip6tables -nL --line-numbers -t filter', $rLines);

	foreach ($rLines as $rLine) {
		$rLine = explode(' ', preg_replace('!\\s+!', ' ', $rLine));

		if ($rLine[1] == 'DROP') {
			$rReturn[] = $rLine[3];
		}
	}

	return $rReturn;
}

function blockIP($rIP)
{
	if (filter_var($rIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		exec('sudo iptables -I INPUT -s ' . escapeshellcmd($rIP) . ' -j DROP');
	}
	else if (filter_var($rIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		exec('sudo ip6tables -I INPUT -s ' . escapeshellcmd($rIP) . ' -j DROP');
	}

	touch(FLOOD_TMP_PATH . 'block_' . $rIP);
}

function unblockIP($rIP)
{
	if (filter_var($rIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		exec('sudo iptables -D INPUT -s ' . escapeshellcmd($rIP) . ' -j DROP');
	}
	else if (filter_var($rIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		exec('sudo ip6tables -D INPUT -s ' . escapeshellcmd($rIP) . ' -j DROP');
	}

	if (file_exists(FLOOD_TMP_PATH . 'block_' . $rIP)) {
		unlink(FLOOD_TMP_PATH . 'block_' . $rIP);
	}
}

function flushIPs()
{
	exec('sudo iptables -F && sudo ip6tables -F');
	shell_exec('sudo rm ' . FLOOD_TMP_PATH . 'block_*');
}

function saveIPTables()
{
	exec('sudo iptables-save && sudo ip6tables-save');
}

function loadCron()
{
	global $db;
	global $rSaveIPTables;
	XCMS::$rServers = XCMS::getServers(true);
	XCMS::$rSettings = XCMS::getSettings(true);

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		if (empty(XCMS::$rSettings['redis_password'])) {
			$redis_password_raw = shell_exec('cat /home/xcms/bin/redis/redis.conf | grep pass');
			$redis_password = explode(' ', $redis_password_raw);
			$db->query('update settings set `redis_password` = \'' . $redis_password[1] . '\'');
			$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'CORRECTED\', \'Redis Password was updated as found missing or incorrect\', \'system\', \'localhost\', NULL, ?);', SERVER_ID, time());
		}
	}

	$db->query('SELECT `signal_id` FROM `signals` WHERE `server_id` = ? AND `custom_data` = \'{"action":"flush"}\' AND `cache` = 0;', SERVER_ID);

	if (0 < $db->num_rows()) {
		echo 'Flushing IP\'s...';
		fluships();
		saveiptables();
		$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'FLUSH\', \'Flushed blocked IP\\\'s from iptables.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
		$db->query('DELETE FROM `signals` WHERE `server_id` = ? AND `custom_data` = \'{"action":"flush"}\' AND `cache` = 0;', SERVER_ID);
	}
	else {
		$rActualBlocked = getblockedips();
		$rActualBlockedFlip = array_flip($rActualBlocked);
		$db->query('SELECT `ip` FROM `blocked_ips`;');
		$rBlocked = array_keys($db->get_rows(true, 'ip'));
		$rBlockedFlip = array_flip($rBlocked);
		$rAdd = $rDel = [];

		foreach (array_count_values($rActualBlocked) as $rIP => $rCount) {
			if (1 < $rCount) {
				echo $rCount . "\n";

				foreach (range(1, $rCount - 1) as $i) {
					$rDel[] = $rIP;
				}
			}
		}

		foreach ($rBlocked as $rIP) {
			if (!isset($rActualBlockedFlip[$rIP])) {
				$rAdd[] = $rIP;
			}
		}

		foreach ($rActualBlocked as $rIP) {
			if (!isset($rBlockedFlip[$rIP])) {
				$rDel[] = $rIP;
			}
		}

		if (0 < count($rDel)) {
			$rSaveIPTables = true;

			foreach ($rDel as $rIP) {
				echo 'Unblock IP: ' . $rIP . "\n";
				unblockip($rIP);
			}
		}

		if (0 < count($rAdd)) {
			$rSaveIPTables = true;

			foreach ($rAdd as $rIP) {
				echo 'Block IP: ' . $rIP . "\n";
				blockip($rIP);
			}
		}

		if ($rSaveIPTables) {
			saveiptables();
			$rSaveIPTables = false;
		}
	}

	$rReload = false;
	$rAllowedIPs = XCMS::getAllowedIPs();
	$rXCMSList = [];

	foreach ($rAllowedIPs as $rIP) {
		if (!empty($rIP) && filter_var($rIP, FILTER_VALIDATE_IP) && !in_array('set_real_ip_from ' . $rIP . ';', $rXCMSList)) {
			$rXCMSList[] = 'set_real_ip_from ' . $rIP . ';';
		}
	}

	$rXCMSList = trim(implode("\n", array_unique($rXCMSList)));
	$rCurrentList = trim(file_get_contents(BIN_PATH . 'nginx/conf/realip_xcms.conf')) ?: '';

	if ($rXCMSList != $rCurrentList) {
		echo 'Updating XCMS IP List...' . "\n";
		file_put_contents(BIN_PATH . 'nginx/conf/realip_xcms.conf', $rXCMSList);
		$rReload = true;
	}

	$rCurrentList = trim(file_get_contents(BIN_PATH . 'nginx/conf/realip_cloudflare.conf')) ?: '';

	if (XCMS::$rSettings['cloudflare']) {
		if (empty($rCurrentList)) {
			echo 'Enabling Cloudflare...' . "\n";
			file_put_contents(BIN_PATH . 'nginx/conf/realip_cloudflare.conf', 'set_real_ip_from 103.21.244.0/22;' . "\n" . 'set_real_ip_from 103.22.200.0/22;' . "\n" . 'set_real_ip_from 103.31.4.0/22;' . "\n" . 'set_real_ip_from 104.16.0.0/13;' . "\n" . 'set_real_ip_from 104.24.0.0/14;' . "\n" . 'set_real_ip_from 108.162.192.0/18;' . "\n" . 'set_real_ip_from 131.0.72.0/22;' . "\n" . 'set_real_ip_from 141.101.64.0/18;' . "\n" . 'set_real_ip_from 162.158.0.0/15;' . "\n" . 'set_real_ip_from 172.64.0.0/13;' . "\n" . 'set_real_ip_from 173.245.48.0/20;' . "\n" . 'set_real_ip_from 188.114.96.0/20;' . "\n" . 'set_real_ip_from 190.93.240.0/20;' . "\n" . 'set_real_ip_from 197.234.240.0/22;' . "\n" . 'set_real_ip_from 198.41.128.0/17;' . "\n" . 'set_real_ip_from 2400:cb00::/32;' . "\n" . 'set_real_ip_from 2606:4700::/32;' . "\n" . 'set_real_ip_from 2803:f800::/32;' . "\n" . 'set_real_ip_from 2405:b500::/32;' . "\n" . 'set_real_ip_from 2405:8100::/32;' . "\n" . 'set_real_ip_from 2c0f:f248::/32;' . "\n" . 'set_real_ip_from 2a06:98c0::/29;');
			$rReload = true;
		}
	}
	else if (!empty($rCurrentList)) {
		echo 'Disabling Cloudflare...' . "\n";
		file_put_contents(BIN_PATH . 'nginx/conf/realip_cloudflare.conf', '');
		$rReload = true;
	}

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		$rCurrentStatus = stripos(trim(file_get_contents(BIN_PATH . 'nginx/conf/gzip.conf')) ?: 'gzip off', 'gzip on') !== false;

		if (XCMS::$rServers[SERVER_ID]['enable_gzip']) {
			if (!$rCurrentStatus) {
				echo 'Enabling GZIP...' . "\n";
				file_put_contents(BIN_PATH . 'nginx/conf/gzip.conf', 'gzip on;' . "\n" . 'gzip_min_length 1000;' . "\n" . 'gzip_buffers 4 32k;' . "\n" . 'gzip_proxied any;' . "\n" . 'gzip_types application/json application/xml;' . "\n" . 'gzip_vary on;' . "\n" . 'gzip_disable "MSIE [1-6].(?!.*SV1)";');
				$rReload = true;
			}
		}
		else if ($rCurrentStatus) {
			echo 'Disabling GZIP...' . "\n";
			file_put_contents(BIN_PATH . 'nginx/conf/gzip.conf', 'gzip off;');
			$rReload = true;
		}
	}

	if (0 < XCMS::$rServers[SERVER_ID]['limit_requests']) {
		$rLimitConf = 'limit_req_zone global zone=two:10m rate=' . (int) XCMS::$rServers[SERVER_ID]['limit_requests'] . 'r/s;';
	}
	else {
		$rLimitConf = '';
	}

	$rCurrentConf = trim(file_get_contents(BIN_PATH . 'nginx/conf/limit.conf')) ?: '';

	if ($rLimitConf != $rCurrentConf) {
		echo 'Updating rate limit...' . "\n";
		file_put_contents(BIN_PATH . 'nginx/conf/limit.conf', $rLimitConf);
		$rReload = true;
	}

	if (0 < XCMS::$rServers[SERVER_ID]['limit_requests']) {
		$rLimitConf = 'limit_req zone=two burst=' . (int) XCMS::$rServers[SERVER_ID]['limit_burst'] . ';';
	}
	else {
		$rLimitConf = '';
	}

	$rCurrentConf = trim(file_get_contents(BIN_PATH . 'nginx/conf/limit_queue.conf')) ?: '';

	if ($rLimitConf != $rCurrentConf) {
		echo 'Updating rate limit queue...' . "\n";
		file_put_contents(BIN_PATH . 'nginx/conf/limit_queue.conf', $rLimitConf);
		$rReload = true;
	}

	if ($rReload) {
		shell_exec('sudo ' . BIN_PATH . 'nginx/sbin/nginx -s reload');
	}

	if (XCMS::$rSettings['restart_php_fpm']) {
		$rPHP = $rNginx = 0;
		exec('ps -fp $(pgrep -u xcms)', $rOutput, $rReturnVar);

		foreach ($rOutput as $rProcess) {
			$rSplit = explode(' ', preg_replace('!\\s+!', ' ', trim($rProcess)));
			if (($rSplit[8] == 'php-fpm:') && ($rSplit[9] == 'master')) {
				$rPHP++;
			}
			if (($rSplit[8] == 'nginx:') && ($rSplit[9] == 'master')) {
				$rNginx++;
			}
		}

		if (0 < $rNginx) {
			if ($rPHP == 0) {
				echo 'PHP-FPM ERROR - Restarting...';
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'PHP-FPM\', \'Restarted PHP-FPM instances due to a suspected crash.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo systemctl stop xcms');
				shell_exec('sudo systemctl start xcms');
				exit();
			}
		}

		$rHandle = curl_init('http://127.0.0.1:' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/init');
		curl_setopt($rHandle, CURLOPT_RETURNTRANSFER, true);
		$rResponse = curl_exec($rHandle);
		$rCode = curl_getinfo($rHandle, CURLINFO_HTTP_CODE);

		if (in_array($rCode, [500, 502])) {
			echo $rCode . ' ERROR - Restarting...';
			$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'PHP-FPM\', \'Restarted services due to ' . $rCode . ' error.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
			shell_exec('sudo systemctl stop xcms');
			shell_exec('sudo systemctl start xcms');
			exit();
		}

		curl_close($rHandle);
	}

	if (!$db->query('SELECT `signal_id`, `custom_data` FROM `signals` WHERE `server_id` = ? AND `custom_data` <> \'\' AND `cache` = 0 ORDER BY signal_id ASC;', SERVER_ID)) {
		exit();
	}

	$rRows = $db->get_rows();
	$rCheck = ['mag' => true, 'php' => true, 'services' => true, 'ports' => true, 'ramdisk' => true];

	foreach ($rRows as $rRow) {
		$rData = json_decode($rRow['custom_data'], true);

		switch ($rData['action']) {
		case 'disable_ramdisk':
		case 'enable_ramdisk':
			$rCheck['ramdisk'] = false;
			break;
		case 'enable_ministra':
		case 'disable_ministra':
			$rCheck['mag'] = false;
			break;
		case 'set_services':
			$rCheck['services'] = false;
			break;
		case 'set_port':
			$rCheck['ports'] = false;
			break;
		}
	}

	if ($rCheck['mag']) {
		if (XCMS::$rSettings['mag_legacy_redirect']) {
			if (!file_exists(XCMS_HOME . 'www/c')) {
				array_unshift($rRows, ['custom_data' => json_encode(['action' => 'enable_ministra'])]);
			}
		}
		else if (file_exists(XCMS_HOME . 'www/c')) {
			array_unshift($rRows, ['custom_data' => json_encode(['action' => 'disable_ministra'])]);
		}
	}

	if ($rCheck['services']) {
		$rCurServices = 0;
		$rStartScript = explode("\n", file_get_contents(XCMS_HOME . 'bin/daemons.sh'));

		foreach ($rStartScript as $rLine) {
			if (explode(' ', $rLine)[0] == 'start-stop-daemon') {
				$rCurServices++;
			}
		}

		if ($rCurServices != XCMS::$rServers[SERVER_ID]['total_services']) {
			array_unshift($rRows, ['custom_data' => json_encode(['action' => 'set_services', 'count' => XCMS::$rServers[SERVER_ID]['total_services'], 'reload' => true])]);
		}
	}

	if ($rCheck['ports']) {
		$rListen = $rPorts = [
			'http'  => [],
			'https' => []
		];

		foreach (array_merge([(int) XCMS::$rServers[SERVER_ID]['http_broadcast_port']], explode(',', XCMS::$rServers[SERVER_ID]['http_ports_add'])) as $rPort) {
			if (is_numeric($rPort) && (0 < $rPort) && ($rPort <= 65535)) {
				$rListen['http'][] = 'listen ' . (int) $rPort . ';';
				$rPorts['http'][] = (int) $rPort;
			}
		}

		if (XCMS::$rServers[SERVER_ID]['enable_https']) {
			foreach (array_merge([(int) XCMS::$rServers[SERVER_ID]['https_broadcast_port']], explode(',', XCMS::$rServers[SERVER_ID]['https_ports_add'])) as $rPort) {
				if (is_numeric($rPort) && (0 < $rPort) && ($rPort <= 65535)) {
					$rListen['https'][] = 'listen ' . (int) $rPort . ' ssl;';
					$rPorts['https'][] = (int) $rPort;
				}
			}
		}

		if (trim(implode(' ', $rListen['http'])) != trim(file_get_contents(XCMS_HOME . 'bin/nginx/conf/ports/http.conf'))) {
			array_unshift($rRows, ['custom_data' => json_encode(['action' => 'set_port', 'type' => 0, 'ports' => $rPorts['http'], 'reload' => true])]);
			$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'UPDATED\', \'Nginx HTTP Ports Updated\', \'system\', \'localhost\', NULL, ?);', SERVER_ID, time());
		}

		if (XCMS::$rServers[SERVER_ID]['enable_https']) {
			if (trim(implode(' ', $rListen['https'])) != trim(file_get_contents(XCMS_HOME . 'bin/nginx/conf/ports/https.conf'))) {
				array_unshift($rRows, ['custom_data' => json_encode(['action' => 'set_port', 'type' => 1, 'ports' => $rPorts['https'], 'reload' => true])]);
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'UPDATED\', \'Nginx HTTPs Ports Updated\', \'system\', \'localhost\', NULL, ?);', SERVER_ID, time());
			}
		}

		if (XCMS::$rServers[SERVER_ID]['enable_rtmp']) {
			if (trim(file_get_contents(XCMS_HOME . 'bin/nginx_rtmp/conf/port.conf')) != 'listen ' . (int) XCMS::$rServers[SERVER_ID]['rtmp_port'] . ';') {
				array_unshift($rRows, ['custom_data' => json_encode([
					'action' => 'set_port',
					'type'   => 2,
					'ports'  => [(int) XCMS::$rServers[SERVER_ID]['rtmp_port']],
					'reload' => true
				])]);
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'UPDATED\', \'Nginx RTMP Ports Updated\', \'system\', \'localhost\', NULL, ?);', SERVER_ID, time());
			}
		}
	}

	if ($rCheck['ramdisk']) {
		$rMounted = false;
		exec('df -h', $rLines);
		array_shift($rLines);

		foreach ($rLines as $rLine) {
			$rSplit = explode(' ', preg_replace('!\\s+!', ' ', trim($rLine)));

			if (implode(' ', array_slice($rSplit, 5, count($rSplit) - 5)) == rtrim(STREAMS_PATH, '/')) {
				$rMounted = true;
				break;
			}
		}

		if (XCMS::$rServers[SERVER_ID]['use_disk']) {
			if ($rMounted) {
				array_unshift($rRows, ['custom_data' => json_encode(['action' => 'disable_ramdisk'])]);
			}
		}
		else if (!$rMounted) {
			array_unshift($rRows, ['custom_data' => json_encode(['action' => 'enable_ramdisk'])]);
		}
	}

	if (file_exists(TMP_PATH . 'crontab')) {
		exec('crontab -u xcms -l', $rCrons);
		$rCurrentCron = trim(implode("\n", $rCrons));
		$db->query('SELECT * FROM `crontab` WHERE `enabled` = 1;');

		foreach ($db->get_rows() as $rRow) {
			$rFullPath = CRON_PATH . $rRow['filename'];
			if ((pathinfo($rFullPath, PATHINFO_EXTENSION) == 'php') && file_exists($rFullPath)) {
				$rJobs[] = $rRow['time'] . ' ' . PHP_BIN . ' ' . $rFullPath . ' # XCMS';
			}
		}

		$rActualCron = trim(implode("\n", $rJobs));

		if ($rCurrentCron != $rActualCron) {
			echo 'Updating Crons...' . "\n";
			unlink(TMP_PATH . 'crontab');
		}
	}

	if (file_exists(CONFIG_PATH . 'sysctl.on')) {
		if (!exec('cat /etc/sysctl.conf | grep XCMS')) {
			echo 'Sysctl missing! Writing it.' . "\n";
			exec('sudo modprobe ip_conntrack');
			file_put_contents('/etc/sysctl.conf', implode(PHP_EOL, ['# XCMS', '', 'net.core.somaxconn = 655350', 'net.ipv4.route.flush=1', 'net.ipv4.tcp_no_metrics_save=1', 'net.ipv4.tcp_moderate_rcvbuf = 1', 'fs.file-max = 6815744', 'fs.aio-max-nr = 6815744', 'fs.nr_open = 6815744', 'net.ipv4.ip_local_port_range = 1024 65000', 'net.ipv4.tcp_sack = 1', 'net.ipv4.tcp_rmem = 10000000 10000000 10000000', 'net.ipv4.tcp_wmem = 10000000 10000000 10000000', 'net.ipv4.tcp_mem = 10000000 10000000 10000000', 'net.core.rmem_max = 524287', 'net.core.wmem_max = 524287', 'net.core.rmem_default = 524287', 'net.core.wmem_default = 524287', 'net.core.optmem_max = 524287', 'net.core.netdev_max_backlog = 300000', 'net.ipv4.tcp_max_syn_backlog = 300000', 'net.netfilter.nf_conntrack_max=1215196608', 'net.ipv4.tcp_window_scaling = 1', 'vm.max_map_count = 655300', 'net.ipv4.tcp_max_tw_buckets = 50000', 'net.ipv6.conf.all.disable_ipv6 = 1', 'net.ipv6.conf.default.disable_ipv6 = 1', 'net.ipv6.conf.lo.disable_ipv6 = 1', 'kernel.shmmax=134217728', 'kernel.shmall=134217728', 'vm.overcommit_memory = 1', 'net.ipv4.tcp_tw_reuse=1']));
			exec('sudo sysctl -p > /dev/null');
		}
	}

	if (0 < count($rRows)) {
		foreach ($rRows as $rRow) {
			$rData = json_decode($rRow['custom_data'], true);

			if ($rRow['signal_id']) {
				$db->query('DELETE FROM `signals` WHERE `signal_id` = ?;', $rRow['signal_id']);
			}

			switch ($rData['action']) {
			case 'reboot':
				echo 'Rebooting system...' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'REBOOT\', \'System rebooted on request.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				$db->close_mysql();
				shell_exec('sudo reboot');
				break;
			case 'restart_services':
				echo 'Restarting services...' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'RESTART\', \'XCMS services restarted on request.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo systemctl stop xcms');
				shell_exec('sudo systemctl start xcms');
				break;
			case 'stop_services':
				echo 'Stopping services...' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'STOP\', \'XCMS services stopped on request.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo systemctl stop xcms');
				break;
			case 'reload_nginx':
				echo 'Reloading nginx...' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'RELOAD\', \'NGINX services reloaded on request.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo ' . BIN_PATH . 'nginx_rtmp/sbin/nginx_rtmp -s reload');
				shell_exec('sudo ' . BIN_PATH . 'nginx/sbin/nginx -s reload');
				break;
			case 'disable_ramdisk':
				echo 'Disabling ramdisk...' . "\n";
				$rFstab = file_get_contents('/etc/fstab');
				$rOutput = [];

				foreach (explode("\n", $rFstab) as $rLine) {
					if (substr($rLine, 0, 31) == 'tmpfs /home/xcms/content/streams') {
						$rLine = '#' . $rLine;
					}

					$rOutput[] = $rLine;
				}

				file_put_contents('/etc/fstab', implode("\n", $rOutput));
				shell_exec('sudo umount -l ' . STREAMS_PATH);
				shell_exec('sudo chown -R xcms:xcms ' . STREAMS_PATH);
				break;
			case 'enable_ramdisk':
				echo 'Enabling ramdisk...' . "\n";
				$rFstab = file_get_contents('/etc/fstab');
				$rOutput = [];

				foreach (explode("\n", $rFstab) as $rLine) {
					if (substr($rLine, 0, 32) == '#tmpfs /home/xcms/content/streams') {
						$rLine = ltrim($rLine, '#');
					}

					$rOutput[] = $rLine;
				}

				file_put_contents('/etc/fstab', implode("\n", $rOutput));
				shell_exec('sudo mount ' . STREAMS_PATH);
				shell_exec('sudo chown -R xcms:xcms ' . STREAMS_PATH);
				break;
			case 'certbot_generate':
				echo 'Generating certbot certificate.' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'CERTBOT\', \'Attempting to generate certbot certificate on request.\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/certbot.php "' . base64_encode(json_encode($rData)) . '" 2>&1 &');
				break;
			case 'update_binaries':
				echo 'Updating binaries...' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'BINARIES\', \'Updating XCMS binaries from XCMS server...\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/binaries.php 2>&1 &');
				break;
			case 'update':
				echo 'Updating...' . "\n";
				$db->query('INSERT INTO `mysql_syslog`(`server_id`, `type`, `error`, `username`, `ip`, `database`, `date`) VALUES(?, \'UPDATE\', \'Updating XCMS...\', \'root\', \'localhost\', NULL, ?);', SERVER_ID, time());
				shell_exec('sudo ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/update.php "update" 2>&1 &');
				break 2;
			case 'enable_ministra':
				echo 'Enabling ministra /c...';
				shell_exec('sudo ln -sfn ' . XCMS_HOME . 'ministra ' . XCMS_HOME . 'www/c');
				shell_exec('sudo ln -sfn ' . XCMS_HOME . 'ministra/portal.php ' . XCMS_HOME . 'www/portal.php');
				break;
			case 'disable_ministra':
				echo 'Disabling ministra /c...';
				shell_exec('sudo rm ' . XCMS_HOME . 'www/c');
				shell_exec('sudo rm ' . XCMS_HOME . 'www/portal.php');
				break;
			case 'set_services':
				echo 'Setting PHP Services' . "\n";
				$rServices = (int) $rData['count'];

				if ($rData['reload']) {
					shell_exec('sudo systemctl stop xcms');
				}

				shell_exec('sudo rm ' . XCMS_HOME . 'bin/php/etc/*.conf');
				$rNewScript = '#! /bin/bash' . "\n";
				$rNewBalance = 'upstream php {' . "\n" . '    least_conn;' . "\n";
				$rTemplate = file_get_contents(XCMS_HOME . 'bin/php/etc/template');

				foreach (range(1, $rServices) as $i) {
					$rNewScript .= 'start-stop-daemon --start --quiet --pidfile ' . XCMS_HOME . 'bin/php/sockets/' . $i . '.pid --exec ' . XCMS_HOME . 'bin/php/sbin/php-fpm -- --daemonize --fpm-config ' . XCMS_HOME . 'bin/php/etc/' . $i . '.conf' . "\n";
					$rNewBalance .= '    server unix:' . XCMS_HOME . 'bin/php/sockets/' . $i . '.sock;' . "\n";
					file_put_contents(XCMS_HOME . 'bin/php/etc/' . $i . '.conf', str_replace('#PATH#', XCMS_HOME, str_replace('#ID#', $i, $rTemplate)));
				}

				file_put_contents(XCMS_HOME . 'bin/daemons.sh', $rNewScript);
				file_put_contents(XCMS_HOME . 'bin/nginx/conf/balance.conf', $rNewBalance . '}');
				shell_exec('sudo chown xcms:xcms ' . XCMS_HOME . 'bin/php/etc/*');

				if ($rData['reload']) {
					shell_exec('sudo systemctl start xcms');
				}

				break;
			case 'set_governor':
				$rNewGovernor = $rData['data'];
				if (!empty($rNewGovernor) && shell_exec('which cpufreq-info')) {
					$rGovernors = array_filter(explode(' ', trim(shell_exec('cpufreq-info -g'))));
					$rGovernor = explode(' ', trim(shell_exec('cpufreq-info -p')));
					if (($rNewGovernor != $rGovernor[2]) && in_array($rNewGovernor, $rGovernors)) {
						shell_exec('sudo bash -c \'for ((i=0;i<$(nproc);i++)); do cpufreq-set -c ' . $i . ' -g ' . $rNewGovernor . '; done\'');
						sleep(2);
						$rGovernor = explode(' ', trim(shell_exec('cpufreq-info -p')));
						$db->query('UPDATE `servers` SET `governor` = ? WHERE `id` = ?;', json_encode($rGovernor), SERVER_ID);
					}
				}

				break;
			case 'set_sysctl':
				$rNewConfig = $rData['data'];

				if (!empty($rNewConfig)) {
					$rSysCtl = file_get_contents('/etc/sysctl.conf');

					if ($rSysCtl != $rNewConfig) {
						shell_exec('sudo modprobe ip_conntrack > /dev/null');
						file_put_contents('/etc/sysctl.conf', $rNewConfig);
						shell_exec('sudo sysctl -p > /dev/null');
						$db->query('UPDATE `servers` SET `sysctl` = ? WHERE `id` = ?;', $rNewConfig, SERVER_ID);
					}
				}

				break;
			case 'set_port':
				echo 'Setting NGINX Port' . "\n";

				if ((int) $rData['type'] == 0) {
					$rListen = [];

					foreach ($rData['ports'] as $rPort) {
						if (is_numeric($rPort) && (80 <= $rPort) && ($rPort <= 65535)) {
							$rListen[] = 'listen ' . (int) $rPort . ';';
						}
					}

					file_put_contents(XCMS_HOME . 'bin/nginx/conf/ports/http.conf', implode(' ', $rListen));

					if (XCMS::$rServers[SERVER_ID]['enable_rtmp']) {
						file_put_contents(XCMS_HOME . 'bin/nginx_rtmp/conf/live.conf', 'on_play http://127.0.0.1:' . (int) $rData['ports'][0] . '/stream/rtmp; on_publish http://127.0.0.1:' . (int) $rData['ports'][0] . '/stream/rtmp; on_play_done http://127.0.0.1:' . (int) $rData['ports'][0] . '/stream/rtmp;');
					}

					if ($rData['reload']) {
						shell_exec('sudo ' . BIN_PATH . 'nginx/sbin/nginx -s reload');
					}
				}
				else if ((int) $rData['type'] == 1) {
					$rListen = [];

					foreach ($rData['ports'] as $rPort) {
						if (is_numeric($rPort) && (80 <= $rPort) && ($rPort <= 65535)) {
							$rListen[] = 'listen ' . (int) $rPort . ' ssl;';
						}
					}

					if (XCMS::$rServers[SERVER_ID]['enable_https']) {
						file_put_contents(XCMS_HOME . 'bin/nginx/conf/ports/https.conf', implode(' ', $rListen));
					}
					else {
						file_put_contents(XCMS_HOME . 'bin/nginx/conf/ports/https.conf', ' ');
					}

					if ($rData['reload']) {
						shell_exec('sudo ' . BIN_PATH . 'nginx/sbin/nginx -s reload');
					}
				}
				else if ((int) $rData['type'] == 2) {
					if (XCMS::$rServers[SERVER_ID]['enable_rtmp']) {
						file_put_contents(XCMS_HOME . 'bin/nginx_rtmp/conf/port.conf', 'listen ' . (int) $rData['ports'][0] . ';');
					}
					else {
						file_put_contents(XCMS_HOME . 'bin/nginx_rtmp/conf/port.conf', ' ');
					}

					if ($rData['reload']) {
						shell_exec('sudo ' . BIN_PATH . 'nginx_rtmp/sbin/nginx_rtmp -s reload');
					}
				}

				break;
			default:
			}
		}
	}

	$db->query('DELETE FROM `signals` WHERE LENGTH(`custom_data`) > 0 AND UNIX_TIMESTAMP() - `time` >= 86400;');
	$db->close_mysql();
}

function shutdown()
{
	global $db;
	global $rIdentifier;
	global $rSaveIPTables;

	if ($rSaveIPTables) {
		saveiptables();
	}

	if (is_object($db)) {
		$db->close_mysql();
	}

	@unlink($rIdentifier);
}

if (posix_getpwuid(posix_geteuid())['name'] != 'root') {
	exit('Please run as root!' . "\n");
}

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
shell_exec('sudo kill -9 `ps -ef | grep \'XCMSSignals\' | grep -v grep | awk \'{print $2}\'`;');
cli_set_process_title('XCMSSignals');
file_put_contents(CONFIG_PATH . 'signals.last', time());
$rSaveIPTables = false;
loadcron();

?>