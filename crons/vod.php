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
	$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t3 ON t3.stream_id = t1.id LEFT JOIN `profiles` t2 ON t2.profile_id = t1.transcode_profile_id WHERE t1.type = 3 AND t3.server_id = ? AND t3.parent_id IS NULL;', SERVER_ID);

	if (0 < $db->num_rows()) {
		$rStreams = $db->get_rows();

		foreach ($rStreams as $rStream) {
			echo "\n\n" . '[*] Checking Stream ' . $rStream['stream_display_name'] . "\n";
			$rPID = (int) file_get_contents(CREATED_PATH . $rStream['id'] . '_.create');
			if ($rPID && XCMS::isPIDRunning(SERVER_ID, $rPID, PHP_BIN)) {
				echo "\t" . 'Build Is Still Going!' . "\n";
			}
			else {
				$rSourcesLeft = array_diff(json_decode($rStream['stream_source'], true), json_decode($rStream['cchannel_rsources'], true));

				if (0 < count($rSourcesLeft)) {
					echo "\t" . 'Needs Updating!' . "\n";
					XCMS::queueChannel($rStream['id']);
				}
				else {
					if (file_exists(CREATED_PATH . $rStream['id'] . '_.info')) {
						$rCCInfo = file_get_contents(CREATED_PATH . $rStream['id'] . '_.info');
						$db->query('UPDATE `streams_servers` SET `cc_info` = ? WHERE `server_id` = ? AND `stream_id` = ?;', $rCCInfo, SERVER_ID, $rStream['id']);
						unlink(CREATED_PATH . $rStream['id'] . '_.info');
					}

					echo "\t" . 'Build Finished' . "\n";
				}
			}
		}
	}

	$db->query('SELECT `id` FROM `recordings` WHERE `status` NOT IN (1,2) AND `source_id` = ? AND ((`start` <= UNIX_TIMESTAMP() AND `end` > UNIX_TIMESTAMP()) OR (`archive` = 1));', SERVER_ID);

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			shell_exec(PHP_BIN . ' ' . INCLUDES_PATH . 'cli/record.php ' . (int) $rRow['id'] . ' > /dev/null 2>/dev/null &');
		}
	}

	exec('ps ax | grep \'ffmpeg\' | awk \'{print $1}\'', $rPIDs);
	$db->query('SELECT COUNT(*) AS `count` FROM `streams_servers` WHERE `to_analyze` = 1 AND `server_id` = ?', SERVER_ID);
	$rCount = $db->get_row()['count'];

	if (0 < $rCount) {
		$rSteps = range(0, $rCount, 1000);

		if (!$rSteps) {
			$rSteps = [0];
		}

		foreach ($rSteps as $rStep) {
			$db->query('SELECT t1.*,t2.* FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.direct_source = 0 INNER JOIN `streams_types` t3 ON t3.type_id = t2.type AND t3.live = 0 WHERE t1.to_analyze = 1 AND t1.server_id = ? LIMIT ' . $rStep . ', 1000', SERVER_ID);

			if (0 < $db->num_rows()) {
				$rRows = $db->get_rows();

				foreach ($rRows as $rRow) {
					echo '[*] Checking Movie ' . $rRow['stream_display_name'] . ' ' . "\t\t" . '---> ';

					if (in_array($rRow['pid'], $rPIDs)) {
						echo 'ENCODING...' . "\n";
					}
					else {
						$rMoviePath = VOD_PATH . (int) $rRow['stream_id'] . '.' . escapeshellcmd($rRow['target_container']);

						if ($rFFProbe = XCMS::probeStream($rMoviePath)) {
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

							$db->query('UPDATE `streams` SET `movie_properties` = ? WHERE `id` = ?', json_encode($rMovieProperties, JSON_UNESCAPED_UNICODE), $rRow['stream_id']);
							$db->query('UPDATE `streams_servers` SET `bitrate` = ?,`to_analyze` = 0,`stream_status` = 0,`stream_info` = ?,`audio_codec` = ?,`video_codec` = ?,`resolution` = ?,`compatible` = ? WHERE `server_stream_id` = ?', $rBitrate, json_encode($rFFProbe, JSON_UNESCAPED_UNICODE), $rAudioCodec, $rVideoCodec, $rResolution, $rCompatible, $rRow['server_stream_id']);
							echo 'VALID' . "\n";
						}
						else {
							$db->query('UPDATE `streams_servers` SET `to_analyze` = 0,`stream_status` = 1 WHERE `server_stream_id` = ?', $rRow['server_stream_id']);
							echo 'BROKEN' . "\n";
						}

						XCMS::updateStream($rRow['stream_id']);
					}
				}
			}
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
cli_set_process_title('XCMS[VOD]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();

?>