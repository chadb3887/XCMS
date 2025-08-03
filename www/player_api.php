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

function getOutputFormats($rFormats)
{
	$rFormatArray = [1 => 'm3u8', 2 => 'ts', 3 => 'rtmp'];
	$rReturn = [];

	foreach ($rFormats as $rFormat) {
		$rReturn[] = $rFormatArray[$rFormat];
	}

	return $rReturn;
}

function shutdown()
{
	global $rDeny;

	if ($rDeny) {
		XCMS::checkFlood();
	}

	if (is_object(XCMS::$db)) {
		XCMS::$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
require './stream/init.php';
set_time_limit(0);

if (XCMS::$rSettings['force_epg_timezone']) {
	date_default_timezone_set('UTC');
}

$rDeny = true;

if (XCMS::$rSettings['disable_player_api']) {
	$rDeny = false;
	generateError('PLAYER_API_DISABLED');
}

$rPanelAPI = false;

if (strtolower(explode('.', ltrim(parse_url($_SERVER['REQUEST_URI'])['path'], '/'))[0]) == 'panel_api') {
	if (!XCMS::$rSettings['legacy_panel_api']) {
		$rDeny = false;
		generateError('LEGACY_PANEL_API_DISABLED');
	}
	else {
		$rPanelAPI = true;
	}
}

$rIP = $_SERVER['REMOTE_ADDR'];
$rUserAgent = trim($_SERVER['HTTP_USER_AGENT']);
$rOffset = (empty(XCMS::$rRequest['params']['offset']) ? 0 : abs((int) XCMS::$rRequest['params']['offset']));
$rLimit = (empty(XCMS::$rRequest['params']['items_per_page']) ? 0 : abs((int) XCMS::$rRequest['params']['items_per_page']));
$rNameTypes = ['live' => 'Live Streams', 'movie' => 'Movies', 'created_live' => 'Created Channels', 'radio_streams' => 'Radio Stations', 'series' => 'TV Series'];
$rDomainName = XCMS::getDomainName();
$rDomain = parse_url($rDomainName)['host'];
$rValidActions = [0 => 'get_epg', 200 => 'get_vod_categories', 201 => 'get_live_categories', 202 => 'get_live_streams', 203 => 'get_vod_streams', 204 => 'get_series_info', 205 => 'get_short_epg', 206 => 'get_series_categories', 207 => 'get_simple_data_table', 208 => 'get_series', 209 => 'get_vod_info'];
$rOutput = [];
$rAction = (!empty(XCMS::$rRequest['action']) && (in_array(XCMS::$rRequest['action'], $rValidActions) || array_key_exists(XCMS::$rRequest['action'], $rValidActions)) ? XCMS::$rRequest['action'] : '');

if (isset($rValidActions[$rAction])) {
	$rAction = $rValidActions[$rAction];
}
if ($rPanelAPI && empty($rAction)) {
	$rGetChannels = true;
}
else {
	$rGetChannels = in_array($rAction, ['get_series' => true, 'get_vod_streams' => true, 'get_live_streams' => true]);
}

if ($rGetChannels) {
	XCMS::$rBouquets = XCMS::getCache('bouquets');
}
if (($rPanelAPI && empty($rAction)) || in_array($rAction, ['get_vod_categories' => true, 'get_series_categories' => true, 'get_live_categories' => true])) {
	XCMS::$rCategories = XCMS::getCache('categories');
}

$rExtract = ['offset' => $rOffset, 'items_per_page' => $rLimit];
if (isset(XCMS::$rRequest['username']) && isset(XCMS::$rRequest['password'])) {
	$rUsername = XCMS::$rRequest['username'];
	$rPassword = XCMS::$rRequest['password'];
	if (empty($rUsername) || empty($rPassword)) {
		generateError('NO_CREDENTIALS');
	}

	$rUserInfo = XCMS::getUserInfo(NULL, $rUsername, $rPassword, $rGetChannels);
}
else if (isset(XCMS::$rRequest['token'])) {
	$rToken = XCMS::$rRequest['token'];

	if (empty($rToken)) {
		generateError('NO_CREDENTIALS');
	}

	$rUserInfo = XCMS::getUserInfo(NULL, $rToken, NULL, $rGetChannels);
}

ini_set('memory_limit', -1);

if ($rUserInfo) {
	$rDeny = false;
	$rValidUser = false;
	if (($rUserInfo['admin_enabled'] == 1) && ($rUserInfo['enabled'] == 1) && (is_null($rUserInfo['exp_date']) || (time() < $rUserInfo['exp_date']))) {
		$rValidUser = true;
	}
	else if (!$rUserInfo['admin_enabled']) {
		generateError('BANNED');
	}
	else if (!$rUserInfo['enabled']) {
		generateError('DISABLED');
	}
	else {
		generateError('EXPIRED');
	}

	XCMS::checkAuthFlood($rUserInfo);
	header('Content-Type: application/json');

	if (isset($_SERVER['HTTP_ORIGIN'])) {
		header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
	}

	header('Access-Control-Allow-Credentials: true');

	switch ($rAction) {
	case 'get_epg':
		if (!empty(XCMS::$rRequest['stream_id']) && (is_null($rUserInfo['exp_date']) || (time() < $rUserInfo['exp_date']))) {
			$rFromNow = !empty(XCMS::$rRequest['from_now']) && (0 < XCMS::$rRequest['from_now']);
			if (is_numeric(XCMS::$rRequest['stream_id']) && !isset(XCMS::$rRequest['multi'])) {
				$rMulti = false;
				$rStreamIDs = [(int) XCMS::$rRequest['stream_id']];
			}
			else {
				$rMulti = true;
				$rStreamIDs = array_map('intval', explode(',', XCMS::$rRequest['stream_id']));
			}

			$rEPGs = [];

			if (0 < count($rStreamIDs)) {
				foreach ($rStreamIDs as $rStreamID) {
					if (file_exists(EPG_PATH . 'stream_' . (int) $rStreamID)) {
						$rRows = igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStreamID));

						foreach ($rRows as $rRow) {
							if ($rFromNow && ($rRow['end'] < time())) {
								continue;
							}

							$rRow['title'] = base64_encode($rRow['title']);
							$rRow['description'] = base64_encode($rRow['description']);
							$rRow['start'] = (int) $rRow['start'];
							$rRow['end'] = (int) $rRow['end'];

							if ($rMulti) {
								$rEPGs[$rStreamID][] = $rRow;
							}
							else {
								$rEPGs[] = $rRow;
							}
						}
					}
				}
			}

			echo json_encode($rEPGs);
			exit();
		}
		else {
			echo json_encode([]);
			exit();
		}
	case 'get_series_info':
		$rSeriesID = (empty(XCMS::$rRequest['series_id']) ? 0 : (int) XCMS::$rRequest['series_id']);

		if (XCMS::$rCached) {
			$rSeriesInfo = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'series_' . $rSeriesID));
			$rRows = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'episodes_' . $rSeriesID));
		}
		else {
			XCMS::$db->query('SELECT * FROM `streams_episodes` t1 INNER JOIN `streams` t2 ON t2.id=t1.stream_id WHERE t1.series_id = ? ORDER BY t1.season_num ASC, t1.episode_num ASC', $rSeriesID);
			$rRows = XCMS::$db->get_rows(true, 'season_num', false);
			XCMS::$db->query('SELECT * FROM `streams_series` WHERE `id` = ?', $rSeriesID);
			$rSeriesInfo = XCMS::$db->get_row();
		}

		$rOutput['seasons'] = [];

		foreach (!empty($rSeriesInfo['seasons']) ? array_values(json_decode($rSeriesInfo['seasons'], true)) : [] as $rSeason) {
			$rSeason['cover'] = XCMS::validateImage($rSeason['cover']);
			$rSeason['cover_big'] = XCMS::validateImage($rSeason['cover_big']);
			$rOutput['seasons'][] = $rSeason;
		}

		$rBackdrops = json_decode($rSeriesInfo['backdrop_path'], true);

		if (0 < count($rBackdrops)) {
			foreach (range(0, count($rBackdrops) - 1) as $i) {
				$rBackdrops[$i] = XCMS::validateImage($rBackdrops[$i]);
			}
		}

		$rOutput['info'] = ['name' => XCMS::formatTitle($rSeriesInfo['title'], $rSeriesInfo['year']), 'title' => $rSeriesInfo['title'], 'year' => $rSeriesInfo['year'], 'cover' => XCMS::validateImage($rSeriesInfo['cover']), 'plot' => $rSeriesInfo['plot'], 'cast' => $rSeriesInfo['cast'], 'director' => $rSeriesInfo['director'], 'genre' => $rSeriesInfo['genre'], 'release_date' => $rSeriesInfo['release_date'], 'releaseDate' => $rSeriesInfo['release_date'], 'last_modified' => $rSeriesInfo['last_modified'], 'rating' => number_format($rSeriesInfo['rating'], 0), 'rating_5based' => number_format($rSeriesInfo['rating'] * 0.5, 1) + 0, 'backdrop_path' => $rBackdrops, 'youtube_trailer' => $rSeriesInfo['youtube_trailer'], 'episode_run_time' => $rSeriesInfo['episode_run_time'], 'category_id' => strval(json_decode($rSeriesInfo['category_id'], true)[0]), 'category_ids' => json_decode($rSeriesInfo['category_id'], true)];

		foreach ($rRows as $rSeason => $rEpisodes) {
			$rNum = 1;

			foreach ($rEpisodes as $rEpisode) {
				if (XCMS::$rCached) {
					$rEpisodeData = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rEpisode['stream_id']))['info'];
				}
				else {
					$rEpisodeData = $rEpisode;
				}

				if (XCMS::$rSettings['api_redirect']) {
					$rEncData = 'series/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rEpisodeData['id'] . '/' . $rEpisodeData['target_container'];
					$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
					$rURL = $rDomainName . ('play/' . $rToken);
				}
				else {
					$rURL = '';
				}

				$rProperties = (!empty($rEpisodeData['movie_properties']) ? json_decode($rEpisodeData['movie_properties'], true) : '');
				$rProperties['cover_big'] = XCMS::validateImage($rProperties['cover_big']);
				$rProperties['movie_image'] = XCMS::validateImage($rProperties['movie_image']);

				if (!$rProperties['cover_big']) {
					$rProperties['cover_big'] = $rProperties['movie_image'];
				}

				if (0 < count($rProperties['backdrop_path'])) {
					foreach (range(0, count($rProperties['backdrop_path']) - 1) as $i) {
						if ($rProperties['backdrop_path'][$i]) {
							$rProperties['backdrop_path'][$i] = XCMS::validateImage($rProperties['backdrop_path'][$i]);
						}
					}
				}

				$rSubtitles = [];

				if (is_array($rProperties['subtitle'])) {
					$i = 0;

					foreach ($rProperties['subtitle'] as $rSubtitle) {
						$rSubtitles[] = ['index' => $i, 'language' => $rSubtitle['tags']['language'] ?: NULL, 'title' => $rSubtitle['tags']['title'] ?: NULL];
						$i++;
					}
				}

				foreach (['audio', 'video', 'subtitle'] as $rKey) {
					if (isset($rProperties[$rKey])) {
						unset($rProperties[$rKey]);
					}
				}

				$rOutput['episodes'][$rSeason][] = ['id' => $rEpisode['stream_id'], 'episode_num' => $rEpisode['episode_num'], 'title' => $rEpisodeData['stream_display_name'], 'container_extension' => $rEpisodeData['target_container'], 'info' => $rProperties, 'subtitles' => $rSubtitles, 'custom_sid' => strval($rEpisodeData['custom_sid']), 'added' => $rEpisodeData['added'] ?: '', 'season' => $rSeason, 'direct_source' => $rURL];
			}
		}

		break;
	case 'get_series':
		$rCategoryIDSearch = (empty(XCMS::$rRequest['category_id']) ? NULL : (int) XCMS::$rRequest['category_id']);
		$rMovieNum = 0;

		if (0 < count($rUserInfo['series_ids'])) {
			if (XCMS::$rCached) {
				if (XCMS::$rSettings['vod_sort_newest']) {
					$rUserInfo['series_ids'] = XCMS::sortSeries($rUserInfo['series_ids']);
				}

				foreach ($rUserInfo['series_ids'] as $rSeriesID) {
					$rSeriesItem = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'series_' . $rSeriesID));
					$rBackdrops = json_decode($rSeriesItem['backdrop_path'], true);

					if (0 < count($rBackdrops)) {
						foreach (range(0, count($rBackdrops) - 1) as $i) {
							$rBackdrops[$i] = XCMS::validateImage($rBackdrops[$i]);
						}
					}

					$rCategoryIDs = json_decode($rSeriesItem['category_id'], true);

					foreach ($rCategoryIDs as $rCategoryID) {
						if (!$rCategoryIDSearch || ($rCategoryIDSearch == $rCategoryID)) {
							$rOutput[] = ['num' => ++$rMovieNum, 'name' => XCMS::formatTitle($rSeriesItem['title'], $rSeriesItem['year']), 'title' => $rSeriesItem['title'], 'year' => $rSeriesItem['year'], 'stream_type' => 'series', 'series_id' => (int) $rSeriesItem['id'], 'cover' => XCMS::validateImage($rSeriesItem['cover']), 'plot' => $rSeriesItem['plot'], 'cast' => $rSeriesItem['cast'], 'director' => $rSeriesItem['director'], 'genre' => $rSeriesItem['genre'], 'release_date' => $rSeriesItem['release_date'], 'releaseDate' => $rSeriesItem['release_date'], 'last_modified' => $rSeriesItem['last_modified'], 'rating' => number_format($rSeriesItem['rating'], 0), 'rating_5based' => number_format($rSeriesItem['rating'] * 0.5, 1) + 0, 'backdrop_path' => $rBackdrops, 'youtube_trailer' => $rSeriesItem['youtube_trailer'], 'episode_run_time' => $rSeriesItem['episode_run_time'], 'category_id' => strval($rCategoryID), 'category_ids' => $rCategoryIDs];
						}
						if (!$rCategoryIDSearch && !XCMS::$rSettings['show_category_duplicates']) {
							break;
						}
					}
				}
			}
			else if (!empty($rUserInfo['series_ids'])) {
				if (XCMS::$rSettings['vod_sort_newest']) {
					XCMS::$db->query('SELECT *, (SELECT MAX(`streams`.`added`) FROM `streams_episodes` LEFT JOIN `streams` ON `streams`.`id` = `streams_episodes`.`stream_id` WHERE `streams_episodes`.`series_id` = `streams_series`.`id`) AS `last_modified_stream` FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['series_ids'])) . ') ORDER BY `last_modified_stream` DESC, `last_modified` DESC;');
				}
				else {
					XCMS::$db->query('SELECT * FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['series_ids'])) . ') ORDER BY FIELD(`id`,' . implode(',', $rUserInfo['series_ids']) . ') ASC;');
				}

				$rSeries = XCMS::$db->get_rows(true, 'id');

				foreach ($rSeries as $rSeriesItem) {
					if (isset($rSeriesItem['last_modified_stream']) && !empty($rSeriesItem['last_modified_stream'])) {
						$rSeriesItem['last_modified'] = $rSeriesItem['last_modified_stream'];
					}

					$rBackdrops = json_decode($rSeriesItem['backdrop_path'], true);

					if (0 < count($rBackdrops)) {
						foreach (range(0, count($rBackdrops) - 1) as $i) {
							$rBackdrops[$i] = XCMS::validateImage($rBackdrops[$i]);
						}
					}

					$rCategoryIDs = json_decode($rSeriesItem['category_id'], true);

					foreach ($rCategoryIDs as $rCategoryID) {
						if (!$rCategoryIDSearch || ($rCategoryIDSearch == $rCategoryID)) {
							$rOutput[] = ['num' => ++$rMovieNum, 'name' => XCMS::formatTitle($rSeriesItem['title'], $rSeriesItem['year']), 'title' => $rSeriesItem['title'], 'year' => $rSeriesItem['year'], 'stream_type' => 'series', 'series_id' => (int) $rSeriesItem['id'], 'cover' => XCMS::validateImage($rSeriesItem['cover']), 'plot' => $rSeriesItem['plot'], 'cast' => $rSeriesItem['cast'], 'director' => $rSeriesItem['director'], 'genre' => $rSeriesItem['genre'], 'release_date' => $rSeriesItem['release_date'], 'releaseDate' => $rSeriesItem['release_date'], 'last_modified' => $rSeriesItem['last_modified'], 'rating' => number_format($rSeriesItem['rating'], 0), 'rating_5based' => number_format($rSeriesItem['rating'] * 0.5, 1) + 0, 'backdrop_path' => $rBackdrops, 'youtube_trailer' => $rSeriesItem['youtube_trailer'], 'episode_run_time' => $rSeriesItem['episode_run_time'], 'category_id' => strval($rCategoryID), 'category_ids' => $rCategoryIDs];
						}
						if (!$rCategoryIDSearch && !XCMS::$rSettings['show_category_duplicates']) {
							break;
						}
					}
				}
			}
		}

		break;
	case 'get_vod_categories':
		$rCategories = XCMS::getCategories('movie');

		foreach ($rCategories as $rCategory) {
			if (in_array($rCategory['id'], $rUserInfo['category_ids'])) {
				$rOutput[] = ['category_id' => strval($rCategory['id']), 'category_name' => $rCategory['category_name'], 'parent_id' => 0];
			}
		}

		break;
	case 'get_series_categories':
		$rCategories = XCMS::getCategories('series');

		foreach ($rCategories as $rCategory) {
			if (in_array($rCategory['id'], $rUserInfo['category_ids'])) {
				$rOutput[] = ['category_id' => strval($rCategory['id']), 'category_name' => $rCategory['category_name'], 'parent_id' => 0];
			}
		}

		break;
	case 'get_live_categories':
		$rCategories = array_merge(XCMS::getCategories('live'), XCMS::getCategories('radio'));

		foreach ($rCategories as $rCategory) {
			if (in_array($rCategory['id'], $rUserInfo['category_ids'])) {
				$rOutput[] = ['category_id' => strval($rCategory['id']), 'category_name' => $rCategory['category_name'], 'parent_id' => 0];
			}
		}

		break;
	case 'get_simple_data_table':
		$rOutput['epg_listings'] = [];

		if (!empty(XCMS::$rRequest['stream_id'])) {
			if (is_numeric(XCMS::$rRequest['stream_id']) && !isset(XCMS::$rRequest['multi'])) {
				$rMulti = false;
				$rStreamIDs = [(int) XCMS::$rRequest['stream_id']];
			}
			else {
				$rMulti = true;
				$rStreamIDs = array_map('intval', explode(',', XCMS::$rRequest['stream_id']));
			}

			if (0 < count($rStreamIDs)) {
				$rArchiveInfo = [];

				if (XCMS::$rCached) {
					foreach ($rStreamIDs as $rStreamID) {
						if (file_exists(STREAMS_TMP_PATH . 'stream_' . (int) $rStreamID)) {
							$rRow = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rStreamID))['info'];
							$rArchiveInfo[$rStreamID] = (int) $rRow['tv_archive_duration'];
						}
					}
				}
				else {
					$db->query('SELECT `id`, `tv_archive_duration` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rStreamIDs)) . ');');

					if (0 < $db->num_rows()) {
						foreach ($db->get_rows() as $rRow) {
							$rArchiveInfo[$rRow['id']] = (int) $rRow['tv_archive_duration'];
						}
					}
				}

				foreach ($rStreamIDs as $rStreamID) {
					if (file_exists(EPG_PATH . 'stream_' . (int) $rStreamID)) {
						$rRows = igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStreamID));

						foreach ($rRows as $rEPGData) {
							$rNowPlaying = $rHasArchive = 0;
							$rEPGData['start_timestamp'] = $rEPGData['start'];
							$rEPGData['stop_timestamp'] = $rEPGData['end'];
							if (($rEPGData['start_timestamp'] <= time()) && (time() <= $rEPGData['stop_timestamp'])) {
								$rNowPlaying = 1;
							}
							if (!empty($rArchiveInfo[$rStreamID]) && ($rEPGData['stop_timestamp'] < time()) && (strtotime('-' . $rArchiveInfo[$rStreamID] . ' days') <= $rEPGData['stop_timestamp'])) {
								$rHasArchive = 1;
							}

							$rEPGData['now_playing'] = $rNowPlaying;
							$rEPGData['has_archive'] = $rHasArchive;
							$rEPGData['title'] = base64_encode($rEPGData['title']);
							$rEPGData['description'] = base64_encode($rEPGData['description']);
							$rEPGData['start'] = date('Y-m-d H:i:s', $rEPGData['start_timestamp']);
							$rEPGData['end'] = date('Y-m-d H:i:s', $rEPGData['stop_timestamp']);

							if ($rMulti) {
								$rOutput['epg_listings'][$rStreamID][] = $rEPGData;
							}
							else {
								$rOutput['epg_listings'][] = $rEPGData;
							}
						}
					}
				}
			}
		}

		break;
	case 'get_short_epg':
		$rOutput['epg_listings'] = [];

		if (!empty(XCMS::$rRequest['stream_id'])) {
			$rLimit = (empty(XCMS::$rRequest['limit']) ? 4 : (int) XCMS::$rRequest['limit']);
			if (is_numeric(XCMS::$rRequest['stream_id']) && !isset(XCMS::$rRequest['multi'])) {
				$rMulti = false;
				$rStreamIDs = [(int) XCMS::$rRequest['stream_id']];
			}
			else {
				$rMulti = true;
				$rStreamIDs = array_map('intval', explode(',', XCMS::$rRequest['stream_id']));
			}

			if (0 < count($rStreamIDs)) {
				$rTime = time();

				foreach ($rStreamIDs as $rStreamID) {
					if (file_exists(EPG_PATH . 'stream_' . (int) $rStreamID)) {
						$rRows = igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStreamID));

						foreach ($rRows as $rRow) {
							if ((($rRow['start'] <= $rTime) && ($rTime <= $rRow['end'])) || ($rTime <= $rRow['start'])) {
								$rRow['start_timestamp'] = $rRow['start'];
								$rRow['stop_timestamp'] = $rRow['end'];
								$rRow['title'] = base64_encode($rRow['title']);
								$rRow['description'] = base64_encode($rRow['description']);
								$rRow['start'] = date('Y-m-d H:i:s', $rRow['start']);
								$rRow['stop'] = date('Y-m-d H:i:s', $rRow['end']);
								$rRow['stream_id'] = $rStreamIDs;
								$rRow['end'] = date('Y-m-d H:i:s', $rRow['end']);

								if ($rMulti) {
									$rOutput['epg_listings'][$rStreamID][] = $rRow;
								}
								else {
									$rOutput['epg_listings'][] = $rRow;
								}

								if ($rLimit <= count($rOutput['epg_listings'])) {
									break;
								}
							}
						}
					}
				}
			}
		}

		break;
	case 'get_live_streams':
		$rCategoryIDSearch = (empty(XCMS::$rRequest['category_id']) ? NULL : (int) XCMS::$rRequest['category_id']);
		$rLiveNum = 0;
		$rUserInfo['live_ids'] = array_merge($rUserInfo['live_ids'], $rUserInfo['radio_ids']);

		if (!empty($rExtract['items_per_page'])) {
			$rUserInfo['live_ids'] = array_slice($rUserInfo['live_ids'], $rExtract['offset'], $rExtract['items_per_page']);
		}

		$rUserInfo['live_ids'] = XCMS::sortChannels($rUserInfo['live_ids']);

		if (!XCMS::$rCached) {
			$rChannels = [];

			if (0 < count($rUserInfo['live_ids'])) {
				$rWhereV = $rWhere = [];

				if (!empty($rCategoryIDSearch)) {
					$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
					$rWhereV[] = $rCategoryIDSearch;
				}

				$rWhere[] = '`t1`.`id` IN (' . implode(',', $rUserInfo['live_ids']) . ')';
				$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);

				if (XCMS::$rSettings['channel_number_type'] != 'manual') {
					$rOrder = 'FIELD(`t1`.`id`,' . implode(',', $rUserInfo['live_ids']) . ')';
				}
				else {
					$rOrder = '`order`';
				}

				XCMS::$db->query('SELECT t1.id,t1.epg_id,t1.added,t1.allow_record,t1.year,t1.channel_id,t1.movie_properties,t1.stream_source,t1.tv_archive_server_id,t1.vframes_server_id,t1.tv_archive_duration,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t1.series_no,t1.direct_source,t2.type_output,t1.target_container,t2.live,t1.rtmp_output,t1.order,t2.type_key FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type ' . $rWhereString . ' ORDER BY ' . $rOrder . ';', ...$rWhereV);
				$rChannels = XCMS::$db->get_rows();
			}
		}
		else {
			$rChannels = $rUserInfo['live_ids'];
		}

		foreach ($rChannels as $rChannel) {
			if (XCMS::$rCached) {
				$rChannel = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rChannel))['info'];
			}

			if (!in_array($rChannel['type_key'], ['live' => true, 'created_live' => true, 'radio_streams' => true])) {
				continue;
			}

			$rCategoryIDs = json_decode($rChannel['category_id'], true);

			foreach ($rCategoryIDs as $rCategoryID) {
				if (!$rCategoryIDSearch || ($rCategoryIDSearch == $rCategoryID)) {
					$rStreamIcon = XCMS::validateImage($rChannel['stream_icon']) ?: '';
					$rTVArchive = (!empty($rChannel['tv_archive_server_id']) && !empty($rChannel['tv_archive_duration']) ? 1 : 0);

					if (XCMS::$rSettings['api_redirect']) {
						$rEncData = 'live/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'];
						$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
						if (XCMS::$rSettings['cloudflare'] && (XCMS::$rSettings['api_container'] == 'ts')) {
							$rURL = $rDomainName . ('play/' . $rToken);
						}
						else {
							$rURL = $rDomainName . ('play/' . $rToken . '/') . XCMS::$rSettings['api_container'];
						}
					}
					else {
						$rURL = '';
					}

					if ($rChannel['vframes_server_id']) {
						$rEncData = 'thumb/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'];
						$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
						$rThumbURL = $rDomainName . ('play/' . $rToken);
					}
					else {
						$rThumbURL = '';
					}

					$rOutput[] = ['num' => ++$rLiveNum, 'name' => $rChannel['stream_display_name'], 'stream_type' => $rChannel['type_key'], 'stream_id' => (int) $rChannel['id'], 'stream_icon' => $rStreamIcon, 'epg_channel_id' => $rChannel['channel_id'], 'added' => $rChannel['added'] ?: '', 'custom_sid' => strval($rChannel['custom_sid']), 'tv_archive' => $rTVArchive, 'direct_source' => $rURL, 'tv_archive_duration' => $rTVArchive ? (int) $rChannel['tv_archive_duration'] : 0, 'category_id' => strval($rCategoryID), 'category_ids' => $rCategoryIDs, 'thumbnail' => $rThumbURL];
				}
				if (!$rCategoryIDSearch && !XCMS::$rSettings['show_category_duplicates']) {
					break;
				}
			}
		}

		break;
	case 'get_vod_info':
		$rOutput['info'] = [];

		if (!empty(XCMS::$rRequest['vod_id'])) {
			$rVODID = (int) XCMS::$rRequest['vod_id'];

			if (XCMS::$rCached) {
				$rRow = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rVODID))['info'];
			}
			else {
				XCMS::$db->query('SELECT * FROM `streams` WHERE `id` = ?', $rVODID);
				$rRow = XCMS::$db->get_row();
			}

			if ($rRow) {
				if (XCMS::$rSettings['api_redirect']) {
					$rEncData = 'movie/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rRow['id'] . '/' . $rRow['target_container'];
					$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
					$rURL = $rDomainName . ('play/' . $rToken);
				}
				else {
					$rURL = '';
				}

				$rOutput['info'] = json_decode($rRow['movie_properties'], true);
				$rOutput['info']['tmdb_id'] = (int) $rOutput['info']['tmdb_id'];
				$rOutput['info']['episode_run_time'] = (int) $rOutput['info']['episode_run_time'];
				$rOutput['info']['releasedate'] = $rOutput['info']['release_date'];
				$rOutput['info']['cover_big'] = XCMS::validateImage($rOutput['info']['cover_big']);
				$rOutput['info']['movie_image'] = XCMS::validateImage($rOutput['info']['movie_image']);
				$rOutput['info']['rating'] = number_format($rOutput['info']['rating'], 2) + 0;

				if (0 < count($rOutput['info']['backdrop_path'])) {
					foreach (range(0, count($rOutput['info']['backdrop_path']) - 1) as $i) {
						$rOutput['info']['backdrop_path'][$i] = XCMS::validateImage($rOutput['info']['backdrop_path'][$i]);
					}
				}

				$rOutput['info']['subtitles'] = [];

				if (is_array($rOutput['info']['subtitle'])) {
					$i = 0;

					foreach ($rOutput['info']['subtitle'] as $rSubtitle) {
						$rOutput['info']['subtitles'][] = ['index' => $i, 'language' => $rSubtitle['tags']['language'] ?: NULL, 'title' => $rSubtitle['tags']['title'] ?: NULL];
						$i++;
					}
				}

				foreach (['audio', 'video', 'subtitle'] as $rKey) {
					if (isset($rOutput['info'][$rKey])) {
						unset($rOutput['info'][$rKey]);
					}
				}

				$rOutput['movie_data'] = ['stream_id' => (int) $rRow['id'], 'name' => XCMS::formatTitle($rRow['stream_display_name'], $rRow['year']), 'title' => $rRow['stream_display_name'], 'year' => $rRow['year'], 'added' => $rRow['added'] ?: '', 'category_id' => strval(json_decode($rRow['category_id'], true)[0]), 'category_ids' => json_decode($rRow['category_id'], true), 'container_extension' => $rRow['target_container'], 'custom_sid' => strval($rRow['custom_sid']), 'direct_source' => $rURL];
			}
		}

		break;
	case 'get_vod_streams':
		$rCategoryIDSearch = (empty(XCMS::$rRequest['category_id']) ? NULL : (int) XCMS::$rRequest['category_id']);
		$rMovieNum = 0;

		if (!empty($rExtract['items_per_page'])) {
			$rUserInfo['vod_ids'] = array_slice($rUserInfo['vod_ids'], $rExtract['offset'], $rExtract['items_per_page']);
		}

		$rUserInfo['vod_ids'] = XCMS::sortChannels($rUserInfo['vod_ids']);

		if (!XCMS::$rCached) {
			$rChannels = [];

			if (0 < count($rUserInfo['vod_ids'])) {
				$rWhereV = $rWhere = [];

				if (!empty($rCategoryIDSearch)) {
					$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
					$rWhereV[] = $rCategoryIDSearch;
				}

				$rWhere[] = '`t1`.`id` IN (' . implode(',', $rUserInfo['vod_ids']) . ')';
				$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);

				if (XCMS::$rSettings['channel_number_type'] != 'manual') {
					$rOrder = 'FIELD(`t1`.`id`,' . implode(',', $rUserInfo['vod_ids']) . ')';
				}
				else {
					$rOrder = '`order`';
				}

				XCMS::$db->query('SELECT t1.id,t1.epg_id,t1.added,t1.allow_record,t1.year,t1.channel_id,t1.movie_properties,t1.stream_source,t1.tv_archive_server_id,t1.vframes_server_id,t1.tv_archive_duration,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t1.series_no,t1.direct_source,t2.type_output,t1.target_container,t2.live,t1.rtmp_output,t1.order,t2.type_key FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type ' . $rWhereString . ' ORDER BY ' . $rOrder . ';', ...$rWhereV);
				$rChannels = XCMS::$db->get_rows();
			}
		}
		else {
			$rChannels = $rUserInfo['vod_ids'];
		}

		foreach ($rChannels as $rChannel) {
			if (XCMS::$rCached) {
				$rChannel = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rChannel))['info'];
			}

			if (!in_array($rChannel['type_key'], ['movie' => true])) {
				continue;
			}

			$rProperties = json_decode($rChannel['movie_properties'], true);
			$rCategoryIDs = json_decode($rChannel['category_id'], true);

			foreach ($rCategoryIDs as $rCategoryID) {
				if (!$rCategoryIDSearch || ($rCategoryIDSearch == $rCategoryID)) {
					if (XCMS::$rSettings['api_redirect']) {
						$rEncData = 'movie/' . $rUserInfo['username'] . '/' . $rUserInfo['password'] . '/' . $rChannel['id'] . '/' . $rChannel['target_container'];
						$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
						$rURL = $rDomainName . ('play/' . $rToken);
					}
					else {
						$rURL = '';
					}

					$rOutput[] = ['num' => ++$rMovieNum, 'name' => XCMS::formatTitle($rChannel['stream_display_name'], $rChannel['year']), 'title' => $rChannel['stream_display_name'], 'year' => $rChannel['year'], 'stream_type' => $rChannel['type_key'], 'stream_id' => (int) $rChannel['id'], 'stream_icon' => XCMS::validateImage($rProperties['movie_image']) ?: '', 'rating' => number_format($rProperties['rating'], 1) + 0, 'rating_5based' => number_format($rProperties['rating'] * 0.5, 1) + 0, 'added' => $rChannel['added'] ?: '', 'plot' => $rProperties['plot'], 'cast' => $rProperties['cast'], 'director' => $rProperties['director'], 'genre' => $rProperties['genre'], 'release_date' => $rProperties['release_date'], 'youtube_trailer' => $rProperties['youtube_trailer'], 'episode_run_time' => $rProperties['episode_run_time'], 'category_id' => strval($rCategoryID), 'category_ids' => $rCategoryIDs, 'container_extension' => $rChannel['target_container'], 'custom_sid' => strval($rChannel['custom_sid']), 'direct_source' => $rURL];
				}
				if (!$rCategoryIDSearch && !XCMS::$rSettings['show_category_duplicates']) {
					break;
				}
			}
		}

		break;
	default:
		$rOutput['user_info'] = [];
		$rOutput['server_info'] = ['xcms' => true, 'version' => XCMS_VERSION, 'revision' => XCMS_REVISION, 'url' => $rDomain, 'port' => XCMS::$rServers[SERVER_ID]['http_broadcast_port'], 'https_port' => XCMS::$rServers[SERVER_ID]['https_broadcast_port'], 'server_protocol' => XCMS::$rServers[SERVER_ID]['server_protocol'], 'rtmp_port' => XCMS::$rServers[SERVER_ID]['rtmp_port'], 'timestamp_now' => time(), 'time_now' => date('Y-m-d H:i:s')];

		if (XCMS::$rSettings['force_epg_timezone']) {
			$rOutput['server_info']['timezone'] = 'UTC';
		}
		else {
			$rOutput['server_info']['timezone'] = XCMS::$rSettings['default_timezone'];
		}

		$rOutput['user_info']['username'] = $rUserInfo['username'];
		$rOutput['user_info']['password'] = $rUserInfo['password'];

		if (isset($rToken)) {
			$rOutput['user_info']['token'] = $rToken;
		}

		$rOutput['user_info']['message'] = XCMS::$rSettings['message_of_day'];
		$rOutput['user_info']['auth'] = 1;
		$rOutput['user_info']['status'] = 'Active';
		$rOutput['user_info']['exp_date'] = $rUserInfo['exp_date'];
		$rOutput['user_info']['is_trial'] = $rUserInfo['is_trial'];

		if (XCMS::$rSettings['redis_handler']) {
			XCMS::connectRedis();
			$rOutput['user_info']['active_cons'] = count(XCMS::getConnections($rUserInfo['id'], true, true));
		}
		else {
			if (XCMS::$rCached) {
				XCMS::connectDatabase();
			}

			XCMS::$db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `user_id` = ? AND `hls_end` = 0 ORDER BY activity_id ASC', $rUserInfo['id']);
			$rOutput['user_info']['active_cons'] = XCMS::$db->get_row()['count'];
			XCMS::$db->close_mysql();
		}

		$rOutput['user_info']['created_at'] = $rUserInfo['created_at'];
		$rOutput['user_info']['max_connections'] = $rUserInfo['max_connections'];
		$rOutput['user_info']['allowed_output_formats'] = getoutputformats($rUserInfo['allowed_outputs']);

		if ($rPanelAPI) {
			$rOutput['categories'] = $rCategoryNames = [];

			foreach (XCMS::getCategories() as $rID => $rCategory) {
				$rOutput['categories'][$rCategory['category_type']][] = ['category_id' => $rCategory['id'], 'category_name' => $rCategory['category_name'], 'parent_id' => 0];
				$rCategoryNames[$rCategory['id']] = $rCategory['category_name'];
			}

			$rOutput['available_channels'] = [];
			$rLiveNum = $rMovieNum = 0;
			$rUserInfo['channel_ids'] = XCMS::sortChannels($rUserInfo['channel_ids']);

			if (!XCMS::$rCached) {
				$rChannels = [];

				if (0 < count($rUserInfo['channel_ids'])) {
					$rWhereV = $rWhere = [];
					$rWhere[] = '`t1`.`id` IN (' . implode(',', $rUserInfo['channel_ids']) . ')';
					$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);

					if (XCMS::$rSettings['channel_number_type'] != 'manual') {
						$rOrder = 'FIELD(`t1`.`id`,' . implode(',', $rUserInfo['channel_ids']) . ')';
					}
					else {
						$rOrder = '`order`';
					}

					XCMS::$db->query('SELECT t1.id,t1.epg_id,t1.added,t1.allow_record,t1.year,t1.channel_id,t1.movie_properties,t1.stream_source,t1.tv_archive_server_id,t1.vframes_server_id,t1.tv_archive_duration,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t1.series_no,t1.direct_source,t2.type_output,t1.target_container,t2.live,t1.rtmp_output,t1.order,t2.type_key FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type ' . $rWhereString . ' ORDER BY ' . $rOrder . ';', ...$rWhereV);
					$rChannels = XCMS::$db->get_rows();
				}
			}
			else {
				$rChannels = $rUserInfo['channel_ids'];
			}

			foreach ($rChannels as $rChannel) {
				if (XCMS::$rCached) {
					$rChannel = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rChannel))['info'];
				}

				if ($rChannel['live']) {
					$rLiveNum++;
					$rIcon = $rChannel['stream_icon'];
				}
				else {
					$rMovieNum++;
					$rIcon = json_decode($rChannel['movie_properties'], true)['movie_image'];
				}
				$rTVArchive = (!empty($rChannel['tv_archive_server_id']) && !empty($rChannel['tv_archive_duration']) ? 1 : 0);

				foreach (json_decode($rChannel['category_id'], true) as $rCategoryID) {
					$rOutput['available_channels'][$rChannel['id']] = ['num' => $rChannel['live'] ? $rLiveNum : $rMovieNum, 'name' => XCMS::formatTitle($rChannel['stream_display_name'], $rChannel['year']), 'title' => $rChannel['stream_display_name'], 'year' => $rChannel['year'], 'stream_type' => $rChannel['type_key'], 'type_name' => $rNameTypes[$rChannel['type_key']], 'stream_id' => $rChannel['id'], 'stream_icon' => XCMS::validateImage($rIcon) ?: '', 'epg_channel_id' => $rChannel['channel_id'], 'added' => $rChannel['added'] ?: '', 'category_name' => $rCategoryNames[$rCategoryID], 'category_id' => strval($rCategoryID), 'series_no' => !empty($rChannel['series_no']) ? $rChannel['series_no'] : NULL, 'live' => $rChannel['live'], 'container_extension' => $rChannel['target_container'], 'custom_sid' => strval($rChannel['custom_sid']), 'tv_archive' => $rTVArchive, 'direct_source' => $rChannel['direct_source'] ? json_decode($rChannel['stream_source'], true)[0] : '', 'tv_archive_duration' => $rTVArchive ? (int) $rChannel['tv_archive_duration'] : 0];
				}
			}
		}

		break;
	}

	exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
}
else {
	XCMS::checkBruteforce(NULL, NULL, $rUsername);
	generateError('INVALID_CREDENTIALS');
}

?>