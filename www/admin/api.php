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

	if (is_object($db)) {
		$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
set_time_limit(0);
require '../init.php';
$rIP = XCMS::getUserIP();
if (!in_array($rIP, XCMS::getAllowedIPs()) && !in_array($rIP, XCMS::$rSettings['api_ips'])) {
	generate404();
}
if (!empty(XCMS::$rSettings['api_pass']) && (XCMS::$rRequest['api_pass'] != XCMS::$rSettings['api_pass'])) {
	generate404();
}

$db = new Database();
XCMS::$db = & $db;
$rAction = (!empty(XCMS::$rRequest['action']) ? XCMS::$rRequest['action'] : '');
$rSubAction = (!empty(XCMS::$rRequest['sub']) ? XCMS::$rRequest['sub'] : '');

switch ($rAction) {
case 'server':
	switch ($rSubAction) {
	case 'list':
		$rOutput = [];

		foreach (XCMS::$rServers as $rServerID => $rServerInfo) {
			$rOutput[] = ['id' => $rServerID, 'server_name' => $rServerInfo['server_name'], 'online' => $rServerInfo['server_online'], 'info' => json_decode($rServerInfo['server_hardware'], true)];
		}

		echo json_encode($rOutput);
		break;
	}

	break;
case 'vod':
	switch ($rSubAction) {
	case 'start':
		$rStreamIDs = array_map('intval', XCMS::$rRequest['stream_ids']);
		$rForce = XCMS::$rRequest['force'] ?: false;
		$rServers = (empty(XCMS::$rRequest['servers']) ? array_keys(XCMS::$rServers) : array_map('intval', XCMS::$rRequest['servers']));
		$rURLs = [];

		foreach ($rServers as $rServerID) {
			$rURLs[$rServerID] = [
				'url'      => XCMS::$rServers[$rServerID]['api_url_ip'] . '&action=vod',
				'postdata' => ['function' => $rSubAction, 'stream_ids' => $rStreamIDs, 'force' => $rForce]
			];
		}

		XCMS::getMultiCURL($rURLs);
		echo json_encode(['result' => true]);
		exit();
	case 'stop':
		$rStreamIDs = array_map('intval', XCMS::$rRequest['stream_ids']);
		$rServers = (empty(XCMS::$rRequest['servers']) ? array_keys(XCMS::$rServers) : array_map('intval', XCMS::$rRequest['servers']));
		$rURLs = [];

		foreach ($rServers as $rServerID) {
			$rURLs[$rServerID] = [
				'url'      => XCMS::$rServers[$rServerID]['api_url_ip'] . '&action=vod',
				'postdata' => ['function' => $rSubAction, 'stream_ids' => $rStreamIDs]
			];
		}

		XCMS::getMultiCURL($rURLs);
		echo json_encode(['result' => true]);
		exit();
	}

	break;
case 'stream':
	switch ($rSubAction) {
	case 'start':
		$rStreamIDs = array_map('intval', XCMS::$rRequest['stream_ids']);
		$rServers = (empty(XCMS::$rRequest['servers']) ? array_keys(XCMS::$rServers) : array_map('intval', XCMS::$rRequest['servers']));
		$rURLs = [];

		foreach ($rServers as $rServerID) {
			$rURLs[$rServerID] = [
				'url'      => XCMS::$rServers[$rServerID]['api_url_ip'] . '&action=stream',
				'postdata' => ['function' => $rSubAction, 'stream_ids' => $rStreamIDs]
			];
		}

		XCMS::getMultiCURL($rURLs);
		echo json_encode(['result' => true]);
		exit();
	case 'stop':
		$rStreamIDs = array_map('intval', XCMS::$rRequest['stream_ids']);
		$rServers = (empty(XCMS::$rRequest['servers']) ? array_keys(XCMS::$rServers) : array_map('intval', XCMS::$rRequest['servers']));
		$rURLs = [];

		foreach ($rServers as $rServerID) {
			$rURLs[$rServerID] = [
				'url'      => XCMS::$rServers[$rServerID]['api_url_ip'] . '&action=stream',
				'postdata' => ['function' => $rSubAction, 'stream_ids' => $rStreamIDs]
			];
		}

		XCMS::getMultiCURL($rURLs);
		echo json_encode(['result' => true]);
		exit();
	case 'list':
		$rOutput = [];
		$db->query('SELECT id,stream_display_name FROM `streams` WHERE type <> 2');

		foreach ($db->get_rows() as $rRow) {
			$rOutput[] = ['id' => $rRow['id'], 'stream_name' => $rRow['stream_display_name']];
		}

		echo json_encode($rOutput);
		break;
	case 'offline':
		$db->query('SELECT t1.stream_status,t1.server_id,t1.stream_id  FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.type <> 2 WHERE t1.stream_status <> 0');
		$rStreams = $db->get_rows(true, 'stream_id', false, 'server_id');
		$rOutput = [];

		foreach ($rStreams as $rStreamID => $rServers) {
			$rOutput[$rStreamID] = array_keys($rServers);
		}

		echo json_encode($rOutput);
		break;
	case 'online':
		$db->query('SELECT t1.stream_status,t1.server_id,t1.stream_id FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.type <> 2 WHERE t1.pid > 0 AND t1.stream_status = 0');
		$rStreams = $db->get_rows(true, 'stream_id', false, 'server_id');
		$rOutput = [];

		foreach ($rStreams as $rStreamID => $rServers) {
			$rOutput[$rStreamID] = array_keys($rServers);
		}

		echo json_encode($rOutput);
		break;
	}

	break;
case 'line':
	switch ($rSubAction) {
	case 'info':
		if (!empty(XCMS::$rRequest['username']) && !empty(XCMS::$rRequest['password'])) {
			$rUsername = XCMS::$rRequest['username'];
			$rPassword = XCMS::$rRequest['password'];
			$rUserInfo = XCMS::getUserInfo(false, $rUsername, $rPassword, true, true);

			if (!empty($rUserInfo)) {
				echo json_encode(['result' => true, 'user_info' => $rUserInfo]);
			}
			else {
				echo json_encode(['result' => false, 'error' => 'NOT EXISTS']);
			}
		}
		else {
			echo json_encode(['result' => false, 'error' => 'PARAMETER ERROR (user/pass)']);
		}

		break;
	}

	break;
case 'reg_user':
	switch ($rSubAction) {
	case 'list':
		$db->query('SELECT id,username,credits,group_id,group_name,last_login,date_registered,email,ip,status FROM `users` t1 INNER JOIN `users_groups` t2 ON t1.member_group_id = t2.group_id');
		$rResults = $db->get_rows();
		echo json_encode($rResults);
		break;
	}

	break;
}

?>