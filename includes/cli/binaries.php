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
	global $rBaseURL;
	global $rPermissions;
	global $rBaseDir;

	if (shell_exec('which apparmor_status')) {
		exec('sudo apparmor_status', $rAppArmor);

		if (strtolower(trim($rAppArmor[0])) == 'apparmor module is loaded.') {
			exec('sudo systemctl is-active apparmor', $rStatus);

			if (strtolower(trim($rStatus[0])) == 'active') {
				echo 'AppArmor is loaded! Disabling...' . "\n";
				shell_exec('sudo systemctl stop apparmor');
				shell_exec('sudo systemctl disable apparmor');
			}
		}
	}

	$rPHPUpdated = $rUpdated = false;
	$rAPI = json_decode(file_get_contents($rBaseURL), true);

	if (is_array($rAPI)) {
		foreach ($rAPI['files'] as $rFile) {
			if (!file_exists($rFile['path']) || (md5_file($rFile['path']) != $rFile['md5'])) {
				echo $rFile['path'] . ' Needs Updating' . PHP_EOL;
				$rFolderPath = pathinfo($rFile['path'])['dirname'] . '/';

				if (!file_exists($rFolderPath)) {
					shell_exec('sudo mkdir -p "' . $rFolderPath . '"');
				}

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $rBaseDownloadURL . $rFile['md5']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
				curl_setopt($ch, CURLOPT_TIMEOUT, 300);
				$rData = curl_exec($ch);
				$rMD5 = md5($rData);

				if ($rMD5 == $rFile['md5']) {
					echo 'Updated binary: ' . $rFile['path'] . "\n";
					shell_exec('sudo rm -rf "' . $rFile['path'] . '"');
					file_put_contents($rFile['path'], $rData);
					shell_exec('sudo chown xcms:xcms "' . $rFile['path'] . '"');
					shell_exec('sudo chmod ' . $rPermissions . ' "' . $rFile['path'] . '"');
					$rUpdated = true;

					if (substr(basename($rFile['path']), 0, 3) == 'php') {
						$rPHPUpdated = true;
					}
				}
			}
		}
	}

	if ($rUpdated) {
		shell_exec('sudo chown -R xcms:xcms "' . $rBaseDir . '"');
	}

	if ($rPHPUpdated) {
		shell_exec('sudo chown -R xcms:xcms ' . BIN_PATH . 'php');
		shell_exec('sudo service xcms restart');
	}
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

if (posix_getpwuid(posix_geteuid())['name'] != 'root') {
	exit('Please run as root!' . "\n");
}

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
$rBaseDir = '/home/xcms/bin/';
$rBaseURL = 'https://license2.xcms.live/binaries';
$rBaseDownloadURL = 'https://xcms.live/files/';
$rPermissions = '0755';
loadcli();

?>