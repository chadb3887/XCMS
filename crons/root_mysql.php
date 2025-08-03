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

function inArray($needles, $haystack)
{
	foreach ($needles as $needle) {
		if (stristr($haystack, $needle)) {
			return true;
		}
	}

	return false;
}

if (posix_getpwuid(posix_geteuid())['name'] != 'root') {
	exit('Please run as root!' . "\n");
}

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

require str_replace('\\', '/', dirname($argv[0])) . '/../includes/admin.php';
cli_set_process_title('XCMS[MysqlErrors]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rIgnoreErrors = ['innodb: page_cleaner', 'aborted connection', 'got an error reading communication packets', 'got packets out of order', 'got timeout reading communication packets'];

if (0 < XCMS::$rSettings['mysql_sleep_kill']) {
	$db->query('SELECT `id` FROM `INFORMATION_SCHEMA`.`PROCESSLIST` WHERE `COMMAND` = \'Sleep\' AND `TIME` > ?', (int) XCMS::$rSettings['mysql_sleep_kill']);

	foreach ($db->get_rows() as $rRow) {
		$db->query('KILL ?;', $rRow['id']);
		echo 'Killing ' . $rRow['id'];
	}
}

$db->query('SELECT MAX(`date`) AS `date` FROM `mysql_syslog`;');
$rMaxTime = (int) $db->get_row()['date'];
$rMaxAttempts = 10;
$rAttempts = [];
$db->query('SELECT `mysql_syslog`.`ip`, COUNT(`mysql_syslog`.`id`) AS `count`, `blocked_ips`.`id` AS `block_id` FROM `mysql_syslog` LEFT JOIN `blocked_ips` ON `blocked_ips`.`ip` = `mysql_syslog`.`ip` WHERE `type` = \'AUTH\' AND `mysql_syslog`.`date` > UNIX_TIMESTAMP() - 86400 GROUP BY `mysql_syslog`.`ip`;');

foreach ($db->get_rows() as $rRow) {
	$rAttempts[$rRow['ip']] = $rRow['count'];
	if (($rMaxAttempts < $rRow['count']) && !$rRow['block_id']) {
		if (!in_array($rRow['ip'], XCMS::getAllowedIPs())) {
			echo 'Blocking IP ' . $rRow['ip'] . "\n";
			API::blockIP(['ip' => $rRow['ip'], 'notes' => 'MYSQL BRUTEFORCE ATTACK']);
		}
	}
}

exec('sudo tail -n 1000 /var/log/syslog | grep mysqld', $rOutput, $rRetVal);

foreach ($rOutput as $rError) {
	$rStrip = trim(explode(']:', explode('mysqld[', $rError)[1])[1]);
	$rTime = strtotime(substr($rStrip, 0, 19));

	if ($rMaxTime < $rTime) {
		if (empty($rStrip) || inArray($rIgnoreErrors, $rStrip)) {
			continue;
		}

		if (stripos($rStrip, '[Note]') !== false) {
			$rNote = trim(explode('[Note]', $rStrip)[1]);
			$rType = 'NOTICE';
		}
		else if (stripos($rStrip, '[Warning]') !== false) {
			$rNote = trim(explode('[Warning]', $rStrip)[1]);
			$rType = 'WARNING';
		}
		else if (stripos($rStrip, '[Error]') !== false) {
			$rNote = trim(explode('[Error]', $rStrip)[1]);
			$rType = 'ERROR';
		}

		if ($rNote) {
			$rUsername = NULL;
			$rHost = NULL;
			$rDatabase = NULL;

			if (stripos($rNote, 'access denied for user') !== false) {
				$rUsername = trim(explode('\'', explode('user \'', $rNote)[1])[0]);
				$rHost = trim(explode('\'', explode('user \'', $rNote)[1])[2]);
				$rType = 'AUTH';
			}

			if (stripos($rNote, 'user:') !== false) {
				$rUsername = trim(explode('\'', explode('user: \'', $rNote)[1])[0]);
				$rHost = trim(explode('\'', explode('host: \'', $rNote)[1])[0]);
				$rDatabase = trim(explode('\'', explode('db: \'', $rNote)[1])[0]);
				$rType = 'ABORTED';
			}

			$db->query('INSERT INTO `mysql_syslog`(`type`,`error`,`username`,`ip`,`database`,`date`) VALUES(?,?,?,?,?,?)', $rType, $rNote, $rUsername, $rHost, $rDatabase, $rTime);
		}
	}
}

@unlink($rIdentifier);

?>