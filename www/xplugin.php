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

function shutdown()
{
	global $db;
	global $rDeny;

	if ($rDeny) {
		XCMS::checkFlood();
	}

	if (is_object($db)) {
		$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
require 'init.php';
$rDeny = true;

if (XCMS::$rSettings['disable_enigma2']) {
	$rDeny = false;
	generateError('E2_DISABLED');
}

$rIP = $_SERVER['REMOTE_ADDR'];
$rUserAgent = trim($_SERVER['HTTP_USER_AGENT']);
if (!empty(XCMS::$rRequest['action']) && (XCMS::$rRequest['action'] == 'gen_mac') && !empty(XCMS::$rRequest['pversion'])) {
	$rDeny = false;

	if (XCMS::$rRequest['pversion'] != '0.0.1') {
		echo json_encode(strtoupper(implode(':', str_split(substr(md5(mt_rand()), 0, 12), 2))));
	}

	exit();
}

$db = new Database();
XCMS::$db = & $db;
if (!empty(XCMS::$rRequest['action']) && (XCMS::$rRequest['action'] == 'auth')) {
	$rMAC = (isset(XCMS::$rRequest['mac']) ? htmlentities(XCMS::$rRequest['mac']) : '');
	$rModemMAC = (isset(XCMS::$rRequest['mmac']) ? htmlentities(XCMS::$rRequest['mmac']) : '');
	$rLocalIP = (isset(XCMS::$rRequest['ip']) ? htmlentities(XCMS::$rRequest['ip']) : '');
	$rEnigmaVersion = (isset(XCMS::$rRequest['version']) ? htmlentities(XCMS::$rRequest['version']) : '');
	$rCPU = (isset(XCMS::$rRequest['type']) ? htmlentities(XCMS::$rRequest['type']) : '');
	$rPluginVersion = (isset(XCMS::$rRequest['pversion']) ? htmlentities(XCMS::$rRequest['pversion']) : '');
	$rLVersion = (isset(XCMS::$rRequest['lversion']) ? base64_decode(XCMS::$rRequest['lversion']) : '');
	$rDNS = (!empty(XCMS::$rRequest['dn']) ? htmlentities(XCMS::$rRequest['dn']) : '-');
	$rCMAC = (!empty(XCMS::$rRequest['cmac']) ? htmlentities(strtoupper(XCMS::$rRequest['cmac'])) : '');
	$rDetails = [];

	if ($rDevice = XCMS::getE2Info(['device_id' => NULL, 'mac' => strtoupper($rMAC)])) {
		$rDeny = false;

		if ($rDevice['enigma2']['lock_device'] == 1) {
			if (!empty($rDevice['enigma2']['modem_mac']) && ($rModemMAC !== $rDevice['enigma2']['modem_mac'])) {
				XCMS::checkBruteforce(NULL, strtoupper($rMAC));
				generateError('E2_DEVICE_LOCK_FAILED');
			}
		}

		$rToken = strtoupper(md5(uniqid(rand(), true)));
		$rTimeout = mt_rand(60, 70);
		$db->query('UPDATE `enigma2_devices` SET `original_mac` = ?,`dns` = ?,`key_auth` = ?,`lversion` = ?,`watchdog_timeout` = ?,`modem_mac` = ?,`local_ip` = ?,`public_ip` = ?,`enigma_version` = ?,`cpu` = ?,`version` = ?,`token` = ?,`last_updated` = ? WHERE `device_id` = ?', $rCMAC, $rDNS, $rUserAgent, $rLVersion, $rTimeout, $rModemMAC, $rLocalIP, $rIP, $rEnigmaVersion, $rCPU, $rPluginVersion, $rToken, time(), $rDevice['enigma2']['device_id']);
		$rDetails['details'] = [];
		$rDetails['details']['token'] = $rToken;
		$rDetails['details']['username'] = $rDevice['user_info']['username'];
		$rDetails['details']['password'] = $rDevice['user_info']['password'];
		$rDetails['details']['watchdog_seconds'] = $rTimeout;
		header('Content-Type: application/json');
		echo json_encode($rDetails);
		exit();
	}
	else {
		XCMS::checkBruteforce(NULL, strtoupper($rMAC));
		generateError('INVALID_CREDENTIALS');
	}
}

if (empty(XCMS::$rRequest['token'])) {
	generateError('E2_NO_TOKEN');
}

$rToken = XCMS::$rRequest['token'];
$db->query('SELECT * FROM enigma2_devices WHERE `token` = ? AND `public_ip` = ? AND `key_auth` = ? LIMIT 1;', $rToken, $rIP, $rUserAgent);

if ($db->num_rows() <= 0) {
	generateError('E2_TOKEN_DOESNT_MATCH');
}

$rDeny = false;
$rDeviceInfo = $db->get_row();

if (($rDeviceInfo['watchdog_timeout'] + 20) < (time() - $rDeviceInfo['last_updated'])) {
	generateError('E2_WATCHDOG_TIMEOUT');
}

$rPage = XCMS::$rRequest['page'] ?? '';

if (empty($rPage)) {
	$db->query('UPDATE `enigma2_devices` SET `last_updated` = ?,`rc` = ? WHERE `device_id` = ?;', time(), XCMS::$rRequest['rc'], $rDeviceInfo['device_id']);
	$db->query('SELECT * FROM `enigma2_actions` WHERE `device_id` = ?;', $rDeviceInfo['device_id']);
	$rResult = [];

	if (0 < $db->num_rows()) {
		$rFirst = $db->get_row();

		if ($rFirst['key'] == 'message') {
			$rResult['message'] = [];
			$rResult['message']['title'] = $rFirst['command2'];
			$rResult['message']['message'] = $rFirst['command'];
		}
		else if ($rFirst['key'] == 'ssh') {
			$rResult['ssh'] = $rFirst['command'];
		}
		else if ($rFirst['key'] == 'screen') {
			$rResult['screen'] = '1';
		}
		else if ($rFirst['key'] == 'reboot_gui') {
			$rResult['reboot_gui'] = 1;
		}
		else if ($rFirst['key'] == 'reboot') {
			$rResult['reboot'] = 1;
		}
		else if ($rFirst['key'] == 'update') {
			$rResult['update'] = $rFirst['command'];
		}
		else if ($rFirst['key'] == 'block_ssh') {
			$rResult['block_ssh'] = (int) $rFirst['type'];
		}
		else if ($rFirst['key'] == 'block_telnet') {
			$rResult['block_telnet'] = (int) $rFirst['type'];
		}
		else if ($rFirst['key'] == 'block_ftp') {
			$rResult['block_ftp'] = (int) $rFirst['type'];
		}
		else if ($rFirst['key'] == 'block_all') {
			$rResult['block_all'] = (int) $rFirst['type'];
		}
		else if ($rFirst['key'] == 'block_plugin') {
			$rResult['block_plugin'] = (int) $rFirst['type'];
		}

		$db->query('DELETE FROM `enigma2_actions` WHERE `id` = ?;', $rFirst['id']);
	}

	header('Content-Type: application/json');
	exit(json_encode(['valid' => true, 'data' => $rResult]));
}
else if ($rPage == 'file') {
	if (!empty($_FILES['f']['name'])) {
		if ($_FILES['f']['error'] == 0) {
			$rNewFileName = strtolower($_FILES['f']['tmp_name']);
			$rType = XCMS::$rRequest['t'];

			switch ($rType) {
			case 'screen':
				$rInfo = getimagesize($_FILES['f']['tmp_name']);
				if ($rInfo && ($rInfo[2] == 'IMAGETYPE_JPEG')) {
					move_uploaded_file($_FILES['f']['tmp_name'], E2_IMAGES_PATH . $rDeviceInfo['device_id'] . '_screen_' . time() . '_' . uniqid() . '.jpg');
				}

				break;
			}
		}
	}
}

?>