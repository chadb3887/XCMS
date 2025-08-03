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

	if (XCMS::isRunning()) {
		if (XCMS::$rSettings['redis_handler']) {
			XCMS::connectRedis();
		}

		$rActivePIDs = [];
		$rStreamIDs = [];

		if (XCMS::$rSettings['redis_handler']) {
			$db->query('SELECT t2.stream_display_name, t1.stream_started, t1.stream_info, t2.fps_restart, t1.stream_status, t1.progress_info, t1.stream_id, t1.monitor_pid, t1.on_demand, t1.server_stream_id, t1.pid, servers_attached.attached, t2.vframes_server_id, t2.vframes_pid, t2.tv_archive_server_id, t2.tv_archive_pid FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.direct_source = 0 INNER JOIN `streams_types` t3 ON t3.type_id = t2.type LEFT JOIN (SELECT `stream_id`, COUNT(*) AS `attached` FROM `streams_servers` WHERE `parent_id` = ? AND `pid` IS NOT NULL AND `pid` > 0 AND `monitor_pid` IS NOT NULL AND `monitor_pid` > 0) AS `servers_attached` ON `servers_attached`.`stream_id` = t1.`stream_id` WHERE (t1.pid IS NOT NULL OR t1.stream_status <> 0 OR t1.to_analyze = 1) AND t1.server_id = ? AND t3.live = 1', SERVER_ID, SERVER_ID);
		}
		else {
			$db->query('SELECT t2.stream_display_name, t1.stream_started, t1.stream_info, t2.fps_restart, t1.stream_status, t1.progress_info, t1.stream_id, t1.monitor_pid, t1.on_demand, t1.server_stream_id, t1.pid, clients.online_clients, clients_hls.online_clients_hls, servers_attached.attached, t2.vframes_server_id, t2.vframes_pid, t2.tv_archive_server_id, t2.tv_archive_pid FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.direct_source = 0 INNER JOIN `streams_types` t3 ON t3.type_id = t2.type LEFT JOIN (SELECT stream_id, COUNT(*) as online_clients FROM `lines_live` WHERE `server_id` = ? AND `hls_end` = 0 GROUP BY stream_id) AS clients ON clients.stream_id = t1.stream_id LEFT JOIN (SELECT `stream_id`, COUNT(*) AS `attached` FROM `streams_servers` WHERE `parent_id` = ? AND `pid` IS NOT NULL AND `pid` > 0 AND `monitor_pid` IS NOT NULL AND `monitor_pid` > 0) AS `servers_attached` ON `servers_attached`.`stream_id` = t1.`stream_id` LEFT JOIN (SELECT stream_id, COUNT(*) as online_clients_hls FROM `lines_live` WHERE `server_id` = ? AND `container` = \'hls\' AND `hls_end` = 0 GROUP BY stream_id) AS clients_hls ON clients_hls.stream_id = t1.stream_id WHERE (t1.pid IS NOT NULL OR t1.stream_status <> 0 OR t1.to_analyze = 1) AND t1.server_id = ? AND t3.live = 1', SERVER_ID, SERVER_ID, SERVER_ID, SERVER_ID);
		}

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rStream) {
				echo 'Stream ID: ' . $rStream['stream_id'] . "\n";
				$rStreamIDs[] = $rStream['stream_id'];
				if (!XCMS::isMonitorRunning($rStream['monitor_pid'], $rStream['stream_id']) && !$rStream['on_demand']) {
					echo 'Start monitor...' . "\n\n";
					XCMS::startMonitor($rStream['stream_id']);
					usleep(50000);
					continue;
				}
				if (($rStream['on_demand'] == 1) && ($rStream['attached'] == 0)) {
					if (XCMS::$rSettings['redis_handler']) {
						$rCount = 0;
						$rKeys = XCMS::$redis->zRangeByScore('STREAM#' . $rStream['stream_id'], '-inf', '+inf');

						if (0 < count($rKeys)) {
							$rConnections = array_map('igbinary_unserialize', XCMS::$redis->mGet($rKeys));

							foreach ($rConnections as $rConnection) {
								if ($rConnection && ($rConnection['server_id'] == SERVER_ID)) {
									$rCount++;
								}
							}
						}

						$rStream['online_clients'] = $rCount;
					}

					$rAdminQueue = $rQueue = 0;
					if (XCMS::$rSettings['on_demand_instant_off'] && file_exists(SIGNALS_TMP_PATH . 'queue_' . (int) $rStream['stream_id'])) {
						foreach (igbinary_unserialize(file_get_contents(SIGNALS_TMP_PATH . 'queue_' . (int) $rStream['stream_id'])) ?: [] as $rPID) {
							if (XCMS::isProcessRunning($rPID, 'php-fpm')) {
								$rQueue++;
							}
						}
					}

					if (file_exists(SIGNALS_TMP_PATH . 'admin_' . (int) $rStream['stream_id'])) {
						if ((time() - filemtime(SIGNALS_TMP_PATH . 'admin_' . (int) $rStream['stream_id'])) <= 30) {
							$rAdminQueue = 1;
						}
						else {
							unlink(SIGNALS_TMP_PATH . 'admin_' . (int) $rStream['stream_id']);
						}
					}
					if (($rQueue == 0) && ($rAdminQueue == 0) && ($rStream['online_clients'] == 0) && (file_exists(STREAMS_PATH . $rStream['stream_id'] . '_.m3u8') || ((int) XCMS::$rSettings['on_demand_wait_time'] < (time() - (int) $rStream['stream_started'])) || ($rStream['stream_status'] == 1))) {
						echo 'Stop on-demand stream...' . "\n\n";
						XCMS::stopStream($rStream['stream_id'], true);
						continue;
					}
				}
				if (($rStream['vframes_server_id'] == SERVER_ID) && !XCMS::isThumbnailRunning($rStream['vframes_pid'], $rStream['stream_id'])) {
					echo 'Start Thumbnail...' . "\n";
					XCMS::startThumbnail($rStream['stream_id']);
				}
				if (($rStream['tv_archive_server_id'] == SERVER_ID) && !XCMS::isArchiveRunning($rStream['tv_archive_pid'], $rStream['stream_id'])) {
					echo 'Start TV Archive...' . "\n";
					shell_exec(PHP_BIN . ' ' . CLI_PATH . 'archive.php ' . (int) $rStream['stream_id'] . ' >/dev/null 2>/dev/null & echo $!');
				}

				foreach (glob(STREAMS_PATH . $rStream['stream_id'] . '_*.ts.enc') as $rFile) {
					if (!file_exists(rtrim($rFile, '.enc'))) {
						unlink($rFile);
					}
				}

				if (file_exists(STREAMS_PATH . $rStream['stream_id'] . '_.pid')) {
					$rPID = (int) file_get_contents(STREAMS_PATH . $rStream['stream_id'] . '_.pid');
				}
				else {
					$rPID = (int) shell_exec('ps aux | grep -v grep | grep \'/' . (int) $rStream['stream_id'] . '_.m3u8\' | awk \'{print $2}\'');
				}

				$rActivePIDs[] = (int) $rPID;
				$rPlaylist = STREAMS_PATH . $rStream['stream_id'] . '_.m3u8';
				if (XCMS::isStreamRunning($rPID, $rStream['stream_id']) && file_exists($rPlaylist)) {
					echo 'Update Stream Information...' . "\n";
					$rBitrate = XCMS::getStreamBitrate('live', STREAMS_PATH . $rStream['stream_id'] . '_.m3u8');

					if (file_exists(STREAMS_PATH . $rStream['stream_id'] . '_.progress')) {
						$rProgress = file_get_contents(STREAMS_PATH . $rStream['stream_id'] . '_.progress');
						unlink(STREAMS_PATH . $rStream['stream_id'] . '_.progress');

						if ($rStream['fps_restart']) {
							file_put_contents(STREAMS_PATH . $rStream['stream_id'] . '_.progress_check', $rProgress);
						}
					}
					else {
						$rProgress = $rStream['progress_info'];
					}

					if (file_exists(STREAMS_PATH . $rStream['stream_id'] . '_.stream_info')) {
						$rStreamInfo = file_get_contents(STREAMS_PATH . $rStream['stream_id'] . '_.stream_info');
						unlink(STREAMS_PATH . $rStream['stream_id'] . '_.stream_info');
					}
					else {
						$rStreamInfo = $rStream['stream_info'];
					}

					$rCompatible = 0;
					$rAudioCodec = $rVideoCodec = $rResolution = NULL;

					if ($rStreamInfo) {
						$rStreamJSON = json_decode($rStreamInfo, true);
						$rCompatible = (int) XCMS::checkCompatibility($rStreamJSON);
						$rAudioCodec = $rStreamJSON['codecs']['audio']['codec_name'] ?: NULL;
						$rVideoCodec = $rStreamJSON['codecs']['video']['codec_name'] ?: NULL;
						$rResolution = $rStreamJSON['codecs']['video']['height'] ?: NULL;

						if ($rResolution) {
							$rResolution = XCMS::getNearest([240, 360, 480, 576, 720, 1080, 1440, 2160], $rResolution);
						}
					}

					if ($rPID != $rStream['pid']) {
						$db->query('UPDATE `streams_servers` SET `pid` = ?, `progress_info` = ?, `stream_info` = ?, `compatible` = ?, `bitrate` = ?, `audio_codec` = ?, `video_codec` = ?, `resolution` = ? WHERE `server_stream_id` = ?', $rPID, $rProgress, $rStreamInfo, $rCompatible, $rBitrate, $rAudioCodec, $rVideoCodec, $rResolution, $rStream['server_stream_id']);
					}
					else {
						$db->query('UPDATE `streams_servers` SET `progress_info` = ?, `stream_info` = ?, `compatible` = ?, `bitrate` = ?, `audio_codec` = ?, `video_codec` = ?, `resolution` = ? WHERE `server_stream_id` = ?', $rProgress, $rStreamInfo, $rCompatible, $rBitrate, $rAudioCodec, $rVideoCodec, $rResolution, $rStream['server_stream_id']);
					}
				}

				echo "\n";
			}
		}

		$db->query('SELECT `streams`.`id` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams`.`direct_source` = 1 AND `streams`.`direct_proxy` = 1 AND `streams_servers`.`server_id` = ? AND `streams_servers`.`pid` > 0;', SERVER_ID);

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rStream) {
				if (file_exists(STREAMS_PATH . $rStream['id'] . '.analyse')) {
					$rFFProbeOutput = XCMS::probeStream(STREAMS_PATH . $rStream['id'] . '.analyse');

					if ($rFFProbeOutput) {
						$rBitrate = $rFFProbeOutput['bitrate'] / 1024;
						$rCompatible = (int) XCMS::checkCompatibility($rFFProbeOutput);
						$rAudioCodec = $rFFProbeOutput['codecs']['audio']['codec_name'] ?: NULL;
						$rVideoCodec = $rFFProbeOutput['codecs']['video']['codec_name'] ?: NULL;
						$rResolution = $rFFProbeOutput['codecs']['video']['height'] ?: NULL;

						if ($rResolution) {
							$rResolution = XCMS::getNearest([240, 360, 480, 576, 720, 1080, 1440, 2160], $rResolution);
						}
					}

					echo 'Stream ID: ' . $rStream['id'] . "\n";
					echo 'Update Stream Information...' . "\n";
					$db->query('UPDATE `streams_servers` SET `bitrate` = ?, `stream_info` = ?, `audio_codec` = ?, `video_codec` = ?, `resolution` = ?, `compatible` = ? WHERE `stream_id` = ? AND `server_id` = ?', $rBitrate, json_encode($rFFProbeOutput), $rAudioCodec, $rVideoCodec, $rResolution, $rCompatible, $rStream['id'], SERVER_ID);
				}

				$rUUIDs = [];
				$rConnections = XCMS::getConnections(SERVER_ID, NULL, $rStream['id']);

				foreach ($rConnections as $rUserID => $rItems) {
					foreach ($rItems as $rItem) {
						$rUUIDs[] = $rItem['uuid'];
					}
				}

				if ($rHandle = opendir(CONS_TMP_PATH . $rStream['id'] . '/')) {
					while (($rFilename = readdir($rHandle)) !== false) {
						if (($rFilename != '.') && ($rFilename != '..')) {
							if (!in_array($rFilename, $rUUIDs)) {
								unlink(CONS_TMP_PATH . $rStream['id'] . '/' . $rFilename);
							}
						}
					}

					closedir($rHandle);
				}
			}
		}

		$db->query('SELECT `stream_id` FROM `streams_servers` WHERE `on_demand` = 1 AND `server_id` = ?;', SERVER_ID);
		$rOnDemandIDs = array_keys($db->get_rows(true, 'stream_id'));
		$rProcesses = shell_exec('ps aux | grep XCMS');

		if (preg_match_all('/XCMS\\[(.*)\\]/', $rProcesses, $rMatches)) {
			$rRemove = array_diff($rMatches[1], $rStreamIDs);
			$rRemove = array_diff($rRemove, $rOnDemandIDs);

			foreach ($rRemove as $rStreamID) {
				if (!is_numeric($rStreamID)) {
					continue;
				}

				echo 'Kill Stream ID: ' . $rStreamID . "\n";
				shell_exec('kill -9 `ps -ef | grep \'/' . (int) $rStreamID . '_.m3u8\\|XCMS\\[' . (int) $rStreamID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
				shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*');
			}
		}

		if (XCMS::$rSettings['kill_rogue_ffmpeg']) {
			exec('ps aux | grep -v grep | grep \'/*_.m3u8\' | awk \'{print $2}\'', $rFFMPEG);

			foreach ($rFFMPEG as $rPID) {
				if (is_numeric($rPID) && (0 < (int) $rPID) && !in_array($rPID, $rActivePIDs)) {
					echo 'Kill Roque PID: ' . $rPID . "\n";
					shell_exec('kill -9 ' . $rPID . ';');
				}
			}
		}
	}
	else {
		echo 'XCMS not running...' . "\n";
	}
}

function shutdown()
{
	global $db;
	global $rIdentifier;

	if (is_object($db)) {
		$db->close_mysql();
	}

	@unlink($rIdentifier);
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
cli_set_process_title('XCMS[Live Checker]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();

?>