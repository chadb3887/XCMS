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

function getSeriesByID($rPlexID, $rTMDBID)
{
	global $db;
	if (file_exists(WATCH_TMP_PATH . 'series_' . $rPlexID . '.data') && ((time() - filemtime(WATCH_TMP_PATH . 'series_' . $rPlexID . '.data')) < 360)) {
		return json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . $rPlexID . '.data'), true);
	}
	if (file_exists(WATCH_TMP_PATH . 'series_' . (int) $rTMDBID . '.data') && ((time() - filemtime(WATCH_TMP_PATH . 'series_' . (int) $rTMDBID . '.data')) < 360)) {
		return json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . (int) $rTMDBID . '.data'), true);
	}

	$db->query('SELECT * FROM `streams_series` WHERE `plex_uuid` = ? OR `tmdb_id` = ?;', $rPlexID, $rTMDBID);

	if ($db->num_rows() == 1) {
		return $db->get_row();
	}

	return NULL;
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

function getNextOrder()
{
	global $db;
	$db->query('SELECT MAX(`order`) AS `order` FROM `streams`;');

	if ($db->num_rows() == 1) {
		return (int) $db->get_row()['order'] + 1;
	}

	return 0;
}

function addToBouquet($rType, $rBouquetID, $rID)
{
	global $rThreadData;
	file_put_contents(WATCH_TMP_PATH . md5($rThreadData['uuid'] . '_' . $rThreadData['key'] . '_' . $rType . '_' . $rBouquetID . '_' . $rID) . '.pbouquet', json_encode(['type' => $rType, 'bouquet_id' => $rBouquetID, 'id' => $rID]));
}

function getMovie($rPlexID, $rTMDBID)
{
	if (file_exists(WATCH_TMP_PATH . 'movie_' . $rPlexID . '.pcache')) {
		return json_decode(file_get_contents(WATCH_TMP_PATH . 'movie_' . $rPlexID . '.pcache'), true);
	}
	else if (file_exists(WATCH_TMP_PATH . 'movie_' . $rTMDBID . '.pcache')) {
		return json_decode(file_get_contents(WATCH_TMP_PATH . 'movie_' . $rTMDBID . '.pcache'), true);
	}

	return NULL;
}

function getEpisode($rPlexID, $rTMDBID, $rSeason, $rEpisode)
{
	if (file_exists(WATCH_TMP_PATH . 'series_' . $rPlexID . '.pcache')) {
		$rData = json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . $rPlexID . '.pcache'), true);

		if (isset($rData[$rSeason . '_' . $rEpisode])) {
			return $rData[$rSeason . '_' . $rEpisode];
		}
	}

	if (file_exists(WATCH_TMP_PATH . 'series_' . $rTMDBID . '.pcache')) {
		$rData = json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . $rTMDBID . '.pcache'), true);

		if (isset($rData[$rSeason . '_' . $rEpisode])) {
			return $rData[$rSeason . '_' . $rEpisode];
		}
	}

	return NULL;
}

function addCategory($rType, $rGenreTag)
{
	file_put_contents(WATCH_TMP_PATH . md5($rType . '_' . $rGenreTag) . '.pcat', json_encode(['type' => $rType, 'title' => $rGenreTag]));
}

function readURL($rURL)
{
	$rCurl = curl_init($rURL);
	curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($rCurl, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($rCurl, CURLOPT_TIMEOUT, 10);
	return curl_exec($rCurl);
}

function makeArray($rArray)
{
	if (isset($rArray['@attributes'])) {
		$rArray = [$rArray];
	}

	return $rArray;
}

function loadCLI()
{
	global $db;
	global $rThreadData;
	global $rStreamDatabase;
	$rServers = [SERVER_ID];

	if (!empty($rThreadData['server_add'])) {
		foreach (json_decode($rThreadData['server_add'], true) as $rServerID) {
			$rServers[] = (int) $rServerID;
		}
	}

	$rBouquetIDs = $rCategoryIDs = [];

	if (0 < $rThreadData['category_id']) {
		$rCategoryIDs = [(int) $rThreadData['category_id']];
	}

	if (0 < count(json_decode($rThreadData['bouquets'], true))) {
		$rBouquetIDs = json_decode($rThreadData['bouquets'], true);
	}

	$rLanguage = NULL;
	$rPlexCategories = $rThreadData['plex_categories'];
	$rImportArray = verifyposttable('streams');
	$rImportArray['type'] = ['movie' => 2, 'show' => 5][$rThreadData['type']];

	if (!$rImportArray['type']) {
		exit();
	}

	$rThreadType = ['movie' => 1, 'show' => 2][$rThreadData['type']];

	switch ($rThreadData['type']) {
	case 'movie':
		$rURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/library/metadata/' . $rThreadData['key'] . '?X-Plex-Token=' . $rThreadData['token'];
		$rContent = json_decode(json_encode(simplexml_load_string(readurl($rURL))), true);

		if (!$rContent) {
			exit('Failed to get information.' . "\n");
		}

		$rTMDBID = NULL;
		$rFirstFile = NULL;

		foreach (makearray($rContent['Video']['Guid']) as $rGUID) {
			if (substr($rGUID['@attributes']['id'], 0, 7) == 'tmdb://') {
				$rTMDBID = (int) explode('tmdb://', $rGUID['@attributes']['id'])[1];
				echo 'TMDB ID: ' . $rTMDBID . "\n";
				break;
			}
		}

		$rFileArray = ['file' => NULL, 'size' => NULL, 'data' => NULL, 'key' => NULL];

		foreach (makearray($rContent['Video']['Media']) as $rMedia) {
			if (!$rFirstFile) {
				$rFirstFile = $rMedia['Part']['@attributes']['file'];
			}
			if (!$rFileArray['size'] || ($rFileArray['size'] < (int) $rMedia['Part']['@attributes']['size'])) {
				if (file_exists($rMedia['Part']['@attributes']['file']) || $rThreadData['direct_proxy']) {
					$rFileArray = ['file' => $rMedia['Part']['@attributes']['file'], 'size' => (int) $rMedia['Part']['@attributes']['size'], 'data' => $rMedia, 'key' => $rMedia['Part']['@attributes']['key']];
				}
			}
		}

		if (!empty($rFileArray['file'])) {
			$rInternalPath = json_encode(['s:' . SERVER_ID . ':' . $rFileArray['file']], JSON_UNESCAPED_UNICODE);
			$rDirectURL = json_encode(['http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . $rFileArray['key'] . '?X-Plex-Token=' . $rThreadData['token']], JSON_UNESCAPED_UNICODE);
			if (!in_array($rInternalPath, $rStreamDatabase) && !in_array($rDirectURL, $rStreamDatabase)) {
				$rStreamDatabase[] = $rInternalPath;
				$rStreamDatabase[] = $rDirectURL;
				if (($rThreadData['target_container'] != 'auto') && $rThreadData['target_container'] && !$rThreadData['direct_proxy']) {
					$rImportArray['target_container'] = $rThreadData['target_container'];
				}
				else {
					$rImportArray['target_container'] = pathinfo($rFileArray['file'])['extension'];
				}

				if (empty($rImportArray['target_container'])) {
					$rImportArray['target_container'] = 'mp4';
				}

				$db->query('DELETE FROM `watch_logs` WHERE `filename` = ? AND `type` = ? AND `server_id` = ?;', utf8_decode($rFileArray['file']), $rThreadType, SERVER_ID);

				if ($rContent['Video']['@attributes']['thumb']) {
					$rThumbURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=300&height=450&minSize=1&quality=100&upscale=1&url=' . $rContent['Video']['@attributes']['thumb'] . '&X-Plex-Token=' . $rThreadData['token'];
					$rThumb = XCMS::downloadImage($rThumbURL);
				}

				if ($rContent['Video']['@attributes']['art']) {
					$rBGURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=1280&height=720&minSize=1&quality=100&upscale=1&url=' . $rContent['Video']['@attributes']['art'] . '&X-Plex-Token=' . $rThreadData['token'];
					$rBG = XCMS::downloadImage($rBGURL);
				}

				$rCast = [];

				foreach (array_slice(makearray($rContent['Video']['Role']), 0, 5) as $rMember) {
					$rCast[] = $rMember['@attributes']['tag'];
				}

				$rDirectors = [];

				foreach (array_slice(makearray($rContent['Video']['Director']), 0, 3) as $rMember) {
					$rDirectors[] = $rMember['@attributes']['tag'];
				}

				$rGenres = [];

				foreach (array_slice(makearray($rContent['Video']['Genre']), 0, $rThreadData['max_genres']) as $rGenre) {
					$rGenres[] = $rGenre['@attributes']['tag'];
				}

				$rCountry = makearray($rContent['Video']['Country'])[0]['@attributes']['tag'] ?: NULL;
				$rSeconds = (int) ((int) $rContent['Video']['@attributes']['duration'] / 1000);
				$rImportArray['stream_display_name'] = $rContent['Video']['@attributes']['title'];

				if ($rContent['Video']['@attributes']['year']) {
					$rImportArray['year'] = (int) $rContent['Video']['@attributes']['year'];
				}

				$rImportArray['tmdb_id'] = $rTMDBID ?: NULL;
				$rImportArray['movie_properties'] = [
					'kinopoisk_url'          => $rTMDBID ? 'https://www.themoviedb.org/movie/' . $rTMDBID : NULL,
					'tmdb_id'                => $rTMDBID,
					'plex_id'                => $rThreadData['key'],
					'name'                   => $rContent['Video']['@attributes']['title'],
					'o_name'                 => $rContent['Video']['@attributes']['title'],
					'cover_big'              => $rThumb,
					'movie_image'            => $rThumb,
					'release_date'           => $rContent['Video']['@attributes']['originallyAvailableAt'],
					'episode_run_time'       => (int) ($rSeconds / 60),
					'youtube_trailer'        => NULL,
					'director'               => implode(', ', $rDirectors),
					'actors'                 => implode(', ', $rCast),
					'cast'                   => implode(', ', $rCast),
					'description'            => trim($rContent['Video']['@attributes']['summary']),
					'plot'                   => $rContent['Video']['@attributes']['summary'],
					'age'                    => '',
					'mpaa_rating'            => '',
					'rating_count_kinopoisk' => 0,
					'country'                => $rCountry,
					'genre'                  => implode(', ', $rGenres),
					'backdrop_path'          => [$rBG],
					'duration_secs'          => $rSeconds,
					'duration'               => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
					'video'                  => [],
					'audio'                  => [],
					'bitrate'                => 0,
					'rating'                 => (float) $rContent['Video']['@attributes']['rating'] ?: (float) $rContent['Video']['@attributes']['audienceRating']
				];
				$rImportArray['rating'] = (float) $rContent['Video']['@attributes']['rating'] ?: (float) $rContent['Video']['@attributes']['audienceRating'] ?: 0;
				$rImportArray['read_native'] = $rThreadData['read_native'];
				$rImportArray['movie_symlink'] = $rThreadData['movie_symlink'];
				$rImportArray['remove_subtitles'] = $rThreadData['remove_subtitles'];
				$rImportArray['transcode_profile_id'] = $rThreadData['transcode_profile_id'];

				if ($rThreadData['direct_proxy']) {
					$rImportArray['stream_source'] = $rDirectURL;
					$rImportArray['direct_source'] = 1;
					$rImportArray['direct_proxy'] = 1;
				}
				else {
					$rImportArray['stream_source'] = $rInternalPath;
					$rImportArray['direct_source'] = 0;
					$rImportArray['direct_proxy'] = 0;
				}

				$rImportArray['order'] = getnextorder();
				$rImportArray['tmdb_language'] = $rLanguage;

				if (count($rCategoryIDs) == 0) {
					if (0 < $rThreadData['max_genres']) {
						$rParsed = array_slice(makearray($rContent['Video']['Genre']), 0, $rThreadData['max_genres']);
					}
					else {
						$rParsed = makearray($rContent['Video']['Genre']);
					}

					foreach ($rParsed as $rGenre) {
						$rGenreTag = $rGenre['@attributes']['tag'];

						if (isset($rPlexCategories[3][$rGenreTag])) {
							$rCategoryID = (int) $rPlexCategories[3][$rGenreTag]['category_id'];

							if (0 < $rCategoryID) {
								if (!in_array($rCategoryID, $rCategoryIDs)) {
									$rCategoryIDs[] = $rCategoryID;
								}
							}
						}
						else if ($rThreadData['store_categories']) {
							addcategory($rThreadData['type'], $rGenreTag);
						}
					}
				}
				if ((count($rCategoryIDs) == 0) && (0 < (int) $rThreadData['fb_category_id'])) {
					$rCategoryIDs = [(int) $rThreadData['fb_category_id']];
				}

				if (count($rBouquetIDs) == 0) {
					if (0 < $rThreadData['max_genres']) {
						$rParsed = array_slice(makearray($rContent['Video']['Genre']), 0, $rThreadData['max_genres']);
					}
					else {
						$rParsed = makearray($rContent['Video']['Genre']);
					}

					foreach ($rParsed as $rGenre) {
						$rGenreTag = $rGenre['@attributes']['tag'];
						$rBouquets = json_decode($rPlexCategories[3][$rGenreTag]['bouquets'], true);

						foreach ($rBouquets as $rBouquetID) {
							if (!in_array($rBouquetID, $rBouquetIDs)) {
								$rBouquetIDs[] = $rBouquetID;
							}
						}
					}
				}

				if (count($rBouquetIDs) == 0) {
					$rBouquetIDs = array_map('intval', json_decode($rThreadData['fb_bouquets'], true));
				}

				if ($rYear) {
					$rImportArray['year'] = $rYear;
				}

				$rImportArray['added'] = time();
				$rImportArray['plex_uuid'] = $rThreadData['uuid'];
				$rImportArray['category_id'] = '[' . implode(',', array_map('intval', $rCategoryIDs)) . ']';

				if ($rUpgradeData = getmovie($rThreadData['uuid'], $rThreadData['check_tmdb'] ? $rTMDBID : NULL)) {
					if ($rUpgradeData['source'] == $rFileArray['file']) {
						echo 'File remains unchanged' . "\n";
						exit();
					}

					if (!$rThreadData['auto_upgrade']) {
						echo 'Upgrade disabled' . "\n";
						exit();
					}

					echo 'Upgrade movie!' . "\n";
					$rImportArray['id'] = $rUpgradeData['id'];
				}
				else if (count($rCategoryIDs) == 0) {
					$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 3, 0);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']));
					exit();
				}

				$rPrepare = preparearray($rImportArray);
				$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

				if ($db->query($rQuery, ...$rPrepare['data'])) {
					$rInsertID = $db->last_insert_id();

					if ($rUpgradeData) {
						foreach ($rServers as $rServerID) {
							$db->query('UPDATE `streams_servers` SET `bitrate` = NULL, `current_source` = NULL, `to_analyze` = 0, `pid` = NULL, `stream_started` = NULL, `stream_info` = NULL, `compatible` = 0, `video_codec` = NULL, `audio_codec` = NULL, `resolution` = NULL, `stream_status` = 0 WHERE `stream_id` = ? AND `server_id` = ?', $rInsertID, $rServerID);
						}

						$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 6, 0);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']));
					}
					else {
						foreach ($rServers as $rServerID) {
							$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`) VALUES(?, ?, NULL);', $rInsertID, $rServerID);
						}

						foreach ($rBouquetIDs as $rBouquet) {
							addtobouquet('movie', $rBouquet, $rInsertID);
						}

						$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 1, ?);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']), $rInsertID);
						exit();
					}

					if ($rThreadData['auto_encode']) {
						foreach ($rServers as $rServerID) {
							XCMS::queueMovie($rInsertID, $rServerID);
						}
					}

					echo 'Success!' . "\n";
				}
				else {
					echo 'Insert failed!' . "\n";
					$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 2, 0);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']));
					exit();
				}
			}
		}
		else if ($rFirstFile) {
			$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 5, 0);', $rThreadType, SERVER_ID, utf8_decode($rFirstFile));
			exit();
		}
		else {
			exit();
		}

		break;
	case 'show':
		$rURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/library/metadata/' . $rThreadData['key'] . '?X-Plex-Token=' . $rThreadData['token'];
		$rContent = json_decode(json_encode(simplexml_load_string(readurl($rURL))), true);

		if (!$rContent) {
			exit('Failed to get information.' . "\n");
		}

		$rShowData = makearray($rContent['Directory'])[0];
		$rTMDBID = NULL;

		if (substr($rShowData['@attributes']['guid'], 0, 32) == 'com.plexapp.agents.themoviedb://') {
			$rSplit = explode('com.plexapp.agents.themoviedb://', $rShowData['@attributes']['guid'])[1];
			$rTMDBID = (int) explode('?lang=', $rSplit)[0];
			$rLanguage = explode('?lang=', $rSplit)[1] ?: NULL;
			echo 'TMDB ID: ' . $rTMDBID . "\n";
		}

		if (!$rTMDBID) {
			foreach ($rShowData['Guid'] as $rGUID) {
				if (substr($rGUID['@attributes']['id'], 0, 7) == 'tmdb://') {
					$rTMDBID = substr($rGUID['@attributes']['id'], 7, strlen($rGUID['@attributes']['id']) - 7);
					$rLanguage = explode('?lang=', $rSplit)[1] ?: NULL;
					echo 'TMDB ID: ' . $rTMDBID . "\n";
					break;
				}
			}
		}

		$rSeasonInfo = $rSeasonData = [];
		$rURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/library/metadata/' . $rThreadData['key'] . '/children?X-Plex-Token=' . $rThreadData['token'];
		$rSeasons = makearray(json_decode(json_encode(simplexml_load_string(readurl($rURL))), true)['Directory']);
		$rURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/library/metadata/' . $rThreadData['key'] . '/allLeaves?X-Plex-Token=' . $rThreadData['token'];
		$rEpisodes = makearray(json_decode(json_encode(simplexml_load_string(readurl($rURL))), true)['Video']);

		foreach ($rEpisodes as $rEpisode) {
			if (!in_array($rEpisode['@attributes']['parentIndex'], array_keys($rSeasonInfo))) {
				$rSeasonInfo[$rEpisode['@attributes']['parentIndex']] = $rEpisode['@attributes']['originallyAvailableAt'];
			}
		}

		foreach ($rSeasons as $rSeason) {
			if ($rSeason['@attributes']['index']) {
				$rCover = NULL;

				if ($rSeason['@attributes']['thumb']) {
					$rThumbURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=300&height=450&minSize=1&quality=100&upscale=1&url=' . $rSeason['@attributes']['thumb'] . '&X-Plex-Token=' . $rThreadData['token'];
					$rCover = XCMS::downloadImage($rThumbURL);
				}

				$rSeasonData[] = ['name' => $rSeason['@attributes']['title'], 'air_date' => $rSeasonInfo[$rSeason['@attributes']['index']] ?: '', 'overview' => trim($rShowData['@attributes']['summary']) ?: '', 'cover_big' => $rCover, 'cover' => $rCover, 'episode_count' => $rSeason['@attributes']['leafCount'], 'season_number' => $rSeason['@attributes']['index'], 'id' => $rSeason['@attributes']['ratingKey']];
			}
		}

		$rSeries = getseriesbyid($rThreadData['uuid'], $rTMDBID);

		if (!$rSeries) {
			$rSeriesArray = [
				'title'            => $rShowData['@attributes']['title'],
				'category_id'      => [],
				'episode_run_time' => (int) ($rShowData['@attributes']['duration'] / 1000 / 60) ?: 0,
				'tmdb_id'          => $rTMDBID,
				'cover'            => '',
				'genre'            => '',
				'plot'             => trim($rShowData['@attributes']['summary']),
				'cast'             => '',
				'rating'           => (float) $rShowData['@attributes']['rating'] ?: (float) $rShowData['@attributes']['audienceRating'] ?: 0,
				'director'         => '',
				'release_date'     => $rShowData['@attributes']['originallyAvailableAt'],
				'last_modified'    => time(),
				'seasons'          => $rSeasonData,
				'backdrop_path'    => [],
				'youtube_trailer'  => '',
				'year'             => NULL
			];

			if ($rSeriesArray['release_date']) {
				$rSeriesArray['year'] = (int) substr($rSeriesArray['release_date'], 0, 4);
			}

			if ($rShowData['@attributes']['thumb']) {
				$rThumbURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=300&height=450&minSize=1&quality=100&upscale=1&url=' . $rShowData['@attributes']['thumb'] . '&X-Plex-Token=' . $rThreadData['token'];
				$rThumb = XCMS::downloadImage($rThumbURL);
			}

			if ($rShowData['@attributes']['art']) {
				$rBGURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=1280&height=720&minSize=1&quality=100&upscale=1&url=' . $rShowData['@attributes']['art'] . '&X-Plex-Token=' . $rThreadData['token'];
				$rBG = XCMS::downloadImage($rBGURL);
			}

			$rSeriesArray['cover'] = $rThumb;
			$rSeriesArray['cover_big'] = $rThumb;

			if ($rBG) {
				$rSeriesArray['backdrop_path'] = [$rBG];
			}
			else {
				$rSeriesArray['backdrop_path'] = [];
			}

			$rCast = [];

			foreach (array_slice(makearray($rShowData['Role']), 0, 5) as $rMember) {
				$rCast[] = $rMember['@attributes']['tag'];
			}

			$rSeriesArray['cast'] = implode(', ', $rCast);
			$rDirectors = [];

			foreach (array_slice(makearray($rShowData['Director']), 0, 3) as $rMember) {
				$rDirectors[] = $rMember['@attributes']['tag'];
			}

			$rSeriesArray['director'] = implode(', ', $rDirectors);
			$rGenres = [];

			foreach (array_slice(makearray($rShowData['Genre']), 0, 3) as $rGenre) {
				$rGenres[] = $rGenre['@attributes']['tag'];
			}

			$rSeriesArray['genre'] = implode(', ', $rGenres);

			if (count($rCategoryIDs) == 0) {
				if (0 < $rThreadData['max_genres']) {
					$rParsed = array_slice(makearray($rShowData['Genre']), 0, $rThreadData['max_genres']);
				}
				else {
					$rParsed = makearray($rShowData['Genre']);
				}

				foreach ($rParsed as $rGenre) {
					$rGenreTag = $rGenre['@attributes']['tag'];

					if (isset($rPlexCategories[3][$rGenreTag])) {
						$rCategoryID = (int) $rPlexCategories[4][$rGenreTag]['category_id'];

						if (0 < $rCategoryID) {
							if (!in_array($rCategoryID, $rCategoryIDs)) {
								$rCategoryIDs[] = $rCategoryID;
							}
						}
					}
					else if ($rThreadData['store_categories']) {
						addcategory($rThreadData['type'], $rGenreTag);
					}
				}
			}
			if ((count($rCategoryIDs) == 0) && (0 < (int) $rThreadData['fb_category_id'])) {
				$rCategoryIDs = [(int) $rThreadData['fb_category_id']];
			}

			if (count($rBouquetIDs) == 0) {
				if (0 < $rThreadData['max_genres']) {
					$rParsed = array_slice(makearray($rShowData['Genre']), 0, $rThreadData['max_genres']);
				}
				else {
					$rParsed = makearray($rShowData['Genre']);
				}

				foreach ($rParsed as $rGenre) {
					$rGenreTag = $rGenre['@attributes']['tag'];
					$rBouquets = json_decode($rPlexCategories[4][$rGenreTag]['bouquets'], true);

					foreach ($rBouquets as $rBouquetID) {
						if (!in_array($rBouquetID, $rBouquetIDs)) {
							$rBouquetIDs[] = $rBouquetID;
						}
					}
				}
			}

			if (count($rBouquetIDs) == 0) {
				$rBouquetIDs = array_map('intval', json_decode($rThreadData['fb_bouquets'], true));
			}

			if (count($rCategoryIDs) == 0) {
				$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 3, 0);', $rThreadType, SERVER_ID, 'Plex Series: ' . utf8_decode($rSeriesArray['title']));
				exit();
			}

			$rSeriesArray['plex_uuid'] = $rThreadData['uuid'];
			$rSeriesArray['tmdb_language'] = $rLanguage;
			$rSeriesArray['category_id'] = '[' . implode(',', array_map('intval', $rCategoryIDs)) . ']';
			$rPrepare = preparearray($rSeriesArray);
			$rQuery = 'INSERT INTO `streams_series`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if ($db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = $db->last_insert_id();
				$rSeries = getserie($rInsertID);

				foreach ($rBouquetIDs as $rBouquet) {
					addtobouquet('series', $rBouquet, $rInsertID);
				}
			}
			else {
				$rSeries = NULL;
			}
		}
		else {
			$db->query('UPDATE `streams_series` SET `seasons` = ? WHERE `id` = ?;', json_encode($rSeasonData, JSON_UNESCAPED_UNICODE), $rSeries['id']);

			if (!$rSeries['cover']) {
				if ($rShowData['@attributes']['thumb']) {
					$rThumbURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=300&height=450&minSize=1&quality=100&upscale=1&url=' . $rShowData['@attributes']['thumb'] . '&X-Plex-Token=' . $rThreadData['token'];
					$rThumb = XCMS::downloadImage($rThumbURL);
				}

				if ($rShowData['@attributes']['art']) {
					$rBGURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=1280&height=720&minSize=1&quality=100&upscale=1&url=' . $rShowData['@attributes']['art'] . '&X-Plex-Token=' . $rThreadData['token'];
					$rBG = XCMS::downloadImage($rBGURL);
				}
				if ($rThumb || $rBG) {
					if ($rBG) {
						$rBG = [$rBG];
					}
					else {
						$rBG = [];
					}

					$db->query('UPDATE `streams_series` SET `cover` = ?, `cover_big` = ?, `backdrop_path` = ? WHERE `id` = ?;', $rThumb, $rThumb, $rBG, $rSeries['id']);
				}
			}
		}

		foreach ($rEpisodes as $rEpisode) {
			if ($rEpisode['@attributes']['parentIndex'] && $rEpisode['@attributes']['index']) {
				$rFirstFile = NULL;
				$rReleaseSeason = $rEpisode['@attributes']['parentIndex'];
				$rReleaseEpisode = $rEpisode['@attributes']['index'];
				$rFileArray = ['file' => NULL, 'size' => NULL, 'data' => NULL, 'key' => NULL];

				foreach (makearray($rEpisode['Media']) as $rMedia) {
					if (!$rFirstFile) {
						$rFirstFile = $rMedia['Part']['@attributes']['file'];
					}
					if (!$rFileArray['size'] || ($rFileArray['size'] < (int) $rMedia['Part']['@attributes']['size'])) {
						if (file_exists($rMedia['Part']['@attributes']['file']) || $rThreadData['direct_proxy']) {
							$rFileArray = ['file' => $rMedia['Part']['@attributes']['file'], 'size' => (int) $rMedia['Part']['@attributes']['size'], 'data' => $rMedia, 'key' => $rMedia['Part']['@attributes']['key']];
						}
					}
				}

				if (!empty($rFileArray['file'])) {
					$rInternalPath = json_encode(['s:' . SERVER_ID . ':' . $rFileArray['file']], JSON_UNESCAPED_UNICODE);
					$rDirectURL = json_encode(['http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . $rFileArray['key'] . '?X-Plex-Token=' . $rThreadData['token']], JSON_UNESCAPED_UNICODE);
					if (!in_array($rInternalPath, $rStreamDatabase) && !in_array($rDirectURL, $rStreamDatabase)) {
						$rStreamDatabase[] = $rInternalPath;
						$rStreamDatabase[] = $rDirectURL;
						if (($rThreadData['target_container'] != 'auto') && $rThreadData['target_container'] && !$rThreadData['direct_proxy']) {
							$rImportArray['target_container'] = $rThreadData['target_container'];
						}
						else {
							$rImportArray['target_container'] = pathinfo($rFileArray['file'])['extension'];
						}

						if (empty($rImportArray['target_container'])) {
							$rImportArray['target_container'] = 'mp4';
						}

						if ($rUpgradeData = getepisode($rThreadData['uuid'], $rThreadData['check_tmdb'] ? $rTMDBID : NULL, $rReleaseSeason, $rReleaseEpisode)) {
							if ($rUpgradeData['source'] == $rFileArray['file']) {
								echo 'File remains unchanged' . "\n";
								continue;
							}

							if (!$rThreadData['auto_upgrade']) {
								echo 'Upgrade disabled' . "\n";
								continue;
							}

							echo 'Upgrade episode!' . "\n";
							$db->query('UPDATE `streams` SET `plex_uuid` = ?, `stream_source` = ?, `target_container` = ? WHERE `id` = ?;', $rThreadData['uuid'], $rImportArray['stream_source'], $rImportArray['target_container'], $rUpgradeData['id']);

							foreach ($rServers as $rServerID) {
								$db->query('UPDATE `streams_servers` SET `bitrate` = NULL, `current_source` = NULL, `to_analyze` = 0, `pid` = NULL, `stream_started` = NULL, `stream_info` = NULL, `compatible` = 0, `video_codec` = NULL, `audio_codec` = NULL, `resolution` = NULL, `stream_status` = 0 WHERE `stream_id` = ? AND `server_id` = ?', $rUpgradeData['id'], $rServerID);
							}

							if ($rThreadData['auto_encode']) {
								foreach ($rServers as $rServerID) {
									XCMS::queueMovie($rUpgradeData['id'], $rServerID);
								}
							}

							$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 6, 0);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']));
							continue;
						}

						$db->query('DELETE FROM `watch_logs` WHERE `filename` = ? AND `type` = ? AND `server_id` = ?;', utf8_decode($rFileArray['file']), $rThreadType, SERVER_ID);
						$rThumb = NULL;

						if ($rEpisode['@attributes']['thumb']) {
							$rThumbURL = 'http://' . $rThreadData['ip'] . ':' . $rThreadData['port'] . '/photo/:/transcode?width=450&height=253&minSize=1&quality=100&upscale=1&url=' . $rEpisode['@attributes']['thumb'] . '&X-Plex-Token=' . $rThreadData['token'];
							$rThumb = XCMS::downloadImage($rThumbURL);
						}

						$rSeconds = (int) ($rEpisode['@attributes']['duration'] / 1000);
						$rImportArray['movie_properties'] = [
							'tmdb_id'       => $rSeries['tmdb_id'] ?: NULL,
							'release_date'  => $rEpisode['@attributes']['originallyAvailableAt'],
							'plot'          => $rEpisode['@attributes']['summary'],
							'duration_secs' => $rSeconds,
							'duration'      => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
							'movie_image'   => $rThumb,
							'video'         => [],
							'audio'         => [],
							'bitrate'       => 0,
							'rating'        => (float) $rEpisode['@attributes']['rating'] ?: (float) $rEpisode['@attributes']['audienceRating'] ?: $rSeries['rating'],
							'season'        => $rReleaseSeason
						];
						$rImportArray['stream_display_name'] = $rSeries['title'] . ' - S' . sprintf('%02d', (int) $rReleaseSeason) . 'E' . sprintf('%02d', $rReleaseEpisode) . ' - ' . $rEpisode['@attributes']['title'];
						$rImportArray['read_native'] = $rThreadData['read_native'];
						$rImportArray['movie_symlink'] = $rThreadData['movie_symlink'];
						$rImportArray['remove_subtitles'] = $rThreadData['remove_subtitles'];
						$rImportArray['transcode_profile_id'] = $rThreadData['transcode_profile_id'];

						if ($rThreadData['direct_proxy']) {
							$rImportArray['stream_source'] = $rDirectURL;
							$rImportArray['direct_source'] = 1;
							$rImportArray['direct_proxy'] = 1;
						}
						else {
							$rImportArray['stream_source'] = $rInternalPath;
							$rImportArray['direct_source'] = 0;
							$rImportArray['direct_proxy'] = 0;
						}

						$rImportArray['order'] = getnextorder();
						$rImportArray['tmdb_language'] = $rLanguage;
						$rImportArray['added'] = time();
						$rImportArray['uuid'] = $rThreadData['uuid'];
						$rImportArray['series_no'] = $rSeries['id'];
						$rPrepare = preparearray($rImportArray);
						$rQuery = 'REPLACE INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

						if ($db->query($rQuery, ...$rPrepare['data'])) {
							$rInsertID = $db->last_insert_id();

							foreach ($rServers as $rServerID) {
								$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`) VALUES(?, ?, NULL);', $rInsertID, $rServerID);
							}

							$db->query('INSERT INTO `streams_episodes`(`season_num`, `series_id`, `stream_id`, `episode_num`) VALUES(?, ?, ?, ?);', $rReleaseSeason, $rSeries['id'], $rInsertID, $rReleaseEpisode);

							if ($rThreadData['auto_encode']) {
								foreach ($rServers as $rServerID) {
									XCMS::queueMovie($rInsertID, $rServerID);
								}
							}

							$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 1, ?);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']), $rInsertID);
						}
						else {
							echo 'Insert failed!' . "\n";
							$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 2, 0);', $rThreadType, SERVER_ID, utf8_decode($rFileArray['file']));
						}
					}
					else {
						echo 'Already exists!' . "\n";
					}
				}
				else if ($rFirstFile) {
					$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 5, 0);', $rThreadType, SERVER_ID, utf8_decode($rFirstFile));
				}
				else {
					exit();
				}
			}
		}

		break;
	}
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}

	@unlink(WATCH_TMP_PATH . getmypid() . '.ppid');
}

setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(30711);
$rStreamDatabase = json_decode(file_get_contents(WATCH_TMP_PATH . 'stream_database.pcache'), true) ?: [];
$rThreadData = json_decode(base64_decode($argv[1]), true);

if (!$rThreadData) {
	exit();
}

file_put_contents(WATCH_TMP_PATH . getmypid() . '.ppid', time());

if ($rThreadData['type'] == 'movie') {
	$rTimeout = 60;
}
else {
	$rTimeout = 600;
}

set_time_limit($rTimeout);
ini_set('max_execution_time', $rTimeout);
loadcli();

?>