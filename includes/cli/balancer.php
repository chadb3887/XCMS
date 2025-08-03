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

function sendFile($rConn, $rPath, $rOutput, $rWarn = false)
{
	$rMD5 = md5_file($rPath);
	ssh2_scp_send($rConn, $rPath, $rOutput);
	$rOutMD5 = trim(explode(' ', runCommand($rConn, 'md5sum "' . $rOutput . '"')['output'])[0]);

	if ($rMD5 != $rOutMD5) {
		if ($rWarn) {
			echo 'Failed to write using SCP, reverting to SFTP transfer... This will be take significantly longer!' . "\n";
		}

		$rSFTP = ssh2_sftp($rConn);
		$rSuccess = true;
		$rStream = @fopen('ssh2.sftp://' . $rSFTP . $rOutput, 'wb');

		try {
			$rData = @file_get_contents($rPath);

			if (@fwrite($rStream, $rData) === false) {
				$rSuccess = false;
			}

			fclose($rStream);
		}
		catch (Exception $e) {
			$rSuccess = false;
			fclose($rStream);
		}

		return $rSuccess;
	}

	return true;
}

function runCommand($rConn, $rCommand)
{
	$rStream = ssh2_exec($rConn, $rCommand);
	$rError = ssh2_fetch_stream($rStream, SSH2_STREAM_STDERR);
	stream_set_blocking($rError, true);
	stream_set_blocking($rStream, true);
	return ['output' => stream_get_contents($rStream), 'error' => stream_get_contents($rError)];
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
if (!@$argc || ($argc < 6)) {
	exit(0);
}

$rServerID = (int) $argv[2];

if ($rServerID == 0) {
	exit();
}

shell_exec('kill -9 `ps -ef | grep \'XCMS Install\\[' . $rServerID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
set_time_limit(0);
cli_set_process_title('XCMS Install[' . $rServerID . ']');
register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
unlink(CACHE_TMP_PATH . 'servers');
XCMS::$rServers = XCMS::getServers();
$rType = (int) $argv[1];
if (($rType != 1) && (Xcms\Functions::getLicense()[9] == 1)) {
	exit('Not supported in Trial Mode.' . "\n");
}

$rPort = (int) $argv[3];
$rUsername = $argv[4];
$rPassword = $argv[5];
$rHTTPPort = (empty($argv[6]) ? 80 : (int) $argv[6]);
$rHTTPSPort = (empty($argv[7]) ? 443 : (int) $argv[7]);
$rUpdateSysctl = (empty($argv[8]) ? 0 : (int) $argv[8]);
$rPrivateIP = (empty($argv[9]) ? 0 : (int) $argv[9]);
$rParentIDs = (empty($argv[10]) ? [] : json_decode($argv[10], true));
$rSysCtl = '# XCMS' . PHP_EOL . PHP_EOL . 'net.ipv4.tcp_congestion_control = bbr' . PHP_EOL . 'net.core.default_qdisc = fq' . PHP_EOL . 'net.ipv4.tcp_rmem = 8192 87380 134217728' . PHP_EOL . 'net.ipv4.udp_rmem_min = 16384' . PHP_EOL . 'net.core.rmem_default = 262144' . PHP_EOL . 'net.core.rmem_max = 268435456' . PHP_EOL . 'net.ipv4.tcp_wmem = 8192 65536 134217728' . PHP_EOL . 'net.ipv4.udp_wmem_min = 16384' . PHP_EOL . 'net.core.wmem_default = 262144' . PHP_EOL . 'net.core.wmem_max = 268435456' . PHP_EOL . 'net.core.somaxconn = 1000000' . PHP_EOL . 'net.core.netdev_max_backlog = 250000' . PHP_EOL . 'net.core.optmem_max = 65535' . PHP_EOL . 'net.ipv4.tcp_max_tw_buckets = 1440000' . PHP_EOL . 'net.ipv4.tcp_max_orphans = 16384' . PHP_EOL . 'net.ipv4.ip_local_port_range = 2000 65000' . PHP_EOL . 'net.ipv4.tcp_no_metrics_save = 1' . PHP_EOL . 'net.ipv4.tcp_slow_start_after_idle = 0' . PHP_EOL . 'net.ipv4.tcp_fin_timeout = 15' . PHP_EOL . 'net.ipv4.tcp_keepalive_time = 300' . PHP_EOL . 'net.ipv4.tcp_keepalive_probes = 5' . PHP_EOL . 'net.ipv4.tcp_keepalive_intvl = 15' . PHP_EOL . 'fs.file-max=20970800' . PHP_EOL . 'fs.nr_open=20970800' . PHP_EOL . 'fs.aio-max-nr=20970800' . PHP_EOL . 'net.ipv4.tcp_timestamps = 1' . PHP_EOL . 'net.ipv4.tcp_window_scaling = 1' . PHP_EOL . 'net.ipv4.tcp_mtu_probing = 1' . PHP_EOL . 'net.ipv4.route.flush = 1' . PHP_EOL . 'net.ipv6.route.flush = 1';
$rInstallDir = BIN_PATH . 'install/';
$rFiles = ['lb' => 'loadbalancer.tar.gz', 'lb_update' => 'loadbalancer_update.tar.gz', 'proxy' => 'proxy.tar.gz'];
$rRemovePacks = ['apparmor'];

if ($rType == 1) {
	$rPackages = ['iproute2', 'net-tools', 'libcurl4', 'libxslt1-dev', 'libonig-dev', 'e2fsprogs', 'wget', 'sysstat', 'mcrypt', 'python3', 'certbot', 'iptables-persistent', 'libjpeg-dev', 'libpng-dev', 'php-ssh2', 'xz-utils', 'zip', 'unzip'];
	$rInstallFiles = [$rFiles['proxy']];
}
else if ($rType == 2) {
	$rPackages = ['cpufrequtils', 'iproute2', 'python', 'net-tools', 'dirmngr', 'gpg-agent', 'software-properties-common', 'libmaxminddb0', 'libmaxminddb-dev', 'mmdb-bin', 'libcurl4', 'libgeoip-dev', 'libxslt1-dev', 'libonig-dev', 'e2fsprogs', 'wget', 'sysstat', 'alsa-utils', 'v4l-utils', 'mcrypt', 'python3', 'certbot', 'iptables-persistent', 'libjpeg-dev', 'libpng-dev', 'php-ssh2', 'xz-utils', 'zip', 'unzip', 'dsniff'];
	$rInstallFiles = [$rFiles['lb']];
}
else if ($rType == 3) {
	$rPackages = ['cpufrequtils'];
	$rInstallFiles = [$rFiles['lb_update']];
}
else {
	$db->query('UPDATE `servers` SET `status` = 4 WHERE `id` = ?;', $rServerID);
	echo 'Invalid type specified!' . "\n";
	exit();
}

if ($rType == 1) {
	file_put_contents($rInstallDir . $rServerID . '.json', json_encode(['root_username' => $rUsername, 'root_password' => $rPassword, 'ssh_port' => $rPort, 'http_broadcast_port' => $rHTTPPort, 'https_broadcast_port' => $rHTTPSPort, 'parent_id' => $rParentIDs]));
}
else {
	file_put_contents($rInstallDir . $rServerID . '.json', json_encode(['root_username' => $rUsername, 'root_password' => $rPassword, 'ssh_port' => $rPort]));
}

$rHost = XCMS::$rServers[$rServerID]['server_ip'];
echo 'Connecting to ' . $rHost . ':' . $rPort . "\n";

if ($rConn = ssh2_connect($rHost, $rPort)) {
	if ($rUsername == 'root') {
		echo 'Connected! Authenticating as root user...' . "\n";
	}
	else {
		echo 'Connected! Authenticating as non-root user...' . "\n";
	}

	$rResult = @ssh2_auth_password($rConn, $rUsername, $rPassword);

	if ($rResult) {
		if (stripos(runcommand($rConn, 'cat /etc/passwd')['output'], 'xui')) {
			echo 'XUI Install Found ..... Disabling ... ' . "\n";
			runcommand($rConn, 'sudo systemctl disable xuione');
			runcommand($rConn, 'sudo systemctl stop xuione');
			runcommand($rConn, 'sudo killall -u xui');
			runcommand($rConn, '/home/xui/service stop');
			runcommand($rConn, 'crontab -r -u xui');
			runcommand($rConn, 'deluser xui');
			runcommand($rConn, 'sudo chattr -i /var/spool/cron/crontabs/root');
			runcommand($rConn, 'crontab -r -u root');
			runcommand($rConn, 'umount /home/xui/*');
			runcommand($rConn, 'mv /home/xui /home/oldxui');
			runcommand($rConn, 'killall nginx');
			runcommand($rConn, 'killall php-fpm');
			echo 'XUI Directory moved to /home/oldxui ..... ' . "\n";
		}

		if (stripos(runcommand($rConn, 'cat /etc/passwd')['output'], 'streamcreed')) {
			echo 'streamcreed Install Found ..... Disabling ... ' . "\n";
			runcommand($rConn, 'killall -u streamcreed');
			runcommand($rConn, 'umount /home/streamcreed/*');
			runcommand($rConn, 'mv /home/streamcreed /home/oldstreamcreed');
			runcommand($rConn, 'crontab -r -u streamcreed');
			runcommand($rConn, 'deluser streamcreed');
			runcommand($rConn, 'sudo chattr -i /var/spool/cron/crontabs/root');
			runcommand($rConn, 'crontab -r -u root');
			runcommand($rConn, 'killall nginx');
			runcommand($rConn, 'killall php-fpm');
			sleep(3);
			echo 'Streamcreed Directory moved to /home/oldstreamcreed ..... ' . "\n";
		}

		echo "\n" . 'Stopping any previous version of XCMS' . "\n";
		runcommand($rConn, 'sudo systemctl stop xcms');
		runcommand($rConn, 'sudo killall -9 -u xcms');
		echo "\n" . 'Updating system' . "\n";
		runcommand($rConn, 'sudo rm /var/lib/dpkg/lock-frontend && sudo rm /var/cache/apt/archives/lock && sudo rm /var/lib/dpkg/lock');

		if ($rType == 2) {
			runcommand($rConn, 'sudo add-apt-repository -y ppa:maxmind/ppa');
		}

		runcommand($rConn, 'sudo apt-get update');

		foreach ($rPackages as $rPackage) {
			echo 'Installing package: ' . $rPackage . "\n";
			runcommand($rConn, 'sudo DEBIAN_FRONTEND=noninteractive apt-get -yq install ' . $rPackage);
		}

		foreach ($rRemovePacks as $rRemovePack) {
			echo 'Removing package: ' . $rRemovePack . "\n";
			runcommand($rConn, 'sudo DEBIAN_FRONTEND=noninteractive apt-get -y remove ' . $rPackage);
		}

		echo 'Cleaning up packages' . "\n";
		runcommand($rConn, 'sudo DEBIAN_FRONTEND=noninteractive apt-get -y autoremove');

		if (in_array($rType, [1, 2])) {
			echo 'Creating XCMS system user' . "\n";
			runcommand($rConn, 'sudo adduser --system --shell /bin/false --group --disabled-login xcms');
			runcommand($rConn, 'sudo mkdir ' . XCMS_HOME);
			runcommand($rConn, 'sudo rm -rf ' . BIN_PATH);
		}

		$i = 0;

		foreach ($rInstallFiles as $rFile) {
			$i++;
			echo 'Transferring compressed system files (' . $i . ' of ' . count($rInstallFiles) . ')' . "\n";

			if (sendfile($rConn, $rInstallDir . $rFile, '/tmp/' . $rFile, true)) {
				echo 'Extracting to directory' . "\n";
				$rRet = runcommand($rConn, 'sudo rm -rf ' . XCMS_HOME . 'status');
				$rRet = runcommand($rConn, 'sudo tar -zxvf "/tmp/' . $rFile . '" -C "' . XCMS_HOME . '"');

				if (!file_exists(XCMS_HOME . 'status')) {
					$db->query('UPDATE `servers` SET `status` = 4 WHERE `id` = ?;', $rServerID);
					echo 'Failed to extract files! Exiting' . "\n";
					exit();
				}
			}
			else {
				$db->query('UPDATE `servers` SET `status` = 4 WHERE `id` = ?;', $rServerID);
				echo 'Invalid MD5 checksum! Exiting' . "\n";
				exit();
			}

			runcommand($rConn, 'sudo rm -f "/tmp/' . $rFile . '.tar.gz"');
		}

		if (in_array($rType, [2, 3])) {
			if (stripos(runcommand($rConn, 'sudo cat /etc/fstab')['output'], STREAMS_PATH) === false) {
				echo 'Adding ramdisk mounts' . "\n";
				runcommand($rConn, 'sudo echo "tmpfs ' . STREAMS_PATH . ' tmpfs defaults,noatime,nosuid,nodev,noexec,mode=1777,size=80% 0 0" >> /etc/fstab');
				runcommand($rConn, 'sudo echo "tmpfs ' . TMP_PATH . ' tmpfs defaults,noatime,nosuid,nodev,noexec,mode=1777,size=20% 0 0" >> /etc/fstab');
			}

			if (stripos(runcommand($rConn, 'sudo cat /etc/sysctl.conf')['output'], 'XCMS') === false) {
				if ($rUpdateSysctl) {
					echo 'Adding sysctl.conf' . "\n";
					runcommand($rConn, 'sudo modprobe ip_conntrack');
					file_put_contents(TMP_PATH . 'sysctl_' . $rServerID, $rSysCtl);
					sendfile($rConn, TMP_PATH . 'sysctl_' . $rServerID, '/etc/sysctl.conf');
					runcommand($rConn, 'sudo sysctl -p');
					runcommand($rConn, 'sudo touch ' . CONFIG_PATH . 'sysctl.on');
				}
				else {
					runcommand($rConn, 'sudo rm ' . CONFIG_PATH . 'sysctl.on');
				}
			}
			else if (!$rUpdateSysctl) {
				runcommand($rConn, 'sudo rm ' . CONFIG_PATH . 'sysctl.on');
			}
			else {
				runcommand($rConn, 'sudo touch ' . CONFIG_PATH . 'sysctl.on');
			}
		}

		echo 'Generating configuration file' . "\n";
		$rMasterConfig = parse_ini_file(CONFIG_PATH . 'config.ini');

		if ($rType == 1) {
			if ($rPrivateIP) {
				$rNewConfig = '; XCMS Configuration' . "\n" . '; -----------------' . "\n\n" . '[XCMS]' . "\n" . 'hostname    =   "' . XCMS::$rServers[SERVER_ID]['private_ip'] . '"' . "\n" . 'port        =   ' . (int) XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . "\n" . 'server_id   =   ' . $rServerID;
			}
			else {
				$rNewConfig = '; XCMS Configuration' . "\n" . '; -----------------' . "\n\n" . '[XCMS]' . "\n" . 'hostname    =   "' . XCMS::$rServers[SERVER_ID]['server_ip'] . '"' . "\n" . 'port        =   ' . (int) XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . "\n" . 'server_id   =   ' . $rServerID;
			}
		}
		else {
			$rNewConfig = '; XCMS Configuration' . "\n" . '; -----------------' . "\n" . '; Your username and password will be encrypted and' . "\n" . '; saved to the \'credentials\' file in this folder' . "\n" . '; automatically.' . "\n" . ';' . "\n" . '; To change your username or password, modify BOTH' . "\n" . '; below and XCMS will read and re-encrypt them.' . "\n\n" . '[XCMS]' . "\n" . 'hostname    =   "' . XCMS::$rServers[SERVER_ID]['server_ip'] . '"' . "\n" . 'database    =   "xcms"' . "\n" . 'port        =   ' . (int) XCMS::$rConfig['port'] . "\n" . 'server_id   =   ' . $rServerID . "\n" . 'is_lb       =   1' . "\n\n" . '[Encrypted]' . "\n" . 'username    =   ""' . "\n" . 'password    =   ""';
		}

		file_put_contents(TMP_PATH . 'config_' . $rServerID, $rNewConfig);
		sendfile($rConn, TMP_PATH . 'config_' . $rServerID, CONFIG_PATH . 'config.ini');
		echo 'Installing service' . "\n";
		runcommand($rConn, 'sudo rm /etc/systemd/system/xcms.service');
		$rSystemd = '[Unit]' . "\n" . 'SourcePath=/home/xcms/service' . "\n" . 'Description=XCMS Service' . "\n" . 'After=network.target' . "\n" . 'StartLimitIntervalSec=0' . "\n\n" . '[Service]' . "\n" . 'TasksMax=infinity' . "\n" . 'Type=simple' . "\n" . 'User=root' . "\n" . 'Restart=always' . "\n" . 'RestartSec=1' . "\n" . 'ExecStart=/bin/bash /home/xcms/service start' . "\n" . 'ExecRestart=/bin/bash /home/xcms/service restart' . "\n" . 'ExecStop=/bin/bash /home/xcms/service stop' . "\n" . 'LimitNOFILE=500000' . "\n" . '[Install]' . "\n" . 'WantedBy=multi-user.target';
		file_put_contents(TMP_PATH . 'systemd_' . $rServerID, $rSystemd);
		sendfile($rConn, TMP_PATH . 'systemd_' . $rServerID, '/etc/systemd/system/xcms.service');
		runcommand($rConn, 'sudo chmod +x /etc/systemd/system/xcms.service');
		runcommand($rConn, 'sudo rm /etc/init.d/xcms');
		runcommand($rConn, 'sudo systemctl daemon-reload');
		runcommand($rConn, 'sudo systemctl enable xcms');

		if ($rType == 1) {
			runcommand($rConn, 'sudo rm /home/xcms/bin/nginx/conf/servers/*.conf');

			foreach ($rParentIDs as $rParentID) {
				if ($rPrivateIP) {
					$rIP = XCMS::$rServers[$rParentID]['private_ip'] . ':' . XCMS::$rServers[$rParentID]['http_broadcast_port'];
				}
				else {
					$rIP = XCMS::$rServers[$rParentID]['server_ip'] . ':' . XCMS::$rServers[$rParentID]['http_broadcast_port'];
				}

				if (XCMS::$rServers[$rParentID]['is_main']) {
					$rConfigText = 'location / {' . "\n" . '    include options.conf;' . "\n" . '    proxy_pass http://' . $rIP . '$1;' . "\n" . '}';
				}
				else {
					$rKey = md5($rServerID . '_' . $rParentID . '_' . OPENSSL_EXTRA);
					$rConfigText = 'location ~/' . $rKey . '(.*)$ {' . "\n" . '    include options.conf;' . "\n" . '    proxy_pass http://' . $rIP . '$1;' . "\n" . '    proxy_set_header X-Token "' . $rKey . '";' . "\n" . '}';
				}

				$rTmpPath = TMP_PATH . md5(time() . $rKey . '.conf');
				file_put_contents($rTmpPath, $rConfigText);
				sendfile($rConn, $rTmpPath, '/home/xcms/bin/nginx/conf/servers/' . (int) $rParentID . '.conf');
			}

			runcommand($rConn, 'sudo echo "listen ' . $rHTTPPort . ';" > "/home/xcms/bin/nginx/conf/ports/http.conf"');
			runcommand($rConn, 'rm -rf /home/xcms/bin/nginx/conf/ports/https.conf');
			runcommand($rConn, 'sudo echo "listen ' . $rHTTPSPort . ' ssl;" > "/home/xcms/bin/nginx/conf/ports/https.conf"');
			runcommand($rConn, 'sudo chmod 0777 /home/xcms/bin');
		}
		else {
			sendfile($rConn, CONFIG_PATH . 'credentials', CONFIG_PATH . 'credentials');
			sendfile($rConn, XCMS_HOME . 'bin/nginx/conf/custom.conf', XCMS_HOME . 'bin/nginx/conf/custom.conf');
			sendfile($rConn, XCMS_HOME . 'bin/nginx/conf/realip_cdn.conf', XCMS_HOME . 'bin/nginx/conf/realip_cdn.conf');
			sendfile($rConn, XCMS_HOME . 'bin/nginx/conf/realip_cloudflare.conf', XCMS_HOME . 'bin/nginx/conf/realip_cloudflare.conf');
			sendfile($rConn, XCMS_HOME . 'bin/nginx/conf/realip_xcms.conf', XCMS_HOME . 'bin/nginx/conf/realip_xcms.conf');
			runcommand($rConn, 'touch ' . XCMS_HOME . 'bin/nginx/conf/geo.conf');
			runcommand($rConn, 'sudo echo "" > "/home/xcms/bin/nginx/conf/limit.conf"');
			runcommand($rConn, 'sudo echo "" > "/home/xcms/bin/nginx/conf/limit_queue.conf"');

			if ($rType == 2) {
				$rIP = '127.0.0.1:' . XCMS::$rServers[$rServerID]['http_broadcast_port'];

				if (XCMS::$rServers[$rServerID]['enable_https'] == 1) {
					echo 'Enabling HTTPs ' . "\n";
					runcommand($rConn, 'sudo echo "listen ' . $rHTTPSPort . ' ssl;" > "/home/xcms/bin/nginx/conf/ports/https.conf"');
				}
				else {
					echo 'Disabling HTTPs ' . "\n";
					runcommand($rConn, 'sudo echo \' \' > "/home/xcms/bin/nginx/conf/ports/https.conf"');
				}

				if (XCMS::$rServers[$rServerID]['enable_rtmp'] == 1) {
					echo 'Enabling RTMP ' . "\n";
					runcommand($rConn, 'sudo echo "on_play http://' . $rIP . '/stream/rtmp; on_publish http://' . $rIP . '/stream/rtmp; on_play_done http://' . $rIP . '/stream/rtmp;" > "/home/xcms/bin/nginx_rtmp/conf/live.conf"');
					runcommand($rConn, 'sudo echo \'listen ' . (int) XCMS::$rServers[$rServerID]['rtmp_port'] . ';\' > "/home/xcms/bin/nginx_rtmp/conf/port.conf"');
				}
				else {
					echo 'Disabling RTMPs ' . "\n";
					runcommand($rConn, 'sudo echo \' \' > /home/xcms/bin/nginx_rtmp/conf/port.conf');
				}

				$rServices = (int) runcommand($rConn, 'sudo cat /proc/cpuinfo | grep "^processor" | wc -l')['output'] ?: 4;
				runcommand($rConn, 'sudo rm ' . XCMS_HOME . 'bin/php/etc/*.conf');
				$rNewScript = '#! /bin/bash' . "\n";
				$rNewBalance = 'upstream php {' . "\n" . '    least_conn;' . "\n";
				$rTemplate = file_get_contents(XCMS_HOME . 'bin/php/etc/template');

				foreach (range(1, $rServices) as $i) {
					$rNewScript .= 'start-stop-daemon --start --quiet --pidfile ' . XCMS_HOME . 'bin/php/sockets/' . $i . '.pid --exec ' . XCMS_HOME . 'bin/php/sbin/php-fpm -- --daemonize --fpm-config ' . XCMS_HOME . 'bin/php/etc/' . $i . '.conf' . "\n";
					$rNewBalance .= '    server unix:' . XCMS_HOME . 'bin/php/sockets/' . $i . '.sock;' . "\n";
					$rTmpPath = TMP_PATH . md5(time() . $i . '.conf');
					file_put_contents($rTmpPath, str_replace('#PATH#', XCMS_HOME, str_replace('#ID#', $i, $rTemplate)));
					sendfile($rConn, $rTmpPath, XCMS_HOME . 'bin/php/etc/' . $i . '.conf');
				}

				$rNewBalance .= '}';
				$rTmpPath = TMP_PATH . md5(time() . 'daemons.sh');
				file_put_contents($rTmpPath, $rNewScript);
				sendfile($rConn, $rTmpPath, XCMS_HOME . 'bin/daemons.sh');
				$rTmpPath = TMP_PATH . md5(time() . 'balance.conf');
				file_put_contents($rTmpPath, $rNewBalance);
				sendfile($rConn, $rTmpPath, XCMS_HOME . 'bin/nginx/conf/balance.conf');
				runcommand($rConn, 'sudo chmod +x ' . XCMS_HOME . 'bin/daemons.sh');
			}
		}

		$rSystemConf = runcommand($rConn, 'sudo cat "/etc/systemd/system.conf"')['output'];

		if (strpos($rSystemConf, 'DefaultLimitNOFILE=1048576') === false) {
			runcommand($rConn, 'sudo echo "' . "\n" . 'DefaultLimitNOFILE=1048576" >> "/etc/systemd/system.conf"');
			runcommand($rConn, 'sudo echo "' . "\n" . 'DefaultLimitNOFILE=1048576" >> "/etc/systemd/user.conf"');
		}

		if (strpos($rSystemConf, 'nDefaultLimitNOFILESoft=1048576') === false) {
			runcommand($rConn, 'sudo echo "' . "\n" . 'DefaultLimitNOFILESoft=1048576" >> "/etc/systemd/system.conf"');
			runcommand($rConn, 'sudo echo "' . "\n" . 'DefaultLimitNOFILESoft=1048576" >> "/etc/systemd/user.conf"');
		}

		runcommand($rConn, 'sudo systemctl stop apparmor');
		runcommand($rConn, 'sudo systemctl disable apparmor');
		runcommand($rConn, 'sudo mount -a');
		runcommand($rConn, 'sudo echo \'net.ipv4.ip_unprivileged_port_start=0\' > /etc/sysctl.d/50-allports-nonroot.conf && sudo sysctl --system');
		sleep(3);
		runcommand($rConn, 'sudo chown -R xcms:xcms ' . XCMS_HOME . 'tmp');
		runcommand($rConn, 'sudo chown -R xcms:xcms ' . XCMS_HOME . 'content/streams');
		runcommand($rConn, 'sudo chown -R xcms:xcms ' . XCMS_HOME);
		Xcms\Functions::grantPrivileges($rHost);
		echo 'Installation complete! Starting XCMS' . "\n";
		runcommand($rConn, 'sudo service xcms restart');

		if ($rType == 2) {
			runcommand($rConn, 'sudo ' . XCMS_HOME . 'status 1');
			runcommand($rConn, 'sudo -u xcms ' . PHP_BIN . ' ' . CLI_PATH . 'startup.php');
			runcommand($rConn, 'sudo -u xcms ' . PHP_BIN . ' ' . CRON_PATH . 'servers.php');
		}
		else if ($rType == 3) {
			runcommand($rConn, 'sudo ' . PHP_BIN . ' ' . CLI_PATH . 'update.php "post-update"');
			runcommand($rConn, 'sudo ' . XCMS_HOME . 'status 1');
			runcommand($rConn, 'sudo -u xcms ' . PHP_BIN . ' ' . CLI_PATH . 'startup.php');
			runcommand($rConn, 'sudo -u xcms ' . PHP_BIN . ' ' . CRON_PATH . 'servers.php');
		}
		else {
			runcommand($rConn, 'sudo -u xcms ' . PHP_BIN . ' ' . INCLUDES_PATH . 'startup.php');
		}

		if (in_array($rType, [1, 2])) {
			$db->query('UPDATE `servers` SET `status` = 1, `http_broadcast_port` = ?, `https_broadcast_port` = ?, `total_services` = ? WHERE `id` = ?;', $rHTTPPort, $rHTTPSPort, $rServices, $rServerID);
		}
		else {
			$db->query('UPDATE `servers` SET `status` = 1 WHERE `id` = ?;', $rServerID);
		}

		unlink($rInstallDir . $rServerID . '.json');
	}
	else {
		$db->query('UPDATE `servers` SET `status` = 4 WHERE `id` = ?;', $rServerID);
		echo 'Failed to authenticate using credentials. Exiting' . "\n";
		exit();
	}
}
else {
	$db->query('UPDATE `servers` SET `status` = 4 WHERE `id` = ?;', $rServerID);
	echo 'Failed to connect to server. Exiting' . "\n";
	exit();
}

?>