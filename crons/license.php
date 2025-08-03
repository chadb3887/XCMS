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

function isRunning()
{
	$rNginx = 0;
	exec('ps -fp $(pgrep -u xcms)', $rOutput, $rReturnVar);

	foreach ($rOutput as $rProcess) {
		$rSplit = explode(' ', preg_replace('!\\s+!', ' ', trim($rProcess)));
		if (($rSplit[8] == 'nginx:') && ($rSplit[9] == 'master')) {
			$rNginx++;
		}
	}

	return 0 < $rNginx;
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

if (isrunning()) {
	$rConfig = parse_ini_string(file_get_contents('/home/xcms/config/config.ini'));
	if ($rConfig['license'] && (!isset($rConfig['is_lb']) || !$rConfig['is_lb'])) {
		$rPort = (int) explode(';', explode(' ', trim(explode('listen ', file_get_contents('/home/xcms/bin/nginx/conf/ports/http.conf'))[1]))[0])[0] ?: 80;
		$rLicenseReturn = Xcms\Functions::updateLicense($rPort);

		if ($rLicenseReturn['status']) {
			echo 'Updated XCMS License' . "\n";
		}
		else {
			if (file_exists('/home/xcms/config/license')) {
				shell_exec('rm -rf /home/xcms/config/license');
			}

			echo 'Failed to generate license! Error: ' . $rLicenseReturn['error'] . "\n";
			exit();
		}
	}

	if (Xcms\Functions::verifyLicense()) {
		$rData = Xcms\Functions::getLicense();
		echo 'License is valid, expires: ' . gmdate('Y-m-d', $rData[3]) . "\n";
		require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
		$rReissueInfo = Xcms\Functions::checkReissues();
		$rUpdate = json_decode(str_replace('</>', '', str_replace('<>', '', str_replace('&nbsp;', '', str_replace('div', '', str_replace('&lt;', '', str_replace('&gt;', '', file_get_contents('https://license2.xcms.live/update.json', false, stream_context_create([
			'http' => ['timeout' => 5]
		])))))))), true);
		if ((XCMS_VERSION < $rUpdate['version']) || (XCMS_REVISION < $rUpdate['revision'])) {
			$db->query('UPDATE `settings` SET `update_version` = ?, `reissues` = ?;', $rUpdate['version'], json_encode($rReissueInfo));
		}
		else {
			$db->query('UPDATE `settings` SET `update_version` = NULL, `update_data` = NULL, `reissues` = ?;', json_encode($rReissueInfo));
		}
	}
	else {
		echo 'License is invalid.' . "\n";
		exit();
	}
}

?>