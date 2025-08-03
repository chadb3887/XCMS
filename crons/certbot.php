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
	global $rCheck;

	if (!$rCheck) {
		if (XCMS::$rSettings['auto_send_logs']) {
			XCMS::submitPanelLogs();
		}

		$rCertInfo = XCMS::getCertificateInfo();
		if (XCMS::$rServers[SERVER_ID]['enable_https'] && $rCertInfo) {
			if (($rCertInfo['expiration'] - time()) < 604800) {
				echo 'Certificate due for renewal.' . "\n";
				$rData = [
					'action' => 'certbot_generate',
					'domain' => []
				];

				foreach (explode(',', XCMS::$rServers[SERVER_ID]['domain_name']) as $rDomain) {
					if (!filter_var($rDomain, FILTER_VALIDATE_IP)) {
						$rData['domain'][] = $rDomain;
					}
				}

				if (0 < count($rData['domain'])) {
					$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', SERVER_ID, time(), json_encode($rData));
				}
			}
			else {
				echo 'Certificate valid, not due for renewal.' . "\n";
			}
		}
	}

	$db->query('SELECT `certbot_ssl` FROM `servers` WHERE `id` = ?;', SERVER_ID);
	$rDBCertInfo = json_decode($db->get_row()['certbot_ssl'], true);
	$rLines = explode("\n", file_get_contents(XCMS_HOME . 'bin/nginx/conf/ssl.conf'));

	foreach ($rLines as $rLine) {
		if (explode(' ', $rLine)[0] == 'ssl_certificate') {
			$rCertificate = explode(';', explode(' ', $rLine)[1])[0];

			if ($rCertificate != 'server.crt') {
				$rCertInfoFile = XCMS::getCertificateInfo($rCertificate);
				if (($rCertInfo['serial'] != $rCertInfoFile['serial']) || !XCMS::$rServers[SERVER_ID]['certbot_ssl'] || ($rDBCertInfo['serial'] != $rCertInfoFile['serial'])) {
					$db->query('UPDATE `servers` SET `certbot_ssl` = ? WHERE `id` = ?;', json_encode($rCertInfoFile), SERVER_ID);
					echo 'Updated ssl configuration in database' . "\n";
					$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rServer['id'], time(), json_encode(['action' => 'reload_nginx']));
				}
			}
			else if (XCMS::$rServers[SERVER_ID]['certbot_ssl']) {
				$rCertInfo = json_decode(XCMS::$rServers[SERVER_ID]['certbot_ssl'], true);

				if (file_exists($rCertInfo['path'] . '/fullchain.pem')) {
					$rCertificate = $rCertInfo['path'] . '/fullchain.pem';
					$rChain = $rCertInfo['path'] . '/chain.pem';
					$rPrivateKey = $rCertInfo['path'] . '/privkey.pem';
					$rSSLConfig = 'ssl_certificate ' . $rCertificate . ';' . "\n" . 'ssl_certificate_key ' . $rPrivateKey . ';' . "\n" . 'ssl_trusted_certificate ' . $rChain . ';' . "\n" . 'ssl_protocols TLSv1.2 TLSv1.3;' . "\n" . 'ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;' . "\n" . 'ssl_prefer_server_ciphers off;' . "\n" . 'ssl_ecdh_curve auto;' . "\n" . 'ssl_session_timeout 10m;' . "\n" . 'ssl_session_cache shared:MozSSL:10m;' . "\n" . 'ssl_session_tickets off;';
					file_put_contents(BIN_PATH . 'nginx/conf/ssl.conf', $rSSLConfig);
					echo 'Fixed ssl configuration file' . "\n";
					$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', SERVER_ID, time(), json_encode(['action' => 'reload_nginx']));
				}
			}
		}
	}
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

if (!@$argc) {
	exit(0);
}

$rCheck = false;

if (count($argv) == 2) {
	$rCheck = true;
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
loadcron();

?>