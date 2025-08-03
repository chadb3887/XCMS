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

	if ((int) XCMS::$rSettings['cleanup'] == 1) {
		$rStreams = [];
		$db->query('SELECT `id` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams`.`type` IN (1,3,4) AND `streams_servers`.`server_id` = ?;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rStreams[] = (int) $rRow['id'];
		}

		foreach (glob(STREAMS_PATH . '*') as $rFilename) {
			$rID = (int) rtrim(explode('.', basename($rFilename))[0], '_') . "\n";
			if ((0 < $rID) && !in_array($rID, $rStreams)) {
				echo 'Deleting: ' . $rFilename . "\n";
				unlink($rFilename);
			}
		}

		$rArchive = [];
		$db->query('SELECT `id`, `tv_archive_duration` FROM `streams` WHERE `type` = 1 AND `tv_archive_server_id` = ? AND `tv_archive_duration` > 0;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rArchive[(int) $rRow['id']] = $rRow['tv_archive_duration'];
		}

		date_default_timezone_set('UTC');

		foreach (glob(ARCHIVE_PATH . '*') as $rStreamID) {
			$rID = (int) basename($rStreamID);
			if ((0 < $rID) && is_dir(ARCHIVE_PATH . $rID)) {
				if (!isset($rArchive[$rID])) {
					echo 'Deleting: ' . $rStreamID . "\n";
					exec('rm -rf ' . $rStreamID);
				}
				else {
					$rDuration = $rArchive[$rID];
					$rDeleteBefore = (time() - ($rDuration * 86400)) + 3600;

					foreach (glob(ARCHIVE_PATH . $rID . '/*') as $rArchiveFile) {
						list($rDate, $rTime) = explode(':', explode('.', basename($rArchiveFile))[0]);
						list($rHour, $rMinute) = explode('-', $rTime);
						$rFileTime = strtotime($rDate . ' ' . $rHour . ':' . $rMinute . ':00');

						if ($rFileTime < $rDeleteBefore) {
							echo 'Deleting: ' . $rArchiveFile . "\n";
							unlink($rArchiveFile);
						}
					}
				}
			}
		}

		$rCreated = [];
		$db->query('SELECT `id` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams`.`type` = 3 AND `streams_servers`.`server_id` = ?;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rCreated[] = (int) $rRow['id'];
		}

		foreach (glob(CREATED_PATH . '*') as $rFilename) {
			$rID = (int) rtrim(explode('.', basename($rFilename))[0], '_') . "\n";
			if ((0 < $rID) && !in_array($rID, $rCreated)) {
				echo 'Deleting: ' . $rFilename . "\n";
				unlink($rFilename);
			}
		}
	}

	if ((int) XCMS::$rSettings['check_vod'] == 1) {
		$db->query('SELECT `server_stream_id`, `id`, `target_container`, `movie_properties`, `stream_status` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `server_id` = ? AND `type` IN (2,5) AND `streams`.`direct_source` = 0 AND `streams_servers`.`pid` > 0;', SERVER_ID);

		if (0 < $db->num_rows()) {
			$rRows = $db->get_rows();

			foreach ($rRows as $rRow) {
				$rMoviePath = VOD_PATH . $rRow['id'] . '.' . $rRow['target_container'];

				if ($rRow['stream_status'] == 0) {
					if (!file_exists($rMoviePath)) {
						echo 'BAD MOVIE' . "\n";
						$db->query('UPDATE `streams_servers` SET `stream_status` = 1 WHERE `server_stream_id` = ?', $rRow['server_stream_id']);
						XCMS::updateStream($rRow['id']);
					}
				}
				else if ($rRow['stream_status'] == 1) {
					if (file_exists($rMoviePath) && ($rFFProbe = XCMS::probeStream($rMoviePath))) {
						$rDuration = (isset($rFFProbe['duration']) ? $rFFProbe['duration'] : 0);
						sscanf($rDuration, '%d:%d:%d', $rHours, $rMinutes, $rSeconds);
						$rSeconds = (isset($rSeconds) ? ($rHours * 3600) + ($rMinutes * 60) + $rSeconds : ($rHours * 60) + $rMinutes);
						$rSize = filesize($rMoviePath);
						$rBitrate = round(($rSize * 0.008) / $rSeconds);
						$rMovieProperties = json_decode($rRow['movie_properties'], true);

						if (!is_array($rMovieProperties)) {
							$rMovieProperties = [];
						}
						if (!isset($rMovieProperties['duration_secs']) || ($rSeconds != $rMovieProperties['duration_secs'])) {
							$rMovieProperties['duration_secs'] = $rSeconds;
							$rMovieProperties['duration'] = $rDuration;
						}
						if (!isset($rMovieProperties['video']) || ($rFFProbe['codecs']['video']['codec_name'] != $rMovieProperties['video'])) {
							$rMovieProperties['video'] = $rFFProbe['codecs']['video'];
						}
						if (!isset($rMovieProperties['audio']) || ($rFFProbe['codecs']['audio']['codec_name'] != $rMovieProperties['audio'])) {
							$rMovieProperties['audio'] = $rFFProbe['codecs']['audio'];
						}

						if (XCMS::$rSettings['extract_subtitles']) {
							if (!isset($rMovieProperties['subtitle']) || ($rFFProbe['codecs']['subtitle']['codec_name'] != $rMovieProperties['subtitle'])) {
								$rMovieProperties['subtitle'] = $rFFProbe['codecs']['subtitle'];
							}
						}
						if (!isset($rMovieProperties['bitrate']) || ($rBitrate != $rMovieProperties['bitrate'])) {
							if (0 < $rBitrate) {
								$rMovieProperties['bitrate'] = $rBitrate;
							}
							else {
								$rBitrate = $rMovieProperties['bitrate'];
							}
						}
						if (isset($rFFProbe['codecs']['subtitle']) && XCMS::$rSettings['extract_subtitles']) {
							$i = 0;

							foreach ($rFFProbe['codecs']['subtitle'] as $rSubtitle) {
								XCMS::extractSubtitle($rRow['stream_id'], $rMoviePath, $i);
								$i++;
							}
						}

						$rCompatible = 0;
						$rAudioCodec = $rVideoCodec = $rResolution = NULL;

						if ($rFFProbe) {
							$rCompatible = (int) XCMS::checkCompatibility($rFFProbe);
							$rAudioCodec = $rFFProbe['codecs']['audio']['codec_name'] ?: NULL;
							$rVideoCodec = $rFFProbe['codecs']['video']['codec_name'] ?: NULL;
							$rResolution = $rFFProbe['codecs']['video']['height'] ?: NULL;

							if ($rResolution) {
								$rResolution = XCMS::getNearest([240, 360, 480, 576, 720, 1080, 1440, 2160], $rResolution);
							}
						}

						$db->query('UPDATE `streams` SET `movie_properties` = ? WHERE `id` = ?', json_encode($rMovieProperties, JSON_UNESCAPED_UNICODE), $rRow['id']);
						$db->query('UPDATE `streams_servers` SET `bitrate` = ?,`to_analyze` = 0,`stream_status` = 0,`stream_info` = ?, `audio_codec` = ?, `video_codec` = ?, `resolution` = ?, `compatible` = ? WHERE `server_stream_id` = ?', $rBitrate, json_encode($rFFProbe, JSON_UNESCAPED_UNICODE), $rAudioCodec, $rVideoCodec, $rResolution, $rCompatible, $rRow['server_stream_id']);
						XCMS::updateStream($rRow['id']);
						echo 'VALID MOVIE' . "\n";
					}
				}
			}
		}

		$db->query('SELECT `id`, `stream_display_name`, `server_stream_id` FROM `streams` t1 INNER JOIN `streams_servers` t3 ON t3.stream_id = t1.id LEFT JOIN `profiles` t2 ON t2.profile_id = t1.transcode_profile_id WHERE t1.type = 3 AND t3.server_id = ? AND JSON_CONTAINS(t3.cchannel_rsources, t1.stream_source) AND JSON_CONTAINS(t1.stream_source, t3.cchannel_rsources) AND t3.pids_create_channel = \'[]\';', SERVER_ID);

		if (0 < $db->num_rows()) {
			$rStreams = $db->get_rows();

			foreach ($rStreams as $rStream) {
				echo "\n\n" . '[*] Checking Channel ' . $rStream['stream_display_name'] . "\n";

				if (file_exists(CREATED_PATH . $rStream['id'] . '_.list')) {
					$rList = explode("\n", file_get_contents(CREATED_PATH . $rStream['id'] . '_.list'));
					$rExisting = glob(CREATED_PATH . $rStream['id'] . '*.*');
					$rFailure = false;
					$rActualFiles = [];

					foreach ($rList as $rItem) {
						$rFilename = trim(explode('\'', explode('\'', $rItem)[1])[0]);

						if (0 < strlen($rFilename)) {
							if (in_array($rFilename, $rExisting)) {
								$rActualFiles[] = $rFilename;
							}
							else {
								$rFailure = true;
							}
						}
					}

					if ($rFailure) {
						echo 'BAD CHANNEL' . "\n";
						$db->query('UPDATE `streams_servers` SET `cchannel_rsources` = ? WHERE `server_stream_id` = ?;', json_encode($rActualFiles, JSON_UNESCAPED_UNICODE), $rStream['server_stream_id']);
						XCMS::updateStream($rStream['id']);
					}
				}
				else {
					echo 'BAD CHANNEL' . "\n";
					$db->query('UPDATE `streams_servers` SET `cchannel_rsources` = \'[]\' WHERE `server_stream_id` = ?;', $rStream['server_stream_id']);
					XCMS::updateStream($rStream['id']);
				}
			}
		}
	}

	$rTables = [
		'lines_activity' => ['keep_activity', 'date_end'],
		'lines_logs'     => ['keep_client', 'date'],
		'login_logs'     => ['keep_login', 'date'],
		'streams_errors' => ['keep_errors', 'date'],
		'streams_logs'   => ['keep_restarts', 'date'],
		'ondemand_check' => ['on_demand_scan_keep', 'date']
	];

	foreach ($rTables as $rTable => $rArray) {
		if (XCMS::$rSettings[$rArray[0]] && (0 < XCMS::$rSettings[$rArray[0]])) {
			$rDeleteBefore = time() - (int) XCMS::$rSettings[$rArray[0]];
			$db->query('DELETE FROM `' . $rTable . '` WHERE `' . $rArray[1] . '` < ?;', $rDeleteBefore);
		}
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
cli_set_process_title('XCMS[Cleanup]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rTimeout = 3600;
set_time_limit($rTimeout);
ini_set('max_execution_time', $rTimeout);
loadcron();

?>