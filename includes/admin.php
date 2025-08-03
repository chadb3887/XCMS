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

function getPageName()
{
	return strtolower(basename(get_included_files()[0], '.php'));
}

function sortArrayByArray($rArray, $rSort)
{
	if (empty($rArray) || empty($rSort)) {
		return [];
	}

	$rOrdered = [];

	foreach ($rSort as $rValue) {
		if (($rKey = array_search($rValue, $rArray)) !== false) {
			$rOrdered[] = $rValue;
			unset($rArray[$rKey]);
		}
	}

	return $rOrdered + $rArray;
}

function cleanValue($rValue)
{
	if ($rValue == '') {
		return '';
	}

	$rValue = str_replace('&#032;', ' ', stripslashes($rValue));
	$rValue = str_replace(["\r\n", "\n\r", "\r"], "\n", $rValue);
	$rValue = str_replace('<!--', '&#60;&#33;--', $rValue);
	$rValue = str_replace('-->', '--&#62;', $rValue);
	$rValue = str_ireplace('<script', '&#60;script', $rValue);
	$rValue = preg_replace('/&amp;#([0-9]+);/s', '&#\\1;', $rValue);
	$rValue = preg_replace('/&#(\\d+?)([^\\d;])/i', '&#\\1;\\2', $rValue);
	return trim($rValue);
}

function getStreamStats($rStreamID)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `streams_stats` WHERE `stream_id` = ?;', $rStreamID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['type']] = $rRow;
		}
	}

	foreach (['today', 'week', 'month', 'all'] as $rType) {
		if (!isset($rReturn[$rType])) {
			$rReturn[$rType] = ['rank' => 0, 'users' => 0, 'connections' => 0, 'time' => 0];
		}
	}

	return $rReturn;
}

function resetSTB($rID)
{
	global $db;
	$db->query('UPDATE `mag_devices` SET `ip` = \'\', `ver` = \'\', `image_version` = \'\', `stb_type` = \'\', `sn` = \'\', `device_id` = \'\', `device_id2` = \'\', `hw_version` = \'\', `token` = \'\' WHERE `mag_id` = ?;', $rID);
}

function formatUptime($rUptime)
{
	if (86400 <= $rUptime) {
		$rUptime = sprintf('%02dd %02dh %02dm', $rUptime / 86400, ($rUptime / 3600) % 24, ($rUptime / 60) % 60);
	}
	else {
		$rUptime = sprintf('%02dh %02dm %02ds', $rUptime / 3600, ($rUptime / 60) % 60, $rUptime % 60);
	}

	return $rUptime;
}

function getSettings()
{
	global $db;
	$db->query('SELECT * FROM `settings` LIMIT 1;');
	return $db->get_row();
}

function APIRequest($rData, $rTimeout = 5)
{
	ini_set('default_socket_timeout', $rTimeout);
	$rAPI = 'http://127.0.0.1:' . (int) XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/admin/api';

	if (!empty(XCMS::$rSettings['api_pass'])) {
		$rData['api_pass'] = XCMS::$rSettings['api_pass'];
	}

	$rPost = http_build_query($rData);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $rAPI);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $rPost);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $rTimeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $rTimeout);
	return curl_exec($ch);
}

function SystemAPIRequest($rServerID, $rData, $rTimeout = 5)
{
	ini_set('default_socket_timeout', $rTimeout);

	if (XCMS::$rServers[$rServerID]['server_online']) {
		$rAPI = 'http://' . XCMS::$rServers[(int) $rServerID]['server_ip'] . ':' . XCMS::$rServers[(int) $rServerID]['http_broadcast_port'] . '/api';
		$rData['password'] = XCMS::$rSettings['live_streaming_pass'];
		$rPost = http_build_query($rData);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $rAPI);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $rPost);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $rTimeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $rTimeout);
		return curl_exec($ch);
	}
	else {
		return NULL;
	}
}

function AsyncAPIRequest($rServerIDs, $rData)
{
	$rURLs = [];

	foreach ($rServerIDs as $rServerID) {
		if (XCMS::$rServers[$rServerID]['server_online']) {
			$rURLs[$rServerID] = ['url' => XCMS::$rServers[$rServerID]['api_url'], 'postdata' => $rData];
		}
	}

	XCMS::getMultiCURL($rURLs);
	return ['result' => true];
}

function changePort($rServerID, $rType, $rPorts, $rReload = false)
{
	global $db;
	$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rServerID, time(), json_encode(['action' => 'set_port', 'type' => (int) $rType, 'ports' => $rPorts, 'reload' => $rReload]));
}

function setServices($rServerID, $rNumServices, $rReload = true)
{
	global $db;
	$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rServerID, time(), json_encode(['action' => 'set_services', 'count' => (int) $rNumServices, 'reload' => $rReload]));
}

function setGovernor($rServerID, $rGovernor)
{
	global $db;
	$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rServerID, time(), json_encode(['action' => 'set_governor', 'data' => $rGovernor]));
}

function setSysctl($rServerID, $rSysCtl)
{
	global $db;
	$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`) VALUES(?, ?, ?);', $rServerID, time(), json_encode(['action' => 'set_sysctl', 'data' => $rSysCtl]));
}

function restoreImages()
{
	global $db;

	foreach (array_keys(XCMS::$rServers) as $rServerID) {
		if (XCMS::$rServers[$rServerID]['server_online']) {
			systemapirequest($rServerID, ['action' => 'restore_images']);
		}
	}

	return true;
}

function killWatchFolder()
{
	global $db;
	$db->query('SELECT DISTINCT(`server_id`) AS `server_id` FROM `watch_folders` WHERE `active` = 11 AND `type` <> \'plex\';');

	foreach ($db->get_rows() as $rRow) {
		if (XCMS::$rServers[$rRow['server_id']]['server_online']) {
			systemapirequest($rRow['server_id'], ['action' => 'kill_watch']);
		}
	}

	return true;
}

function killPlexSync()
{
	global $db;
	$db->query('SELECT DISTINCT(`server_id`) AS `server_id` FROM `watch_folders` WHERE `active` = 1 AND `type` = \'plex\';');

	foreach ($db->get_rows() as $rRow) {
		if (XCMS::$rServers[$rRow['server_id']]['server_online']) {
			systemapirequest($rRow['server_id'], ['action' => 'kill_plex']);
		}
	}

	return true;
}

function getPIDs($rServerID)
{
	$rReturn = [];
	$rProcesses = json_decode(systemapirequest($rServerID, ['action' => 'get_pids']), true);
	array_shift($rProcesses);

	foreach ($rProcesses as $rProcess) {
		$rSplit = explode(' ', preg_replace('!\\s+!', ' ', trim($rProcess)));

		if ($rSplit[0] == 'xcms') {
			$rUsage = [0, 0, 0];
			$rTimer = explode('-', $rSplit[9]);

			if (1 < count($rTimer)) {
				$rDays = (int) $rTimer[0];
				$rTime = $rTimer[1];
			}
			else {
				$rDays = 0;
				$rTime = $rTimer[0];
			}

			$rTime = explode(':', $rTime);

			if (count($rTime == 3)) {
				$rSeconds = ((int) $rTime[0] * 3600) + ((int) $rTime[1] * 60) + (int) $rTime[2];
			}
			else if (count($rTime) == 2) {
				$rSeconds = ((int) $rTime[0] * 60) + (int) $rTime[1];
			}
			else {
				$rSeconds = (int) $rTime[2];
			}

			$rUsage[0] = $rSeconds + ($rDays * 86400);
			$rTimer = explode('-', $rSplit[8]);

			if (1 < count($rTimer)) {
				$rDays = (int) $rTimer[0];
				$rTime = $rTimer[1];
			}
			else {
				$rDays = 0;
				$rTime = $rTimer[0];
			}

			$rTime = explode(':', $rTime);

			if (count($rTime == 3)) {
				$rSeconds = ((int) $rTime[0] * 3600) + ((int) $rTime[1] * 60) + (int) $rTime[2];
			}
			else if (count($rTime) == 2) {
				$rSeconds = ((int) $rTime[0] * 60) + (int) $rTime[1];
			}
			else {
				$rSeconds = (int) $rTime[2];
			}

			$rUsage[1] = $rSeconds + ($rDays * 86400);
			$rUsage[2] = ($rUsage[1] / $rUsage[0]) * 100;
			$rReturn[] = ['user' => $rSplit[0], 'pid' => $rSplit[1], 'cpu' => $rSplit[2], 'mem' => $rSplit[3], 'vsz' => $rSplit[4], 'rss' => $rSplit[5], 'tty' => $rSplit[6], 'stat' => $rSplit[7], 'time' => $rUsage[1], 'etime' => $rUsage[0], 'load_average' => $rUsage[2], 'command' => implode(' ', array_splice($rSplit, 10, count($rSplit) - 10))];
		}
	}

	return $rReturn;
}

function clearSettingsCache()
{
	unlink(CACHE_TMP_PATH . 'settings');
}

function validateCIDR($rCIDR)
{
	$rParts = explode('/', $rCIDR);
	$rIP = $rParts[0];
	$rNetmask = NULL;

	if (count($rParts) == 2) {
		$rNetmask = (int) $rParts[1];

		if ($rNetmask < 0) {
			return false;
		}
	}

	if (filter_var($rIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return is_null($rNetmask) ? true : $rNetmask <= 32;
	}

	if (filter_var($rIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		return is_null($rNetmask) ? true : $rNetmask <= 128;
	}

	return false;
}

function getFreeSpace($rServerID)
{
	$rReturn = [];
	$rLines = json_decode(systemapirequest($rServerID, ['action' => 'get_free_space']), true);
	array_shift($rLines);

	foreach ($rLines as $rLine) {
		$rSplit = explode(' ', preg_replace('!\\s+!', ' ', trim($rLine)));
		if (((0 < strlen($rSplit[0])) && (strpos($rSplit[5], 'xcms') !== false)) || ($rSplit[5] == '/')) {
			$rReturn[] = ['filesystem' => $rSplit[0], 'size' => $rSplit[1], 'used' => $rSplit[2], 'avail' => $rSplit[3], 'percentage' => $rSplit[4], 'mount' => implode(' ', array_slice($rSplit, 5, count($rSplit) - 5))];
		}
	}

	return $rReturn;
}

function getStreamsRamdisk($rServerID)
{
	$rReturn = json_decode(systemapirequest($rServerID, ['action' => 'streams_ramdisk']), true);

	if ($rReturn['result']) {
		return $rReturn['streams'];
	}

	return [];
}

function killPID($rServerID, $rPID)
{
	systemapirequest($rServerID, ['action' => 'kill_pid', 'pid' => $rPID]);
}

function getRTMPStats($rServerID)
{
	return json_decode(systemapirequest($rServerID, ['action' => 'rtmp_stats']), true);
}

function forceWatch($rServerID, $rWatchID)
{
	systemapirequest($rServerID, ['action' => 'watch_force', 'id' => $rWatchID]);
}

function forcePlex($rServerID, $rPlexID)
{
	systemapirequest($rServerID, ['action' => 'plex_force', 'id' => $rPlexID]);
}

function freeTemp($rServerID)
{
	systemapirequest($rServerID, ['action' => 'free_temp']);
}

function freeStreams($rServerID)
{
	systemapirequest($rServerID, ['action' => 'free_streams']);
}

function probeSource($rServerID, $rURL, $rUserAgent = NULL, $rProxy = NULL, $rCookies = NULL, $rHeaders = NULL)
{
	return json_decode(systemapirequest($rServerID, ['action' => 'probe', 'url' => $rURL, 'user_agent' => $rUserAgent, 'http_proxy' => $rProxy, 'cookies' => $rCookies, 'headers' => $rHeaders], 30), true);
}

function getStreamPIDs($rServerID)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT `streams`.`id`, `streams`.`stream_display_name`, `streams`.`type`, `streams_servers`.`pid`, `streams_servers`.`monitor_pid`, `streams_servers`.`delay_pid` FROM `streams_servers` LEFT JOIN `streams` ON `streams`.`id` = `streams_servers`.`stream_id` WHERE `streams_servers`.`server_id` = ?;', $rServerID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			foreach (['pid', 'monitor_pid', 'delay_pid'] as $rPIDType) {
				if ($rRow[$rPIDType]) {
					$rReturn[$rRow[$rPIDType]] = ['id' => $rRow['id'], 'title' => $rRow['stream_display_name'], 'type' => $rRow['type'], 'pid_type' => $rPIDType];
				}
			}
		}
	}

	$db->query('SELECT `id`, `stream_display_name`, `type`, `tv_archive_pid` FROM `streams` WHERE `tv_archive_server_id` = ?;', $rServerID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['tv_archive_pid']] = ['id' => $rRow['id'], 'title' => $rRow['stream_display_name'], 'type' => $rRow['type'], 'pid_type' => 'timeshift'];
		}
	}

	$db->query('SELECT `id`, `stream_display_name`, `type`, `vframes_pid` FROM `streams` WHERE `vframes_server_id` = ?;', $rServerID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['vframes_pid']] = ['id' => $rRow['id'], 'title' => $rRow['stream_display_name'], 'type' => $rRow['type'], 'pid_type' => 'vframes'];
		}
	}

	if (XCMS::$rSettings['redis_handler']) {
		$rStreamIDs = $rStreamMap = [];
		$rConnections = XCMS::getRedisConnections(NULL, $rServerID, NULL, true, false, false);

		foreach ($rConnections as $rConnection) {
			if (!in_array($rConnection['stream_id'], $rStreamIDs)) {
				$rStreamIDs[] = (int) $rConnection['stream_id'];
			}
		}

		if (0 < count($rStreamIDs)) {
			$db->query('SELECT `id`, `type`, `stream_display_name` FROM `streams` WHERE `id` IN (' . implode(',', $rStreamIDs) . ');');

			foreach ($db->get_rows() as $rRow) {
				$rStreamMap[$rRow['id']] = [$rRow['stream_display_name'], $rRow['type']];
			}
		}

		foreach ($rConnections as $rRow) {
			$rReturn[$rRow['pid']] = ['id' => $rRow['stream_id'], 'title' => $rStreamMap[$rRow['stream_id']][0], 'type' => $rStreamMap[$rRow['stream_id']][1], 'pid_type' => 'activity'];
		}
	}
	else {
		$db->query('SELECT `streams`.`id`, `streams`.`stream_display_name`, `streams`.`type`, `lines_live`.`pid` FROM `lines_live` LEFT JOIN `streams` ON `streams`.`id` = `lines_live`.`stream_id` WHERE `lines_live`.`server_id` = ?;', $rServerID);

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rRow) {
				$rReturn[$rRow['pid']] = ['id' => $rRow['id'], 'title' => $rRow['stream_display_name'], 'type' => $rRow['type'], 'pid_type' => 'activity'];
			}
		}
	}

	return $rReturn;
}

function roundUpToAny($n, $x = 5)
{
	return $x * round(($n + ($x / 2)) / $x);
}

function checkSource($rServerID, $rFilename)
{
	$rAPI = XCMS::$rServers[(int) $rServerID]['api_url_ip'] . '&action=getFile&filename=' . urlencode($rFilename);
	$rCommand = 'timeout 10 ' . XCMS::$rFFPROBE . ' -user_agent "Mozilla/5.0" -show_streams -v quiet "' . $rAPI . '" -of json';
	return json_decode(shell_exec($rCommand), true);
}

function getSSLLog($rServerID)
{
	$rAPI = XCMS::$rServers[(int) $rServerID]['api_url_ip'] . '&action=getFile&filename=' . urlencode(BIN_PATH . 'certbot/logs/xcms.log');
	return json_decode(file_get_contents($rAPI), true);
}

function getWatchdog($rID, $rLimit = 86400)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `servers_stats` WHERE `server_id` = ? AND UNIX_TIMESTAMP() - `time` <= ? ORDER BY `time` DESC;', $rID, $rLimit);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getSelections($rSources)
{
	global $db;
	$rReturn = [];

	foreach ($rSources as $rSource) {
		$db->query('SELECT `id` FROM `streams` WHERE `type` IN (2,5) AND `stream_source` LIKE ? ESCAPE \'|\' LIMIT 1;', '%' . str_replace('/', '\\/', $rSource) . '"%');

		if ($db->num_rows() == 1) {
			$rReturn[] = (int) $db->get_row()['id'];
		}
	}

	return $rReturn;
}

function getBackups()
{
	$rBackups = [];

	foreach (scandir(XCMS_HOME . 'backups/') as $rBackup) {
		$rInfo = pathinfo(XCMS_HOME . 'backups/' . $rBackup);

		if ($rInfo['extension'] == 'sql') {
			$rBackups[] = ['filename' => $rBackup, 'timestamp' => filemtime(XCMS_HOME . 'backups/' . $rBackup), 'date' => date('Y-m-d H:i:s', filemtime(XCMS_HOME . 'backups/' . $rBackup)), 'filesize' => filesize(XCMS_HOME . 'backups/' . $rBackup)];
		}
	}
	usort($rBackups, function($a, $b) {
		return $a['timestamp'] <=> $b['timestamp'];
	});
	return $rBackups;
}

function checkRemote()
{
	require_once XCMS_HOME . 'includes/libs/Dropbox.php';

	try {
		$rClient = new DropboxClient();
		$rClient->SetBearerToken(['t' => XCMS::$rSettings['dropbox_token']]);
		$rClient->GetFiles();
		return true;
	}
	catch (exception $e) {
		return false;
	}
}

function getRemoteBackups()
{
	require_once XCMS_HOME . 'includes/libs/Dropbox.php';

	try {
		$rClient = new DropboxClient();
		$rClient->SetBearerToken(['t' => XCMS::$rSettings['dropbox_token']]);
		$rFiles = $rClient->GetFiles();
	}
	catch (exception $e) {
		$rFiles = [];
	}

	$rBackups = [];

	foreach ($rFiles as $rFile) {
		try {
			if (!$rFile->isDir && (strtolower(pathinfo($rFile->name)['extension']) == 'sql') && (0 < $rFile->size)) {
				$rJSON = json_decode(json_encode($rFile, JSON_UNESCAPED_UNICODE), true);
				$rJSON['time'] = strtotime($rFile->server_modified);
				$rBackups[] = $rJSON;
			}
		}
		catch (exception $e) {
		}
	}

	array_multisort(array_column($rBackups, 'time'), SORT_ASC, $rBackups);
	return $rBackups;
}

function uploadRemoteBackup($rPath, $rFilename, $rOverwrite = true)
{
	require_once XCMS_HOME . 'includes/libs/Dropbox.php';
	$rClient = new DropboxClient();

	try {
		$rClient->SetBearerToken(['t' => XCMS::$rSettings['dropbox_token']]);
		return $rClient->UploadFile($rFilename, $rPath, $rOverwrite);
	}
	catch (exception $e) {
		return (object) ['error' => $e];
	}
}

function downloadRemoteBackup($rPath, $rFilename)
{
	require_once XCMS_HOME . 'includes/libs/Dropbox.php';
	$rClient = new DropboxClient();

	try {
		$rClient->SetBearerToken(['t' => XCMS::$rSettings['dropbox_token']]);
		$rClient->DownloadFile($rPath, $rFilename);
		return true;
	}
	catch (exception $e) {
		return false;
	}
}

function deleteRemoteBackup($rPath)
{
	require_once XCMS_HOME . 'includes/libs/Dropbox.php';
	$rClient = new DropboxClient();

	try {
		$rClient->SetBearerToken(['t' => XCMS::$rSettings['dropbox_token']]);
		$rClient->Delete($rPath);
		return true;
	}
	catch (exception $e) {
		return false;
	}
}

function parseRelease($rRelease)
{
	if (XCMS::$rSettings['parse_type'] == 'guessit') {
		$rCommand = XCMS_HOME . 'bin/guess ' . escapeshellarg(pathinfo($rRelease)['filename'] . '.mkv');
	}
	else {
		$rCommand = '/usr/bin/python3 ' . XCMS_HOME . 'includes/python/release.py ' . escapeshellarg(pathinfo(str_replace('-', '_', $rRelease))['filename']);
	}

	return json_decode(shell_exec($rCommand), true);
}

function scanRecursive($rServerID, $rDirectory, $rAllowed = NULL)
{
	return json_decode(systemapirequest($rServerID, ['action' => 'scandir_recursive', 'dir' => $rDirectory, 'allowed' => implode('|', $rAllowed)]), true);
}

function listDir($rServerID, $rDirectory, $rAllowed = NULL)
{
	return json_decode(systemapirequest($rServerID, ['action' => 'scandir', 'dir' => $rDirectory, 'allowed' => implode('|', $rAllowed)]), true);
}

function getEncodeErrors($rID)
{
	global $db;
	$rErrors = [];
	$db->query('SELECT `server_id`, `error` FROM `streams_errors` WHERE `stream_id` = ?;', $rID);

	foreach ($db->get_rows() as $rRow) {
		$rErrors[(int) $rRow['server_id']] = $rRow['error'];
	}

	return $rErrors;
}

function deleteRecording($rID)
{
	global $db;
	$db->query('SELECT `created_id`, `source_id` FROM `recordings` WHERE `id` = ?;', $rID);

	if (0 < $db->num_rows()) {
		$rRecording = $db->get_row();

		if ($rRecording['created_id']) {
			deleteStream($rRecording['created_id'], $rRecording['source_id'], true, true);
		}

		shell_exec('kill -9 `ps -ef | grep \'Record\\[' . (int) $rID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
		$db->query('DELETE FROM `recordings` WHERE `id` = ?;', $rID);
	}
}

function getRecordings()
{
	global $db;
	$rRecordings = [];
	$db->query('SELECT * FROM `recordings` ORDER BY `id` DESC;');

	foreach ($db->get_rows() as $rRow) {
		$rRecordings[] = $rRow;
	}

	return $rRecordings;
}

function isSecure()
{
	return (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) || ($_SERVER['SERVER_PORT'] == 443);
}

function getProtocol()
{
	if (issecure()) {
		return 'https';
	}
	else {
		return 'http';
	}
}

function deleteMovieFile($rServerIDs, $rID)
{
	global $db;

	if (!is_array($rServerIDs)) {
		$rServerIDs = [$rServerIDs];
	}

	foreach ($rServerIDs as $rServerID) {
		$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`, `cache`) VALUES(?, ?, ?, 1);', $rServerID, time(), json_encode(['type' => 'delete_vod', 'id' => $rID]));
	}

	return true;
}

function generateString($strength = 10)
{
	$input = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
	$input_length = strlen($input);
	$random_string = '';

	for ($i = 0; $i < $strength; $i++) {
		$random_character = $input[mt_rand(0, $input_length - 1)];
		$random_string .= $random_character;
	}

	return $random_string;
}

function getStreamingServers($rOnline = false)
{
	global $db;
	global $rPermissions;
	$rReturn = [];
	$db->query('SELECT * FROM `servers` WHERE `server_type` = 0 ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			if ($rPermissions['is_reseller']) {
				$rRow['server_name'] = 'Server #' . $rRow['id'];
			}
			$rRow['server_online'] = (in_array($rRow['status'], [1, 3]) && ((time() - $rRow['last_check_ago']) <= 90)) || $rRow['is_main'];
			if ($rRow['server_online'] || !$rOnline) {
				$rReturn[$rRow['id']] = $rRow;
			}
		}
	}

	return $rReturn;
}

function getAllServers()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `servers` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getProxyServers($rOnline = false)
{
	global $db;
	global $rPermissions;
	$rReturn = [];
	$db->query('SELECT * FROM `servers` WHERE `server_type` = 1 ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			if ($rPermissions['is_reseller']) {
				$rRow['server_name'] = 'Proxy #' . $rRow['id'];
			}
			$rRow['server_online'] = (in_array($rRow['status'], [1, 3]) && ((time() - $rRow['last_check_ago']) <= 90)) || $rRow['is_main'];
			if ($rRow['server_online'] || !$rOnline) {
				$rReturn[$rRow['id']] = $rRow;
			}
		}
	}

	return $rReturn;
}

function getStreamingServersByID($rID)
{
	global $db;
	$db->query('SELECT * FROM `servers` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return false;
}

function getLiveConnections($rServerID, $rProxy = false)
{
	global $db;

	if (XCMS::$rSettings['redis_handler']) {
		$rCount = 0;

		if ($rProxy) {
			$rParentIDs = XCMS::$rServers[$rServerID]['parent_id'];

			foreach ($rParentIDs as $rParentID) {
				foreach (XCMS::getRedisConnections(NULL, $rParentID, NULL, true, false, false) as $rConnection) {
					if ($rServerID == $rConnection['proxy_id']) {
						$rCount++;
					}
				}
			}
		}
		else {
			$rCount = XCMS::getRedisConnections(NULL, $rServerID, NULL, true, true, false)[0];
		}

		return $rCount;
	}
	else {
		if ($rProxy) {
			$db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `proxy_id` = ? AND `hls_end` = 0;', $rServerID);
		}
		else {
			$db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `server_id` = ? AND `hls_end` = 0;', $rServerID);
		}

		return $db->get_row()['count'];
	}
}

function getEPGSources()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `epg`;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getCategories($rType = 'live')
{
	global $db;
	$rReturn = [];

	if ($rType) {
		$db->query('SELECT * FROM `streams_categories` WHERE `category_type` = ? ORDER BY `cat_order` ASC;', $rType);
	}
	else {
		$db->query('SELECT * FROM `streams_categories` ORDER BY `cat_order` ASC;');
	}

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function findEPG($rEPGName)
{
	global $db;
	$db->query('SELECT `id`, `data` FROM `epg`;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			foreach (json_decode($rRow['data'], true) as $rChannelID => $rChannelData) {
				if ($rChannelID == $rEPGName) {
					if (0 < count($rChannelData['langs'])) {
						$rEPGLang = $rChannelData['langs'][0];
					}
					else {
						$rEPGLang = '';
					}

					return ['channel_id' => $rChannelID, 'epg_lang' => $rEPGLang, 'epg_id' => (int) $rRow['id']];
				}
			}
		}
	}

	return NULL;
}

function getStreamArguments()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `streams_arguments` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['argument_key']] = $rRow;
		}
	}

	return $rReturn;
}

function getTranscodeProfiles()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `profiles` ORDER BY `profile_id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getWatchFolders($rType = NULL)
{
	global $db;
	$rReturn = [];

	if ($rType) {
		$db->query('SELECT * FROM `watch_folders` WHERE `type` = ? AND `type` <> \'plex\' ORDER BY `id` ASC;', $rType);
	}
	else {
		$db->query('SELECT * FROM `watch_folders` WHERE `type` <> \'plex\' ORDER BY `id` ASC;');
	}

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getPlexServers()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `watch_folders` WHERE `type` = \'plex\' ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getWatchCategories($rType = NULL)
{
	global $db;
	$rReturn = [];

	if ($rType) {
		$db->query('SELECT * FROM `watch_categories` WHERE `type` = ? ORDER BY `genre_id` ASC;', $rType);
	}
	else {
		$db->query('SELECT * FROM `watch_categories` ORDER BY `genre_id` ASC;');
	}

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[$rRow['genre_id']] = $rRow;
		}
	}

	return $rReturn;
}

function getWatchFolder($rID)
{
	global $db;
	$db->query('SELECT * FROM `watch_folders` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getSeriesByTMDB($rID)
{
	global $db;
	$db->query('SELECT * FROM `streams_series` WHERE `tmdb_id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getSeries()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `streams_series` ORDER BY `title` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getSerie($rID)
{
	global $db;
	$db->query('SELECT * FROM `streams_series` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getSeriesTrailer($rTMDBID, $rLanguage = NULL)
{
	$rURL = 'https://api.themoviedb.org/3/tv/' . (int) $rTMDBID . '/videos?api_key=' . urlencode(XCMS::$rSettings['tmdb_api_key']);

	if ($rLanguage) {
		$rURL .= '&language=' . urlencode($rLanguage);
	}
	else if (0 < strlen(XCMS::$rSettings['tmdb_language'])) {
		$rURL .= '&language=' . urlencode(XCMS::$rSettings['tmdb_language']);
	}

	$rJSON = json_decode(file_get_contents($rURL), true);

	foreach ($rJSON['results'] as $rVideo) {
		if ((strtolower($rVideo['type']) == 'trailer') && (strtolower($rVideo['site']) == 'youtube')) {
			return $rVideo['key'];
		}
	}

	return '';
}

function getStills($rTMDBID, $rSeason, $rEpisode)
{
	$rURL = 'https://api.themoviedb.org/3/tv/' . (int) $rTMDBID . '/season/' . (int) $rSeason . '/episode/' . (int) $rEpisode . '/images?api_key=' . urlencode(XCMS::$rSettings['tmdb_api_key']);

	if (0 < strlen(XCMS::$rSettings['tmdb_language'])) {
		$rURL .= '&language=' . urlencode(XCMS::$rSettings['tmdb_language']);
	}

	return json_decode(file_get_contents($rURL), true);
}

function getUserAgents()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `blocked_uas` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getISPs()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `blocked_isps` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getStreamProviders()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `providers` ORDER BY `last_changed` DESC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getStreamProvider($rID)
{
	global $db;
	$db->query('SELECT * FROM `providers` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getBlockedIPs()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `blocked_ips` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getRTMPIPs()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `rtmp_ips` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getStream($rID)
{
	global $db;
	$db->query('SELECT * FROM `streams` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getUser($rID)
{
	global $db;
	$db->query('SELECT * FROM `lines` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getRegisteredUser($rID)
{
	global $db;
	$db->query('SELECT * FROM `users` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getChannelEPG($rStreamID, $rArchive = false)
{
	global $db;
	$rStream = getstream($rStreamID);

	if ($rStream['channel_id']) {
		if ($rArchive) {
			return XCMS::getEPG($rStreamID, time() - ($rStream['tv_archive_duration'] * 86400), time());
		}
		else {
			return XCMS::getEPG($rStreamID, time(), time() + 1209600);
		}
	}

	return [];
}

function getEPG($rID)
{
	global $db;
	$db->query('SELECT * FROM `epg` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getStreamOptions($rID)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `streams_options` WHERE `stream_id` = ?;', $rID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['argument_id']] = $rRow;
		}
	}

	return $rReturn;
}

function getStreamSys($rID)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `streams_servers` WHERE `stream_id` = ?;', $rID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['server_id']] = $rRow;
		}
	}

	return $rReturn;
}

function getRegisteredUsers($rOwner = NULL, $rIncludeSelf = true)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `users` ORDER BY `username` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			if (!$rOwner || ($rOwner == $rRow['owner_id']) || (($rOwner == $rRow['id']) && $rIncludeSelf)) {
				$rReturn[(int) $rRow['id']] = $rRow;
			}
		}
	}

	if (count($rReturn) == 0) {
		$rReturn[-1] = [];
	}

	return $rReturn;
}

function getResellers($rOwner, $rIncludeSelf = true)
{
	global $db;
	$rReturn = [];

	if ($rIncludeSelf) {
		$db->query('SELECT `id`, `username` FROM `users` WHERE `owner_id` = ? OR `id` = ? ORDER BY `username` ASC;', $rOwner, $rOwner);
	}
	else {
		$db->query('SELECT `id`, `username` FROM `users` WHERE `owner_id` = ? ORDER BY `username` ASC;', $rOwner);
	}

	return $db->get_rows(true, 'id');
}

function getDirectReports($rIncludeSelf = true)
{
	global $db;
	global $rPermissions;
	global $rUserInfo;
	$rUserIDs = $rPermissions['direct_reports'];

	if ($rIncludeSelf) {
		$rUserIDs[] = $rUserInfo['id'];
	}

	$rReturn = [];

	if (0 < count($rUserIDs)) {
		$db->query('SELECT * FROM `users` WHERE `owner_id` IN (' . implode(',', array_map('intval', $rUserIDs)) . ') ORDER BY `username` ASC;');

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rRow) {
				$rReturn[(int) $rRow['id']] = $rRow;
			}
		}
	}

	return $rReturn;
}

function hasResellerPermissions($rType)
{
	global $rPermissions;
	return $rPermissions[$rType];
}

function hasPermissions($rType, $rID)
{
	global $rUserInfo;
	global $db;
	global $rPermissions;
	if (!isset($rUserInfo) || !isset($rPermissions)) {
		return false;
	}

	if ($rType == 'user') {
		$rReports = array_map('intval', array_merge([$rUserInfo['id']], $rPermissions['all_reports']));

		if (0 < count($rReports)) {
			$db->query('SELECT `id` FROM `users` WHERE `id` = ? AND (`owner_id` IN (' . implode(',', $rReports) . ') OR `id` = ?);', $rID, $rUserInfo['id']);
			return 0 < $db->num_rows();
		}
		else {
			return false;
		}
	}
	else if ($rType == 'line') {
		$rReports = array_map('intval', array_merge([$rUserInfo['id']], $rPermissions['all_reports']));

		if (0 < count($rReports)) {
			$db->query('SELECT `id` FROM `lines` WHERE `id` = ? AND `member_id` IN (' . implode(',', $rReports) . ');', $rID);
			return 0 < $db->num_rows();
		}
		else {
			return false;
		}
	}
	else if (($rType == 'adv') && $rPermissions['is_admin']) {
		if ((0 < count($rPermissions['advanced'])) && ($rUserInfo['member_group_id'] != 1)) {
			return in_array($rID, $rPermissions['advanced'] ?: []);
		}
		else {
			return true;
		}
	}

	return false;
}

function getMemberGroups()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `users_groups` ORDER BY `group_id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['group_id']] = $rRow;
		}
	}

	return $rReturn;
}

function getMemberGroup($rID)
{
	global $db;
	$db->query('SELECT * FROM `users_groups` WHERE `group_id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getOutputs()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `output_formats` ORDER BY `access_output_id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function getUserBouquets()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT `id`, `bouquet` FROM `lines` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getBouquets()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `bouquets` ORDER BY `bouquet_order` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getBouquetOrder()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `bouquets` ORDER BY `bouquet_order` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getBouquet($rID)
{
	global $db;
	$db->query('SELECT * FROM `bouquets` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getLanguages()
{
	return [];
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `languages` ORDER BY `key` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[] = $rRow;
		}
	}

	return $rReturn;
}

function addToBouquet($rType, $rBouquetID, $rIDs)
{
	global $db;

	if (!is_array($rIDs)) {
		$rIDs = [$rIDs];
	}

	$rBouquet = getbouquet($rBouquetID);

	if ($rBouquet) {
		if ($rType == 'stream') {
			$rColumn = 'bouquet_channels';
		}
		else if ($rType == 'movie') {
			$rColumn = 'bouquet_movies';
		}
		else if ($rType == 'radio') {
			$rColumn = 'bouquet_radios';
		}
		else {
			$rColumn = 'bouquet_series';
		}

		$rChanged = false;
		$rChannels = confirmIDs(json_decode($rBouquet[$rColumn], true));

		foreach ($rIDs as $rID) {
			if ((0 < (int) $rID) && !in_array($rID, $rChannels)) {
				$rChannels[] = $rID;
				$rChanged = true;
			}
		}

		if ($rChanged) {
			$db->query('UPDATE `bouquets` SET `' . $rColumn . '` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rChannels)) . ']', $rBouquetID);
		}
	}
}

function removeFromBouquet($rType, $rBouquetID, $rIDs)
{
	global $db;

	if (!is_array($rIDs)) {
		$rIDs = [$rIDs];
	}

	$rBouquet = getbouquet($rBouquetID);

	if ($rBouquet) {
		if ($rType == 'stream') {
			$rColumn = 'bouquet_channels';
		}
		else if ($rType == 'movie') {
			$rColumn = 'bouquet_movies';
		}
		else if ($rType == 'radio') {
			$rColumn = 'bouquet_radios';
		}
		else {
			$rColumn = 'bouquet_series';
		}

		$rChanged = false;
		$rChannels = confirmIDs(json_decode($rBouquet[$rColumn], true));

		foreach ($rIDs as $rID) {
			if (($rKey = array_search($rID, $rChannels)) !== false) {
				unset($rChannels[$rKey]);
				$rChanged = true;
			}
		}

		if ($rChanged) {
			$db->query('UPDATE `bouquets` SET `' . $rColumn . '` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rChannels)) . ']', $rBouquetID);
		}
	}
}

function confirmIDs($rIDs)
{
	$rReturn = [];

	foreach ($rIDs as $rID) {
		if (0 < (int) $rID) {
			$rReturn[] = $rID;
		}
	}

	return array_unique($rReturn);
}

function getPackages($rGroup = NULL, $rType = NULL)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `users_packages` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			if (!isset($rGroup) || in_array((int) $rGroup, json_decode($rRow['groups'], true))) {
				if (!$rType || $rRow['is_' . $rType]) {
					$rReturn[(int) $rRow['id']] = $rRow;
				}
			}
		}
	}

	return $rReturn;
}

function checkCompatible($rIDA, $rIDB)
{
	$rPackageA = getPackage($rIDA);
	$rPackageB = getPackage($rIDB);
	$rCompatible = true;
	if ($rPackageA && $rPackageB) {
		foreach (['bouquets', 'output_formats'] as $rKey) {
			if (json_decode($rPackageA[$rKey], true) != json_decode($rPackageB[$rKey], true)) {
				$rCompatible = false;
			}
		}

		foreach (['is_restreamer', 'is_isplock', 'max_connections', 'force_server_id', 'forced_country', 'lock_device'] as $rKey) {
			if ($rPackageA[$rKey] != $rPackageB[$rKey]) {
				$rCompatible = false;
			}
		}
	}

	return $rCompatible;
}

function getPackage($rID)
{
	global $db;
	$db->query('SELECT * FROM `users_packages` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getCodes($rType = NULL)
{
	global $db;
	$rReturn = [];

	if (!is_null($rType)) {
		$db->query('SELECT * FROM `access_codes` WHERE `type` = ? ORDER BY `id` ASC;', $rType);
	}
	else {
		$db->query('SELECT * FROM `access_codes` ORDER BY `id` ASC;');
	}

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getCode($rID)
{
	global $db;
	$db->query('SELECT * FROM `access_codes` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getHMACTokens()
{
	global $db;
	$rReturn = [];
	$db->query('SELECT * FROM `hmac_keys` ORDER BY `id` ASC;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rReturn[(int) $rRow['id']] = $rRow;
		}
	}

	return $rReturn;
}

function getHMACToken($rID)
{
	global $db;
	$db->query('SELECT * FROM `hmac_keys` WHERE `id` = ?;', $rID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
}

function getActiveCodes()
{
	$rCodes = [];
	$rFiles = scandir(XCMS_HOME . 'bin/nginx/conf/codes/');

	foreach ($rFiles as $rFile) {
		$rPathInfo = pathinfo($rFile);
		$rExt = $rPathInfo['extension'];
		if (($rExt == 'conf') && ($rPathInfo['filename'] != 'default')) {
			$rCodes[] = $rPathInfo['filename'];
		}
	}

	return $rCodes;
}

