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

function checkSource($rFilename)
{
	$rCommand = 'timeout 10 ' . XCMS::$rFFPROBE . ' -show_streams -show_format -v quiet ' . escapeshellarg($rFilename) . ' -of json';
	return json_decode(shell_exec($rCommand), true);
}

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

function getSeriesByTMDB($rID)
{
	global $db;
	if (file_exists(WATCH_TMP_PATH . 'series_' . (int) $rID . '.data') && ((time() - filemtime(WATCH_TMP_PATH . 'series_' . (int) $rID . '.data')) < 360)) {
		return json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . (int) $rID . '.data'), true);
	}

	$db->query('SELECT * FROM `streams_series` WHERE `tmdb_id` = ?;', $rID);

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

function confirmIDs($rIDs)
{
	$rReturn = [];

	foreach ($rIDs as $rID) {
		if (0 < (int) $rID) {
			$rReturn[] = $rID;
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

function addToBouquet($rType, $rBouquetID, $rID)
{
	global $db;
	global $rThreadData;

	if ($rThreadData['import']) {
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

			$rChannels = confirmids(json_decode($rBouquet[$rColumn], true));
			if ((0 < (int) $rID) && !in_array($rID, $rChannels)) {
				$rChannels[] = $rID;

				if (0 < count($rChannels)) {
					$db->query('UPDATE `bouquets` SET `' . $rColumn . '` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rChannels)) . ']', $rBouquetID);
				}
			}
		}
	}
	else {
		file_put_contents(WATCH_TMP_PATH . md5($rThreadData['file'] . '_' . $rType . '_' . $rBouquetID . '_' . $rID) . '.bouquet', json_encode(['type' => $rType, 'bouquet_id' => $rBouquetID, 'id' => $rID]));
	}
}

function parseRelease($rRelease, $rType = 'guessit')
{
	if ($rType == 'guessit') {
		$rCommand = XCMS_HOME . 'bin/guess ' . escapeshellarg($rRelease . '.mkv');
	}
	else {
		$rCommand = '/usr/bin/python3 ' . XCMS_HOME . 'includes/python/release.py ' . escapeshellarg(str_replace('-', '_', $rRelease));
	}

	return json_decode(shell_exec($rCommand), true);
}

function getMovie($rTMDBID)
{
	if (file_exists(WATCH_TMP_PATH . 'movie_' . $rTMDBID . '.cache')) {
		return json_decode(file_get_contents(WATCH_TMP_PATH . 'movie_' . $rTMDBID . '.cache'), true);
	}

	return NULL;
}

function getEpisode($rTMDBID, $rSeason, $rEpisode)
{
	if (file_exists(WATCH_TMP_PATH . 'series_' . $rTMDBID . '.cache')) {
		$rData = json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . $rTMDBID . '.cache'), true);

		if (isset($rData[$rSeason . '_' . $rEpisode])) {
			return $rData[$rSeason . '_' . $rEpisode];
		}
	}

	return NULL;
}

function parseTitle($rTitle)
{
	return strtolower(preg_replace('/(?![.=$\'€%-])\\p{P}/u', '', $rTitle));
}

function loadCLI()
{
	global $db;
	global $rThreadData;
	global $rTimeout;
	if ((strpos($rThreadData['file'], $rThreadData['directory']) !== 0) && !$rThreadData['import']) {
		echo 'Incorrect root directory!';
		exit();
	}

	$rWatchCategories = $rThreadData['watch_categories'];
	$rLanguage = NULL;

	if (!empty($rThreadData['language'])) {
		$rTMDB = new TMDB(XCMS::$rSettings['tmdb_api_key'], $rThreadData['language']);
		$rLanguage = $rThreadData['language'];
	}
	else if (!empty(XCMS::$rSettings['tmdb_language'])) {
		$rTMDB = new TMDB(XCMS::$rSettings['tmdb_api_key'], XCMS::$rSettings['tmdb_language']);
	}
	else {
		$rTMDB = new TMDB(XCMS::$rSettings['tmdb_api_key']);
	}

	if ($rThreadData['type'] != 'movie') {
		$rThreadData['extract_metadata'] = false;
	}

	$rImportArray = verifyposttable('streams');
	$rImportArray['type'] = ['movie' => 2, 'series' => 5][$rThreadData['type']];

	if (!$rImportArray['type']) {
		exit();
	}

	$rThreadType = ['movie' => 1, 'series' => 2][$rThreadData['type']];
	$rFile = $rThreadData['file'];

	if ($rThreadData['import']) {
		$rImportArray['stream_source'] = json_encode([$rFile], JSON_UNESCAPED_UNICODE);
	}
	else {
		$rImportArray['stream_source'] = json_encode(['s:' . SERVER_ID . ':' . $rFile], JSON_UNESCAPED_UNICODE);
		$db->query('DELETE FROM `watch_logs` WHERE `filename` = ? AND `type` = ? AND `server_id` = ?;', utf8_decode($rFile), $rThreadType, SERVER_ID);
	}
	if (($rThreadData['target_container'] != 'auto') && $rThreadData['target_container']) {
		$rImportArray['target_container'] = $rThreadData['target_container'];
	}
	else {
		$rImportArray['target_container'] = pathinfo(explode('?', $rFile)[0])['extension'];
	}

	if (empty($rImportArray['target_container'])) {
		$rImportArray['target_container'] = 'mp4';
	}

	$rSourceData = NULL;
	if ($rThreadData['ffprobe_input'] || $rThreadData['extract_metadata']) {
		$rSourceData = checksource($rFile);
	}
	if (!$rThreadData['ffprobe_input'] || isset($rSourceData['streams'])) {
		$rMatch = $rYear = $rPaths = NULL;
		$rMetaMatch = false;
		if ($rThreadData['extract_metadata'] && isset($rSourceData['format']) && $rSourceData['tags']['title']) {
			$rYear = (int) explode('-', $rSourceData['tags']['date'])[0] ?: NULL;
			$rPaths = [$rSourceData['tags']['title']];
			$rMetaMatch = true;
		}

		if (!$rPaths) {
			if ($rThreadData['fallback_title']) {
				$rPaths = [pathinfo($rFile)['filename'], basename(pathinfo($rFile)['dirname'])];
			}
			else {
				$rPaths = [pathinfo($rFile)['filename']];
			}

			$rMetaMatch = false;
		}

		foreach ($rPaths as $rFilename) {
			echo 'Scanning: ' . $rFilename . "\n";
			$rTitle = NULL;
			$rAltTitle = NULL;

			if ($rThreadData['import']) {
				$rFilename = $rThreadData['title'];
			}
			if ($rThreadData['fallback_parser'] && (!$rThreadData['disable_tmdb'] && !$rMetaMatch)) {
				$rParseTypes = [XCMS::$rSettings['parse_type'], XCMS::$rSettings['parse_type'] == 'guessit' ? 'ptn' : 'guessit'];
			}
			else {
				$rParseTypes = [XCMS::$rSettings['parse_type']];
			}

			foreach ($rParseTypes as $rParseType) {
				if (!$rThreadData['disable_tmdb'] && !$rMetaMatch) {
					$rRelease = parserelease($rFilename, $rParseType);
					$rTitle = $rRelease['title'];

					if (isset($rRelease['excess'])) {
						$rTitle = trim($rTitle, is_array($rRelease['excess']) ? $rRelease['excess'][0] : $rRelease['excess']);
					}

					if (isset($rRelease['group'])) {
						$rAltTitle = $rTitle . '-' . $rRelease['group'];
					}
					else if (isset($rRelease['alternative_title'])) {
						$rAltTitle = $rTitle . ' - ' . $rRelease['alternative_title'];
					}

					$rYear = $rRelease['year'] ?? NULL;

					if ($rThreadData['type'] != 'movie') {
						$rReleaseSeason = $rRelease['season'];

						if (is_array($rRelease['episode'])) {
							$rReleaseEpisode = $rRelease['episode'][0];
						}
						else {
							$rReleaseEpisode = $rRelease['episode'];
						}
					}
					else if (isset($rRelease['season'])) {
						$rTitle .= $rRelease['season'];
					}
				}

				echo 'Checking .... ' . explode('s:' . SERVER_ID . ':', $rFile);
				$db->query('SELECT * FROM `streams` WHERE `stream_source` = ?;', explode('s:' . SERVER_ID . ':', $rFile));
				$rDupe = $db->get_rows();

				if (0 < count($rDupe)) {
					echo 'Duplicate File Found Exiting' . PHP_EOL;
					$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 7, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
					exit();
					exit();
				}

				echo ' .... Not Found Continue' . PHP_EOL;
				if (($rThreadData['type'] == 'series') && (!$rReleaseSeason || !$rReleaseEpisode)) {
					$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 4, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
					exit();
				}

				if (!$rTitle) {
					$rTitle = $rFilename;
				}

				echo 'Title: ' . $rTitle . "\n";

				if (!$rThreadData['disable_tmdb']) {
					$rMatches = [];

					foreach (range(0, 1) as $rIgnoreYear) {
						if ($rIgnoreYear) {
							if ($rYear) {
								$rYear = NULL;
							}
							else {
								break;
							}
						}

						if ($rThreadData['type'] == 'movie') {
							$rResults = $rTMDB->searchMovie($rTitle, $rYear);
						}
						else {
							$rResults = $rTMDB->searchTVShow($rTitle, $rYear);
						}

						foreach ($rResults as $rResultArr) {
							similar_text(parsetitle($rTitle), parsetitle($rResultArr->get('title') ?: $rResultArr->get('name')), $rPercentage);
							$rPercentageAlt = 0;

							if ($rAltTitle) {
								similar_text(parsetitle($rAltTitle), parsetitle($rResultArr->get('title') ?: $rResultArr->get('name')), $rPercentageAlt);
							}
							if ((XCMS::$rSettings['percentage_match'] <= $rPercentage) || (XCMS::$rSettings['percentage_match'] <= $rPercentageAlt)) {
								if (!$rYear || in_array((int) substr($rResultArr->get('release_date') ?: $rResultArr->get('first_air_date'), 0, 4), range((int) $rYear - 1, (int) $rYear + 1))) {
									if ($rAltTitle && (parsetitle($rResultArr->get('title') ?: $rResultArr->get('name')) == parsetitle($rAltTitle))) {
										$rMatches = [
											['percentage' => 100, 'data' => $rResultArr]
										];
										break;
									}
									else if ((parsetitle($rResultArr->get('title') ?: $rResultArr->get('name')) == parsetitle($rTitle)) && !$rAltTitle) {
										$rMatches = [
											['percentage' => 100, 'data' => $rResultArr]
										];
										break;
									}
									else {
										$rMatches[] = ['percentage' => $rPercentage, 'data' => $rResultArr];
									}
								}
							}
							else if ($rThreadData['alternative_titles'] && in_array((int) substr($rResultArr->get('release_date') ?: $rResultArr->get('first_air_date'), 0, 4), range((int) $rYear - 1, (int) $rYear + 1))) {
								$rPartialMatch = false;

								if (strpos(parsetitle($rTitle), parsetitle($rResultArr->get('title') ?: $rResultArr->get('name'))) === 0) {
									$rPartialMatch = true;
								}
								else if ($rAltTitle && (strpos(parsetitle($rAltTitle), parsetitle($rResultArr->get('title') ?: $rResultArr->get('name'))) === 0)) {
									$rPartialMatch = true;
								}

								if ($rPartialMatch) {
									if ($rThreadData['type'] == 'movie') {
										$rAlternativeTitles = $rTMDB->getMovieTitles($rResultArr->get('id'))['titles'];
									}
									else {
										$rAlternativeTitles = $rTMDB->getSeriesTitles($rResultArr->get('id'))['titles'];
									}

									foreach ($rAlternativeTitles as $rAlternativeTitle) {
										if ($rAltTitle && (parsetitle($rAlternativeTitle['title']) == parsetitle($rAltTitle))) {
											$rMatches = [
												['percentage' => 100, 'data' => $rResultArr]
											];
											break;
										}
										else if ((parsetitle($rAlternativeTitle['title']) == parsetitle($rTitle)) && !$rAltTitle) {
											$rMatches = [
												['percentage' => 100, 'data' => $rResultArr]
											];
											break;
										}
									}
								}
							}
						}

						if (0 < count($rMatches)) {
							break;
						}
					}

					if (0 < count($rMatches)) {
						$rMax = max(array_column($rMatches, 'percentage'));
						$rKeys = array_filter(array_map(function($rMatches) use($rMax) {
							return $rMax == $rMatches['percentage'] ? $rMatches['data'] : NULL;
						}, $rMatches));
						$rMatch = array_values($rKeys)[0];
					}
				}

				if ($rMatch) {
					break;
				}
			}
		}
		if ($rMatch || $rThreadData['ignore_no_match']) {
			$rBouquetIDs = [];
			$rCategoryIDs = [];

			if (!empty($rThreadData['category_id'])) {
				if (is_array($rThreadData['category_id'])) {
					$rCategoryIDs = array_map('intval', $rThreadData['category_id']);
				}
				else {
					$rCategoryIDs = [(int) $rThreadData['category_id']];
				}
			}

			if (!empty($rThreadData['bouquets'])) {
				if (is_array($rThreadData['bouquets'])) {
					$rBouquetIDs = array_map('intval', $rThreadData['bouquets']);
				}
				else {
					$rBouquetIDs = json_decode($rThreadData['bouquets'], true);
				}
			}

			if ($rMatch) {
				if ($rThreadData['type'] == 'movie') {
					if ($rThreadData['duplicate_tmdb']) {
						$rUpgradeData = NULL;
					}
					else {
						$rUpgradeData = getmovie($rMatch->get('id'));
					}

					if ($rUpgradeData) {
						if (!$rThreadData['auto_upgrade']) {
							echo 'Upgrade disabled' . "\n";
							exit();
						}

						if (substr($rUpgradeData['source'], 0, 3 + strlen(strval(SERVER_ID))) != 's:' . SERVER_ID . ':') {
							echo 'Old file path doesn\'t match this server, don\'t upgrade.' . "\n";
							exit();
						}
						else {
							$rActualPath = explode('s:' . SERVER_ID . ':', $rUpgradeData['source'])[1];
						}
						if (!file_exists($rActualPath) || (filesize($rActualPath) < filesize($rFile))) {
							echo 'Upgrade movie!' . "\n";
							$db->query('UPDATE `streams` SET `stream_source` = ?, `target_container` = ? WHERE `id` = ?;', $rImportArray['stream_source'], $rImportArray['target_container'], $rUpgradeData['id']);
							$db->query('UPDATE `streams_servers` SET `bitrate` = NULL, `current_source` = NULL, `to_analyze` = 0, `pid` = NULL, `stream_started` = NULL, `stream_info` = NULL, `compatible` = 0, `video_codec` = NULL, `audio_codec` = NULL, `resolution` = NULL, `stream_status` = 0 WHERE `stream_id` = ? AND `server_id` = ?', $rUpgradeData['id'], SERVER_ID);

							if ($rThreadData['auto_encode']) {
								XCMS::queueMovie($rUpgradeData['id']);
							}

							$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 6, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
							file_put_contents(WATCH_TMP_PATH . 'movie_' . $rMatch->get('id') . '.cache', json_encode(['id' => $rUpgradeData['id'], 'source' => 's:' . SERVER_ID . ':' . $rFile]));
							exit();
						}
						else {
							echo 'File isn\'t a better source, don\'t upgrade.' . "\n";
							exit();
						}
					}
					else {
						$rMovie = $rTMDB->getMovie($rMatch->get('id'));
						$rMovieData = json_decode($rMovie->getJSON(), true);
						$rMovieData['trailer'] = $rMovie->getTrailer();
						$rThumb = 'https://image.tmdb.org/t/p/w600_and_h900_bestv2' . $rMovieData['poster_path'];
						$rBG = 'https://image.tmdb.org/t/p/w1280' . $rMovieData['backdrop_path'];

						if (XCMS::$rSettings['download_images']) {
							$rThumb = XCMS::downloadImage($rThumb);
							$rBG = XCMS::downloadImage($rBG);
						}

						$rCast = [];

						foreach ($rMovieData['credits']['cast'] as $rMember) {
							if (count($rCast) < 5) {
								$rCast[] = $rMember['name'];
							}
						}

						$rDirectors = [];

						foreach ($rMovieData['credits']['crew'] as $rMember) {
							if ((count($rDirectors) < 5) && (($rMember['department'] == 'Directing') || ($rMember['known_for_department'] == 'Directing')) && !in_array($rMember['name'], $rDirectors)) {
								$rDirectors[] = $rMember['name'];
							}
						}

						$rCountry = '';

						if (isset($rMovieData['production_countries'][0]['name'])) {
							$rCountry = $rMovieData['production_countries'][0]['name'];
						}

						$rGenres = [];

						foreach ($rMovieData['genres'] as $rGenre) {
							if (count($rGenres) < 3) {
								$rGenres[] = $rGenre['name'];
							}
						}

						$rSeconds = (int) $rMovieData['runtime'] * 60;
						$rImportArray['stream_display_name'] = $rMovieData['title'];

						if (0 < strlen($rMovieData['release_date'])) {
							$rImportArray['year'] = (int) substr($rMovieData['release_date'], 0, 4);
						}

						$rImportArray['tmdb_id'] = $rMovieData['id'] ?: NULL;
						$rImportArray['movie_properties'] = [
							'kinopoisk_url'          => 'https://www.themoviedb.org/movie/' . $rMovieData['id'],
							'tmdb_id'                => $rMovieData['id'],
							'name'                   => $rMovieData['title'],
							'o_name'                 => $rMovieData['original_title'],
							'cover_big'              => $rThumb,
							'movie_image'            => $rThumb,
							'release_date'           => $rMovieData['release_date'],
							'episode_run_time'       => $rMovieData['runtime'],
							'youtube_trailer'        => $rMovieData['trailer'],
							'director'               => implode(', ', $rDirectors),
							'actors'                 => implode(', ', $rCast),
							'cast'                   => implode(', ', $rCast),
							'description'            => $rMovieData['overview'],
							'plot'                   => $rMovieData['overview'],
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
							'rating'                 => $rMovieData['vote_average']
						];
						$rImportArray['rating'] = $rImportArray['movie_properties']['rating'] ?: 0;
						$rImportArray['read_native'] = $rThreadData['read_native'];
						$rImportArray['movie_symlink'] = $rThreadData['movie_symlink'];
						$rImportArray['remove_subtitles'] = $rThreadData['remove_subtitles'];
						$rImportArray['transcode_profile_id'] = $rThreadData['transcode_profile_id'];

						if ($rThreadData['import']) {
							$rImportArray['direct_source'] = $rThreadData['direct_source'];
							$rImportArray['direct_proxy'] = $rThreadData['direct_proxy'];
						}

						$rImportArray['order'] = getnextorder();
						$rImportArray['tmdb_language'] = $rLanguage;

						if (count($rCategoryIDs) == 0) {
							if (0 < $rThreadData['max_genres']) {
								$rParsed = array_slice($rMovieData['genres'], 0, $rThreadData['max_genres']);
							}
							else {
								$rParsed = $rMovieData['genres'];
							}

							foreach ($rParsed as $rGenre) {
								$rCategoryID = (int) $rWatchCategories[1][(int) $rGenre['id']]['category_id'];

								if (0 < $rCategoryID) {
									if (!in_array($rCategoryID, $rCategoryIDs)) {
										$rCategoryIDs[] = $rCategoryID;
									}
								}
							}
						}

						if (count($rBouquetIDs) == 0) {
							if (0 < $rThreadData['max_genres']) {
								$rParsed = array_slice($rMovieData['genres'], 0, $rThreadData['max_genres']);
							}
							else {
								$rParsed = $rMovieData['genres'];
							}

							foreach ($rParsed as $rGenre) {
								$rBouquets = json_decode($rWatchCategories[1][(int) $rGenre['id']]['bouquets'], true);

								foreach ($rBouquets as $rBouquetID) {
									if (!in_array($rBouquetID, $rBouquetIDs)) {
										$rBouquetIDs[] = $rBouquetID;
									}
								}
							}
						}
					}
				}
				else {
					$rShow = $rTMDB->getTVShow($rMatch->get('id'));

					if ($rThreadData['duplicate_tmdb']) {
						$rUpgradeData = NULL;
					}
					else {
						$rUpgradeData = getepisode($rMatch->get('id'), $rReleaseSeason, $rReleaseEpisode);
					}

					if ($rUpgradeData) {
						if (!$rThreadData['auto_upgrade']) {
							echo 'Upgrade disabled' . "\n";
							exit();
						}

						if (substr($rUpgradeData['source'], 0, 3 + strlen(strval(SERVER_ID))) != 's:' . SERVER_ID . ':') {
							echo 'Old file path doesn\'t match this server, don\'t upgrade.' . "\n";
							exit();
						}
						else {
							$rActualPath = explode('s:' . SERVER_ID . ':', $rUpgradeData['source'])[1];
						}
						if (!file_exists($rActualPath) || (filesize($rActualPath) < filesize($rFile))) {
							echo 'Upgrade episode!' . "\n";
							$db->query('UPDATE `streams` SET `stream_source` = ?, `target_container` = ? WHERE `id` = ?;', $rImportArray['stream_source'], $rImportArray['target_container'], $rUpgradeData['id']);
							$db->query('UPDATE `streams_servers` SET `bitrate` = NULL, `current_source` = NULL, `to_analyze` = 0, `pid` = NULL, `stream_started` = NULL, `stream_info` = NULL, `compatible` = 0, `video_codec` = NULL, `audio_codec` = NULL, `resolution` = NULL, `stream_status` = 0 WHERE `stream_id` = ? AND `server_id` = ?', $rUpgradeData['id'], SERVER_ID);

							if ($rThreadData['auto_encode']) {
								XCMS::queueMovie($rUpgradeData['id']);
							}

							$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 6, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
							$rCacheData = json_decode(file_get_contents(WATCH_TMP_PATH . 'series_' . $rMatch->get('id') . '.cache'), true);
							$rCacheData[$rReleaseSeason . '_' . $rReleaseEpisode] = ['id' => $rUpgradeData['id'], 'source' => 's:' . SERVER_ID . ':' . $rFile];
							file_put_contents(WATCH_TMP_PATH . 'series_' . $rMatch->get('id') . '.cache', json_encode($rCacheData));
							exit();
						}
						else {
							echo 'File isn\'t a better source, don\'t upgrade.' . "\n";
							exit();
						}
					}
					else {
						$rShowData = json_decode($rShow->getJSON(), true);

						if ($rShowData['id']) {
							while (file_exists(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id'])) {
								if ($rTimeout < (time() - filemtime(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id']))) {
									unlink(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id']);
								}

								usleep(100000);
							}

							$rFileLock = fopen(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id'], 'w');

							while (!flock($rFileLock, LOCK_EX)) {
								usleep(100000);
							}

							fwrite($rFileLock, time());
							$rSeasonData = [];

							foreach ($rShowData['seasons'] as $rSeason) {
								$rSeason['cover'] = 'https://image.tmdb.org/t/p/w600_and_h900_bestv2' . $rSeason['poster_path'];

								if (XCMS::$rSettings['download_images']) {
									$rSeason['cover'] = XCMS::downloadImage($rSeason['cover'], 2);
								}

								$rSeason['cover_big'] = $rSeason['cover'];
								unset($rSeason['poster_path']);
								$rSeasonData[] = $rSeason;
							}

							$rSeries = getseriesbytmdb($rShowData['id']);

							if (!$rSeries) {
								$rSeriesArray = [
									'title'            => $rShowData['name'],
									'category_id'      => [],
									'episode_run_time' => 0,
									'tmdb_id'          => $rShowData['id'],
									'cover'            => '',
									'genre'            => '',
									'plot'             => $rShowData['overview'],
									'cast'             => '',
									'rating'           => $rShowData['vote_average'],
									'director'         => '',
									'release_date'     => $rShowData['first_air_date'],
									'last_modified'    => time(),
									'seasons'          => $rSeasonData,
									'backdrop_path'    => [],
									'youtube_trailer'  => '',
									'year'             => NULL
								];
								$rSeriesArray['youtube_trailer'] = getseriestrailer($rShowData['id'], !empty($rThreadData['language']) ? $rThreadData['language'] : XCMS::$rSettings['tmdb_language']);
								$rSeriesArray['cover'] = 'https://image.tmdb.org/t/p/w600_and_h900_bestv2' . $rShowData['poster_path'];
								$rSeriesArray['cover_big'] = $rSeriesArray['cover'];
								$rSeriesArray['backdrop_path'] = ['https://image.tmdb.org/t/p/w1280' . $rShowData['backdrop_path']];

								if (XCMS::$rSettings['download_images']) {
									$rSeriesArray['cover'] = XCMS::downloadImage($rSeriesArray['cover'], 2);
									$rSeriesArray['backdrop_path'] = [XCMS::downloadImage($rSeriesArray['backdrop_path'][0])];
								}

								$rCast = [];

								foreach ($rShowData['credits']['cast'] as $rMember) {
									if (count($rCast) < 5) {
										$rCast[] = $rMember['name'];
									}
								}

								$rSeriesArray['cast'] = implode(', ', $rCast);
								$rDirectors = [];

								foreach ($rShowData['credits']['crew'] as $rMember) {
									if ((count($rDirectors) < 5) && (($rMember['department'] == 'Directing') || ($rMember['known_for_department'] == 'Directing')) && !in_array($rMember['name'], $rDirectors)) {
										$rDirectors[] = $rMember['name'];
									}
								}

								$rSeriesArray['director'] = implode(', ', $rDirectors);
								$rGenres = [];

								foreach ($rShowData['genres'] as $rGenre) {
									if (count($rGenres) < $rThreadData['max_genres']) {
										$rGenres[] = $rGenre['name'];
									}
								}

								if ($rShowData['first_air_date']) {
									$rSeriesArray['year'] = (int) substr($rShowData['first_air_date'], 0, 4);
								}

								$rSeriesArray['genre'] = implode(', ', $rGenres);
								$rSeriesArray['episode_run_time'] = (int) $rShowData['episode_run_time'][0];

								if (count($rCategoryIDs) == 0) {
									if (0 < $rThreadData['max_genres']) {
										$rParsed = array_slice($rShowData['genres'], 0, $rThreadData['max_genres']);
									}
									else {
										$rParsed = $rShowData['genres'];
									}

									foreach ($rParsed as $rGenre) {
										$rCategoryID = (int) $rWatchCategories[2][(int) $rGenre['id']]['category_id'];

										if (0 < $rCategoryID) {
											if (!in_array($rCategoryID, $rCategoryIDs)) {
												$rCategoryIDs[] = $rCategoryID;
											}
										}
									}
								}

								if (count($rBouquetIDs) == 0) {
									if (0 < $rThreadData['max_genres']) {
										$rParsed = array_slice($rShowData['genres'], 0, $rThreadData['max_genres']);
									}
									else {
										$rParsed = $rShowData['genres'];
									}

									foreach ($rParsed as $rGenre) {
										$rBouquets = json_decode($rWatchCategories[2][(int) $rGenre['id']]['bouquets'], true);

										foreach ($rBouquets as $rBouquetID) {
											if (!in_array($rBouquetID, $rBouquetIDs)) {
												$rBouquetIDs[] = $rBouquetID;
											}
										}
									}
								}
								if ((count($rCategoryIDs) == 0) && !empty($rThreadData['fb_category_id'])) {
									if (is_array($rThreadData['fb_category_id'])) {
										$rCategoryIDs = array_map('intval', $rThreadData['fb_category_id']);
									}
									else {
										$rCategoryIDs = [(int) $rThreadData['fb_category_id']];
									}
								}
								if ((count($rBouquetIDs) == 0) && !empty($rThreadData['fb_bouquets'])) {
									if (is_array($rThreadData['fb_bouquets'])) {
										$rBouquetIDs = array_map('intval', $rThreadData['fb_bouquets']);
									}
									else {
										$rBouquetIDs = json_decode($rThreadData['fb_bouquets'], true);
									}
								}

								if (count($rCategoryIDs) == 0) {
									$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 3, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
									exit();
								}

								$rSeriesArray['tmdb_language'] = $rLanguage;
								$rSeriesArray['category_id'] = '[' . implode(',', array_map('intval', $rCategoryIDs)) . ']';
								$rPrepare = preparearray($rSeriesArray);
								$rQuery = 'INSERT INTO `streams_series`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

								if ($db->query($rQuery, ...$rPrepare['data'])) {
									$rInsertID = $db->last_insert_id();
									$rSeries = getserie($rInsertID);
									file_put_contents(WATCH_TMP_PATH . 'series_' . (int) $rShowData['id'], json_encode($rSeries));

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

								if (!file_exists(WATCH_TMP_PATH . 'series_' . (int) $rShowData['id'])) {
									file_put_contents(WATCH_TMP_PATH . 'series_' . (int) $rShowData['id'], json_encode($rSeries));
								}
							}

							flock($rFileLock, LOCK_UN);
							unlink(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id']);
							$rImportArray['read_native'] = $rThreadData['read_native'];
							$rImportArray['movie_symlink'] = $rThreadData['movie_symlink'];
							$rImportArray['remove_subtitles'] = $rThreadData['remove_subtitles'];
							$rImportArray['transcode_profile_id'] = $rThreadData['transcode_profile_id'];

							if ($rThreadData['import']) {
								$rImportArray['direct_source'] = $rThreadData['direct_source'];
								$rImportArray['direct_proxy'] = $rThreadData['direct_proxy'];
							}

							$rImportArray['order'] = getnextorder();
							if ($rReleaseSeason && $rReleaseEpisode) {
								if (is_array($rRelease['episode']) && (count($rRelease['episode']) == 2)) {
									$rImportArray['stream_display_name'] = $rShowData['name'] . ' - S' . sprintf('%02d', (int) $rReleaseSeason) . 'E' . sprintf('%02d', $rRelease['episode'][0]) . '-' . sprintf('%02d', $rRelease['episode'][1]);
								}
								else {
									$rImportArray['stream_display_name'] = $rShowData['name'] . ' - S' . sprintf('%02d', (int) $rReleaseSeason) . 'E' . sprintf('%02d', $rReleaseEpisode);
								}

								$rEpisodes = json_decode($rTMDB->getSeason($rShowData['id'], (int) $rReleaseSeason)->getJSON(), true);

								foreach ($rEpisodes['episodes'] as $rEpisode) {
									if ($rReleaseEpisode == (int) $rEpisode['episode_number']) {
										if (0 < strlen($rEpisode['still_path'])) {
											$rImage = 'https://image.tmdb.org/t/p/w1280' . $rEpisode['still_path'];

											if (XCMS::$rSettings['download_images']) {
												$rImage = XCMS::downloadImage($rImage, 5);
											}
										}

										if (0 < strlen($rEpisode['name'])) {
											$rImportArray['stream_display_name'] .= ' - ' . $rEpisode['name'];
										}

										$rSeconds = (int) $rShowData['episode_run_time'][0] * 60;
										$rImportArray['movie_properties'] = [
											'tmdb_id'       => $rEpisode['id'],
											'release_date'  => $rEpisode['air_date'],
											'plot'          => $rEpisode['overview'],
											'duration_secs' => $rSeconds,
											'duration'      => sprintf('%02d:%02d:%02d', $rSeconds / 3600, ($rSeconds / 60) % 60, $rSeconds % 60),
											'movie_image'   => $rImage,
											'video'         => [],
											'audio'         => [],
											'bitrate'       => 0,
											'rating'        => $rEpisode['vote_average'],
											'season'        => $rReleaseSeason
										];

										if (strlen($rImportArray['movie_properties']['movie_image'][0]) == 0) {
											unset($rImportArray['movie_properties']['movie_image']);
										}
									}
								}

								if (strlen($rImportArray['stream_display_name']) == 0) {
									$rImportArray['stream_display_name'] = 'No Episode Title';
								}
							}
						}
					}
				}
			}
			else {
				if ($rThreadData['type'] == 'movie') {
					$rImportArray['stream_display_name'] = $rTitle;

					if ($rYear) {
						$rImportArray['year'] = $rYear;
					}
				}
				else if ($rReleaseSeason && $rReleaseEpisode) {
					$rImportArray['stream_display_name'] = $rTitle . ' - S' . sprintf('%02d', (int) $rReleaseSeason) . 'E' . sprintf('%02d', $rReleaseEpisode) . ' - ';
				}

				$rImportArray['read_native'] = $rThreadData['read_native'];
				$rImportArray['movie_symlink'] = $rThreadData['movie_symlink'];
				$rImportArray['remove_subtitles'] = $rThreadData['remove_subtitles'];
				$rImportArray['transcode_profile_id'] = $rThreadData['transcode_profile_id'];

				if ($rThreadData['import']) {
					$rImportArray['direct_source'] = $rThreadData['direct_source'];
					$rImportArray['direct_proxy'] = $rThreadData['direct_proxy'];
				}

				$rImportArray['order'] = getnextorder();
				$rImportArray['tmdb_language'] = $rLanguage;
			}

			if ($rThreadData['type'] == 'movie') {
				if ((count($rCategoryIDs) == 0) && !empty($rThreadData['fb_category_id'])) {
					if (is_array($rThreadData['fb_category_id'])) {
						$rCategoryIDs = array_map('intval', $rThreadData['fb_category_id']);
					}
					else {
						$rCategoryIDs = [(int) $rThreadData['fb_category_id']];
					}
				}
				if ((count($rBouquetIDs) == 0) && !empty($rThreadData['fb_bouquets'])) {
					if (is_array($rThreadData['fb_bouquets'])) {
						$rBouquetIDs = array_map('intval', $rThreadData['fb_bouquets']);
					}
					else {
						$rBouquetIDs = json_decode($rThreadData['fb_bouquets'], true);
					}
				}

				$rImportArray['category_id'] = '[' . implode(',', array_map('intval', $rCategoryIDs)) . ']';

				if (count($rCategoryIDs) == 0) {
					$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 3, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
					exit();
				}
			}
			else if ($rSeries) {
				$rImportArray['series_no'] = $rSeries['id'];
			}
			else {
				$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 4, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
				exit();
			}

			if ($rThreadData['subtitles']) {
				$rImportArray['movie_subtitles'] = $rThreadData['subtitles'];
			}

			$rImportArray['added'] = time();
			$rPrepare = preparearray($rImportArray);
			$rQuery = 'INSERT INTO `streams`(' . $rPrepare['columns'] . ') VALUES(' . $rPrepare['placeholder'] . ');';

			if ($db->query($rQuery, ...$rPrepare['data'])) {
				$rInsertID = $db->last_insert_id();

				if ($rThreadData['import']) {
					foreach ($rThreadData['servers'] as $rServerID) {
						$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`) VALUES(?, ?, NULL);', $rInsertID, $rServerID);
					}
				}
				else {
					$db->query('INSERT INTO `streams_servers`(`stream_id`, `server_id`, `parent_id`) VALUES(?, ?, NULL);', $rInsertID, SERVER_ID);
				}

				if ($rThreadData['type'] == 'movie') {
					if ($rMatch && !$rThreadData['import']) {
						file_put_contents(WATCH_TMP_PATH . 'movie_' . $rMatch->get('id') . '.cache', json_encode(['id' => $rInsertID, 'source' => 's:' . SERVER_ID . ':' . $rFile]));
					}

					foreach ($rBouquetIDs as $rBouquet) {
						addtobouquet('movie', $rBouquet, $rInsertID);
					}
				}
				else {
					$db->query('INSERT INTO `streams_episodes`(`season_num`, `series_id`, `stream_id`, `episode_num`) VALUES(?, ?, ?, ?);', $rReleaseSeason, $rSeries['id'], $rInsertID, $rReleaseEpisode);
				}

				if ($rThreadData['auto_encode']) {
					if ($rThreadData['import']) {
						foreach ($rThreadData['servers'] as $rServerID) {
							XCMS::queueMovie($rInsertID, $rServerID);
						}
					}
					else {
						XCMS::queueMovie($rInsertID);
					}
				}

				echo 'Success!' . "\n";
				$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 1, ?);', $rThreadType, SERVER_ID, utf8_decode($rFile), $rInsertID);
				exit();
			}
			else {
				echo 'Insert failed!' . "\n";
				$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 2, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
				exit();
			}
		}
		else {
			echo 'No match!' . "\n";
			$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 4, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
			exit();
		}
	}
	else {
		echo 'File is broken!' . "\n";
		$db->query('INSERT INTO `watch_logs`(`type`, `server_id`, `filename`, `status`, `stream_id`) VALUES(?, ?, ?, 5, 0);', $rThreadType, SERVER_ID, utf8_decode($rFile));
		exit();
	}
}

function shutdown()
{
	global $db;
	global $rShowData;
	if (is_array($rShowData) && $rShowData['id'] && file_exists(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id'])) {
		unlink(WATCH_TMP_PATH . 'lock_' . (int) $rShowData['id']);
	}

	if (is_object($db)) {
		$db->close_mysql();
	}

	@unlink(WATCH_TMP_PATH . getmypid() . '.wpid');
}

setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

$rTimeout = 60;
set_time_limit($rTimeout);
ini_set('max_execution_time', $rTimeout);
register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
require INCLUDES_PATH . 'libs/tmdb.php';
require INCLUDES_PATH . 'libs/tmdb_release.php';
$rThreadData = json_decode(base64_decode($argv[1]), true);

if (!$rThreadData) {
	exit();
}

file_put_contents(WATCH_TMP_PATH . getmypid() . '.wpid', time());
loadcli();

?>