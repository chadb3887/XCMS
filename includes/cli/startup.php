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

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

$rFixCron = false;

if (1 < count($argv)) {
	if ((int) $argv[1] == 1) {
		$rFixCron = true;
	}
}

define('XCMS_HOME', '/home/xcms/');
require XCMS_HOME . 'www/stream/init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(32767);

if (file_exists(XCMS_HOME . 'status')) {
	exec('sudo ' . XCMS_HOME . 'status 1');
}

if (filesize(XCMS_HOME . 'bin/daemons.sh') == 0) {
	echo 'Daemons corrupted! Regenerating...' . "\n";
	$rNewScript = '#! /bin/bash' . "\n";
	$rNewBalance = 'upstream php {' . "\n" . '    least_conn;' . "\n";
	$rTemplate = file_get_contents(XCMS_HOME . 'bin/php/etc/template');
	exec('rm -f ' . XCMS_HOME . 'bin/php/etc/*.conf');

	foreach (range(1, 4) as $i) {
		$rNewScript .= 'start-stop-daemon --start --quiet --pidfile ' . XCMS_HOME . 'bin/php/sockets/' . $i . '.pid --exec ' . XCMS_HOME . 'bin/php/sbin/php-fpm -- --daemonize --fpm-config ' . XCMS_HOME . 'bin/php/etc/' . $i . '.conf' . "\n";
		$rNewBalance .= '    server unix:' . XCMS_HOME . 'bin/php/sockets/' . $i . '.sock;' . "\n";
		file_put_contents(XCMS_HOME . 'bin/php/etc/' . $i . '.conf', str_replace('#PATH#', XCMS_HOME, str_replace('#ID#', $i, $rTemplate)));
	}

	$rNewBalance .= '}';
	file_put_contents(XCMS_HOME . 'bin/daemons.sh', $rNewScript);
	file_put_contents(XCMS_HOME . 'bin/nginx/conf/balance.conf', $rNewBalance);
}

if (posix_getpwuid(posix_geteuid())['name'] == 'root') {
	$rCrons = [];

	if (file_exists(CRON_PATH . 'root_signals.php')) {
		$rCrons[] = '* * * * * ' . PHP_BIN . ' ' . CRON_PATH . 'root_signals.php # XCMS';
	}

	if (file_exists(CRON_PATH . 'root_mysql.php')) {
		$rCrons[] = '* * * * * ' . PHP_BIN . ' ' . CRON_PATH . 'root_mysql.php # XCMS';
	}

	$rWrite = false;
	exec('sudo crontab -l', $rOutput);

	foreach ($rCrons as $rCron) {
		if (!in_array($rCron, $rOutput)) {
			$rOutput[] = $rCron;
			$rWrite = true;
		}
	}

	if ($rWrite) {
		$rCronFile = tempnam(TMP_PATH, 'crontab');
		file_put_contents($rCronFile, implode("\n", $rOutput) . "\n");
		exec('sudo chattr -i /var/spool/cron/crontabs/root');
		exec('sudo crontab -r');
		exec('sudo crontab ' . $rCronFile);
		exec('sudo chattr +i /var/spool/cron/crontabs/root');
		echo 'Crontab installed' . "\n";
	}
	else {
		echo 'Crontab already installed' . "\n";
	}

	if (!$rFixCron) {
		exec('sudo -u xcms ' . PHP_BIN . ' ' . CRON_PATH . 'cache.php 1', $rOutput);
		if (file_exists(CRON_PATH . 'cache_engine.php') && !file_exists(CACHE_TMP_PATH . 'cache_complete')) {
			echo 'Generating cache...' . "\n";
			exec('sudo -u xcms ' . PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php >/dev/null 2>/dev/null &');
		}
	}
}
else if (!$rFixCron) {
	exec(PHP_BIN . ' ' . CRON_PATH . 'cache.php 1');
	if (file_exists(CRON_PATH . 'cache_engine.php') && !file_exists(CACHE_TMP_PATH . 'cache_complete')) {
		echo 'Generating cache...' . "\n";
		exec(PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php >/dev/null 2>/dev/null &');
	}
}

echo "\n";

?>