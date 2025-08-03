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

function checkRunning($rStreamID)
{
	clearstatcache(true);

	if (file_exists(STREAMS_PATH . $rStreamID . '_.monitor')) {
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
	}

	if (empty($rPID)) {
		shell_exec('kill -9 `ps -ef | grep \'XCMS\\[' . (int) $rStreamID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
	}
	else if (file_exists('/proc/' . $rPID)) {
		$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
		if (($rCommand == 'XCMS[' . $rStreamID . ']') && is_numeric($rPID) && (0 < $rPID)) {
			posix_kill($rPID, 9);
		}
	}
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}
if (!@$argc || ($argc <= 1)) {
	exit(0);
}

$rStreamID = (int) $argv[1];
$rRestart = !empty($argv[2]);
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
checkRunning($rStreamID);
set_time_limit(0);
cli_set_process_title('XCMS[' . $rStreamID . ']');
$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id AND t2.server_id = ? WHERE t1.id = ?', SERVER_ID, $rStreamID);

if ($db->num_rows() <= 0) {
	XCMS::stopStream($rStreamID);
	exit();
}

$rStreamInfo = $db->get_row();
$db->query('UPDATE `streams_servers` SET `monitor_pid` = ? WHERE `server_stream_id` = ?', getmypid(), $rStreamInfo['server_stream_id']);

if (XCMS::$rSettings['enable_cache']) {
	XCMS::updateStream($rStreamID);
}

$rPID = (file_exists(STREAMS_PATH . $rStreamID . '_.pid') ? (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid') : $rStreamInfo['pid']);
$rAutoRestart = json_decode($rStreamInfo['auto_restart'], true);
$rPlaylist = STREAMS_PATH . $rStreamID . '_.m3u8';
$rDelayPID = $rStreamInfo['delay_pid'];
$rParentID = $rStreamInfo['parent_id'];
$rStreamProbe = false;
$rSources = [];
$rSegmentTime = XCMS::$rSegmentSettings['seg_time'];
$rPrioritySwitch = false;
$rMaxFails = 0;

if ($rParentID == 0) {
	$rSources = json_decode($rStreamInfo['stream_source'], true);
}

if (0 < $rParentID) {
	$rCurrentSource = 'Loopback: #' . $rParentID;
}
else {
	$rCurrentSource = $rStreamInfo['current_source'];
}

$rLastSegment = $rForceSource = NULL;
$db->query('SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 WHERE t1.stream_id = ? AND t1.argument_id = t2.id', $rStreamID);
$rStreamArguments = $db->get_rows();
if ((0 < $rStreamInfo['delay_minutes']) && ($rStreamInfo['parent_id'] == 0)) {
	$rFolder = DELAY_PATH;
	$rPlaylist = DELAY_PATH . $rStreamID . '_.m3u8';
	$rDelay = true;
}
else {
	$rDelay = false;
	$rFolder = STREAMS_PATH;
}

$rFirstRun = true;
$rTotalCalls = 0;

if (XCMS::isStreamRunning($rPID, $rStreamID)) {
	echo 'Stream is running.' . "\n";

	if ($rRestart) {
		$rTotalCalls = MONITOR_CALLS;
		if (is_numeric($rPID) && (0 < $rPID)) {
			shell_exec('kill -9 ' . (int) $rPID);
		}

		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*');
		file_put_contents(STREAMS_PATH . $rStreamID . '_.monitor', getmypid());
		if ($rDelay && XCMS::isDelayRunning($rDelayPID, $rStreamID) && is_numeric($rDelayPID) && (0 < $rDelayPID)) {
			shell_exec('kill -9 ' . (int) $rDelayPID);
		}

		usleep(50000);
		$rDelayPID = $rPID = 0;
	}
}
else {
	file_put_contents(STREAMS_PATH . $rStreamID . '_.monitor', getmypid());
}

if (XCMS::$rSettings['kill_rogue_ffmpeg']) {
	exec('ps aux | grep -v grep | grep \'/' . $rStreamID . '_.m3u8\' | awk \'{print $2}\'', $rFFMPEG);

	foreach ($rFFMPEG as $rRoguePID) {
		if (is_numeric($rRoguePID) && (0 < (int) $rRoguePID) && ((int) $rRoguePID != (int) $rPID)) {
			shell_exec('kill -9 ' . $rRoguePID . ';');
		}
	}
}

while (true) {
	if (0 < $rPID) {
		$db->close_mysql();
		$rStartedTime = $rDurationChecked = $rAudioChecked = $rCheckedTime = $rBackupsChecked = time();
		$rMD5 = md5_file($rPlaylist);
		$rFailed = XCMS::isStreamRunning($rPID, $rStreamID) && file_exists($rPlaylist);
		$rOrigFrameRate = NULL;

		while (XCMS::isStreamRunning($rPID, $rStreamID) && file_exists($rPlaylist)) {
			if (!empty($rAutoRestart['days']) && !empty($rAutoRestart['at'])) {
				list($rHour, $rMinutes) = explode(':', $rAutoRestart['at']);
				if (in_array(date('l'), $rAutoRestart['days']) && ($rHour == date('H'))) {
					if ($rMinutes == date('i')) {
						echo 'Auto-restart' . "\n";
						XCMS::streamLog($rStreamID, SERVER_ID, 'AUTO_RESTART', $rCurrentSource);
						$rFailed = false;
						break;
					}
				}
			}
			if ($rStreamProbe || (!file_exists(STREAMS_PATH . $rStreamID . '_.dur') && (300 < (time() - $rDurationChecked)))) {
				echo 'Probe Stream' . "\n";
				$rSegment = XCMS::getPlaylistSegments($rPlaylist, 10)[0];

				if (!empty($rSegment)) {
					if ((300 < (time() - $rDurationChecked)) && ($rSegment == $rLastSegment)) {
						XCMS::streamLog($rStreamID, SERVER_ID, 'FFMPEG_ERROR', $rCurrentSource);
						break;
					}

					$rLastSegment = $rSegment;
					$rProbe = XCMS::probeStream($rFolder . $rSegment);

					if (10 < (int) $rProbe['of_duration']) {
						$rProbe['of_duration'] = 10;
					}

					file_put_contents(STREAMS_PATH . $rStreamID . '_.dur', (int) $rProbe['of_duration']);

					if ($rSegmentTime < (int) $rProbe['of_duration']) {
						$rSegmentTime = (int) $rProbe['of_duration'];
					}

					file_put_contents(STREAMS_PATH . $rStreamID . '_.stream_info', json_encode($rProbe, JSON_UNESCAPED_UNICODE));
					$rStreamInfo['stream_info'] = json_encode($rProbe, JSON_UNESCAPED_UNICODE);
				}

				$rStreamProbe = false;
				$rDurationChecked = time();

				if (!file_exists(STREAMS_PATH . $rStreamID . '_.pid')) {
					file_put_contents(STREAMS_PATH . $rStreamID . '_.pid', $rPID);
				}

				if (!file_exists(STREAMS_PATH . $rStreamID . '_.monitor')) {
					file_put_contents(STREAMS_PATH . $rStreamID . '_.monitor', getmypid());
				}
			}
			if (($rStreamInfo['fps_restart'] == 1) && (XCMS::$rSettings['fps_delay'] < (time() - $rStartedTime)) && file_exists(STREAMS_PATH . $rStreamID . '_.progress_check')) {
				echo 'Checking FPS...' . "\n";
				$rFrameRate = (float) json_decode(file_get_contents(STREAMS_PATH . $rStreamID . '_.progress_check'), true)['fps'] ?: 0;

				if (0 < $rFrameRate) {
					if (!$rOrigFrameRate) {
						if (XCMS::$rSettings['fps_check_type'] == 1) {
							$rSegment = XCMS::getPlaylistSegments($rPlaylist, 10)[0];

							if (!empty($rSegment)) {
								$rProbe = XCMS::probeStream($rFolder . $rSegment);
								if (isset($rProbe['codecs']['video']['avg_frame_rate']) || isset($rProbe['codecs']['video']['r_frame_rate'])) {
									$rFrameRate = $rProbe['codecs']['video']['avg_frame_rate'] ?: $rProbe['codecs']['video']['r_frame_rate'];

									if (stripos($rFrameRate, '/') !== false) {
										list($rPartA, $rPartB) = array_map('floatval', explode('/', $rFrameRate));
										$rFrameRate = (float) ($rPartA / $rPartB);
									}
									else {
										$rFrameRate = (float) $rFrameRate;
									}

									if (0 < $rFrameRate) {
										$rOrigFrameRate = $rFrameRate;
									}
								}
							}
						}
						else {
							$rOrigFrameRate = $rFrameRate;
						}
					}
					else if ($rOrigFrameRate && (($rFrameRate * ($rStreamInfo['fps_threshold'] ?: 100)) < $rOrigFrameRate)) {
						echo 'FPS dropped below threshold! Break' . "\n";
						XCMS::streamLog($rStreamID, SERVER_ID, 'FPS_DROP_THRESHOLD', $rCurrentSource);
						break;
					}
				}

				unlink(STREAMS_PATH . $rStreamID . '_.progress_check');
			}
			if ((XCMS::$rSettings['audio_restart_loss'] == 1) && (300 < (time() - $rAudioChecked))) {
				echo 'Checking audio...' . "\n";
				$rSegment = XCMS::getPlaylistSegments($rPlaylist, 10)[0];

				if (!empty($rSegment)) {
					$rProbe = XCMS::probeStream($rFolder . $rSegment);
					if (!isset($rProbe['codecs']['audio']) || empty($rProbe['codecs']['audio'])) {
						echo 'Lost audio! Break' . "\n";
						XCMS::streamLog($rStreamID, SERVER_ID, 'AUDIO_LOSS', $rCurrentSource);
						break;
					}

					$rAudioChecked = time();
				}
				else {
					break;
				}
			}

			if (($rSegmentTime * 6) <= time() - $rCheckedTime) {
				$rNewMD5 = md5_file($rPlaylist);

				if ($rMD5 != $rNewMD5) {
					$rMD5 = $rNewMD5;
					$rCheckedTime = time();
				}
				else {
					break;
				}

				if (XCMS::$rSettings['encrypt_hls']) {
					foreach (glob(STREAMS_PATH . $rStreamID . '_*.ts.enc') as $rFile) {
						if (!file_exists(rtrim($rFile, '.enc'))) {
							unlink($rFile);
						}
					}
				}

				if (count(json_decode($rStreamInfo['stream_info'], true)) == 0) {
					$rStreamProbe = true;
				}

				$rCheckedTime = time();
			}
			if ((XCMS::$rSettings['priority_backup'] == 1) && (1 < count($rSources)) && ($rParentID == 0) && (300 < (time() - $rBackupsChecked))) {
				echo 'Checking backups...' . "\n";
				$rBackupsChecked = time();
				$rKey = array_search($rCurrentSource, $rSources);
				if (!is_numeric($rKey) || (0 < $rKey)) {
					foreach ($rSources as $rSource) {
						if (($rSource == $rCurrentSource) || ($rSource == $rForceSource)) {
							break;
						}

						$rStreamSource = XCMS::parseStreamURL($rSource);
						$rProtocol = strtolower(substr($rStreamSource, 0, strpos($rStreamSource, '://')));
						$rArguments = implode(' ', XCMS::getArguments($rStreamArguments, $rProtocol, 'fetch'));

						if ($rProbe = XCMS::probeStream($rStreamSource, $rArguments)) {
							echo 'Switch priority' . "\n";
							XCMS::streamLog($rStreamID, SERVER_ID, 'PRIORITY_SWITCH', $rSource);
							$rForceSource = $rSource;
							$rPrioritySwitch = true;
							$rFailed = false;
							break 2;
						}
					}
				}
			}
			if (file_exists(SIGNALS_TMP_PATH . $rStreamID . '.force') && ($rParentID == 0)) {
				$rForceID = (int) file_get_contents(SIGNALS_TMP_PATH . $rStreamID . '.force');
				$rStreamSource = XCMS::parseStreamURL($rSources[$rForceID]);

				if ($rCurrentSource != $rSources[$rForceID]) {
					$rProtocol = strtolower(substr($rStreamSource, 0, strpos($rStreamSource, '://')));
					$rArguments = implode(' ', XCMS::getArguments($rStreamArguments, $rProtocol, 'fetch'));

					if ($rProbe = XCMS::probeStream($rStreamSource, $rArguments)) {
						echo 'Force new source' . "\n";
						XCMS::streamLog($rStreamID, SERVER_ID, 'FORCE_SOURCE', $rSources[$rForceID]);
						$rForceSource = $rSources[$rForceID];
						unlink(SIGNALS_TMP_PATH . $rStreamID . '.force');
						$rFailed = false;
						break;
					}
				}

				unlink(SIGNALS_TMP_PATH . $rStreamID . '.force');
			}
			if ($rDelay && ($rStreamInfo['delay_available_at'] <= time()) && !XCMS::isDelayRunning($rDelayPID, $rStreamID)) {
				echo 'Start Delay' . "\n";
				XCMS::streamLog($rStreamID, SERVER_ID, 'DELAY_START');
				$rDelayPID = (int) shell_exec(PHP_BIN . ' ' . CLI_PATH . 'delay.php ' . (int) $rStreamID . ' ' . (int) $rStreamInfo['delay_minutes'] . ' >/dev/null 2>/dev/null & echo $!');
			}

			sleep(1);
		}

		if ($rFailed) {
			XCMS::streamLog($rStreamID, SERVER_ID, 'STREAM_FAILED', $rCurrentSource);
			echo 'Stream failed!' . "\n";
		}

		$db->db_connect();
	}

	if (XCMS::isStreamRunning($rPID, $rStreamID)) {
		echo 'Killing stream...' . "\n";
		if (is_numeric($rPID) && (0 < $rPID)) {
			shell_exec('kill -9 ' . (int) $rPID);
		}

		usleep(50000);
	}

	if (XCMS::isDelayRunning($rDelayPID, $rStreamID)) {
		echo 'Killing stream delay...' . "\n";
		if (is_numeric($rDelayPID) && (0 < $rDelayPID)) {
			shell_exec('kill -9 ' . (int) $rDelayPID);
		}

		usleep(50000);
	}

	while (!XCMS::isStreamRunning($rPID, $rStreamID)) {
		$rStartFailed = false;
		echo 'Restarting...' . "\n";
		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*');
		file_put_contents(STREAMS_PATH . $rStreamID . '_.monitor', getmypid());
		$rOffset = 0;
		$rTotalCalls++;
		if ((0 < $rStreamInfo['parent_id']) && XCMS::$rSettings['php_loopback']) {
			$rData = XCMS::startLoopback($rStreamID);
		}
		else if ((0 < $rStreamInfo['llod']) && $rStreamInfo['on_demand'] && $rFirstRun) {
			if ($rStreamInfo['llod'] == 1) {
				if (!$rForceSource) {
					$rLLODSource = json_decode($rStreamInfo['stream_source'], true)[0];
				}
				else {
					$rLLODSource = $rForceSource;
				}

				$rData = XCMS::startStream($rStreamID, false, $rLLODSource, true);
			}
			else {
				if ($rStreamInfo['parent_id']) {
					$rForceSource = (!is_null(XCMS::$rServers[SERVER_ID]['private_url_ip']) && !is_null(XCMS::$rServers[$rStreamInfo['parent_id']]['private_url_ip']) ? XCMS::$rServers[$rStreamInfo['parent_id']]['private_url_ip'] : XCMS::$rServers[$rStreamInfo['parent_id']]['public_url_ip']) . 'admin/live?stream=' . (int) $rStreamID . '&password=' . urlencode(XCMS::$rSettings['live_streaming_pass']) . '&extension=ts';
				}

				$rData = XCMS::startLLOD($rStreamID, $rStreamInfo, $rStreamInfo['parent_id'] ? [] : $rStreamArguments, $rForceSource);
			}
		}
		else if ($rStreamInfo['type'] == 3) {
			if ((0 < $rPID) && !$rStreamInfo['parent_id'] && (0 < $rStreamInfo['stream_started'])) {
				$rCCInfo = json_decode($rStreamInfo['cc_info'], true);
				if ($rCCInfo && ((time() - $rStreamInfo['stream_started']) < ((int) $rCCInfo[count($rCCInfo) - 1]['finish'] * 0.95))) {
					$rOffset = time() - $rStreamInfo['stream_started'];
				}
			}

			$rData = XCMS::startStream($rStreamID, false, $rForceSource, false, $rOffset);
		}
		else {
			$rData = XCMS::startStream($rStreamID, $rTotalCalls < MONITOR_CALLS, $rForceSource);
		}
		if (is_numeric($rData) && ($rData == 0)) {
			$rStartFailed = true;
			$rMaxFails++;
			if ((0 < XCMS::$rSettings['stop_failures']) && ($rMaxFails == XCMS::$rSettings['stop_failures'])) {
				echo 'Failure limit reached, exiting.' . "\n";
				exit();
			}
		}

		if (!$rData) {
			exit();
		}

		if (!$rStartFailed) {
			$rPID = (int) $rData['main_pid'];

			if ($rPID) {
				file_put_contents(STREAMS_PATH . $rStreamID . '_.pid', $rPID);
			}

			$rPlaylist = $rData['playlist'];
			$rDelay = $rData['delay_enabled'];
			$rStreamInfo['delay_available_at'] = $rData['delay_start_at'];
			$rParentID = $rData['parent_id'];

			if (0 < $rParentID) {
				$rCurrentSource = 'Loopback: #' . $rParentID;
			}
			else {
				$rCurrentSource = trim($rData['stream_source'], '\'"');
			}

			$rOffset = $rData['offset'];
			$rStreamProbe = true;
			echo 'Stream started' . "\n";
			echo $rCurrentSource . "\n";

			if ($rPrioritySwitch) {
				$rForceSource = NULL;
				$rPrioritySwitch = false;
			}

			if ($rDelay) {
				$rFolder = DELAY_PATH;
			}
			else {
				$rFolder = STREAMS_PATH;
			}

			$rFirstSegment = $rFolder . $rStreamID . '_0.ts';
			$rOnDemandStarted = false;
			$rChecks = 0;
			$rMaxChecks = (($rSegmentTime * 3) <= 30 ? $rSegmentTime * 3 : 30);

			if ($rMaxChecks < 20) {
				$rMaxChecks = 20;
			}

			while (true) {
				echo 'Checking for playlist ' . ($rChecks + 1) . ('/' . $rMaxChecks . '...' . "\n");

				if (!XCMS::isStreamRunning($rPID, $rStreamID)) {
					echo 'Ffmpeg stopped running' . "\n";
					$rStartFailed = true;
					break;
				}

				if (file_exists($rPlaylist)) {
					echo 'Playlist exists!' . "\n";
					break;
				}
				else if (file_exists($rFirstSegment) && !$rOnDemandStarted && $rStreamInfo['on_demand']) {
					echo 'Segment exists!' . "\n";
					$rOnDemandStarted = true;
					$rChecks = 0;
					$db->query('UPDATE `streams_servers` SET `stream_status` = 0, `stream_started` = ? WHERE `server_stream_id` = ?', time() - $rOffset, $rStreamInfo['server_stream_id']);
				}

				if ($rChecks == $rMaxChecks) {
					echo 'Reached max failures' . "\n";
					$rStartFailed = true;
					break;
				}

				$rChecks++;
				sleep(1);
			}
		}

		XCMS::$rSettings = XCMS::getSettings();
		if (XCMS::isStreamRunning($rPID, $rStreamID) && !$rStartFailed) {
			echo 'Started! Probe Stream' . "\n";

			if ($rFirstRun) {
				$rFirstRun = false;
				XCMS::streamLog($rStreamID, SERVER_ID, 'STREAM_START', $rCurrentSource);
			}
			else {
				XCMS::streamLog($rStreamID, SERVER_ID, 'STREAM_RESTART', $rCurrentSource);
			}

			$rSegment = $rFolder . XCMS::getPlaylistSegments($rPlaylist, 10)[0];
			$rStreamInfo['stream_info'] = NULL;

			if (file_exists($rSegment)) {
				$rProbe = XCMS::probeStream($rSegment);

				if (10 < (int) $rProbe['of_duration']) {
					$rProbe['of_duration'] = 10;
				}

				file_put_contents(STREAMS_PATH . $rStreamID . '_.dur', (int) $rProbe['of_duration']);

				if ($rSegmentTime < (int) $rProbe['of_duration']) {
					$rSegmentTime = (int) $rProbe['of_duration'];
				}

				if ($rProbe) {
					$rStreamInfo['stream_info'] = json_encode($rProbe, JSON_UNESCAPED_UNICODE);
					$rBitrate = XCMS::getStreamBitrate('live', STREAMS_PATH . $rStreamID . '_.m3u8');
					$rStreamProbe = false;
					$rDurationChecked = time();
				}
			}

			$rCompatible = 0;
			$rAudioCodec = $rVideoCodec = $rResolution = NULL;

			if ($rStreamInfo['stream_info']) {
				$rStreamJSON = json_decode($rStreamInfo['stream_info'], true);
				$rCompatible = (int) XCMS::checkCompatibility($rStreamJSON);
				$rAudioCodec = $rStreamJSON['codecs']['audio']['codec_name'] ?: NULL;
				$rVideoCodec = $rStreamJSON['codecs']['video']['codec_name'] ?: NULL;
				$rResolution = $rStreamJSON['codecs']['video']['height'] ?: NULL;

				if ($rResolution) {
					$rResolution = XCMS::getNearest([240, 360, 480, 576, 720, 1080, 1440, 2160], $rResolution);
				}
			}
			if ($rOnDemandStarted && $rStreamInfo['stream_info'] && $rStreamInfo['on_demand']) {
				$db->query('UPDATE `streams_servers` SET `stream_info` = ?, `compatible` = ?, `audio_codec` = ?, `video_codec` = ?, `resolution` = ?, `bitrate` = ?, `stream_status` = 0 WHERE `server_stream_id` = ?', $rStreamInfo['stream_info'], $rCompatible, $rAudioCodec, $rVideoCodec, $rResolution, (int) $rBitrate, $rStreamInfo['server_stream_id']);
			}
			else if ($rStreamInfo['stream_info']) {
				$db->query('UPDATE `streams_servers` SET `stream_info` = ?, `compatible` = ?, `audio_codec` = ?, `video_codec` = ?, `resolution` = ?, `bitrate` = ?, `stream_status` = 0, `stream_started` = ? WHERE `server_stream_id` = ?', $rStreamInfo['stream_info'], $rCompatible, $rAudioCodec, $rVideoCodec, $rResolution, (int) $rBitrate, time() - $rOffset, $rStreamInfo['server_stream_id']);
			}
			else {
				$db->query('UPDATE `streams_servers` SET `stream_status` = 0, `stream_info` = NULL, `compatible` = 0, `audio_codec` = NULL, `video_codec` = NULL, `resolution` = NULL, `stream_started` = ? WHERE `server_stream_id` = ?', time() - $rOffset, $rStreamInfo['server_stream_id']);
			}

			if (XCMS::$rSettings['enable_cache']) {
				XCMS::updateStream($rStreamID);
			}

			echo 'End start process' . "\n";
		}
		else {
			echo 'Stream start failed...' . "\n";

			if ($rParentID == 0) {
				XCMS::streamLog($rStreamID, SERVER_ID, 'STREAM_START_FAIL', $rCurrentSource);
			}
			if (is_numeric($rPID) && (0 < $rPID) && XCMS::isStreamRunning($rPID, $rStreamID)) {
				shell_exec('kill -9 ' . (int) $rPID);
			}

			$db->query('UPDATE `streams_servers` SET `pid` = null, `stream_status` = 1 WHERE `server_stream_id` = ?;', $rStreamInfo['server_stream_id']);

			if (XCMS::$rSettings['enable_cache']) {
				XCMS::updateStream($rStreamID);
			}

			echo 'Sleep for ' . XCMS::$rSettings['stream_fail_sleep'] . ' seconds...';
			sleep(XCMS::$rSettings['stream_fail_sleep']);
			if (XCMS::$rSettings['on_demand_failure_exit'] && $rStreamInfo['on_demand']) {
				echo 'On-demand failed to run!' . "\n";
				exit();
			}
		}

		if (MONITOR_CALLS <= $rTotalCalls) {
			$rTotalCalls = 0;
		}
	}
}

?>