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

function prepareColumn($rValue)
{
	return strtolower(preg_replace('/[^a-z0-9_]+/i', '', $rValue));
}

function prepareArray($rArray)
{
	$rUpdate = $rColumns = $rPlaceholder = $rData = [];

	foreach (array_keys($rArray) as $rKey) {
		$rColumns[] = '`' . preparecolumn($rKey) . '`';
		$rUpdate[] = '`' . preparecolumn($rKey) . '` = ?';
	}

	foreach (array_values($rArray) as $rValue) {
		if (is_array($rValue)) {
			$rValue = json_encode($rValue, JSON_UNESCAPED_UNICODE);
		}

		$rPlaceholder[] = '?';
		$rData[] = $rValue;
	}

	return ['placeholder' => implode(',', $rPlaceholder), 'columns' => implode(',', $rColumns), 'data' => $rData, 'update' => implode(',', $rUpdate)];
}

function verifyPostTable($rTable, $rData = [], $rOnlyExisting = false)
{
	global $db;
	$rReturn = [];
	$db->query('SELECT `column_name`, `column_default`, `is_nullable`, `data_type` FROM `information_schema`.`columns` WHERE `table_schema` = (SELECT DATABASE()) AND `table_name` = ? ORDER BY `ordinal_position`;', $rTable);

	foreach ($db->get_rows() as $rRow) {
		if ($rRow['column_default'] == 'NULL') {
			$rRow['column_default'] = NULL;
		}

		$rForceDefault = false;
		if (($rRow['is_nullable'] == 'NO') && !$rRow['column_default']) {
			if (in_array($rRow['data_type'], ['int' => true, 'float' => true, 'tinyint' => true, 'double' => true, 'decimal' => true, 'smallint' => true, 'mediumint' => true, 'bigint' => true, 'bit' => true])) {
				$rRow['column_default'] = 0;
			}
			else {
				$rRow['column_default'] = '';
			}

			$rForceDefault = true;
		}

		if (array_key_exists($rRow['column_name'], $rData)) {
			if (empty($rData[$rRow['column_name']]) && !is_numeric($rData[$rRow['column_name']]) && is_null($rRow['column_default'])) {
				$rReturn[$rRow['column_name']] = ($rForceDefault ? $rRow['column_default'] : NULL);
			}
			else {
				$rReturn[$rRow['column_name']] = $rData[$rRow['column_name']];
			}
		}
		else if (!$rOnlyExisting) {
			$rReturn[$rRow['column_name']] = $rRow['column_default'];
		}
	}

	return $rReturn;
}

function downloadImage($rFilename, $rImage)
{
	if ((0 < strlen($rImage)) && (substr(strtolower($rImage), 0, 4) == 'http')) {
		$rExt = 'jpg';
		$rPrevPath = IMAGES_PATH . $rFilename . '.' . $rExt;

		if (file_exists($rPrevPath)) {
			return 's:' . SERVER_ID . ':/images/' . $rFilename . '.' . $rExt;
		}
		else {
			$rCurl = curl_init();
			curl_setopt($rCurl, CURLOPT_URL, $rImage);
			curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($rCurl, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($rCurl, CURLOPT_TIMEOUT, 5);
			$rData = curl_exec($rCurl);

			if (0 < strlen($rData)) {
				$rPath = IMAGES_PATH . $rFilename . '.' . $rExt;
				file_put_contents($rPath, $rData);

				if (file_exists($rPath)) {
					return 's:' . SERVER_ID . ':/images/' . $rFilename . '.' . $rExt;
				}
			}
		}
	}

	return NULL;
}

function getNextOrder()
{
	global $db;
	$db->query('SELECT MAX(`order`) AS `order` FROM `streams`;');

	if ($db->num_rows() == 1) {
		return (int) $db->get_row()['order'] + 1;
	}

	return 0;
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

function addToBouquet($rBouquetID, $rID)
{
	global $db;
	$rBouquet = getbouquet($rBouquetID);
	$rMovies = json_decode($rBouquet['bouquet_movies'], true);

	if (!in_array($rID, $rMovies)) {
		$rMovies[] = (int) $rID;
	}

	$rMovies = '[' . implode(',', array_map('intval', $rMovies)) . ']';
	$db->query('UPDATE `bouquets` SET `bouquet_movies` = ? WHERE `id` = ?;', $rMovies, $rBouquetID);
}

function loadCLI()
{
	global $db;
	global $rRecordID;
	$db->query('SELECT * FROM `recordings` WHERE `id` = ?;', $rRecordID);

	if (0 < $db->num_rows()) {
		$rFails = $rBytesWritten = 0;
		$rComplete = false;
		$rRecordInfo = $db->get_row();
		if (((($rRecordInfo['start'] - 60) <= time()) && (time() <= $rRecordInfo['end'])) || $rRecordInfo['archive']) {
			$rPID = (file_exists(STREAMS_PATH . $rRecordInfo['stream_id'] . '_.pid') ? (int) file_get_contents(STREAMS_PATH . $rRecordInfo['stream_id'] . '_.pid') : 0);
			$rPlaylist = STREAMS_PATH . $rRecordInfo['stream_id'] . '_.m3u8';
			if ((0 < $rPID) && file_exists($rPlaylist)) {
				$db->query('UPDATE `recordings` SET `status` = 1 WHERE `id` = ?;', $rRecordID);
				$db->close_mysql();

				while (XCMS::isStreamRunning($rPID, $rRecordInfo['stream_id']) && file_exists($rPlaylist)) {
					if ($rRecordInfo['archive']) {
						$rDuration = (int) (($rRecordInfo['end'] - $rRecordInfo['start']) / 60);
						$rFP = @fopen('http://127.0.0.1:' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/admin/timeshift?password=' . XCMS::$rSettings['live_streaming_pass'] . '&stream=' . $rRecordInfo['stream_id'] . '&start=' . $rRecordInfo['start'] . '&duration=' . $rDuration . '&extension=ts', 'r');
					}
					else {
						$rFP = @fopen('http://127.0.0.1:' . XCMS::$rServers[SERVER_ID]['http_broadcast_port'] . '/admin/live?password=' . XCMS::$rSettings['live_streaming_pass'] . '&stream=' . $rRecordInfo['stream_id'] . '&extension=ts', 'r');
					}

					if ($rFP) {
						echo 'Recording...' . "\n";

						if ($rRecordInfo['archive']) {
							$rWriteFile = fopen(ARCHIVE_PATH . $rRecordID . '.ts', 'w');
						}
						else {
							$rWriteFile = fopen(ARCHIVE_PATH . $rRecordID . '.ts', 'a');
						}

						while (!feof($rFP)) {
							$rData = stream_get_line($rFP, 4096);

							if (!empty($rData)) {
								$rBytesWritten += $rData;
								fwrite($rWriteFile, $rData);
								fflush($rWriteFile);
								$rFails = 0;
							}
							if (($rRecordInfo['end'] <= time()) && !$rRecordInfo['archive']) {
								$rComplete = true;
								fclose($rWriteFile);
								break 2;
							}
						}

						fclose($rFP);

						if ($rRecordInfo['archive']) {
							$rComplete = true;
							break;
						}
					}

					$rFails++;

					if ($rFails == 5) {
						if (10485760 <= $rBytesWritten) {
							$rComplete = true;
						}

						echo 'Too many fails!' . "\n";
						break;
					}

					echo 'Broken pipe! Restarting...' . "\n";
					sleep(1);
				}
			}
			else {
				echo 'Channel is not running.' . "\n";
			}
		}
		else {
			echo 'Programme is not currently airing.' . "\n";
		}

		if (!$db->connected) {
			$db->db_connect();
		}

		if ($rComplete) {
			if (file_exists(ARCHIVE_PATH . $rRecordID . '.ts') && (0 < filesize(ARCHIVE_PATH . $rRecordID . '.ts'))) {
				echo 'Recording complete! Converting to MP4...' . "\n";

				if (!empty($rRecordInfo['stream_icon'])) {
					$rRecordInfo['stream_icon'] = downloadimage($rRecordInfo['stream_icon']);
				}

				$rSeconds = (int) ($rRecordInfo['end'] - $rRecordInfo['start']);
				$rImportArray = verifyposttable('streams');
				$rImportArray['type'] = 2;
				$rImportArray['stream_source'] = '[]';
				$rImportArray['target_container'] = 'mp4';
				$rImportArray['stream_display_name'] = $rRecordInfo['title'];
				$rImportArray['year'] = date('Y');
				$rImportArray['movie_properties'] = [
					'kinopoisk_url'          => NULL,
					'tmdb_id'                => NULL,
					'name'                   => $rRecordInfo['title'],
					'o_name'                 => $rRecordInfo['title'],
					'cover_big'              => $rRecordInfo['stream_icon'],
					'movie_image'            => $rRecordInfo['stream_icon'],
					'release_date'           => date('Y-m-d', $rRecordInfo['start']),
					'episode_run_time'       => (int) ($rSeconds / 60),
					'youtube_trailer'        => NULL,
					'director'               => '',
					'actors'                 => '',
					'cast'                   => '',
					'description'            => trim($rRecordInfo['description']),
					'plot'                   => trim($rRecordInfo['description']),
					'age'                    => '',
					'mpaa_rating'            => '',
					'rating_count_kinopoisk' => 0,
					'country'                => '',
					'genre'                  => '',
					'backdrop_path'          => [],
					'duration_secs'          => $rSeconds,
					'duration'               => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
					'video'                  => [],
					'audio'                  => [],
					'bitrate'                => 0,
					'rating'                 => 0
				];
				$rImportArray['rating'] = 0;
				$rImportArray['read_native'] = 0;
				$rImportArray['movie_symlink'] = 0;
				$rImportArray['remove_subtitles'] = 0;
				$rImportArray['transcode_profile_id'] = 0;
				$rImportArray['order'] = getnextorder();
				$rImportArray['added'] = time();
				$rImportArray['category_id'] = '[' . implode(',', array_map('intval', json_decode($rRecordInfo['category_id'], true))) . ']';
				$rPrepare = preparearray($rImportArray);
				$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if ($db->query($rQuery, ...$rPrepare['data'])) {
					$rInsertID = $db->last_insert_id();
					$rRet = shell_exec(FFMPEG_BIN_40 . ' -i \'' . ARCHIVE_PATH . $rRecordID . '.ts' . '\' -c:v copy -c:a copy \'' . VOD_PATH . $rInsertID . '.mp4' . '\'');
					@unlink(ARCHIVE_PATH . $rRecordID . '.ts');

					if (file_exists(VOD_PATH . $rInsertID . '.mp4')) {
						foreach (json_decode($rRecordInfo['bouquets'], true) as $rBouquet) {
							addtobouquet($rBouquet, $rInsertID);
						}

						$db->query('UPDATE `streams` SET `stream_source` = ? WHERE `id` = ?;', json_encode([VOD_PATH . $rInsertID . '.mp4']), $rInsertID);
						$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`, `pid`, `to_analyze`) VALUES(?, ?, NULL, 1, 1);', $rInsertID, SERVER_ID);
						$db->query('UPDATE `recordings` SET `status` = 2, `created_id` = ? WHERE `id` = ?;', $rInsertID, $rRecordID);
					}
					else {
						echo 'Couldn\'t convert to MP4' . "\n";
						$rComplete = false;
					}
				}
				else {
					echo 'Failed to insert into database!' . "\n";
					$rComplete = false;
				}
			}
			else {
				echo 'Recording size is 0 bytes.' . "\n";
				$rComplete = false;
			}
		}

		if (!$rComplete) {
			echo 'Recording incomplete!' . "\n";
			$db->query('UPDATE `recordings` SET `status` = 3 WHERE `id` = ?;', $rRecordID);
			@unlink(ARCHIVE_PATH . $rRecordID . '.ts');
		}
	}
	else {
		echo 'Recording entry doesn\'t exist.' . "\n";
	}
}

function checkRunning($rRecordID)
{
	clearstatcache(true);

	if (file_exists(ARCHIVE_PATH . $rRecordID . '_.record')) {
		$rPID = (int) file_get_contents(ARCHIVE_PATH . $rRecordID . '_.record');
	}

	if (empty($rPID)) {
		shell_exec('kill -9 `ps -ef | grep \'Record\\[' . (int) $rRecordID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
	}
	else if (file_exists('/proc/' . $rPID)) {
		$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
		if (($rCommand == 'Record[' . $rRecordID . ']') && is_numeric($rPID) && (0 < $rPID)) {
			posix_kill($rPID, 9);
		}
	}

	file_put_contents(ARCHIVE_PATH . $rRecordID . '_.record', getmypid());
}

function shutdown()
{
	global $db;
	global $rRecordID;

	if (file_exists(ARCHIVE_PATH . $rRecordID . '_.record')) {
		unlink(ARCHIVE_PATH . $rRecordID . '_.record');
	}

	if (is_object($db)) {
		$db->close_mysql();
	}
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}
if (!@$argc || ($argc <= 1)) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
$rRecordID = (int) $argv[1];
checkRunning($rRecordID);
set_time_limit(0);
cli_set_process_title('Record[' . $rRecordID . ']');
loadcli();

?>