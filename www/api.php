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

function loadAPI()
{
	global $rDeny;

	if (!empty(XCMS::$rRequest['status'])) {
		if ($rStatus = Xcms\Functions::checkStatus(XCMS::$rRequest['data'])) {
			exit($rStatus);
		}
	}
	if (empty(XCMS::$rRequest['password']) || (XCMS::$rRequest['password'] != XCMS::$rSettings['live_streaming_pass'])) {
		generateError('INVALID_API_PASSWORD');
	}

	unset(unset(XCMS::$rRequest)['password']);
	$db = new Database();
	XCMS::$db = & $db;

	if (!in_array($rIP, XCMS::getAllowedIPs())) {
		generateError('API_IP_NOT_ALLOWED');
	}

	header('Access-Control-Allow-Origin: *');
	$rAction = (!empty(XCMS::$rRequest['action']) ? XCMS::$rRequest['action'] : '');
	$rDeny = false;

	switch ($rAction) {
	case 'view_log':
		if (!empty(XCMS::$rRequest['stream_id'])) {
			$rStreamID = (int) XCMS::$rRequest['stream_id'];

			if (file_exists(STREAMS_PATH . $rStreamID . '.errors')) {
				echo file_get_contents(STREAMS_PATH . $rStreamID . '.errors');
			}
			else if (file_exists(VOD_PATH . $rStreamID . '.errors')) {
				echo file_get_contents(VOD_PATH . $rStreamID . '.errors');
			}

			exit();
		}

		break;
	case 'fpm_status':
		echo file_get_contents('http://127.0.0.1:' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/status');
		break;
	case 'reload_epg':
		shell_exec(PHP_BIN . ' ' . CRON_PATH . 'epg.php >/dev/null 2>/dev/null &');
		break;
	case 'restore_images':
		shell_exec(PHP_BIN . ' ' . INCLUDES_PATH . 'cli/tools.php "images" >/dev/null 2>/dev/null &');
		break;
	case 'reload_nginx':
		shell_exec(BIN_PATH . 'nginx_rtmp/sbin/nginx_rtmp -s reload');
		shell_exec(BIN_PATH . 'nginx/sbin/nginx -s reload');
		break;
	case 'streams_ramdisk':
		set_time_limit(30);
		$rReturn = [
			'result'  => true,
			'streams' => []
		];
		exec('ls -l ' . STREAMS_PATH, $rFiles);

		foreach ($rFiles as $rFile) {
			$rSplit = explode(' ', preg_replace('!\\s+!', ' ', $rFile));
			$rFileSplit = explode('_', $rSplit[count($rSplit) - 1]);

			if (count($rFileSplit) == 2) {
				$rStreamID = (int) $rFileSplit[0];
				$rFileSize = (int) $rSplit[4];

				if ((0 < $rStreamID) & (0 < $rFileSize)) {
					$rReturn['streams'][$rStreamIDs] += $rFileSize;
				}
			}
		}

		echo json_encode($rReturn);
		exit();
	case 'vod':
		if (!empty(XCMS::$rRequest['stream_ids']) && !empty(XCMS::$rRequest['function'])) {
			$rStreamIDs = array_map('intval', XCMS::$rRequest['stream_ids']);
			$rFunction = XCMS::$rRequest['function'];

			switch ($rFunction) {
			case 'start':
				foreach ($rStreamIDs as $rStreamID) {
					XCMS::stopMovie($rStreamID, true);
					if (isset(XCMS::$rRequest['force']) && XCMS::$rRequest['force']) {
						XCMS::startMovie($rStreamID);
					}
					else {
						XCMS::queueMovie($rStreamID);
					}
				}

				echo json_encode(['result' => true]);
				exit();
			case 'stop':
				foreach ($rStreamIDs as $rStreamID) {
					XCMS::stopMovie($rStreamID);
				}

				echo json_encode(['result' => true]);
				exit();
			}
		}

		break;
	case 'rtmp_stats':
		echo json_encode(XCMS::getRTMPStats());
		break;
	case 'kill_pid':
		$rPID = (int) XCMS::$rRequest['pid'];

		if (0 < $rPID) {
			posix_kill($rPID, 9);
			echo json_encode(['result' => true]);
		}
		else {
			echo json_encode(['result' => false]);
		}

		break;
	case 'rtmp_kill':
		$rName = XCMS::$rRequest['name'];
		shell_exec('wget --timeout=2 -O /dev/null -o /dev/null "' . XCMS::$rServers[SERVER_ID]['rtmp_mport_url'] . 'control/drop/publisher?app=live&name=' . escapeshellcmd($rName) . '" >/dev/null 2>/dev/null &');
		echo json_encode(['result' => true]);
		exit();
	case 'stream':
		if (!empty(XCMS::$rRequest['stream_ids']) && !empty(XCMS::$rRequest['function'])) {
			$rStreamIDs = array_map('intval', XCMS::$rRequest['stream_ids']);
			$rFunction = XCMS::$rRequest['function'];

			switch ($rFunction) {
			case 'start':
				foreach ($rStreamIDs as $rStreamID) {
					if (!XCMS::startMonitor($rStreamID, true)) {
						echo json_encode(['result' => false]);
						exit();
					}

					usleep(50000);
				}

				echo json_encode(['result' => true]);
				exit();
			case 'stop':
				foreach ($rStreamIDs as $rStreamID) {
					XCMS::stopStream($rStreamID, true);
				}

				echo json_encode(['result' => true]);
				exit();
			}
		}

		break;
	case 'stats':
		echo json_encode(XCMS::getStats());
		exit();
	case 'force_stream':
		$rStreamID = (int) XCMS::$rRequest['stream_id'];
		$rForceID = (int) XCMS::$rRequest['force_id'];

		if (0 < $rStreamID) {
			file_put_contents(SIGNALS_TMP_PATH . ($rStreamID . '.force'), $rForceID);
		}

		exit(json_encode(['result' => true]));
	case 'closeConnection':
		XCMS::closeConnection((int) XCMS::$rRequest['activity_id']);
		exit(json_encode(['result' => true]));
	case 'pidsAreRunning':
		if (!empty(XCMS::$rRequest['pids']) && is_array(XCMS::$rRequest['pids']) && !empty(XCMS::$rRequest['program'])) {
			$rPIDs = array_map('intval', XCMS::$rRequest['pids']);
			$rProgram = XCMS::$rRequest['program'];
			$rOutput = [];

			foreach ($rPIDs as $rPID) {
				$rOutput[$rPID] = false;
				if (file_exists('/proc/' . $rPID) && is_readable('/proc/' . $rPID . '/exe') && (strpos(basename(readlink('/proc/' . $rPID . '/exe')), basename($rProgram)) === 0)) {
					$rOutput[$rPID] = true;
				}
			}

			echo json_encode($rOutput);
			exit();
		}

		break;
	case 'getFile':
		if (!empty(XCMS::$rRequest['filename'])) {
			$rFilename = XCMS::$rRequest['filename'];

			if (!in_array(strtolower(pathinfo($rFilename)['extension']), ['log' => true, 'tar.gz' => true, 'gz' => true, 'zip' => true, 'm3u8' => true, 'mp4' => true, 'mkv' => true, 'avi' => true, 'mpg' => true, 'flv' => true, '3gp' => true, 'm4v' => true, 'wmv' => true, 'mov' => true, 'ts' => true, 'srt' => true, 'sub' => true, 'sbv' => true, 'jpg' => true, 'png' => true, 'bmp' => true, 'jpeg' => true, 'gif' => true, 'tif' => true])) {
				exit(json_encode(['result' => false, 'error' => 'Invalid file extension.']));
			}
			if (file_exists($rFilename) && is_readable($rFilename)) {
				header('Content-Type: application/octet-stream');
				$rFP = @fopen($rFilename, 'rb');
				$rSize = filesize($rFilename);
				$rLength = $rSize;
				$rStart = 0;
				$rEnd = $rSize - 1;
				header('Accept-Ranges: 0-' . $rLength);

				if (isset($_SERVER['HTTP_RANGE'])) {
					$rRangeEnd = $rEnd;
					list(1 => $rRange) = explode('=', $_SERVER['HTTP_RANGE'], 2);

					if (strpos($rRange, ',') !== false) {
						header('HTTP/1.1 416 Requested Range Not Satisfiable');
						header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
						exit();
					}

					if ($rRange == '-') {
						$rRangeStart = $rSize - substr($rRange, 1);
					}
					else {
						$rRange = explode('-', $rRange);
						$rRangeStart = $rRange[0];
						$rRangeEnd = (isset($rRange[1]) && is_numeric($rRange[1]) ? $rRange[1] : $rSize);
					}

					$rRangeEnd = ($rEnd < $rRangeEnd ? $rEnd : $rRangeEnd);
					if (($rRangeEnd < $rRangeStart) || (($rSize - 1) < $rRangeStart) || ($rSize <= $rRangeEnd)) {
						header('HTTP/1.1 416 Requested Range Not Satisfiable');
						header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
						exit();
					}

					$rStart = $rRangeStart;
					$rEnd = $rRangeEnd;
					$rLength = ($rEnd - $rStart) + 1;
					fseek($rFP, $rStart);
					header('HTTP/1.1 206 Partial Content');
				}

				header('Content-Range: bytes ' . $rStart . '-' . $rEnd . '/' . $rSize);
				header('Content-Length: ' . $rLength);

				while (!feof($rFP) && (ftell($rFP) <= $rEnd)) {
					echo stream_get_line($rFP, (int) XCMS::$rSettings['read_buffer_size'] ?: 8192);
				}

				fclose($rFP);
			}

			exit();
		}

		break;
	case 'scandir_recursive':
		set_time_limit(30);
		$rDirectory = urldecode(XCMS::$rRequest['dir']);
		$rAllowed = (!empty(XCMS::$rRequest['allowed']) ? urldecode(XCMS::$rRequest['allowed']) : NULL);

		if (file_exists($rDirectory)) {
			if ($rAllowed) {
				$rCommand = '/usr/bin/find ' . escapeshellarg($rDirectory) . ' -regex ".*\\.\\(' . escapeshellcmd($rAllowed) . '\\)"';
			}
			else {
				$rCommand = '/usr/bin/find ' . escapeshellarg($rDirectory);
			}

			exec($rCommand, $rReturn);
			echo json_encode($rReturn, JSON_UNESCAPED_UNICODE);
			exit();
		}

		exit(json_encode(['result' => false]));
	case 'scandir':
		set_time_limit(30);
		$rDirectory = urldecode(XCMS::$rRequest['dir']);
		$rAllowed = (!empty(XCMS::$rRequest['allowed']) ? explode('|', urldecode(XCMS::$rRequest['allowed'])) : []);

		if (file_exists($rDirectory)) {
			$rReturn = [
				'result' => true,
				'dirs'   => [],
				'files'  => []
			];
			$rFiles = scanDir($rDirectory);

			foreach ($rFiles as $rKey => $rValue) {
				if (!in_array($rValue, ['.' => true, '..' => true])) {
					if (is_dir($rDirectory . '/' . $rValue)) {
						$rReturn['dirs'][] = $rValue;
					}
					else {
						$rExt = strtolower(pathinfo($rValue)['extension']);
						if ((is_array($rAllowed) && in_array($rExt, $rAllowed)) || !$rAllowed) {
							$rReturn['files'][] = $rValue;
						}
					}
				}
			}

			echo json_encode($rReturn);
			exit();
		}

		exit(json_encode(['result' => false]));
	case 'get_free_space':
		exec('df -h', $rReturn);
		echo json_encode($rReturn);
		exit();
	case 'get_pids':
		exec('ps -e -o user,pid,%cpu,%mem,vsz,rss,tty,stat,time,etime,command', $rReturn);
		echo json_encode($rReturn);
		exit();
	case 'redirect_connection':
		if (!empty(XCMS::$rRequest['uuid']) && !empty(XCMS::$rRequest['stream_id'])) {
			XCMS::$rRequest['type'] = 'redirect';
			file_put_contents(SIGNALS_PATH . XCMS::$rRequest['uuid'], json_encode(XCMS::$rRequest));
		}

		break;
	case 'free_temp':
		exec('rm -rf ' . XCMS_HOME . 'tmp/*');
		shell_exec(PHP_BIN . ' ' . CRON_PATH . 'cache.php');
		echo json_encode(['result' => true]);
		break;
	case 'free_streams':
		exec('rm ' . XCMS_HOME . 'content/streams/*');
		echo json_encode(['result' => true]);
		break;
	case 'signal_send':
		if (!empty(XCMS::$rRequest['message']) && !empty(XCMS::$rRequest['uuid'])) {
			XCMS::$rRequest['type'] = 'signal';
			file_put_contents(SIGNALS_PATH . XCMS::$rRequest['uuid'], json_encode(XCMS::$rRequest));
		}

		break;
	case 'get_certificate_info':
		echo json_encode(XCMS::getCertificateInfo());
		exit();
	case 'watch_force':
		shell_exec(PHP_BIN . ' ' . CRON_PATH . 'watch.php ' . (int) XCMS::$rRequest['id'] . ' >/dev/null 2>/dev/null &');
		break;
	case 'plex_force':
		shell_exec(PHP_BIN . ' ' . CRON_PATH . 'plex.php ' . (int) XCMS::$rRequest['id'] . ' >/dev/null 2>/dev/null &');
		break;
	case 'get_archive_files':
		$rStreamID = (int) XCMS::$rRequest['stream_id'];
		echo json_encode(['result' => true, 'data' => glob(ARCHIVE_PATH . $rStreamID . '/*.ts')]);
		exit();
	case 'request_update':
		if (XCMS::$rRequest['type'] == 0) {
			$rFile = LOADBALANCER_UPDATE;
		}
		else {
			$rFile = PROXY_UPDATE;
		}

		if (file_exists($rFile)) {
			$rMD5 = md5_file($rFile);
			$rURL = 'http://' . XCMS::$rServers[SERVER_ID]['server_ip'] . ':' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/api?password=' . XCMS::$rSettings['live_streaming_pass'] . '&action=getFile&filename=' . urlencode($rFile);
			exit(json_encode(['result' => true, 'md5' => $rMD5, 'url' => $rURL, 'version' => XCMS::$rServers[SERVER_ID]['xcms_version']]));
		}

		exit(json_encode(['result' => false]));
	case 'kill_watch':
		if (file_exists(CACHE_TMP_PATH . 'watch_pid')) {
			$rPrevPID = (int) file_get_contents(CACHE_TMP_PATH . 'watch_pid');
		}
		else {
			$rPrevPID = NULL;
		}
		if ($rPrevPID && XCMS::isProcessRunning($rPrevPID, 'php')) {
			shell_exec('kill -9 ' . $rPrevPID);
		}

		$rPIDs = glob(WATCH_TMP_PATH . '*.wpid');

		foreach ($rPIDs as $rPIDFile) {
			$rPID = (int) basename($rPIDFile, '.wpid');
			if ($rPID && XCMS::isProcessRunning($rPID, 'php')) {
				shell_exec('kill -9 ' . $rPID);
			}

			unlink($rPIDFile);
		}

		exit(json_encode(['result' => true]));
	case 'kill_plex':
		if (file_exists(CACHE_TMP_PATH . 'plex_pid')) {
			$rPrevPID = (int) file_get_contents(CACHE_TMP_PATH . 'plex_pid');
		}
		else {
			$rPrevPID = NULL;
		}
		if ($rPrevPID && XCMS::isProcessRunning($rPrevPID, 'php')) {
			shell_exec('kill -9 ' . $rPrevPID);
		}

		$rPIDs = glob(WATCH_TMP_PATH . '*.ppid');

		foreach ($rPIDs as $rPIDFile) {
			$rPID = (int) basename($rPIDFile, '.ppid');
			if ($rPID && XCMS::isProcessRunning($rPID, 'php')) {
				shell_exec('kill -9 ' . $rPID);
			}

			unlink($rPIDFile);
		}

		exit(json_encode(['result' => true]));
	case 'probe':
		if (!empty(XCMS::$rRequest['url'])) {
			$rURL = escapeshellcmd(XCMS::$rRequest['url']);
			$rFetchArguments = [];

			if (XCMS::$rRequest['user_agent']) {
				$rFetchArguments[] = sprintf('-user_agent \'%s\'', escapeshellcmd(XCMS::$rRequest['user_agent']));
			}

			if (XCMS::$rRequest['http_proxy']) {
				$rFetchArguments[] = sprintf('-http_proxy \'%s\'', escapeshellcmd(XCMS::$rRequest['http_proxy']));
			}

			if (XCMS::$rRequest['cookies']) {
				$rFetchArguments[] = sprintf('-cookies \'%s\'', escapeshellcmd(XCMS::$rRequest['cookies']));
			}

			$rHeaders = (XCMS::$rRequest['headers'] ? rtrim(XCMS::$rRequest['headers'], "\r\n") . "\r\n" : '');
			$rHeaders .= 'X-XCMS-Prebuffer:1' . "\r\n";
			$rFetchArguments[] = sprintf('-headers %s', escapeshellarg($rHeaders));
			exit(json_encode(['result' => true, 'data' => XCMS::probeStream($rURL, $rFetchArguments, '', false)]));
		}

		exit(json_encode(['result' => false]));
	default:
		exit(json_encode(['result' => false]));
	}
}

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
set_time_limit(0);
require 'init.php';
$rDeny = true;
loadapi();

?>