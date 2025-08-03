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

if (!defined('XCMS_HOME')) {
	define('XCMS_HOME', '/home/xcms/');
}

require_once XCMS_HOME . 'includes/admin.php';

if ($rMobile) {
	$rSettings['js_navigate'] = 0;
}

if (isset($_SESSION['reseller'])) {
	$rUserInfo = getRegisteredUser($_SESSION['reseller']);

	if (0 < strlen($rUserInfo['timezone'])) {
		date_default_timezone_set($rUserInfo['timezone']);
	}

	$rPermissions = array_merge(getPermissions($rUserInfo['member_group_id']), getGroupPermissions($rUserInfo['id']));
	$rUserInfo['reports'] = array_map('intval', array_merge([$rUserInfo['id']], $rPermissions['all_reports']));
	$rIP = getIP();
	$rIPMatch = ($rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $_SESSION['rip']), 0, -1)) == implode('.', array_slice(explode('.', $rIP), 0, -1)) : $rIP == $_SESSION['rip']);
	if (!$rUserInfo || !$rPermissions || !$rPermissions['is_reseller'] || (!$rIPMatch && $rSettings['ip_logout']) || (md5($rUserInfo['username'] . '||' . $rUserInfo['password']) != $_SESSION['rverify'])) {
		unset($rUserInfo, $rPermissions);
		destroySession('reseller');
		header('Location: ./index');
		exit();
	}
	else if (($rIP != $_SESSION['rip']) && !$rSettings['ip_logout']) {
		$_SESSION['rip'] = $rIP;
	}
}

if (isset(XCMS::$rRequest['status'])) {
	$_STATUS = (int) XCMS::$rRequest['status'];
	$rArgs = XCMS::$rRequest;
	unset($rArgs['status']);
	$_ARGS = setArgs($rArgs);
}

?>