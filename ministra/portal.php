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

function getMovies($rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL, $rPicking = [])
{
	global $rDevice;
	global $rPageItems;
	global $rForceProtocol;
	$rDefaultPage = false;
	$rPage = (!empty(XCMS::$rRequest['p']) ? XCMS::$rRequest['p'] : 0);

	if ($rPage == 0) {
		$rDefaultPage = true;
		$rPage = 1;
	}

	$rStart = $rPageItems * ($rPage - 1);
	$rStreams = getItems(['movie'], $rCategoryID, $rFav, $rOrderBy, $rSearchBy, $rPicking, $rStart, $rPageItems);
	$rDatas = [];

	foreach ($rStreams['streams'] as $rMovie) {
		$rProperties = (!is_array($rMovie['movie_properties']) ? json_decode($rMovie['movie_properties'], true) : $rMovie['movie_properties']);
		$rHD = (int) (1200 < $rMovie['stream_info']['codecs']['video']['width']);
		$rPostData = ['type' => 'movie', 'stream_id' => $rMovie['id'], 'target_container' => $rMovie['target_container']];
		$rThisMM = date('m');
		$rThisDD = date('d');
		$rThisYY = date('Y');

		if (mktime(0, 0, 0, $rThisMM, $rThisDD, $rThisYY) < $rMovie['added']) {
			$rAddedKey = 'today';
			$rAddedVal = 'Today';
		}
		else if (mktime(0, 0, 0, $rThisMM, $rThisDD - 1, $rThisYY) < $rMovie['added']) {
			$rAddedKey = 'yesterday';
			$rAddedVal = 'Yesterday';
		}
		else if (0 < $rMovie['added']) {
			$rAddedKey = 'week_and_more';
			$rDay = date('d', $rMovie['added']);
			if ((11 <= $rDay % 100) && (($rDay % 100) <= 13)) {
				$rAbb = $rDay . 'th';
			}
			else {
				$rAbb = $rDay . ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][$rDay % 10];
			}

			$rAddedVal = date('M', $rMovie['added']) . ' ' . $rAbb . ' ' . date('Y', $rMovie['added']);
		}
		else {
			$rAddedKey = 'week_and_more';
			$rAddedVal = 'Unknown';
		}

		$rDuration = (isset($rProperties['duration_secs']) ? $rProperties['duration_secs'] : 60);
		$rDatas[] = [
			'id'                     => $rMovie['id'],
			'owner'                  => '',
			'name'                   => $rMovie['stream_display_name'],
			'tmdb_id'                => $rProperties['tmdb_id'],
			'old_name'               => '',
			'o_name'                 => $rMovie['stream_display_name'],
			'fname'                  => '',
			'description'            => empty($rProperties['plot']) ? 'N/A' : $rProperties['plot'],
			'pic'                    => '',
			'cost'                   => 0,
			'time'                   => (int) ($rDuration / 60),
			'file'                   => '',
			'path'                   => str_replace(' ', '_', $rMovie['stream_display_name']),
			'protocol'               => '',
			'rtsp_url'               => '',
			'censored'               => (int) $rMovie['is_adult'],
			'series'                 => [],
			'volume_correction'      => 0,
			'category_id'            => $rMovie['category_id'],
			'genre_id'               => 0,
			'genre_id_1'             => 0,
			'genre_id_2'             => 0,
			'genre_id_3'             => 0,
			'hd'                     => $rHD,
			'genre_id_4'             => 0,
			'cat_genre_id_1'         => $rMovie['category_id'],
			'cat_genre_id_2'         => 0,
			'cat_genre_id_3'         => 0,
			'cat_genre_id_4'         => 0,
			'director'               => empty($rProperties['director']) ? 'N/A' : $rProperties['director'],
			'actors'                 => empty($rProperties['cast']) ? 'N/A' : $rProperties['cast'],
			'year'                   => $rMovie['year'],
			'accessed'               => 1,
			'status'                 => 1,
			'disable_for_hd_devices' => 0,
			'added'                  => date('Y-m-d H:i:s', $rMovie['added']),
			'count'                  => 0,
			'count_first_0_5'        => 0,
			'count_second_0_5'       => 0,
			'vote_sound_good'        => 0,
			'vote_sound_bad'         => 0,
			'vote_video_good'        => 0,
			'vote_video_bad'         => 0,
			'rate'                   => '',
			'last_rate_update'       => '',
			'last_played'            => '',
			'for_sd_stb'             => 0,
			'rating_im'              => empty($rProperties['rating']) ? 'N/A' : $rProperties['rating'],
			'rating_count_im'        => '',
			'rating_last_update'     => '0000-00-00 00:00:00',
			'age'                    => '12+',
			'high_quality'           => 0,
			'rating_kinopoisk'       => empty($rProperties['rating']) ? 'N/A' : $rProperties['rating'],
			'comments'               => '',
			'low_quality'            => 0,
			'is_series'              => 0,
			'year_end'               => 0,
			'autocomplete_provider'  => 'im',
			'screenshots'            => '',
			'is_movie'               => 1,
			'lock'                   => $rMovie['is_adult'],
			'fav'                    => in_array($rMovie['id'], $rDevice['fav_channels']['movie']) ? 1 : 0,
			'for_rent'               => 0,
			'screenshot_uri'         => empty($rProperties['movie_image']) ? '' : XCMS::validateImage($rProperties['movie_image'], $rForceProtocol),
			'genres_str'             => empty($rProperties['genre']) ? 'N/A' : $rProperties['genre'],
			'cmd'                    => base64_encode(json_encode($rPostData, JSON_PARTIAL_OUTPUT_ON_ERROR)),
			$rAddedKey => $rAddedVal,
			'has_files'              => 0
		];
	}

	if (!$rDefaultPage) {
		$rPage = 0;
	}

	$rOutput = [
		'js' => ['total_items' => (int) $rStreams['count'], 'max_page_items' => $rPageItems, 'selected_item' => 0, 'cur_page' => $rPage, 'data' => $rDatas]
	];
	return json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function getSeasons($rSeriesID)
{
	global $db;
	$db->query('SELECT * FROM `streams_episodes` t1 INNER JOIN `streams` t2 ON t2.id=t1.stream_id WHERE t1.series_id = ? ORDER BY t1.season_num DESC, t1.episode_num ASC', $rSeriesID);
	return $db->get_rows(true, 'season_num', false);
}

function getSeries($rMovieID = NULL, $rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL, $rPicking = [])
{
	global $rDevice;
	global $db;
	global $rPageItems;
	global $rForceProtocol;
	$rPage = (!empty(XCMS::$rRequest['p']) ? XCMS::$rRequest['p'] : 0);
	$rDefaultPage = false;

	if (empty($rMovieID)) {
		$rItems = getSeriesItems($rDevice['user_id'], 'series', $rCategoryID, $rFav, $rOrderBy, $rSearchBy, $rPicking);
	}
	else {
		$rItems = getseasons($rMovieID);
		$db->query('SELECT * FROM `streams_series` WHERE `id` = ?', $rMovieID);
		$rSeriesInfo = $db->get_row();
	}

	$rCounter = count($rItems);
	$rChannelIDx = 0;

	if ($rPage == 0) {
		$rDefaultPage = true;
		$rPage = ceil($rChannelIDx / $rPageItems);

		if ($rPage == 0) {
			$rPage = 1;
		}
	}

	$rItems = array_slice($rItems, $rPageItems * ($rPage - 1), $rPageItems, true);
	$rDatas = [];

	foreach ($rItems as $rKey => $rMovie) {
		if (!is_null($rFav) && ($rFav == 1)) {
			if (!in_array($rMovie['id'], $rDevice['fav_channels']['series'])) {
				--$rCounter;
				continue;
			}
		}

		if (!empty($rSeriesInfo)) {
			$rProperties = $rSeriesInfo;
			$rMaxAdded = 0;

			foreach ($rMovie as $vod) {
				if ($rMaxAdded < $vod['added']) {
					$rMaxAdded = $vod['added'];
				}
			}
		}
		else {
			$rProperties = $rMovie;
			$rMaxAdded = $rMovie['last_modified'];
		}

		$rPostData = ['series_id' => $rMovieID, 'season_num' => $rKey, 'type' => 'series'];
		$rThisMM = date('m');
		$rThisDD = date('d');
		$rThisYY = date('Y');

		if (mktime(0, 0, 0, $rThisMM, $rThisDD, $rThisYY) < $rMaxAdded) {
			$rAddedKey = 'today';
			$rAddedVal = 'Today';
		}
		else if (mktime(0, 0, 0, $rThisMM, $rThisDD - 1, $rThisYY) < $rMaxAdded) {
			$rAddedKey = 'yesterday';
			$rAddedVal = 'Yesterday';
		}
		else if (mktime(0, 0, 0, $rThisMM, $rThisDD - 7, $rThisYY) < $rMaxAdded) {
			$rAddedKey = 'week_and_more';
			$rAddedVal = 'Last Week';
		}
		else {
			$rAddedKey = 'week_and_more';

			if (0 < $rMaxAdded) {
				$rAddedVal = date('F', $rMaxAdded) . ' ' . date('Y', $rMaxAdded);
			}
			else {
				$rAddedVal = 'Unknown';
			}
		}

		if (!empty($rSeriesInfo)) {
			if ($rKey == 0) {
				$rTitle = 'Specials';
			}
			else {
				$rTitle = 'Season ' . $rKey;
			}
		}
		else {
			$rTitle = $rMovie['title'];
		}

		$rDatas[] = ['id' => $rProperties['id'], 'owner' => '', 'name' => $rTitle, 'tmdb_id' => $rProperties['tmdb_id'], 'old_name' => '', 'o_name' => $rTitle, 'fname' => '', 'description' => empty($rProperties['plot']) ? 'N/A' : $rProperties['plot'], 'pic' => '', 'cost' => 0, 'time' => 'N/a', 'file' => '', 'path' => str_replace(' ', '_', $rProperties['title']), 'protocol' => '', 'rtsp_url' => '', 'censored' => 0, 'series' => !empty($rSeriesInfo) ? range(1, count($rMovie)) : [], 'volume_correction' => 0, 'category_id' => $rProperties['category_id'], 'genre_id' => 0, 'genre_id_1' => 0, 'genre_id_2' => 0, 'genre_id_3' => 0, 'hd' => 1, 'genre_id_4' => 0, 'cat_genre_id_1' => $rProperties['category_id'], 'cat_genre_id_2' => 0, 'cat_genre_id_3' => 0, 'cat_genre_id_4' => 0, 'director' => empty($rProperties['director']) ? 'N/A' : $rProperties['director'], 'actors' => empty($rProperties['cast']) ? 'N/A' : $rProperties['cast'], 'year' => empty($rProperties['release_date']) ? 'N/A' : $rProperties['release_date'], 'accessed' => 1, 'status' => 1, 'disable_for_hd_devices' => 0, 'added' => date('Y-m-d H:i:s', $rMaxAdded), 'count' => 0, 'count_first_0_5' => 0, 'count_second_0_5' => 0, 'vote_sound_good' => 0, 'vote_sound_bad' => 0, 'vote_video_good' => 0, 'vote_video_bad' => 0, 'rate' => '', 'last_rate_update' => '', 'last_played' => '', 'for_sd_stb' => 0, 'rating_im' => empty($rProperties['rating']) ? 'N/A' : $rProperties['rating'], 'rating_count_im' => '', 'rating_last_update' => '0000-00-00 00:00:00', 'age' => '12+', 'high_quality' => 0, 'rating_kinopoisk' => empty($rProperties['rating']) ? 'N/A' : $rProperties['rating'], 'comments' => '', 'low_quality' => 0, 'is_series' => 1, 'year_end' => 0, 'autocomplete_provider' => 'im', 'screenshots' => '', 'is_movie' => 1, 'lock' => 0, 'fav' => in_array($rProperties['id'], $rDevice['fav_channels']['series']) ? 1 : 0, 'for_rent' => 0, 'screenshot_uri' => empty($rProperties['cover']) ? '' : XCMS::validateImage($rProperties['cover'], $rForceProtocol), 'genres_str' => empty($rProperties['genre']) ? 'N/A' : $rProperties['genre'], 'cmd' => !empty($rSeriesInfo) ? base64_encode(json_encode($rPostData, JSON_PARTIAL_OUTPUT_ON_ERROR)) : '', $rAddedKey => $rAddedVal, 'has_files' => empty($rMovieID) ? 1 : 0];
	}

	if ($rDefaultPage) {
		$rCurrentPage = $rPage;
		$rSelectedItem = $rChannelIDx - ($rPageItems * ($rPage - 1));
	}
	else {
		$rCurrentPage = 0;
		$rSelectedItem = 0;
	}

	$rOutput = [
		'js' => ['total_items' => $rCounter, 'max_page_items' => $rPageItems, 'selected_item' => $rSelectedItem, 'cur_page' => $rCurrentPage, 'data' => $rDatas]
	];
	return json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function getSeriesItems($rUserID, $rType = 'series', $rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL, $rPicking = [])
{
	global $rDevice;
	global $db;

	if (0 < count($rDevice['series_ids'])) {
		$db->query('SELECT *, (SELECT MAX(`streams`.`added`) FROM `streams_episodes` LEFT JOIN `streams` ON `streams`.`id` = `streams_episodes`.`stream_id` WHERE `streams_episodes`.`series_id` = `streams_series`.`id`) AS `last_modified_stream` FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rDevice['series_ids'])) . ') ORDER BY `last_modified_stream` DESC, `last_modified` DESC;');
		$rSeries = $db->get_rows(true, 'id');
	}
	else {
		$rSeries = [];
	}

	$rOutputSeries = [];

	foreach ($rSeries as $rSeriesID => $rSeriesO) {
		$rSeriesO['last_modified'] = $rSeriesO['last_modified_stream'];
		if (!empty($rCategoryID) && !in_array($rCategoryID, json_decode($rSeriesO['category_id'], true))) {
			continue;
		}
		else {
			if (in_array($rCategoryID, json_decode($rSeriesO['category_id'], true))) {
				$rSeriesO['category_id'] = $rCategoryID;
			}
			else {
				$rSeriesO['category_id'] = json_decode($rSeriesO['category_id'], true)[0];
			}
		}
		if (!empty($rSearchBy) && !stristr($rSeriesO['title'], $rSearchBy)) {
			continue;
		}
		if (!empty($rPicking['abc']) && ($rPicking['abc'] != '*') && (strtoupper(substr($rSeriesO['title'], 0, 1)) != $rPicking['abc'])) {
			continue;
		}
		if (!empty($rPicking['genre']) && ($rPicking['genre'] != '*') && ($rSeriesO['category_id'] != $rPicking['genre'])) {
			continue;
		}
		if (!empty($rPicking['years']) && ($rPicking['years'] != '*') && ($rSeriesO['year'] != $rPicking['years'])) {
			continue;
		}

		if (!empty($rFav)) {
			$rFound = false;
			if (!empty($rDevice['fav_channels'][$rType]) && in_array($rSeriesID, $rDevice['fav_channels'][$rType])) {
				$rFound = true;
			}

			if (!$rFound) {
				continue;
			}
		}

		$rOutputSeries[$rSeriesID] = $rSeriesO;
	}

	switch ($rOrderBy) {
	case 'name':
		uasort($rOutputSeries, 'sortArrayStreamName');
		break;
	case 'rating':
	case 'top':
		uasort($rOutputSeries, 'sortArrayStreamRating');
		break;
	case 'number':
		uasort($rOutputSeries, 'sortArrayStreamNumber');
		break;
	default:
		uasort($rOutputSeries, 'sortArrayStreamAdded');
		break;
	}

	return $rOutputSeries;
}

function convertTypes($rTypes)
{
	$rReturn = [];
	$rTypeInt = ['live' => 1, 'movie' => 2, 'created_live' => 3, 'radio_streams' => 4, 'series' => 5];

	foreach ($rTypes as $rType) {
		$rReturn[] = $rTypeInt[$rType];
	}

	return $rReturn;
}

function getItems($rTypes = [], $rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL, $rPicking = [], $rStart = 0, $rLimit = 10, $rLastID = NULL)
{
	global $rDevice;
	global $db;
	$rAdded = false;
	$rChannels = [];

	foreach ($rTypes as $rType) {
		switch ($rType) {
		case 'live':
		case 'created_live':
			if ($rAdded) {
				break;
			}

			$rChannels = array_merge($rChannels, $rDevice['live_ids']);
			$rAdded = true;
			break;
		case 'movie':
			$rChannels = array_merge($rChannels, $rDevice['vod_ids']);
			break;
		case 'radio_streams':
			$rChannels = array_merge($rChannels, $rDevice['radio_ids']);
			break;
		case 'series':
			$rChannels = array_merge($rChannels, $rDevice['episode_ids']);
			break;
		}
	}

	$rStreams = [
		'count'   => 0,
		'streams' => []
	];
	$rAdultCategories = XCMS::getAdultCategories();
	$rKey = $rStart + 1;
	$rWhereV = $rWhere = [];

	if (0 < count($rTypes)) {
		$rWhere[] = '`type` IN (' . implode(',', converttypes($rTypes)) . ')';
	}

	if (!empty($rCategoryID)) {
		$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
		$rWhereV[] = $rCategoryID;
	}
	if (!empty($rPicking['genre']) && ($rPicking['genre'] != '*')) {
		$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
		$rWhereV[] = $rPicking['genre'];
	}

	$rChannels = XCMS::sortChannels($rChannels);

	if (!empty($rFav)) {
		$rFavouriteIDs = [];

		foreach ($rTypes as $rType) {
			foreach ($rDevice['fav_channels'][$rType] as $rStreamID) {
				$rFavouriteIDs[] = (int) $rStreamID;
			}
		}

		$rChannels = array_intersect($rFavouriteIDs, $rChannels);
	}

	if (!empty($rSearchBy)) {
		$rWhere[] = '`stream_display_name` LIKE ?';
		$rWhereV[] = '%' . $rSearchBy . '%';
	}
	if (!empty($rPicking['abc']) && ($rPicking['abc'] != '*')) {
		$rWhere[] = 'UCASE(LEFT(`stream_display_name`, 1)) = ?';
		$rWhereV[] = strtoupper($rPicking['abc']);
	}
	if (!empty($rPicking['years']) && ($rPicking['years'] != '*')) {
		$rWhere[] = '`year` = ?';
		$rWhereV[] = $rPicking['years'];
	}

	$rWhere[] = '`id` IN (' . implode(',', $rChannels) . ')';
	$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);

	switch ($rOrderBy) {
	case 'name':
		$rOrder = '`stream_display_name` ASC';
		break;
	case 'top':
	case 'rating':
		$rOrder = '`rating` DESC';
		break;
	case 'added':
		$rOrder = '`added` DESC';
		break;
	case 'number':
	default:
		if (XCMS::$rSettings['channel_number_type'] != 'manual') {
			$rOrder = 'FIELD(id,' . implode(',', $rChannels) . ')';
		}
		else {
			$rOrder = '`order` ASC';
		}

		break;
	}

	if (0 < count($rChannels)) {
		if ($rLastID) {
			$rWhereV[] = $rLastID;
			$db->query('SELECT * FROM (SELECT @row_number:=@row_number+1 AS `pos`, `id` FROM `streams`, (SELECT @row_number:=0) AS `t` ' . $rWhereString . ' ORDER BY ' . $rOrder . ') `ids` WHERE `ids`.`id` = ?;', ...$rWhereV);
			return $db->get_row()['pos'] ?: NULL;
		}

		$db->query('SELECT COUNT(`id`) AS `count` FROM `streams` ' . $rWhereString . ';', ...$rWhereV);
		$rStreams['count'] = $db->get_row()['count'];

		if ($rLimit) {
			$rQuery = 'SELECT (SELECT `stream_info` FROM `streams_servers` WHERE `streams_servers`.`pid` IS NOT NULL AND `streams_servers`.`stream_id` = `streams`.`id` LIMIT 1) AS `stream_info`, `id`, `stream_display_name`, `movie_properties`, `target_container`, `added`, `year`, `category_id`, `channel_id`, `epg_id`, `tv_archive_duration`, `stream_icon`, `allow_record`, `type` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrder . ' LIMIT ' . $rStart . ', ' . $rLimit . ';';
		}
		else {
			$rQuery = 'SELECT (SELECT `stream_info` FROM `streams_servers` WHERE `streams_servers`.`pid` IS NOT NULL AND `streams_servers`.`stream_id` = `streams`.`id` LIMIT 1) AS `stream_info`, `id`, `stream_display_name`, `movie_properties`, `target_container`, `added`, `year`, `category_id`, `channel_id`, `epg_id`, `tv_archive_duration`, `stream_icon`, `allow_record`, `type` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrder . ';';
		}

		$db->query($rQuery, ...$rWhereV);
		$rRows = $db->get_rows();
	}
	else if ($rLastID) {
		return NULL;
	}
	else {
		$rRows = [];
	}

	foreach ($rRows as $rStream) {
		$rStream['number'] = $rStream['snumber'] = $rKey;

		if (in_array($rCategoryID, json_decode($rStream['category_id'], true))) {
			$rStream['category_id'] = $rCategoryID;
		}
		else {
			$rStream['category_id'] = json_decode($rStream['category_id'], true)[0];
		}

		if (in_array($rStream['category_id'], $rAdultCategories)) {
			$rStream['is_adult'] = 1;
		}
		else {
			$rStream['is_adult'] = 0;
		}

		$rStream['now_playing'] = getEPG($rStream['id'], time(), time() + 86400)[0] ?: NULL;
		$rStream['stream_info'] = json_decode($rStream['stream_info'], true);
		$rStreams['streams'][$rStream['id']] = $rStream;
		$rKey++;
	}

	return $rStreams;
}

function sortArrayStreamRating($a, $b)
{
	if (!isset($a['rating'])) {
		if (!isset($a['movie_properties']) || !isset($b['movie_properties'])) {
			return 0;
		}

		if (!is_array($a['movie_properties'])) {
			$a = json_decode($a['movie_properties'], true);
		}
		else {
			$a = $a['movie_properties'];
		}

		if (!is_array($b['movie_properties'])) {
			$b = json_decode($b['movie_properties'], true);
		}
		else {
			$b = $b['movie_properties'];
		}
	}

	if ($a['rating'] == $b['rating']) {
		return 0;
	}

	return $b['rating'] < $a['rating'] ? -1 : 1;
}

function sortArrayStreamAdded($a, $b)
{
	$rColumn = (isset($a['added']) ? 'added' : 'last_modified');

	if (!is_numeric($a[$rColumn])) {
		$a[$rColumn] = strtotime($a['added']);
	}

	if (!is_numeric($b[$rColumn])) {
		$b[$rColumn] = strtotime($b[$rColumn]);
	}

	if ($a[$rColumn] == $b[$rColumn]) {
		return 0;
	}

	return $b[$rColumn] < $a[$rColumn] ? -1 : 1;
}

function sortArrayStreamNumber($a, $b)
{
	if ($a['number'] == $b['number']) {
		return 0;
	}

	return $a['number'] < $b['number'] ? -1 : 1;
}

function sortArrayStreamName($a, $b)
{
	$rColumn = (isset($a['stream_display_name']) ? 'stream_display_name' : 'title');
	return strcmp($a[$rColumn], $b[$rColumn]);
}

function getStations($rCategoryID = NULL, $rFav = NULL, $rOrderBy = NULL)
{
	global $rDevice;
	global $rPlayer;
	global $rPageItems;
	$rDefaultPage = false;
	$rPage = (!empty(XCMS::$rRequest['p']) ? XCMS::$rRequest['p'] : 0);

	if ($rPage == 0) {
		$rDefaultPage = true;
		$rPage = 1;
	}

	$rStart = $rPageItems * ($rPage - 1);
	$rStreams = getitems(['radio_streams'], $rCategoryID, $rFav, $rOrderBy, NULL, NULL, $rStart, $rPageItems);
	$rDatas = [];

	foreach ($rStreams['streams'] as $rStream) {
		if (XCMS::$rSettings['mag_security'] == 0) {
			$rEncData = 'ministra::live/' . $rDevice['username'] . '/' . $rDevice['password'] . '/' . $rStream['id'] . '/' . XCMS::$rSettings['mag_container'] . ('/' . $rDevice['token']);
			$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
			$rStreamURL = (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken);

			if (XCMS::$rSettings['mag_keep_extension']) {
				$rStreamURL .= '?ext=.' . XCMS::$rSettings['mag_container'];
			}

			$rStreamSourceSt = 0;
		}
		else {
			$rStreamURL = 'http://localhost/ch/' . $rStream['id'] . '_';
			$rStreamSourceSt = 1;
		}

		$rDatas[] = ['id' => $rStream['id'], 'name' => $rStream['stream_display_name'], 'number' => $i++, 'cmd' => $rPlayer . $rStreamURL, 'count' => 0, 'open' => 1, 'status' => 1, 'volume_correction' => 0, 'use_http_tmp_link' => (string) $rStreamSourceSt, 'fav' => in_array($rStream['id'], $rDevice['fav_channels']['radio_streams']) ? 1 : 0];
	}

	if (!$rDefaultPage) {
		$rPage = 0;
	}

	$rOutput = [
		'js' => ['total_items' => (int) $rStreams['count'], 'max_page_items' => $rPageItems, 'selected_item' => 0, 'cur_page' => $rPage, 'data' => $rDatas]
	];
	return json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function getStreams($rCategoryID = NULL, $rAll = false, $rFav = NULL, $rOrderBy = NULL, $rSearchBy = NULL)
{
	global $rDevice;
	global $rPlayer;
	global $rPageItems;
	global $rTimezone;
	global $rForceProtocol;
	$rDefaultPage = false;
	$rPage = (isset(XCMS::$rRequest['p']) ? (int) XCMS::$rRequest['p'] : 0);
	if (($rPage == 0) && ($rCategoryID != -1)) {
		$rDefaultPage = true;
		if ((XCMS::$rRequest['p'] == 0) && !empty($rDevice['last_itv_id'])) {
			$rPosition = getitems(['live', 'created_live'], $rCategoryID, $rFav, $rOrderBy, $rSearchBy, NULL, 0, 0, $rDevice['last_itv_id']);

			if ($rPosition) {
				$rPage = floor(($rPosition - 1) / $rPageItems) + 1;
				$rPosition = $rPosition - ($rPageItems * ($rPage - 1));
			}
			else {
				$rPosition = 0;
			}
		}

		if ($rPage == 0) {
			$rPage = 1;
		}
	}

	$rStart = $rPageItems * ($rPage - 1);

	if ($rCategoryID == -1) {
		if (XCMS::$rSettings['mag_load_all_channels']) {
			$rStreams = getitems(['live', 'created_live'], 0 < $rCategoryID ? $rCategoryID : NULL, $rFav, $rOrderBy, $rSearchBy, NULL, 0, 0);
		}
		else {
			return '{"js":{"total_items":0,"max_page_items":14,"selected_item":0,"cur_page":0,"data":[]}}';
		}
	}
	else if ($rAll) {
		$rStreams = getitems(['live', 'created_live'], $rCategoryID, $rFav, $rOrderBy, $rSearchBy, NULL, 0, 0);
	}
	else {
		$rStreams = getitems(['live', 'created_live'], $rCategoryID, $rFav, $rOrderBy, $rSearchBy, NULL, $rStart, $rPageItems);
	}

	$rDatas = [];
	$rTimeDifference = XCMS::getDiffTimezone($rTimezone);

	foreach ($rStreams['streams'] as $rStream) {
		$rHD = (int) (1200 < $rStream['stream_info']['codecs']['video']['width']);

		if (XCMS::$rSettings['mag_security'] == 0) {
			$rEncData = 'ministra::live/' . $rDevice['username'] . '/' . $rDevice['password'] . '/' . $rStream['id'] . '/' . XCMS::$rSettings['mag_container'] . ('/' . $rDevice['token']);
			$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
			$rStreamURL = (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken);

			if (XCMS::$rSettings['mag_keep_extension']) {
				$rStreamURL .= '?ext=.' . XCMS::$rSettings['mag_container'];
			}

			$rStreamSourceSt = 0;
		}
		else {
			$rStreamURL = 'http://localhost/ch/' . $rStream['id'] . '_';
			$rStreamSourceSt = 1;
		}

		if ($rStream['now_playing']) {
			$rStartTime = new DateTime();
			$rStartTime->setTimestamp($rStream['now_playing']['start']);
			$rStartTime->modify($rTimeDifference . ' seconds');
			$rEndTime = new DateTime();
			$rEndTime->setTimestamp($rStream['now_playing']['end']);
			$rEndTime->modify($rTimeDifference . ' seconds');
			$rNowPlaying = $rStartTime->format('H:i') . ' - ' . $rEndTime->format('H:i') . ': ' . $rStream['now_playing']['title'];
		}
		else {
			$rNowPlaying = 'No channel information is available...';
		}

		$rDatas[] = [
			'id'                          => (int) $rStream['id'],
			'name'                        => $rStream['stream_display_name'],
			'number'                      => (string) $rStream['number'],
			'snumber'                     => (string) $rStream['number'],
			'censored'                    => $rStream['is_adult'] == 1 ? 1 : 0,
			'cmd'                         => $rPlayer . $rStreamURL,
			'cost'                        => '0',
			'count'                       => '0',
			'status'                      => 1,
			'tv_genre_id'                 => $rStream['category_id'],
			'base_ch'                     => '1',
			'hd'                          => $rHD,
			'xmltv_id'                    => !empty($rStream['channel_id']) ? $rStream['channel_id'] : '',
			'service_id'                  => '',
			'bonus_ch'                    => '0',
			'volume_correction'           => '0',
			'use_http_tmp_link'           => $rStreamSourceSt,
			'mc_cmd'                      => '',
			'enable_tv_archive'           => 0 < $rStream['tv_archive_duration'] ? 1 : 0,
			'wowza_tmp_link'              => '0',
			'wowza_dvr'                   => '0',
			'monitoring_status'           => '1',
			'enable_monitoring'           => '0',
			'enable_wowza_load_balancing' => '0',
			'cmd_1'                       => '',
			'cmd_2'                       => '',
			'cmd_3'                       => '',
			'logo'                        => XCMS::validateImage($rStream['stream_icon'], $rForceProtocol),
			'correct_time'                => '0',
			'nimble_dvr'                  => '0',
			'allow_pvr'                   => (int) $rStream['allow_record'],
			'allow_local_pvr'             => (int) $rStream['allow_record'],
			'allow_remote_pvr'            => 0,
			'modified'                    => '',
			'allow_local_timeshift'       => '1',
			'nginx_secure_link'           => $rStreamSourceSt,
			'tv_archive_duration'         => 0 < $rStream['tv_archive_duration'] ? $rStream['tv_archive_duration'] * 24 : 0,
			'locked'                      => 0,
			'lock'                        => $rStream['is_adult'],
			'fav'                         => in_array($rStream['id'], $rDevice['fav_channels']['live']) ? 1 : 0,
			'archive'                     => 0 < $rStream['tv_archive_duration'] ? 1 : 0,
			'genres_str'                  => '',
			'cur_playing'                 => $rNowPlaying,
			'epg'                         => [],
			'open'                        => 1,
			'cmds'                        => [
			['id' => (string) $rStream['id'], 'ch_id' => (string) $rStream['id'], 'priority' => '0', 'url' => $rPlayer . $rStreamURL, 'status' => '1', 'use_http_tmp_link' => $rStreamSourceSt, 'wowza_tmp_link' => '0', 'user_agent_filter' => '', 'use_load_balancing' => '0', 'changed' => '', 'enable_monitoring' => '0', 'enable_balancer_monitoring' => '0', 'nginx_secure_link' => $rStreamSourceSt, 'flussonic_tmp_link' => '0']
		],
			'use_load_balancing'          => 0,
			'pvr'                         => (int) $rStream['allow_record']
		];
	}

	if (!$rDefaultPage) {
		$rPage = 0;
		$rPosition = 0;
	}

	$rOutput = [
		'js' => ['total_items' => (int) $rStreams['count'], 'max_page_items' => (int) $rPageItems, 'selected_item' => $rPosition, 'cur_page' => $rAll ? 0 : $rPage, 'data' => $rDatas]
	];
	return json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function getHeaders()
{
	$rHeaders = [];

	foreach ($_SERVER as $rName => $rValue) {
		if (substr($rName, 0, 5) == 'HTTP_') {
			$rHeaders[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($rName, 5)))))] = $rValue;
		}
	}

	return $rHeaders;
}

function getDevice($rID = NULL, $rMAC = NULL)
{
	global $db;
	global $rIP;
	XCMS::$rBouquets = XCMS::getCache('bouquets');
	$rDevice = ($rID && file_exists(MINISTRA_TMP_PATH . 'ministra_' . $rID) ? $rDevice = igbinary_unserialize(file_get_contents(MINISTRA_TMP_PATH . 'ministra_' . $rID)) : NULL);
	if ((!$rDevice && $rMAC) || ($rDevice && (600 < (time() - $rDevice['generated'])))) {
		if ($rMAC) {
			$db->query('SELECT * FROM `mag_devices` WHERE `mac` = ? LIMIT 1', $rMAC);
		}
		else if ($rDevice) {
			$db->query('SELECT * FROM `mag_devices` WHERE `mac` = ? LIMIT 1', $rDevice['get_profile_vars']['mac']);
		}
		else {
			return NULL;
		}

		if (0 < $db->num_rows()) {
			$rDevice = $db->get_row();
			$rUserInfo = XCMS::getUserInfo($rDevice['user_id'], NULL, NULL, true, false, $rIP);
			$rDevice = array_merge($rDevice, $rUserInfo);
			$rDevice['allowed_ips'] = json_decode($rDevice['allowed_ips'], true);
			$rDevice['fav_channels'] = (!empty($rDevice['fav_channels']) ? json_decode($rDevice['fav_channels'], true) : []);

			if (empty($rDevice['fav_channels']['live'])) {
				$rDevice['fav_channels']['live'] = [];
			}

			if (empty($rDevice['fav_channels']['movie'])) {
				$rDevice['fav_channels']['movie'] = [];
			}

			if (empty($rDevice['fav_channels']['radio_streams'])) {
				$rDevice['fav_channels']['radio_streams'] = [];
			}

			$rDevice['mag_player'] = trim($rDevice['mag_player']);
			unset($rDevice['channel_ids']);
			$rDevice['get_profile_vars'] = ['id' => $rDevice['mag_id'], 'name' => $rDevice['mag_id'], 'parent_password' => $rDevice['parent_password'] ?: '0000', 'bright' => $rDevice['bright'] ?: '200', 'contrast' => $rDevice['contrast'] ?: '127', 'saturation' => $rDevice['saturation'] ?: '127', 'video_out' => $rDevice['video_out'] ?: '', 'volume' => $rDevice['volume'] ?: '70', 'playback_buffer_bytes' => $rDevice['playback_buffer_bytes'] ?: '0', 'playback_buffer_size' => $rDevice['playback_buffer_size'] ?: '0', 'audio_out' => $rDevice['audio_out'] ?: '1', 'mac' => $rDevice['mac'], 'ip' => '127.0.0.1', 'ls' => $rDevice['ls'] ?: '', 'lang' => $rDevice['lang'] ?: '', 'locale' => $rDevice['locale'] ?: 'en_GB.utf8', 'city_id' => $rDevice['city_id'] ?: '0', 'hd' => $rDevice['hd'] ?: '1', 'main_notify' => $rDevice['main_notify'] ?: '1', 'fav_itv_on' => $rDevice['fav_itv_on'] ?: '0', 'now_playing_start' => $rDevice['now_playing_start'] ? date('Y-m-d H:i:s', $rDevice['now_playing_start']) : date('Y-m-d H:i:s'), 'now_playing_type' => $rDevice['now_playing_type'] ?: '1', 'now_playing_content' => $rDevice['now_playing_content'] ?: '', 'time_last_play_tv' => $rDevice['time_last_play_tv'] ? date('Y-m-d H:i:s', $rDevice['time_last_play_tv']) : '0000-00-00 00:00:00', 'time_last_play_video' => $rDevice['time_last_play_video'] ? date('Y-m-d H:i:s', $rDevice['time_last_play_video']) : '0000-00-00 00:00:00', 'hd_content' => $rDevice['hd_content'] ?: '0', 'image_version' => $rDevice['image_version'], 'last_change_status' => $rDevice['last_change_status'] ? date('Y-m-d H:i:s', $rDevice['last_change_status']) : '0000-00-00 00:00:00', 'last_start' => $rDevice['last_start'] ? date('Y-m-d H:i:s', $rDevice['last_start']) : date('Y-m-d H:i:s'), 'last_active' => $rDevice['last_active'] ? date('Y-m-d H:i:s', $rDevice['last_active']) : date('Y-m-d H:i:s'), 'keep_alive' => $rDevice['keep_alive'] ? date('Y-m-d H:i:s', $rDevice['keep_alive']) : date('Y-m-d H:i:s'), 'screensaver_delay' => $rDevice['screensaver_delay'] ?: '10', 'stb_type' => $rDevice['stb_type'], 'now_playing_link_id' => $rDevice['now_playing_link_id'] ?: '0', 'now_playing_streamer_id' => $rDevice['now_playing_streamer_id'] ?: '0', 'last_watchdog' => $rDevice['last_watchdog'] ? date('Y-m-d H:i:s', $rDevice['last_watchdog']) : date('Y-m-d H:i:s'), 'created' => $rDevice['created'] ? date('Y-m-d H:i:s', $rDevice['created']) : date('Y-m-d H:i:s'), 'plasma_saving' => $rDevice['plasma_saving'] ?: '0', 'ts_enabled' => $rDevice['ts_enabled'] ?: '0', 'ts_enable_icon' => $rDevice['ts_enable_icon'] ?: '1', 'ts_path' => $rDevice['ts_path'] ?: '', 'ts_max_length' => $rDevice['ts_max_length'] ?: '3600', 'ts_buffer_use' => $rDevice['ts_buffer_use'] ?: 'cyclic', 'ts_action_on_exit' => $rDevice['ts_action_on_exit'] ?: 'no_save', 'ts_delay' => $rDevice['ts_delay'] ?: 'on_pause', 'video_clock' => $rDevice['video_clock'] == 'On' ? 'On' : 'Off', 'hdmi_event_reaction' => $rDevice['hdmi_event_reaction'] ?: 1, 'show_after_loading' => $rDevice['show_after_loading'] ?: '', 'play_in_preview_by_ok' => $rDevice['play_in_preview_by_ok'] ?: NULL, 'hw_version' => $rDevice['hw_version'], 'units' => $rDevice['units'] ?: 'metric', 'last_itv_id' => $rDevice['last_itv_id'] ?: 0, 'rtsp_type' => $rDevice['rtsp_type'] ?: '4', 'rtsp_flags' => $rDevice['rtsp_flags'] ?: '0', 'stb_lang' => $rDevice['stb_lang'] ?: 'en', 'display_menu_after_loading' => $rDevice['display_menu_after_loading'] ?: '', 'record_max_length' => $rDevice['record_max_length'] ?: 180, 'play_in_preview_only_by_ok' => $rDevice['play_in_preview_only_by_ok'] ?: false, 'tv_archive_continued' => $rDevice['tv_archive_continued'] ?: '', 'plasma_saving_timeout' => $rDevice['plasma_saving_timeout'] ?: '600'];
			$rDevice['mac'] = base64_encode($rDevice['mac']);
			$rDevice['generated'] = time();
		}
	}
	else if ($rDevice) {
		$rLiveIDs = $rVODIDs = $rRadioIDs = $rCategoryIDs = $rChannelIDs = $rSeriesIDs = [];

		foreach ($rDevice['bouquet'] as $rID) {
			if (isset(XCMS::$rBouquets[$rID]['streams'])) {
				$rChannelIDs = array_merge($rChannelIDs, XCMS::$rBouquets[$rID]['streams']);
			}

			if (isset(XCMS::$rBouquets[$rID]['series'])) {
				$rSeriesIDs = array_merge($rSeriesIDs, XCMS::$rBouquets[$rID]['series']);
			}

			if (isset(XCMS::$rBouquets[$rID]['channels'])) {
				$rLiveIDs = array_merge($rLiveIDs, XCMS::$rBouquets[$rID]['channels']);
			}

			if (isset(XCMS::$rBouquets[$rID]['movies'])) {
				$rVODIDs = array_merge($rVODIDs, XCMS::$rBouquets[$rID]['movies']);
			}

			if (isset(XCMS::$rBouquets[$rID]['radios'])) {
				$rRadioIDs = array_merge($rRadioIDs, XCMS::$rBouquets[$rID]['radios']);
			}
		}

		$rDevice['channel_ids'] = array_map('intval', array_unique($rChannelIDs));
		$rDevice['series_ids'] = array_map('intval', array_unique($rSeriesIDs));
		$rDevice['vod_ids'] = array_map('intval', array_unique($rVODIDs));
		$rDevice['live_ids'] = array_map('intval', array_unique($rLiveIDs));
		$rDevice['radio_ids'] = array_map('intval', array_unique($rRadioIDs));
	}

	return $rDevice;
}

function getEPG($rStreamID, $rStartDate = NULL, $rFinishDate = NULL, $rByID = false)
{
	$rReturn = [];
	$rData = (file_exists(EPG_PATH . 'stream_' . $rStreamID) ? igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStreamID)) : []);

	foreach ($rData as $rItem) {
		if (!$rStartDate || (($rStartDate < $rItem['end']) && ($rItem['start'] < $rFinishDate))) {
			if ($rByID) {
				$rReturn[$rItem['id']] = $rItem;
			}
			else {
				$rReturn[] = $rItem;
			}
		}
	}

	return $rReturn;
}

function getEPGs($rStreamIDs, $rStartDate = NULL, $rFinishDate = NULL)
{
	$rReturn = [];

	foreach ($rStreamIDs as $rStreamID) {
		$rReturn[$rStreamID] = getepg($rStreamID, $rStartDate, $rFinishDate);
	}

	return $rReturn;
}

function getProgramme($rStreamID, $rProgrammeID)
{
	$rData = getepg($rStreamID, NULL, NULL, true);

	if (isset($rData[$rProgrammeID])) {
		return $rData[$rProgrammeID];
	}

	return NULL;
}

function updateCache()
{
	global $rDevice;
	file_put_contents(MINISTRA_TMP_PATH . 'ministra_' . $rDevice['mag_id'], igbinary_serialize($rDevice));
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
@header('Content-type: text/javascript');
$rReqType = (!empty($_REQUEST['type']) ? $_REQUEST['type'] : NULL);
$rReqAction = (!empty($_REQUEST['action']) ? $_REQUEST['action'] : NULL);
if ($rReqType && $rReqAction) {
	switch ($rReqType) {
	case 'stb':
		switch ($rReqAction) {
		case 'get_ad':
			exit(json_encode([
				'js' => []
			]));
		case 'get_storages':
			exit(json_encode([
				'js' => []
			]));
		case 'log':
			exit(json_encode(['js' => true]));
		case 'get_countries':
			exit(json_encode([
				'js' => []
			]));
		case 'get_timezones':
			exit(json_encode([
				'js' => []
			]));
		case 'get_cities':
			exit(json_encode([
				'js' => []
			]));
		case 'search_cities':
			exit(json_encode([
				'js' => []
			]));
		}

		break;
	case 'remote_pvr':
		switch ($rReqAction) {
		case 'start_record_on_stb':
			exit(json_encode(['js' => true]));
		case 'stop_record_on_stb':
			exit(json_encode(['js' => true]));
		case 'get_active_recordings':
			exit(json_encode([
				'js' => []
			]));
		}

		break;
	case 'media_favorites':
		exit(json_encode(['js' => '']));
	case 'tvreminder':
		exit(json_encode([
			'js' => []
		]));
	case 'series':
	case 'vod':
		switch ($rReqAction) {
		case 'set_not_ended':
			exit(json_encode(['js' => true]));
		case 'del_link':
			exit(json_encode(['js' => true]));
		case 'log':
			exit(json_encode(['js' => 1]));
		}

		break;
	case 'downloads':
		exit(json_encode(['js' => true]));
	case 'weatherco':
		exit(json_encode(['js' => false]));
	case 'course':
		exit(json_encode(['js' => true]));
	case 'account_info':
		switch ($rReqAction) {
		case 'get_terms_info':
			exit(json_encode(['js' => true]));
		case 'get_payment_info':
			exit(json_encode(['js' => true]));
		case 'get_demo_video_parts':
			exit(json_encode(['js' => true]));
		case 'get_agreement_info':
			exit(json_encode(['js' => true]));
		}

		break;
	case 'tv_archive':
		switch ($rReqAction) {
		case 'set_played_timeshift':
			exit(json_encode(['js' => true]));
		case 'set_played':
			exit(json_encode(['js' => true]));
		case 'update_played_timeshift_end_time':
			exit(json_encode(['js' => true]));
		}

		break;
	case 'itv':
		switch ($rReqAction) {
		case 'set_fav_status':
			exit(json_encode([
				'js' => []
			]));
		case 'set_played':
			exit(json_encode(['js' => true]));
		}

		break;
	}
}

register_shutdown_function('shutdown');
require '/home/xcms/www/stream/init.php';

if (XCMS::$rSettings['disable_ministra']) {
	exit();
}

if (in_array($rReqAction, ['get_categories' => true, 'get_genres' => true, 'get_ordered_list' => true, 'get_all_channels' => true, 'get_all_fav_channels' => true, 'get_all_fav_radio' => true])) {
	XCMS::$rCategories = XCMS::getCache('categories');
}

$rIP = XCMS::getUserIP();
$rCountryCode = XCMS::getIPInfo($rIP)['country']['iso_code'];
$rMAC = (!empty(XCMS::$rRequest['mac']) ? XCMS::$rRequest['mac'] : $_COOKIE['mac']);
$rUserAgent = (!empty($_SERVER['HTTP_X_USER_AGENT']) ? $_SERVER['HTTP_X_USER_AGENT'] : NULL);
$rGMode = (!empty(XCMS::$rRequest['gmode']) ? (int) XCMS::$rRequest['gmode'] : NULL);
$rDebug = XCMS::$rSettings['enable_debug_stalker'];
$rDevice = [];
$rTypes = ['live', 'created_live'];
$rForceProtocol = (XCMS::$rSettings['mag_disable_ssl'] ? 'http' : NULL);
$rUpdateCache = false;
if (($rReqType == 'stb') && ($rReqAction == 'handshake')) {
	$rDevice = getDevice(NULL, $rMAC);
	$rVerifyToken = NULL;

	if ($rDevice) {
		$rDevice['token'] = strtoupper(md5(uniqid(rand(), true)));
		$rVerifyToken = Xcms\Functions::encrypt(igbinary_serialize(['id' => $rDevice['mag_id'], 'token' => $rDevice['token']]), XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
		$rDevice['authenticated'] = false;
		$db->query('UPDATE `mag_devices` SET `token` = ? WHERE `mag_id` = ?', $rDevice['token'], $rDevice['mag_id']);
		$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', SERVER_ID, time(), json_encode(['type' => 'update_line', 'id' => $rDevice['user_id']]));
		updateCache();
	}
	else {
		$rDevice = [];
	}

	exit(json_encode([
		'js' => ['token' => $rVerifyToken]
	]));
}

if (function_exists('getallheaders')) {
	$rHeaders = getallheaders();
}
else {
	$rHeaders = getHeaders();
}

$rAuthToken = NULL;
$rAuthHeader = (!empty($rHeaders['Authorization']) ? $rHeaders['Authorization'] : NULL);
if ($rAuthHeader && preg_match('/Bearer\\s+(.*)$/i', $rAuthHeader, $rMatches)) {
	$rAuthToken = trim($rMatches[1]);
}

if ($rAuthToken) {
	$rVerify = igbinary_unserialize(Xcms\Functions::decrypt($rAuthToken, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA));
	$rDevice = (isset($rVerify['id']) ? getDevice($rVerify['id']) : []);

	if ($rDevice['token'] != $rVerify['token']) {
		$rDevice = [];
	}
	else {
		$rDevice['authenticated'] = true;
		updateCache();
	}
}
if ($rDevice && ($rReqType == 'stb') && ($rReqAction == 'get_profile')) {
	$rSerialNumber = (!empty(XCMS::$rRequest['sn']) ? XCMS::$rRequest['sn'] : NULL);
	$rSTBType = (!empty(XCMS::$rRequest['stb_type']) ? XCMS::$rRequest['stb_type'] : NULL);
	$rVersion = (!empty(XCMS::$rRequest['ver']) ? XCMS::$rRequest['ver'] : NULL);
	$rImageVersion = (!empty(XCMS::$rRequest['image_version']) ? XCMS::$rRequest['image_version'] : NULL);
	$rDeviceID = (!empty(XCMS::$rRequest['device_id']) ? XCMS::$rRequest['device_id'] : NULL);
	$rDeviceID2 = (!empty(XCMS::$rRequest['device_id2']) ? XCMS::$rRequest['device_id2'] : NULL);
	$rHWVersion = (!empty(XCMS::$rRequest['hw_version']) ? XCMS::$rRequest['hw_version'] : NULL);
	$rVerified = true;
	if (!empty(XCMS::$rSettings['allowed_stb_types']) && !in_array(strtolower($rSTBType), XCMS::$rSettings['allowed_stb_types'])) {
		$rVerified = false;
	}

	if ($rDevice['lock_device']) {
		if (!empty($rDevice['sn']) && ($rSerialNumber != $rDevice['sn'])) {
			$rVerified = false;
		}
		if (!empty($rDevice['device_id']) && ($rDeviceID != $rDevice['device_id'])) {
			$rVerified = false;
		}
		if (!empty($rDevice['device_id2']) && ($rDeviceID2 != $rDevice['device_id2'])) {
			$rVerified = false;
		}
		if (!empty($rDevice['hw_version']) && ($rHWVersion != $rDevice['hw_version'])) {
			$rVerified = false;
		}
	}
	if (!empty(XCMS::$rSettings['stalker_lock_images']) && !in_array($rVersion, XCMS::$rSettings['stalker_lock_images'])) {
		$rVerified = false;
	}

	if ($rDebug) {
		$rVerified = true;
	}

	if ($rVerified) {
		$rDevice['ip'] = $rIP;
		$rDevice['stb_type'] = $rSTBType;
		$rDevice['sn'] = $rSerialNumber;
		$rDevice['ver'] = $rVersion;
		$rDevice['image_version'] = $rImageVersion;
		$rDevice['device_id'] = $rDeviceID;
		$rDevice['device_id2'] = $rDeviceID2;
		$rDevice['hw_version'] = $rHWVersion;
		$rDevice['get_profile_vars']['ip'] = $rIP ?: '127.0.0.1';
		$rDevice['get_profile_vars']['image_version'] = $rImageVersion;
		$rDevice['get_profile_vars']['stb_type'] = $rSTBType;
		$rDevice['get_profile_vars']['hw_version'] = $rHWVersion;
		$rDevice['authenticated'] = true;
		$db->query('UPDATE `mag_devices` SET `ip` = ?, `stb_type` = ?, `sn` = ?, `ver` = ?, `image_version` = ?, `device_id` = ?, `device_id2` = ?, `hw_version` = ? WHERE `mag_id` = ?;', $rIP, $rSTBType, $rSerialNumber, $rVersion, $rImageVersion, $rDeviceID, $rDeviceID2, $rHWVersion, $rDevice['mag_id']);
		updateCache();
	}
	else {
		unlink(MINISTRA_TMP_PATH . 'ministra_' . $rDevice['id']);
		$rDevice = [];
	}
}

$rAuthenticated = $rDevice['authenticated'] ?: false;
if (empty($rDevice['locale']) && !empty($_COOKIE['locale'])) {
	$rDevice['locale'] = $_COOKIE['locale'];
}
else {
	$rDevice['locale'] = 'en_GB.utf8';
}

$rMagData = [];
$rProfile = [
	'id'                                   => $rDevice['mag_id'],
	'name'                                 => $rDevice['mag_id'],
	'sname'                                => '',
	'pass'                                 => '',
	'use_embedded_settings'                => '',
	'parent_password'                      => '0000',
	'bright'                               => '200',
	'contrast'                             => '127',
	'saturation'                           => '127',
	'video_out'                            => '',
	'volume'                               => '70',
	'playback_buffer_bytes'                => '0',
	'playback_buffer_size'                 => '0',
	'audio_out'                            => '1',
	'mac'                                  => $rMAC,
	'ip'                                   => '127.0.0.1',
	'ls'                                   => '',
	'version'                              => '',
	'lang'                                 => '',
	'locale'                               => $rDevice['locale'],
	'city_id'                              => '0',
	'hd'                                   => '1',
	'main_notify'                          => '1',
	'fav_itv_on'                           => '0',
	'now_playing_start'                    => '2018-02-18 17:33:43',
	'now_playing_type'                     => '1',
	'now_playing_content'                  => 'Test channel',
	'additional_services_on'               => '1',
	'time_last_play_tv'                    => '0000-00-00 00:00:00',
	'time_last_play_video'                 => '0000-00-00 00:00:00',
	'operator_id'                          => '0',
	'storage_name'                         => '',
	'hd_content'                           => '0',
	'image_version'                        => 'undefined',
	'last_change_status'                   => '0000-00-00 00:00:00',
	'last_start'                           => '2018-02-18 17:33:38',
	'last_active'                          => '2018-02-18 17:33:43',
	'keep_alive'                           => '2018-02-18 17:33:43',
	'screensaver_delay'                    => '10',
	'phone'                                => '',
	'fname'                                => '',
	'login'                                => '',
	'password'                             => '',
	'stb_type'                             => '',
	'num_banks'                            => '0',
	'tariff_plan_id'                       => '0',
	'comment'                              => NULL,
	'now_playing_link_id'                  => '0',
	'now_playing_streamer_id'              => '0',
	'just_started'                         => '1',
	'last_watchdog'                        => '2018-02-18 17:33:39',
	'created'                              => '2018-02-18 14:40:12',
	'plasma_saving'                        => '0',
	'ts_enabled'                           => '0',
	'ts_enable_icon'                       => '1',
	'ts_path'                              => '',
	'ts_max_length'                        => '3600',
	'ts_buffer_use'                        => 'cyclic',
	'ts_action_on_exit'                    => 'no_save',
	'ts_delay'                             => 'on_pause',
	'video_clock'                          => 'Off',
	'verified'                             => '0',
	'hdmi_event_reaction'                  => 1,
	'pri_audio_lang'                       => '',
	'sec_audio_lang'                       => '',
	'pri_subtitle_lang'                    => '',
	'sec_subtitle_lang'                    => '',
	'subtitle_color'                       => '16777215',
	'subtitle_size'                        => '20',
	'show_after_loading'                   => '',
	'play_in_preview_by_ok'                => NULL,
	'hw_version'                           => 'undefined',
	'openweathermap_city_id'               => '0',
	'theme'                                => '',
	'settings_password'                    => '0000',
	'expire_billing_date'                  => '0000-00-00 00:00:00',
	'reseller_id'                          => NULL,
	'account_balance'                      => '',
	'client_type'                          => 'STB',
	'hw_version_2'                         => '62',
	'blocked'                              => '0',
	'units'                                => 'metric',
	'tariff_expired_date'                  => NULL,
	'tariff_id_instead_expired'            => NULL,
	'activation_code_auto_issue'           => '1',
	'last_itv_id'                          => 0,
	'updated'                              => ['id' => '1', 'uid' => '1', 'anec' => '0', 'vclub' => '0'],
	'rtsp_type'                            => '4',
	'rtsp_flags'                           => '0',
	'stb_lang'                             => 'en',
	'display_menu_after_loading'           => '',
	'record_max_length'                    => 180,
	'web_proxy_host'                       => '',
	'web_proxy_port'                       => '',
	'web_proxy_user'                       => '',
	'web_proxy_pass'                       => '',
	'web_proxy_exclude_list'               => '',
	'demo_video_url'                       => '',
	'tv_quality'                           => 'high',
	'tv_quality_filter'                    => '',
	'is_moderator'                         => false,
	'timeslot_ratio'                       => 0.33333333333333,
	'timeslot'                             => 40,
	'kinopoisk_rating'                     => '1',
	'enable_tariff_plans'                  => '',
	'strict_stb_type_check'                => '',
	'cas_type'                             => 0,
	'cas_params'                           => NULL,
	'cas_web_params'                       => NULL,
	'cas_additional_params'                => [],
	'cas_hw_descrambling'                  => 0,
	'cas_ini_file'                         => '',
	'logarithm_volume_control'             => '',
	'allow_subscription_from_stb'          => '1',
	'deny_720p_gmode_on_mag200'            => '1',
	'enable_arrow_keys_setpos'             => '1',
	'show_purchased_filter'                => '',
	'timezone_diff'                        => 0,
	'enable_connection_problem_indication' => '1',
	'invert_channel_switch_direction'      => '',
	'play_in_preview_only_by_ok'           => false,
	'enable_stream_error_logging'          => '',
	'always_enabled_subtitles'             => XCMS::$rSettings['always_enabled_subtitles'] == 1 ? '1' : '',
	'enable_service_button'                => '',
	'enable_setting_access_by_pass'        => '',
	'tv_archive_continued'                 => '',
	'plasma_saving_timeout'                => '600',
	'show_tv_only_hd_filter_option'        => '',
	'tv_playback_retry_limit'              => '0',
	'fading_tv_retry_timeout'              => '1',
	'epg_update_time_range'                => 0.6,
	'store_auth_data_on_stb'               => false,
	'account_page_by_password'             => '',
	'tester'                               => false,
	'enable_stream_losses_logging'         => '',
	'external_payment_page_url'            => '',
	'max_local_recordings'                 => '10',
	'tv_channel_default_aspect'            => 'fit',
	'default_led_level'                    => '10',
	'standby_led_level'                    => '90',
	'show_version_in_main_menu'            => '1',
	'disable_youtube_for_mag200'           => '1',
	'auth_access'                          => false,
	'epg_data_block_period_for_stb'        => '5',
	'standby_on_hdmi_off'                  => '1',
	'force_ch_link_check'                  => '',
	'stb_ntp_server'                       => 'pool.ntp.org',
	'overwrite_stb_ntp_server'             => '',
	'hide_tv_genres_in_fullscreen'         => NULL,
	'advert'                               => NULL
];
$rLocales['get_locales']['English'] = 'en_GB.utf8';
$rLocales['get_locales']['Ελληνικά'] = 'el_GR.utf8';
$rMagData['get_years'] = [
	'js' => [
	['id' => '*', 'title' => '*']
]
];

foreach (range(1900, date('Y')) as $rYear) {
	$rMagData['get_years']['js'][] = ['id' => $rYear, 'title' => $rYear];
}

$rMagData['get_abc'] = [
	'js' => [
	['id' => '*', 'title' => '*'],
	['id' => 'A', 'title' => 'A'],
	['id' => 'B', 'title' => 'B'],
	['id' => 'C', 'title' => 'C'],
	['id' => 'D', 'title' => 'D'],
	['id' => 'E', 'title' => 'E'],
	['id' => 'F', 'title' => 'F'],
	['id' => 'G', 'title' => 'G'],
	['id' => 'H', 'title' => 'H'],
	['id' => 'I', 'title' => 'I'],
	['id' => 'G', 'title' => 'G'],
	['id' => 'K', 'title' => 'K'],
	['id' => 'L', 'title' => 'L'],
	['id' => 'M', 'title' => 'M'],
	['id' => 'N', 'title' => 'N'],
	['id' => 'O', 'title' => 'O'],
	['id' => 'P', 'title' => 'P'],
	['id' => 'Q', 'title' => 'Q'],
	['id' => 'R', 'title' => 'R'],
	['id' => 'S', 'title' => 'S'],
	['id' => 'T', 'title' => 'T'],
	['id' => 'U', 'title' => 'U'],
	['id' => 'V', 'title' => 'V'],
	['id' => 'W', 'title' => 'W'],
	['id' => 'X', 'title' => 'X'],
	['id' => 'W', 'title' => 'W'],
	['id' => 'Z', 'title' => 'Z']
]
];
$rTimezone = (empty($_COOKIE['timezone']) || ($_COOKIE['timezone'] == 'undefined') ? XCMS::$rSettings['default_timezone'] : $_COOKIE['timezone']);

if (!in_array($rTimezone, DateTimeZone::listIdentifiers())) {
	$rTimezone = XCMS::$rSettings['default_timezone'];
}

if (!$rAuthenticated) {
	$rDevice['theme_type'] = XCMS::$rSettings['mag_default_type'];
}

if ($rDevice['theme_type'] == 0) {
	$rTheme = 'xcms';
}
else {
	$rTheme = (empty(XCMS::$rSettings['stalker_theme']) ? 'default' : XCMS::$rSettings['stalker_theme']);
}

$rLanguage = [
	'en_GB.utf8' => [
	'weather_comfort'                      => 'Comfort',
	'weather_pressure'                     => 'Pressure',
	'weather_mmhg'                         => 'mm Hg',
	'weather_wind'                         => 'Wind',
	'weather_speed'                        => 'm/s',
	'weather_humidity'                     => 'Humidity',
	'current_weather_unavailable'          => 'Current weather unavailable',
	'current_weather_not_configured'       => 'The weather is not configured',
	'karaoke_view'                         => 'VIEW',
	'karaoke_sort'                         => 'SORT',
	'karaoke_search'                       => 'SEARCH',
	'karaoke_sampling'                     => 'PICKING',
	'karaoke_by_letter'                    => 'BY LETTER',
	'karaoke_by_performer'                 => 'BY NAME',
	'karaoke_by_title'                     => 'BY TITLE',
	'karaoke_title'                        => 'KARAOKE',
	'layer_page'                           => 'Page',
	'layer_from'                           => 'of',
	'layer_found'                          => 'Total',
	'layer_records'                        => 'items',
	'layer_loading'                        => 'LOADING...',
	'Loading...'                           => 'Loading...',
	'mbrowser_title'                       => 'Media Browser',
	'mbrowser_connected'                   => 'connected',
	'mbrowser_disconnected'                => 'disconnected',
	'mbrowser_not_found'                   => 'not found',
	'usb_drive'                            => 'USB drive',
	'player_limit_notice'                  => 'The number of connections is limited.<br>Try again later',
	'player_file_missing'                  => 'File missing',
	'player_server_error'                  => 'Server error',
	'player_access_denied'                 => 'Access denied',
	'player_server_unavailable'            => 'Server unavailable',
	'player_series'                        => 'part',
	'player_season'                        => 'Season',
	'player_track'                         => 'Track',
	'player_off'                           => 'Off',
	'player_subtitle'                      => 'Subtitles',
	'player_claim'                         => 'Complain',
	'player_on_sound'                      => 'on sound',
	'player_on_video'                      => 'on video',
	'player_audio'                         => 'Audio',
	'player_ty'                            => 'Thank you, your opinion will be taken into account',
	'series_by_one_play'                   => 'play one ⇅',
	'series_continuously_play'             => 'play continuously ⇅',
	'aspect_fit'                           => 'fit on',
	'aspect_big'                           => 'zoom',
	'aspect_opt'                           => 'optimal',
	'aspect_exp'                           => 'stretch',
	'aspect_cmb'                           => 'combined',
	'radio_title'                          => 'Radio Stations',
	'radio_sort'                           => 'SORT',
	'radio_favorite'                       => 'FAVOURITE',
	'radio_search'                         => 'SEARCH',
	'radio_by_number'                      => 'By Number',
	'radio_by_title'                       => 'By Title',
	'radio_only_favorite'                  => 'Only Favourites',
	'radio_fav_add'                        => 'add',
	'radio_fav_del'                        => 'del',
	'radio_search_box'                     => 'SEARCH',
	'tv_view'                              => 'VIEW',
	'tv_sort'                              => 'SORT',
	'favorite'                             => 'FAVOURITE',
	'tv_favorite'                          => 'FAVOURITE',
	'tv_move'                              => 'MOVE',
	'tv_by_number'                         => 'By Number',
	'tv_by_title'                          => 'By Title',
	'tv_only_favorite'                     => 'Only Favourites',
	'tv_only_hd'                           => 'HD Only',
	'tv_list'                              => 'Standard List',
	'tv_list_w_info'                       => 'List With Player',
	'tv_title'                             => 'Live TV',
	'vclub_info'                           => 'information',
	'sclub_info'                           => 'information',
	'vclub_year'                           => 'Year',
	'vclub_country'                        => 'Country',
	'vclub_genre'                          => 'Genre',
	'vclub_length'                         => 'Length',
	'vclub_minutes'                        => 'min',
	'vclub_director'                       => 'Director',
	'vclub_cast'                           => 'Cast',
	'vclub_rating'                         => 'Rating',
	'vclub_age'                            => 'Age',
	'vclub_rating_mpaa'                    => 'Rating MPAA',
	'vclub_view'                           => 'VIEW',
	'vclub_sort'                           => 'SORT',
	'vclub_search'                         => 'SEARCH',
	'vclub_fav'                            => 'FAVOURITE',
	'vclub_other'                          => 'OTHER',
	'vclub_find'                           => 'FIND',
	'vclub_by_letter'                      => 'Alphabetical',
	'vclub_by_genre'                       => 'Genre',
	'vclub_by_year'                        => 'Year',
	'vclub_by_rating'                      => 'Rating',
	'vclub_search_box'                     => 'Search',
	'vclub_query_box'                      => 'Filter',
	'vclub_by_title'                       => 'Alphabetical',
	'vclub_by_addtime'                     => 'Date Added',
	'vclub_top'                            => 'Rating',
	'vclub_only_hd'                        => 'HD Only',
	'vclub_only_favorite'                  => 'Favourites',
	'vclub_only_purchased'                 => 'purchased',
	'vclub_not_ended'                      => 'not ended',
	'vclub_list'                           => 'Standard List',
	'vclub_list_w_info'                    => 'List With Poster',
	'vclub_title'                          => 'Movies',
	'sclub_title'                          => 'TV Series',
	'vclub_purchased'                      => 'Purchased',
	'vclub_rent_expires_in'                => 'rent expires in',
	'cut_off_msg'                          => 'your device is not active<br>',
	'month_arr'                            => ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'],
	'week_arr'                             => ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'],
	'year'                                 => '',
	'records_title'                        => 'RECORDS',
	'ears_back'                            => 'BACK',
	'ears_about_movie'                     => 'ABOUT',
	'ears_tv_guide'                        => 'GUIDE',
	'ears_about_package'                   => 'INFO',
	'settings_title'                       => 'Settings',
	'parent_settings_cancel'               => 'CANCEL',
	'parent_settings_save'                 => 'SAVE',
	'parent_settings_old_pass'             => 'Current password',
	'parent_settings_title'                => 'PARENTAL SETTINGS',
	'parent_settings_title_short'          => 'PARENTAL',
	'parent_settings_new_pass'             => 'New password',
	'parent_settings_repeat_new_pass'      => 'Repeat new password',
	'settings_saved'                       => 'Settings saved',
	'settings_saved_reboot'                => 'Settings saved.<br>The STB will be rebooted. Press OK.',
	'settings_check_error'                 => 'Error filling fields',
	'settings_saving_error'                => 'Saving error',
	'localization_settings_title'          => 'LOCALIZATION',
	'localization_label'                   => 'Language of the interface',
	'country_label'                        => 'Country',
	'city_label'                           => 'City',
	'localization_page_button_info'        => 'Use PAGE-/+ buttons to move through several menu items',
	'settings_software_update'             => 'SOFTWARE UPDATE',
	'update_settings_cancel'               => 'CANCEL',
	'update_settings_start_update'         => 'START UPDATE',
	'update_from_http'                     => 'From HTTP',
	'update_from_usb'                      => 'From USB',
	'update_source'                        => 'Source',
	'update_method_select'                 => 'Method select',
	'empty'                                => 'EMPTY',
	'course_title'                         => 'Exchange rate on',
	'course_title_nbu'                     => 'NBU exchange rate on',
	'course_title_cbr'                     => 'CBR exchange rate on',
	'dayweather_title'                     => 'WEATHER',
	'dayweather_pressure'                  => 'pres.:',
	'dayweather_mmhg'                      => 'mm Hg',
	'dayweather_wind'                      => 'wind:',
	'dayweather_speed'                     => 'm/s',
	'infoportal_title'                     => 'INFOPORTAL',
	'cityinfo_title'                       => 'CITY INFO',
	'cityinfo_main'                        => 'emergency',
	'cityinfo_help'                        => 'help',
	'cityinfo_other'                       => 'other',
	'cityinfo_sort'                        => 'VIEW',
	'horoscope_title'                      => 'HOROSCOPE',
	'anecdote_title'                       => 'JOKES',
	'anecdote_goto'                        => 'GOTO',
	'anecdote_like'                        => 'LIKE',
	'anecdote_bookmark'                    => 'BOOKMARK',
	'anecdote_to_bookmark'                 => 'TO BOOKMARK',
	'anecdote_pagebar_title'               => 'JOKE',
	'mastermind_title'                     => 'MASTERMIND',
	'mastermind_rules'                     => 'RULES',
	'mastermind_rating'                    => 'RATING',
	'mastermind_bull'                      => 'B',
	'mastermind_cow'                       => 'C',
	'mastermind_rules_text'                => 'Your task is to guess the number of unduplicated four digits, the first of them - not zero. Every your guess will be compared with the number put forth a STB. If you guessed a digit, but it is not in place, then it is a COW (C). If you guessed, and a number, and its location, this BULL (B).',
	'mastermind_move_cursor'               => 'Moving the cursor',
	'mastermind_cell_numbers'              => 'Enter numbers into cells',
	'mastermind_step_confirmation'         => 'Confirmation of the step',
	'mastermind_page'                      => 'Page',
	'mastermind_history_moves'             => 'Moving through the pages of history moves',
	'msg_service_off'                      => 'Service is disabled',
	'msg_channel_not_available'            => 'Channel is not available',
	'epg_title'                            => 'TV Guide',
	'epg_record'                           => 'RECORD',
	'epg_remind'                           => 'REMIND',
	'epg_memo'                             => 'Memo',
	'epg_goto_ch'                          => 'Goto channel',
	'epg_close_msg'                        => 'Close',
	'epg_on_ch'                            => 'on channel',
	'epg_now_begins'                       => 'now begins',
	'epg_on_time'                          => 'in',
	'epg_started'                          => 'started',
	'epg_more'                             => 'MORE',
	'epg_category'                         => 'Category',
	'epg_director'                         => 'Director',
	'epg_actors'                           => 'Stars',
	'epg_desc'                             => 'Description',
	'search_box_languages'                 => ['en'],
	'date_format'                          => '{0}, {2} {1}, {3}',
	'time_format'                          => '{0}:{1}',
	'timezone_label'                       => 'Timezone',
	'ntp_server'                           => 'NTP Server',
	'remote_pvr_del'                       => 'DELETE',
	'remote_pvr_stop'                      => 'STOP',
	'remote_pvr_del_confirm'               => 'Do you really want to delete this record?',
	'remote_pvr_stop_confirm'              => 'Do you really want to stop this record?',
	'alert_confirm'                        => 'Confirm',
	'alert_cancel'                         => 'Cancel',
	'recorder_server_error'                => 'Server error. Try again later.',
	'record_duration'                      => 'RECORDING DURATION',
	'rest_length_title'                    => 'FREE on the server, h',
	'channel_recording_restricted'         => 'Recording this channel is forbidden',
	'recording_disabled'                   => 'Recording isn\'t available on this device',
	'playback_settings_buffer_size'        => 'Buffer size',
	'playback_settings_time'               => 'Time, sec',
	'playback_settings_title'              => 'PLAYBACK',
	'cancel_btn'                           => 'CANCEL',
	'settings_cancel'                      => 'CANCEL',
	'playback_settings_cancel'             => 'CANCEL',
	'exit_btn'                             => 'EXIT',
	'yes_btn'                              => 'YES',
	'close_btn'                            => 'CLOSE',
	'ok_btn'                               => 'OK',
	'pay_btn'                              => 'PAY',
	'play_btn'                             => 'PLAY',
	'start_btn'                            => 'START',
	'add_btn'                              => 'ADD',
	'settings_save'                        => 'SAVE',
	'playback_settings_save'               => 'SAVE',
	'audio_out'                            => 'Audio out',
	'audio_out_analog'                     => 'Analog only',
	'audio_out_analog_spdif'               => 'Analog and S/PDIF 2-channel PCM',
	'audio_out_spdif'                      => 'S/PDIF raw or 2-channel PCM',
	'game'                                 => 'GAME',
	'downloads_title'                      => 'DOWNLOADS',
	'not_found_mounted_devices'            => 'Not found mounted devices',
	'downloads_add_download'               => 'Add download',
	'downloads_device'                     => 'Device',
	'downloads_file_name'                  => 'File name',
	'downloads_ok'                         => 'Ok',
	'downloads_cancel'                     => 'Cancel',
	'downloads_create'                     => 'CREATE',
	'downloads_move_up'                    => 'MOVE UP',
	'downloads_move_down'                  => 'MOVE DOWN',
	'downloads_delete'                     => 'DELETE',
	'downloads_record'                     => 'RECORDING',
	'downloads_download'                   => 'DOWNLOAD',
	'downloads_record_and_file'            => 'RECORDING AND FILE',
	'playback_limit_title'                 => 'Duration of continuous playback',
	'playback_limit_off'                   => 'Without limit',
	'playback_hours'                       => 'hours',
	'playback_limit_reached'               => 'Reached limit the duration of continuous playback. To continue playback, press the OK or EXIT.',
	'common_settings_title'                => 'GENERAL SETTINGS',
	'screensaver_delay_title'              => 'Screensaver interval',
	'screensaver_off'                      => 'Disabled',
	'screensaver_minutes'                  => 'min',
	'demo_video_title'                     => 'DEMO VIDEO',
	'account_info_title'                   => 'My Account',
	'coming_soon'                          => 'Coming soon',
	'account_info'                         => 'INFORMATION',
	'account_payment'                      => 'MODERN PORTAL',
	'account_pay'                          => 'PAY',
	'account_agreement'                    => 'LEGACY PORTAL',
	'account_terms'                        => '',
	'demo_video'                           => 'Video instruction',
	'tv_quality'                           => 'QUALITY',
	'tv_quality_low'                       => 'low',
	'tv_quality_medium'                    => 'medium',
	'tv_quality_high'                      => 'high',
	'tv_fav_add'                           => 'add',
	'tv_fav_del'                           => 'del',
	'internet'                             => 'Internet',
	'network_status_title'                 => 'NETWORK STATUS',
	'network_status_refresh'               => 'REFRESH',
	'test_speed'                           => 'Speed test',
	'speedtest_testing'                    => 'testing...',
	'speedtest_error'                      => 'error',
	'speedtest_waiting'                    => 'waiting...',
	'lan_up'                               => 'UP',
	'lan_down'                             => 'DOWN',
	'download_stopped'                     => 'stopped',
	'download_waiting_queue'               => 'waiting queue',
	'download_running'                     => 'running',
	'download_completed'                   => 'completed',
	'download_temporary_error'             => 'temporary error',
	'download_permanent_error'             => 'permanent error',
	'auth_title'                           => 'Authentication',
	'auth_login'                           => 'Login',
	'auth_password'                        => 'Password',
	'auth_error'                           => 'Error',
	'play_or_download'                     => 'Play this url or start download?',
	'player_play'                          => 'Play',
	'player_download'                      => 'Download',
	'play_all'                             => 'PLAY ALL',
	'on'                                   => 'ON',
	'off'                                  => 'OFF',
	'smb_auth'                             => 'Network authentication',
	'smb_username'                         => 'Login',
	'smb_password'                         => 'Password',
	'exit_title'                           => 'Do you really want to exit?',
	'identical_download_exist'             => 'There is an active downloads from this server',
	'alert_form_title'                     => 'Alert',
	'confirm_form_title'                   => 'Confirm',
	'notice_form_title'                    => 'Notice',
	'select_form_title'                    => 'Select',
	'media_favorites'                      => 'Favourites',
	'stb_type_not_supported'               => 'your set-top box is not supported',
	'Phone'                                => 'Subscription Expire Date',
	'Tariff plan'                          => 'Tariff plan',
	'User'                                 => 'User',
	'SERVICES MANAGEMENT'                  => 'SERVICES MANAGEMENT',
	'SUBSCRIBE'                            => 'SUBSCRIBE',
	'UNSUBSCRIBE'                          => 'UNSUBSCRIBE',
	'package_info_title'                   => 'PACKAGE INFO',
	'package_type'                         => 'Type',
	'package_content'                      => 'Content',
	'package_description'                  => 'Description',
	'confirm_service_subscribe_text'       => 'Are you really want to subscribe to this service?',
	'confirm_service_unsubscribe_text'     => 'Are you really want to unsubscribe from this service?',
	'confirm_service_price_text'           => 'The service costs {0}',
	'service_subscribe_success'            => 'You have successfully subscribed to the service.',
	'service_unsubscribe_success'          => 'You have successfully unsubscribed from the service.',
	'service_subscribe_success_reboot'     => 'You have successfully subscribed to the service. STB will be rebooted.',
	'service_unsubscribe_success_reboot'   => 'You have successfully unsubscribed from the service. STB will be rebooted.',
	'service_subscribe_fail'               => 'An error in the management of subscriptions.',
	'service_subscribe_server_error'       => 'Server error. Try again later.',
	'package_price_measurement'            => 'package_price_measurement',
	'rent_movie_text'                      => 'Do you really want to rent this movie?',
	'rent_movie_price_text'                => 'The movie costs {0}',
	'rent_duration_text'                   => 'Rent duration: {0}h',
	'Account number'                       => 'Account number',
	'Password'                             => 'Password',
	'End date'                             => 'End date',
	'3D mode'                              => '3D mode',
	'mode {0}'                             => 'mode {0}',
	'no epg'                               => 'no epg',
	'wrong epg'                            => 'wrong epg',
	'iso_title'                            => 'Part',
	'error_channel_nothing_to_play'        => 'Channel not available',
	'error_channel_limit'                  => 'Channel temporary unavailable',
	'error_channel_not_available_for_zone' => 'Channel not available for this region',
	'error_channel_link_fault'             => 'Channel not available. Server error.',
	'error_channel_access_denied'          => 'Access denied',
	'blocking_account_info'                => 'Account Info',
	'blocking_account_payment'             => 'Payment',
	'blocking_account_reboot'              => 'Reboot',
	'archive_continue_playing_text'        => 'Continue playing?',
	'archive_yes'                          => 'YES',
	'archive_no'                           => 'NO',
	'time_shift_exit_confirm_text'         => 'Do you want to quit the Time Shift mode?',
	'mbrowser_sort_by_name'                => 'by name',
	'mbrowser_sort_by_date'                => 'by date',
	'Connection problem'                   => 'Connection problem',
	'Authentication problem'               => 'Authentication problem',
	'Account balance'                      => 'Account balance',
	'remote_pvr_confirm_text'              => 'Start recording on the server?',
	'remote_deferred_pvr_confirm_text'     => 'Do you really want to schedule a recording on the server?',
	'pvr_target_select_text'               => 'Select where to save the record',
	'usb_target_btn'                       => 'USB Storage',
	'server_target_btn'                    => 'Server',
	'save_path'                            => 'Path',
	'file_name'                            => 'Filename',
	'usb_device'                           => 'USB Device',
	'rec_stop_msg'                         => 'rec_stop_msg',
	'rec_file_missing'                     => 'The recorded file is missing',
	'rec_not_ended'                        => 'Recording is not finished yet',
	'rec_channel_has_scheduled_recording'  => 'The channel already has a scheduled recording',
	'pvr_error_wrong_param'                => 'PVR Error: Wrong parameter',
	'pvr_error_memory'                     => 'PVR Error: Not enough memory to complete the operation',
	'pvr_error_duration'                   => 'PVR Error: Incorrect recording range',
	'pvr_error_not_found'                  => 'PVR Error: Task not found',
	'pvr_error_wrong_filename'             => 'PVR Error: Wrong filename',
	'pvr_error_record_exist'               => 'PVR Error: Record file already exists',
	'pvr_error_url_open_error'             => 'PVR Error: Error opening channel URL',
	'pvr_error_file_open_error'            => 'PVR Error: Error opening file',
	'pvr_error_rec_limit'                  => 'PVR Error: Exceeded the maximum number simultaneous recordings',
	'pvr_error_end_of_stream'              => 'PVR Error: End of stream',
	'pvr_error_file_write_error'           => 'PVR Error: Error writing to file',
	'pvr_start_time'                       => 'Start time',
	'pvr_end_time'                         => 'End time',
	'pvr_duration'                         => 'Duration',
	'rec_options_form_title'               => 'Recording',
	'local_pvr_interrupted'                => 'Recording on USB device interrupted',
	'parent_password_error'                => 'Wrong',
	'parent_password_title'                => 'Parent control',
	'settings_password_title'              => 'Access control',
	'password_label'                       => 'Password',
	'encoding_label'                       => 'Encoding',
	'network_folder'                       => 'Network folder',
	'server_ip'                            => 'IP address',
	'server_path'                          => 'Path',
	'local_folder'                         => 'Local folder',
	'server_type'                          => 'Type',
	'server_login'                         => 'Login',
	'server_password'                      => 'Password',
	'add_folder'                           => 'ADD',
	'server_ip_placeholder'                => 'Server address',
	'server_path_placeholder'              => 'Path to the folder',
	'local_folder_placeholder'             => 'Folder name in favourites',
	'error'                                => 'error',
	'mount_failed'                         => 'Mount failed',
	'ad_skip'                              => 'SKIP',
	'commercial'                           => 'COMMERCIAL',
	'videoClockTitle'                      => 'Clock',
	'videoClock_off'                       => 'Hidden',
	'videoClock_upRight'                   => 'Top Right',
	'videoClock_upLeft'                    => 'Top Left',
	'videoClock_downRight'                 => 'Bottom Right',
	'videoClock_downLeft'                  => 'Bottom Left',
	'settings_unavailable'                 => 'Settings section is currently unavailable',
	'no_dvb_channels_title'                => 'No channels available',
	'go_to_dvb_settings'                   => 'You can configure DVB channels in the settings menu',
	'apps_title'                           => 'Applications',
	'coming_soon_video'                    => 'Video will be available soon',
	'app_install_confirm'                  => 'Install application?',
	'audioclub_title'                      => 'AUDIO CLUB',
	'track_search'                         => 'TRACK SEARCH',
	'album_search'                         => 'ALBUM SEARCH',
	'add_to_playlist'                      => 'ADD TO PLAYLIST',
	'remove_from_playlist'                 => 'DEL FROM PLAYLIST',
	'playlist'                             => 'PLAYLIST',
	'audioclub_year'                       => 'Year',
	'audioclub_country'                    => 'Country',
	'audioclub_languages'                  => 'Language',
	'audioclub_language'                   => 'Language',
	'audioclub_performer'                  => 'Artist',
	'audioclub_album'                      => 'Album',
	'audioclub_albums'                     => 'Albums',
	'audioclub_tracks'                     => 'Compositions',
	'audioclub_select_playlist'            => 'Playlist select',
	'audioclub_playlist'                   => 'Playlist',
	'new_btn'                              => 'NEW',
	'playlist_name'                        => 'Name',
	'audioclub_new_playlist'               => 'New Playlist',
	'audioclub_saving_error'               => 'Error while saving',
	'audioclub_create_new'                 => 'CREATE NEW',
	'remove_from_playlist_confirm'         => 'Do you really want to delete this track from playlist?',
	'delete_playlist_confirm'              => 'Do you really want to delete this playlist?',
	'audioclub_remove_playlist'            => 'DELETE',
	'vk_music_title'                       => 'VK MUSIC',
	'all_title'                            => 'All',
	'outdated_firmware'                    => 'Firmware of your STB is outdated.<br>Please update it.',
	'LOGOUT'                               => 'LOGOUT',
	'confirm_logout_title'                 => 'Confirm',
	'confirm_logout'                       => 'Do you really want to log out?'
]
];

if ($rTheme == 'xcms') {
	$rPageItems = 10;
}
else {
	$rPageItems = 14;
	$rLanguage['en_GB.utf8']['layer_found'] = 'Found';
	$rLanguage['en_GB.utf8']['series_by_one_play'] = 'one series';
	$rLanguage['en_GB.utf8']['series_continuously_play'] = 'continuously';
	$rLanguage['en_GB.utf8']['radio_by_number'] = 'by number';
	$rLanguage['en_GB.utf8']['radio_by_title'] = 'by title';
	$rLanguage['en_GB.utf8']['radio_only_favorite'] = 'only favourite';
	$rLanguage['en_GB.utf8']['tv_by_number'] = 'by number';
	$rLanguage['en_GB.utf8']['tv_by_title'] = 'by title';
	$rLanguage['en_GB.utf8']['tv_only_favorite'] = 'only favourite';
	$rLanguage['en_GB.utf8']['tv_only_hd'] = 'HD only';
	$rLanguage['en_GB.utf8']['tv_list'] = 'list';
	$rLanguage['en_GB.utf8']['tv_list_w_info'] = 'list + info';
	$rLanguage['en_GB.utf8']['vclub_info'] = 'information about the movie';
	$rLanguage['en_GB.utf8']['sclub_info'] = 'information about the series';
	$rLanguage['en_GB.utf8']['vclub_by_letter'] = 'BY LETTER';
	$rLanguage['en_GB.utf8']['vclub_by_genre'] = 'BY GENRE';
	$rLanguage['en_GB.utf8']['vclub_by_year'] = 'BY YEAR';
	$rLanguage['en_GB.utf8']['vclub_by_rating'] = 'BY RATING';
	$rLanguage['en_GB.utf8']['vclub_search_box'] = 'search';
	$rLanguage['en_GB.utf8']['vclub_query_box'] = 'picking';
	$rLanguage['en_GB.utf8']['vclub_by_title'] = 'by title';
	$rLanguage['en_GB.utf8']['vclub_by_addtime'] = 'by addtime';
	$rLanguage['en_GB.utf8']['vclub_top'] = 'rating';
	$rLanguage['en_GB.utf8']['vclub_only_hd'] = 'HD only';
	$rLanguage['en_GB.utf8']['vclub_only_favorite'] = 'favourite only';
	$rLanguage['en_GB.utf8']['vclub_list'] = 'list';
	$rLanguage['en_GB.utf8']['vclub_list_w_info'] = 'list + info';
	$rLanguage['en_GB.utf8']['vclub_title'] = 'Video On Demand';
	$rLanguage['en_GB.utf8']['ears_back'] = '<br>B<br>A<br>C<br>K<br>';
	$rLanguage['en_GB.utf8']['ears_about_movie'] = '<br>A<br>B<br>O<br>U<br>T<br><br>M<br>O<br>V<br>I<br>E<br>';
	$rLanguage['en_GB.utf8']['ears_tv_guide'] = '<br>T<br>V<br><br>G<br>U<br>I<br>D<br>E<br>';
	$rLanguage['en_GB.utf8']['ears_about_package'] = '<br>P<br>A<br>C<br>K<br>A<br>G<br>E<br><br>I<br>N<br>F<br>O<br>';
	$rLanguage['en_GB.utf8']['internet'] = 'internet';
	$rLanguage['en_GB.utf8']['play_all'] = 'Play all';
	$rLanguage['en_GB.utf8']['on'] = 'on';
	$rLanguage['en_GB.utf8']['off'] = 'off';
}

switch ($rReqType) {
case 'stb':
	switch ($rReqAction) {
	case 'get_profile':
		$rTotal = ($rAuthenticated ? array_merge($rProfile, $rDevice['get_profile_vars']) : $rProfile);
		$rTotal['status'] = (int) !$rAuthenticated;
		$rTotal['update_url'] = (empty(XCMS::$rSettings['update_url']) ? '' : XCMS::$rSettings['update_url']);
		$rTotal['test_download_url'] = (empty(XCMS::$rSettings['test_download_url']) ? '' : XCMS::$rSettings['test_download_url']);
		$rTotal['default_timezone'] = XCMS::$rSettings['default_timezone'];
		$rTotal['default_locale'] = $rDevice['locale'];
		$rTotal['allowed_stb_types'] = XCMS::$rSettings['allowed_stb_types'];
		$rTotal['allowed_stb_types_for_local_recording'] = XCMS::$rSettings['allowed_stb_types'];
		$rTotal['storages'] = [];
		$rTotal['tv_channel_default_aspect'] = (empty(XCMS::$rSettings['tv_channel_default_aspect']) ? 'fit' : XCMS::$rSettings['tv_channel_default_aspect']);
		$rTotal['playback_limit'] = (empty(XCMS::$rSettings['playback_limit']) ? false : (int) XCMS::$rSettings['playback_limit']);

		if (empty($rTotal['playback_limit'])) {
			$rTotal['enable_playback_limit'] = false;
		}

		$rTotal['show_tv_channel_logo'] = !empty(XCMS::$rSettings['show_tv_channel_logo']);
		$rTotal['show_channel_logo_in_preview'] = !empty(XCMS::$rSettings['show_channel_logo_in_preview']);
		$rTotal['enable_connection_problem_indication'] = !empty(XCMS::$rSettings['enable_connection_problem_indication']);
		$rTotal['hls_fast_start'] = '1';
		$rTotal['check_ssl_certificate'] = 0;
		$rTotal['enable_buffering_indication'] = 1;
		$rTotal['watchdog_timeout'] = mt_rand(80, 120);
		if (empty($rTotal['aspect']) && (XCMS::$rServers[SERVER_ID]['server_protocol'] == 'https')) {
			$rTotal['aspect'] = '16';
		}

		exit(json_encode(['js' => $rTotal], JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'get_localization':
		exit(json_encode(['js' => $rLanguage[$rDevice['locale']]]));
	case 'log':
		exit(json_encode(['js' => true]));
	case 'get_modules':
		$rModules = [
			'all_modules'          => ['media_browser', 'vclub', 'tv', 'sclub', 'radio', 'dvb', 'tv_archive', 'time_shift', 'time_shift_local', 'epg.reminder', 'epg.recorder', 'epg', 'epg.simple', 'downloads_dialog', 'downloads', 'records', 'pvr_local', 'settings.parent', 'settings.localization', 'settings.update', 'settings.playback', 'settings.common', 'settings.network_status', 'settings', 'account', 'internet', 'logout', 'account_menu'],
			'switchable_modules'   => ['sclub', 'vlub'],
			'disabled_modules'     => ['records', 'downloads', 'settings.update', 'settings.common', 'pvr_local', 'media_browser'],
			'restricted_modules'   => [],
			'template'             => $rTheme,
			'launcher_url'         => '',
			'launcher_profile_url' => ''
		];
		exit(json_encode(['js' => $rModules]));
	}

	break;
}

if (!$rAuthenticated) {
	if (($rReqType == 'stb') && ($rReqAction == 'get_profile')) {
		XCMS::checkBruteforce($rIP, $rMAC);
		XCMS::checkFlood();
	}

	exit();
}

$rDevice['mag_player'] = trim($rDevice['mag_player'], '\'"');
$rPlayer = (!empty($rDevice['mag_player']) ? $rDevice['mag_player'] . ' ' : 'ffmpeg ');

switch ($rReqType) {
case 'stb':
	switch ($rReqAction) {
	case 'set_modern':
		$rDevice['theme_type'] = 0;
		$db->query('UPDATE `mag_devices` SET `theme_type` = 0 WHERE `mag_id` = ?', $rDevice['mag_id']);
		updateCache();
		exit(json_encode(['data' => true]));
	case 'set_legacy':
		$rDevice['theme_type'] = 1;
		$db->query('UPDATE `mag_devices` SET `theme_type` = 1 WHERE `mag_id` = ?', $rDevice['mag_id']);
		updateCache();
		exit(json_encode(['data' => true]));
	case 'get_preload_images':
		$rMode = (is_numeric($rGMode) ? 'i_' . $rGMode : 'i');
		$rImages = ['template/' . $rTheme . '/' . $rMode . '/alert_triangle.png', 'template/' . $rTheme . '/' . $rMode . '/archive.png', 'template/' . $rTheme . '/' . $rMode . '/archive_white.png', 'template/' . $rTheme . '/' . $rMode . '/bg.png', 'template/' . $rTheme . '/' . $rMode . '/bg2.png', 'template/' . $rTheme . '/' . $rMode . '/ears_arrow_l.png', 'template/' . $rTheme . '/' . $rMode . '/ears_arrow_r.png', 'template/' . $rTheme . '/' . $rMode . '/hd.png', 'template/' . $rTheme . '/' . $rMode . '/hd_white.png', 'template/' . $rTheme . '/' . $rMode . '/mb_prev_bg.png', 'template/' . $rTheme . '/' . $rMode . '/mm_hor_surround.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_account.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_default.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_internet.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_mb.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_radio.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_setting.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_tv.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_video.png', 'template/' . $rTheme . '/' . $rMode . '/mm_ico_youtube.png', 'template/' . $rTheme . '/' . $rMode . '/left_white.png', 'template/' . $rTheme . '/' . $rMode . '/logo.png', 'template/' . $rTheme . '/' . $rMode . '/play.png', 'template/' . $rTheme . '/' . $rMode . '/play_white.png', 'template/' . $rTheme . '/' . $rMode . '/rec.png', 'template/' . $rTheme . '/' . $rMode . '/rec_white.png', 'template/' . $rTheme . '/' . $rMode . '/right_white.png', 'template/' . $rTheme . '/' . $rMode . '/star.png', 'template/' . $rTheme . '/' . $rMode . '/star_white.png', 'template/' . $rTheme . '/' . $rMode . '/tv_prev_bg.png', 'template/' . $rTheme . '/' . $rMode . '/volume_bar.png', 'template/' . $rTheme . '/' . $rMode . '/volume_bg.png', 'template/' . $rTheme . '/' . $rMode . '/volume_off.png'];
		exit(json_encode(['js' => $rImages]));
	case 'get_settings_profile':
		$db->query('SELECT * FROM `mag_devices` WHERE `mag_id` = ?', $rDevice['mag_id']);
		$rInfo = $db->get_row();
		$rSettings = [
			'js' => [
			'modules' => [
			['name' => 'lock'],
			['name' => 'lang'],
			['name' => 'update'],
			[
			'name' => 'net_info',
			'sub'  => [
			['name' => 'wired'],
			[
			'name' => 'pppoe',
			'sub'  => [
			['name' => 'dhcp'],
			['name' => 'dhcp_manual'],
			['name' => 'disable']
		]
		],
			['name' => 'wireless'],
			['name' => 'speed']
		]
		],
			['name' => 'video'],
			['name' => 'audio'],
			[
			'name' => 'net',
			'sub'  => [
			[
			'name' => 'ethernet',
			'sub'  => [
			['name' => 'dhcp'],
			['name' => 'dhcp_manual'],
			['name' => 'manual'],
			['name' => 'no_ip']
		]
		],
			[
			'name' => 'pppoe',
			'sub'  => [
			['name' => 'dhcp'],
			['name' => 'dhcp_manual'],
			['name' => 'disable']
		]
		],
			[
			'name' => 'wifi',
			'sub'  => [
			['name' => 'dhcp'],
			['name' => 'dhcp_manual'],
			['name' => 'manual']
		]
		],
			['name' => 'speed']
		]
		],
			['name' => 'advanced'],
			['name' => 'dev_info'],
			['name' => 'reload'],
			['name' => 'internal_portal'],
			['name' => 'reboot']
		]
		]
		];
		$rSettings['js']['parent_password'] = $rInfo['parent_password'];
		$rSettings['js']['update_url'] = XCMS::$rSettings['update_url'];
		$rSettings['js']['test_download_url'] = XCMS::$rSettings['test_download_url'];
		$rSettings['js']['playback_buffer_size'] = $rInfo['playback_buffer_size'];
		$rSettings['js']['screensaver_delay'] = $rInfo['screensaver_delay'];
		$rSettings['js']['plasma_saving'] = $rInfo['plasma_saving'];
		$rSettings['js']['spdif_mode'] = $rInfo['spdif_mode'];
		$rSettings['js']['ts_enabled'] = $rInfo['ts_enabled'];
		$rSettings['js']['ts_enable_icon'] = $rInfo['ts_enable_icon'];
		$rSettings['js']['ts_path'] = $rInfo['ts_path'];
		$rSettings['js']['ts_max_length'] = $rInfo['ts_max_length'];
		$rSettings['js']['ts_buffer_use'] = $rInfo['ts_buffer_use'];
		$rSettings['js']['ts_action_on_exit'] = $rInfo['ts_action_on_exit'];
		$rSettings['js']['ts_delay'] = $rInfo['ts_delay'];
		$rSettings['js']['hdmi_event_reaction'] = $rInfo['hdmi_event_reaction'];
		$rSettings['js']['pri_audio_lang'] = $rProfile['pri_audio_lang'];
		$rSettings['js']['show_after_loading'] = $rInfo['show_after_loading'];
		$rSettings['js']['sec_audio_lang'] = $rProfile['sec_audio_lang'];

		if (XCMS::$rSettings['always_enabled_subtitles'] == 1) {
			$rSettings['js']['pri_subtitle_lang'] = $rProfile['pri_subtitle_lang'];
			$rSettings['js']['sec_subtitle_lang'] = $rProfile['sec_subtitle_lang'];
		}
		else {
			$rSettings['js']['pri_subtitle_lang'] = $rSettings['js']['sec_subtitle_lang'] = '';
		}

		exit(json_encode($rSettings));
	case 'get_locales':
		$db->query('SELECT `locale` FROM `mag_devices` WHERE `mag_id` = ?', $rDevice['mag_id']);
		$rSelected = $db->get_row();
		$rOutput = [];

		foreach ($rLocales['get_locales'] as $country => $code) {
			$rSelected = ($code == $rSelected['locale'] ? 1 : 0);
			$rOutput[] = ['label' => $country, 'value' => $code, 'selected' => $rSelected];
		}

		exit(json_encode(['js' => $rOutput]));
	case 'get_tv_aspects':
		if (!empty($rDevice['aspect'])) {
			exit($rDevice['aspect']);
		}
		else {
			exit(json_encode($rDevice['aspect']));
		}
	case 'set_volume':
		$rVolume = XCMS::$rRequest['vol'];

		if (!empty($rVolume)) {
			$rDevice['volume'] = $rVolume;
			$db->query('UPDATE `mag_devices` SET `volume` = ? WHERE `mag_id` = ?', $rVolume, $rDevice['mag_id']);
			updateCache();
			exit(json_encode(['data' => true]));
		}

		break;
	case 'set_aspect':
		$rChannelID = XCMS::$rRequest['ch_id'];
		$rAspect = XCMS::$rRequest['aspect'];
		$rDeviceAspect = $rDevice['aspect'];

		if (empty($rDeviceAspect)) {
			$rDevice['aspect'] = [
				'js' => [$rChannelID => $rAspect]
			];
			$db->query('UPDATE `mag_devices` SET `aspect` = ? WHERE mag_id = ?', json_encode([
				'js' => [$rChannelID => $rAspect]
			]), $rDevice['mag_id']);
		}
		else {
			$rDeviceAspect = json_decode($rDeviceAspect, true);
			$rDeviceAspect['js'][$rChannelID] = $rAspect;
			$rDevice['aspect'] = $rDeviceAspect;
			$db->query('UPDATE `mag_devices` SET `aspect` = ? WHERE mag_id = ?', json_encode($rDeviceAspect), $rDevice['mag_id']);
		}

		updateCache();
		exit(json_encode(['js' => true]));
	case 'set_stream_error':
		exit(json_encode(['js' => true]));
	case 'set_screensaver_delay':
		if (!empty($_SERVER['HTTP_COOKIE'])) {
			$rDelay = (int) XCMS::$rRequest['screensaver_delay'];
			$rDevice['screensaver_delay'] = $rDelay;
			$db->query('UPDATE `mag_devices` SET `screensaver_delay` = ? WHERE `mag_id` = ?', $rDelay, $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'set_playback_buffer':
		if (!empty($_SERVER['HTTP_COOKIE'])) {
			$rBufferBytes = (int) XCMS::$rRequest['playback_buffer_bytes'];
			$rBufferSize = (int) XCMS::$rRequest['playback_buffer_size'];
			$rDevice['playback_buffer_bytes'] = $rBufferBytes;
			$rDevice['playback_buffer_size'] = $rBufferSize;
			$db->query('UPDATE `mag_devices` SET `playback_buffer_bytes` = ? , `playback_buffer_size` = ? WHERE `mag_id` = ?', $rBufferBytes, $rBufferSize, $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'set_plasma_saving':
		$rPlasmaSaving = (int) XCMS::$rRequest['plasma_saving'];
		$rDevice['plasma_saving'] = $rPlasmaSaving;
		$db->query('UPDATE `mag_devices` SET `plasma_saving` = ? WHERE `mag_id` = ?', $rPlasmaSaving, $rDevice['mag_id']);
		updateCache();
		exit(json_encode(['js' => true]));
	case 'set_parent_password':
		if (isset(XCMS::$rRequest['parent_password']) && isset(XCMS::$rRequest['pass']) && isset(XCMS::$rRequest['repeat_pass']) && (XCMS::$rRequest['pass'] == XCMS::$rRequest['repeat_pass'])) {
			$rDevice['parent_password'] = XCMS::$rRequest['pass'];
			$db->query('UPDATE `mag_devices` SET `parent_password` = ? WHERE `mag_id` = ?', XCMS::$rRequest['pass'], $rDevice['mag_id']);
			updateCache();
			exit(json_encode(['js' => true]));
		}
		else {
			exit(json_encode(['js' => true]));
		}
	case 'set_locale':
		if (!empty(XCMS::$rRequest['locale'])) {
			$rDevice['locale'] = XCMS::$rRequest['locale'];
			$db->query('UPDATE `mag_devices` SET `locale` = ? WHERE `mag_id` = ?', XCMS::$rRequest['locale'], $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode([
			'js' => []
		]));
	case 'set_hdmi_reaction':
		if (!empty($_SERVER['HTTP_COOKIE']) && isset(XCMS::$rRequest['data'])) {
			$rReaction = XCMS::$rRequest['data'];
			$rDevice['hdmi_event_reaction'] = $rReaction;
			$db->query('UPDATE `mag_devices` SET `hdmi_event_reaction` = ? WHERE `mag_id` = ?', $rReaction, $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	}

	break;
case 'watchdog':
	$rDevice['last_watchdog'] = time();
	$db->query('UPDATE `mag_devices` SET `last_watchdog` = ? WHERE `mag_id` = ?', time(), $rDevice['mag_id']);
	updateCache();

	switch ($rReqAction) {
	case 'get_events':
		$db->query('SELECT * FROM `mag_events` WHERE `mag_device_id` = ? AND `status` = 0 ORDER BY `id` ASC LIMIT 1', $rDevice['mag_id']);
		$rData = [
			'data' => ['msgs' => 0, 'additional_services_on' => 1]
		];

		if (0 < $db->num_rows()) {
			$rEvents = $db->get_row();
			$db->query('SELECT count(*) FROM `mag_events` WHERE `mag_device_id` = ? AND `status` = 0 ', $rDevice['mag_id']);
			$rMessages = $db->get_col();
			$rData = [
				'data' => [
				'msgs'                   => $rMessages,
				'id'                     => $rEvents['id'],
				'event'                  => $rEvents['event'],
				'need_confirm'           => $rEvents['need_confirm'],
				'msg'                    => $rEvents['msg'],
				'reboot_after_ok'        => $rEvents['reboot_after_ok'],
				'auto_hide_timeout'      => $rEvents['auto_hide_timeout'],
				'send_time'              => date('d-m-Y H:i:s', $rEvents['send_time']),
				'additional_services_on' => $rEvents['additional_services_on'],
				'updated'                => ['anec' => $rEvents['anec'], 'vclub' => $rEvents['vclub']]
			]
			];
			$rAutoStatus = ['reboot', 'reload_portal', 'play_channel', 'cut_off'];

			if (in_array($rEvents['event'], $rAutoStatus)) {
				$db->query('UPDATE `mag_events` SET `status` = 1 WHERE `id` = ?', $rEvents['id']);
			}
		}

		exit(json_encode(['js' => $rData], JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'confirm_event':
		if (!empty(XCMS::$rRequest['event_active_id'])) {
			$rActiveID = XCMS::$rRequest['event_active_id'];
			$db->query('UPDATE `mag_events` SET `status` = 1 WHERE `id` = ?', $rActiveID);
			exit(json_encode([
				'js' => ['data' => 'ok']
			]));
		}

		break;
	}

	break;
case 'audioclub':
	switch ($rReqAction) {
	case 'get_categories':
		$rOutput = [];
		$rOutput['js'] = [];

		if (XCMS::$rSettings['show_all_category_mag'] == 1) {
			$rOutput['js'][] = ['id' => '*', 'title' => 'All', 'alias' => '*', 'censored' => 0];
		}

		foreach (XCMS::$rCategories as $rCategoryID => $rCategory) {
			if ($rCategory['category_type'] != 'movie') {
				continue;
			}

			if (!in_array($rCategory['id'], $rDevice['category_ids'])) {
				continue;
			}

			$rOutput['js'][] = ['id' => $rCategory['id'], 'title' => $rCategory['category_name'], 'alias' => $rCategory['category_name'], 'censored' => (int) $rCategory['is_adult']];
		}

		exit(json_encode($rOutput));
	}

	break;
case 'itv':
	switch ($rReqAction) {
	case 'create_link':
		$rCommand = XCMS::$rRequest['cmd'];
		$rValue = 'http://localhost/ch/';
		list($rStreamID, $rStreamValue) = explode('_', substr($rCommand, strpos($rCommand, $rValue) + strlen($rValue)));

		if (empty($rStreamValue)) {
			$rEncData = 'ministra::live/' . $rDevice['username'] . '/' . $rDevice['password'] . '/' . $rStreamID . '/' . XCMS::$rSettings['mag_container'] . ('/' . $rDevice['token']);
			$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
			$rURL = $rPlayer . (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken);

			if (XCMS::$rSettings['mag_keep_extension']) {
				$rURL .= '?ext=.' . XCMS::$rSettings['mag_container'];
			}
		}
		else {
			$rURL = $rPlayer . $rStreamValue;
		}

		exit(json_encode([
			'js'          => ['id' => $rStreamID, 'cmd' => $rURL],
			'streamer_id' => 0,
			'link_id'     => 0,
			'load'        => 0,
			'error'       => ''
		]));
	case 'set_claim':
		if (!empty(XCMS::$rRequest['id']) && !empty(XCMS::$rRequest['real_type'])) {
			$rID = (int) XCMS::$rRequest['id'];
			$rRealType = XCMS::$rRequest['real_type'];
			$rDate = date('Y-m-d H:i:s');
			$db->query('INSERT INTO `mag_claims` (`stream_id`,`mag_id`,`real_type`,`date`) VALUES(?,?,?,?)', $rID, $rDevice['mag_id'], $rRealType, $rDate);
		}

		exit(json_encode(['js' => true]));
	case 'set_fav':
		$rChannels = (empty(XCMS::$rRequest['fav_ch']) ? '' : XCMS::$rRequest['fav_ch']);
		$rChannels = array_filter(array_map('intval', explode(',', $rChannels)));
		$rDevice['fav_channels']['live'] = $rChannels;
		$db->query('UPDATE `mag_devices` SET `fav_channels` = ? WHERE `mag_id` = ?', json_encode($rDevice['fav_channels']), $rDevice['mag_id']);
		updateCache();
		exit(json_encode(['js' => true]));
	case 'get_fav_ids':
		exit(json_encode(['js' => $rDevice['fav_channels']['live']]));
	case 'get_all_channels':
		$rGenre = (empty(XCMS::$rRequest['genre']) || !is_numeric(XCMS::$rRequest['genre']) ? NULL : (int) XCMS::$rRequest['genre']);
		exit(getStreams($rGenre, true));
	case 'get_ordered_list':
		$rFav = (!empty(XCMS::$rRequest['fav']) ? 1 : NULL);
		$rSortBy = (!empty(XCMS::$rRequest['sortby']) ? XCMS::$rRequest['sortby'] : NULL);
		$rGenre = (empty(XCMS::$rRequest['genre']) || !is_numeric(XCMS::$rRequest['genre']) ? NULL : (int) XCMS::$rRequest['genre']);
		$rSearch = (!empty(XCMS::$rRequest['search']) ? XCMS::$rRequest['search'] : NULL);
		exit(getStreams($rGenre, false, $rFav, $rSortBy, $rSearch));
	case 'get_all_fav_channels':
		$rGenre = (empty(XCMS::$rRequest['genre']) || !is_numeric(XCMS::$rRequest['genre']) ? NULL : (int) XCMS::$rRequest['genre']);
		exit(getStreams($rGenre, true, 1));
	case 'get_epg_info':
		exit(json_encode([
			'js' => [
			'data' => []
		]
		], JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'get_short_epg':
		if (!empty(XCMS::$rRequest['ch_id'])) {
			$rChannelID = XCMS::$rRequest['ch_id'];
			$rEPG = [
				'js' => []
			];
			$rTime = time();
			$rEPGData = [];

			if (file_exists(EPG_PATH . 'stream_' . (int) $rChannelID)) {
				$rRows = igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rChannelID));

				foreach ($rRows as $rRow) {
					if ((($rRow['start'] <= $rTime) && ($rTime <= $rRow['end'])) || ($rTime <= $rRow['start'])) {
						$rRow['start_timestamp'] = $rRow['start'];
						$rRow['stop_timestamp'] = $rRow['end'];
						$rEPGData[] = $rRow;
					}
				}
			}

			if (!empty($rEPGData)) {
				$rTimeDifference = XCMS::getDiffTimezone($rTimezone) ?: 0;
				$i = 0;

				for ($n = 0; $n < count($rEPGData); $n++) {
					if ($rEPGData[$n]['end'] < time()) {
						continue;
					}

					$rStartTime = new DateTime();
					$rStartTime->setTimestamp($rEPGData[$n]['start']);
					$rStartTime->modify($rTimeDifference . ' seconds');
					$rEndTime = new DateTime();
					$rEndTime->setTimestamp($rEPGData[$n]['end']);
					$rEndTime->modify($rTimeDifference . ' seconds');
					$rEPG['js'][$i]['id'] = $rEPGData[$n]['id'];
					$rEPG['js'][$i]['ch_id'] = $rChannelID;
					$rEPG['js'][$i]['correct'] = $rStartTime->format('Y-m-d H:i:s');
					$rEPG['js'][$i]['time'] = $rStartTime->format('Y-m-d H:i:s');
					$rEPG['js'][$i]['time_to'] = $rEndTime->format('Y-m-d H:i:s');
					$rEPG['js'][$i]['duration'] = $rEPGData[$n]['stop_timestamp'] - $rEPGData[$n]['start_timestamp'];
					$rEPG['js'][$i]['name'] = $rEPGData[$n]['title'];
					$rEPG['js'][$i]['descr'] = $rEPGData[$n]['description'];
					$rEPG['js'][$i]['real_id'] = $rChannelID . '_' . $rEPGData[$n]['start_timestamp'];
					$rEPG['js'][$i]['category'] = '';
					$rEPG['js'][$i]['director'] = '';
					$rEPG['js'][$i]['actor'] = '';
					$rEPG['js'][$i]['start_timestamp'] = $rStartTime->getTimestamp();
					$rEPG['js'][$i]['stop_timestamp'] = $rEndTime->getTimestamp();
					$rEPG['js'][$i]['t_time'] = $rStartTime->format('H:i');
					$rEPG['js'][$i]['t_time_to'] = $rEndTime->format('H:i');
					$rEPG['js'][$i]['mark_memo'] = 0;
					$rEPG['js'][$i]['mark_archive'] = 0;

					if (count($rEPG['js']) == (int) XCMS::$rRequest['size'] ?: 4) {
						break;
					}

					$i++;
				}
			}
		}

		exit(json_encode($rEPG, JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'set_last_id':
		$rChannelID = (int) XCMS::$rRequest['id'];

		if (0 < $rChannelID) {
			$rDevice['last_itv_id'] = $rChannelID;
			$db->query('UPDATE `mag_devices` SET `last_itv_id` = ? WHERE `mag_id` = ?', $rChannelID, $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'get_genres':
		$rOutput = [];
		$rNumber = 1;

		if (XCMS::$rSettings['show_all_category_mag'] == 1) {
			$rOutput['js'][] = ['id' => '*', 'title' => 'All', 'alias' => 'All', 'active_sub' => true, 'censored' => 0];
		}

		foreach (XCMS::$rCategories as $rCategoryID => $rCategory) {
			if ($rCategory['category_type'] != 'live') {
				continue;
			}

			if (!in_array($rCategory['id'], $rDevice['category_ids'])) {
				continue;
			}

			$rOutput['js'][] = ['id' => $rCategory['id'], 'title' => $rCategory['category_name'], 'modified' => '', 'number' => $rNumber++, 'alias' => strtolower($rCategory['category_name']), 'censored' => (int) $rCategory['is_adult']];
		}

		exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
	}

	break;
case 'vod':
	switch ($rReqAction) {
	case 'set_claim':
		if (!empty(XCMS::$rRequest['id']) && !empty(XCMS::$rRequest['real_type'])) {
			$rID = (int) XCMS::$rRequest['id'];
			$rRealType = XCMS::$rRequest['real_type'];
			$rDate = date('Y-m-d H:i:s');
			$db->query('INSERT INTO `mag_claims` (`stream_id`,`mag_id`,`real_type`,`date`) VALUES(?,?,?,?)', $rID, $rDevice['mag_id'], $rRealType, $rDate);
		}

		exit(json_encode(['js' => true]));
	case 'set_fav':
		if (!empty(XCMS::$rRequest['video_id'])) {
			$rVideoID = (int) XCMS::$rRequest['video_id'];

			if (!in_array($rVideoID, $rDevice['fav_channels']['movie'])) {
				$rDevice['fav_channels']['movie'][] = $rVideoID;
			}

			$db->query('UPDATE `mag_devices` SET `fav_channels` = ? WHERE `mag_id` = ?', json_encode($rDevice['fav_channels']), $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'del_fav':
		if (!empty(XCMS::$rRequest['video_id'])) {
			$rVideoID = (int) XCMS::$rRequest['video_id'];

			foreach ($rDevice['fav_channels']['movie'] as $rKey => $rValue) {
				if ($rValue == $rVideoID) {
					unset($rDevice['fav_channels']['movie'][$rKey]);
					break;
				}
			}

			$db->query('UPDATE `mag_devices` SET `fav_channels` = ? WHERE `mag_id` = ?', json_encode($rDevice['fav_channels']), $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'get_categories':
		$rOutput = [];
		$rOutput['js'] = [];

		if (XCMS::$rSettings['show_all_category_mag'] == 1) {
			$rOutput['js'][] = ['id' => '*', 'title' => 'All', 'alias' => '*', 'censored' => 0];
		}

		foreach (XCMS::$rCategories as $rCategoryID => $rCategory) {
			if ($rCategory['category_type'] != 'movie') {
				continue;
			}

			if (!in_array($rCategory['id'], $rDevice['category_ids'])) {
				continue;
			}

			$rOutput['js'][] = ['id' => $rCategory['id'], 'title' => $rCategory['category_name'], 'alias' => $rCategory['category_name'], 'censored' => (int) $rCategory['is_adult']];
		}

		exit(json_encode($rOutput));
	case 'get_genres_by_category_alias':
		$rOutput = [];
		$rOutput['js'][] = ['id' => '*', 'title' => '*'];

		foreach (XCMS::$rCategories as $rCategoryID => $rCategory) {
			if ($rCategory['category_type'] != 'movie') {
				continue;
			}

			if (!in_array($rCategory['id'], $rDevice['category_ids'])) {
				continue;
			}

			$rOutput['js'][] = ['id' => $rCategory['id'], 'title' => $rCategory['category_name']];
		}

		exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'get_years':
		exit(json_encode($rMagData['get_years']));
	case 'get_ordered_list':
		$rCategory = (!empty(XCMS::$rRequest['category']) && is_numeric(XCMS::$rRequest['category']) ? XCMS::$rRequest['category'] : NULL);
		$rFav = (!empty(XCMS::$rRequest['fav']) ? 1 : NULL);
		$rSortBy = (!empty(XCMS::$rRequest['sortby']) ? XCMS::$rRequest['sortby'] : 'added');
		$rSearch = (!empty(XCMS::$rRequest['search']) ? XCMS::$rRequest['search'] : NULL);
		$rPicking = [];
		$rPicking['abc'] = (!empty(XCMS::$rRequest['abc']) ? XCMS::$rRequest['abc'] : '*');
		$rPicking['genre'] = (!empty(XCMS::$rRequest['genre']) ? XCMS::$rRequest['genre'] : '*');
		$rPicking['years'] = (!empty(XCMS::$rRequest['years']) ? XCMS::$rRequest['years'] : '*');
		exit(getMovies($rCategory, $rFav, $rSortBy, $rSearch, $rPicking));
	case 'create_link':
		$rCommand = XCMS::$rRequest['cmd'];
		$rSeries = (!empty(XCMS::$rRequest['series']) ? (int) XCMS::$rRequest['series'] : 0);
		$rError = '';

		if (!stristr($rCommand, '/media/')) {
			$rCommand = json_decode(base64_decode($rCommand), true);
		}
		else {
			$rCommand = ['series_data' => $rCommand, 'type' => 'series'];
		}

		if ($rSeries) {
			$rCommand['type'] = 'series';
		}

		$rValid = false;

		switch ($rCommand['type']) {
		case 'movie':
			$rValid = in_array($rCommand['stream_id'], $rDevice['vod_ids']);
			break;
		case 'series':
			if (!empty($rCommand['series_data'])) {
				list($rCommand['series_id'], $rCommand['season_num']) = explode(':', basename($rCommand['series_data'], '.mpg'));
			}

			$db->query('SELECT t1.stream_id,t2.target_container FROM `streams_episodes` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id WHERE t1.`series_id` = ? AND t1.`season_num` = ? ORDER BY `episode_num` ASC LIMIT ' . (int) ($rSeries - 1) . ', 1', $rCommand['series_id'], $rCommand['season_num']);

			if (0 < $db->num_rows()) {
				$rRow = $db->get_row();
				$rCommand['stream_id'] = $rRow['stream_id'];
				$rCommand['target_container'] = $rRow['target_container'];
				$rValid = in_array($rCommand['series_id'], $rDevice['series_ids']);
				break;
			}

			$rError = 'player_file_missing';
		}

		$rEncData = 'ministra::' . $rCommand['type'] . '/' . $rDevice['username'] . '/' . $rDevice['password'] . '/' . $rCommand['stream_id'] . '/' . $rCommand['target_container'] . '/' . $rDevice['token'];
		$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
		$rURL = (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken);

		if (XCMS::$rSettings['mag_keep_extension']) {
			$rURL .= '?ext=.' . $rCommand['target_container'];
		}

		$rOutput = [
			'js' => [
			'id'        => $rCommand['stream_id'],
			'cmd'       => $rPlayer . $rURL,
			'load'      => '',
			'subtitles' => [],
			'error'     => $rError
		]
		];
		exit(json_encode($rOutput));
	case 'get_abc':
		exit(json_encode($rMagData['get_abc']));
	}

	break;
case 'series':
	switch ($rReqAction) {
	case 'set_claim':
		if (!empty(XCMS::$rRequest['id']) && !empty(XCMS::$rRequest['real_type'])) {
			$rID = (int) XCMS::$rRequest['id'];
			$rRealType = XCMS::$rRequest['real_type'];
			$rDate = date('Y-m-d H:i:s');
			$db->query('INSERT INTO `mag_claims` (`stream_id`,`mag_id`,`real_type`,`date`) VALUES(?,?,?,?)', $rID, $rDevice['mag_id'], $rRealType, $rDate);
		}

		exit(json_encode(['js' => true]));
	case 'set_fav':
		if (!empty(XCMS::$rRequest['video_id'])) {
			$rVideoID = (int) XCMS::$rRequest['video_id'];

			if (!in_array($rVideoID, $rDevice['fav_channels']['series'])) {
				$rDevice['fav_channels']['series'][] = $rVideoID;
			}

			$db->query('UPDATE `mag_devices` SET `fav_channels` = ? WHERE `mag_id` = ?', json_encode($rDevice['fav_channels']), $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'del_fav':
		if (!empty(XCMS::$rRequest['video_id'])) {
			$rVideoID = (int) XCMS::$rRequest['video_id'];

			foreach ($rDevice['fav_channels']['series'] as $rKey => $rValue) {
				if ($rValue == $rVideoID) {
					unset($rDevice['fav_channels']['series'][$rKey]);
					break;
				}
			}

			$db->query('UPDATE `mag_devices` SET `fav_channels` = ? WHERE `mag_id` = ?', json_encode($rDevice['fav_channels']), $rDevice['mag_id']);
			updateCache();
		}

		exit(json_encode(['js' => true]));
	case 'get_categories':
		$rOutput = [];
		$rOutput['js'] = [];

		if (XCMS::$rSettings['show_all_category_mag'] == 1) {
			$rOutput['js'][] = ['id' => '*', 'title' => 'All', 'alias' => '*', 'censored' => 0];
		}

		foreach (XCMS::$rCategories as $rCategoryID => $rCategory) {
			if ($rCategory['category_type'] != 'series') {
				continue;
			}

			if (!in_array($rCategory['id'], $rDevice['category_ids'])) {
				continue;
			}

			$rOutput['js'][] = ['id' => $rCategory['id'], 'title' => $rCategory['category_name'], 'alias' => $rCategory['category_name'], 'censored' => (int) $rCategory['is_adult']];
		}

		exit(json_encode($rOutput));
	case 'get_genres_by_category_alias':
		$rOutput = [];
		$rOutput['js'][] = ['id' => '*', 'title' => '*'];

		foreach (XCMS::$rCategories as $rCategoryID => $rCategory) {
			if ($rCategory['category_type'] != 'series') {
				continue;
			}

			if (!in_array($rCategory['id'], $rDevice['category_ids'])) {
				continue;
			}

			$rOutput['js'][] = ['id' => $rCategory['id'], 'title' => $rCategory['category_name']];
		}

		exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'get_years':
		exit(json_encode($rMagData['get_years']));
	case 'get_ordered_list':
		$rCategory = (!empty(XCMS::$rRequest['category']) && is_numeric(XCMS::$rRequest['category']) ? XCMS::$rRequest['category'] : NULL);
		$rFav = (!empty(XCMS::$rRequest['fav']) ? 1 : NULL);
		$rSortBy = (!empty(XCMS::$rRequest['sortby']) ? XCMS::$rRequest['sortby'] : 'added');
		$rSearch = (!empty(XCMS::$rRequest['search']) ? XCMS::$rRequest['search'] : NULL);
		$rMovieID = (!empty(XCMS::$rRequest['movie_id']) ? (int) XCMS::$rRequest['movie_id'] : NULL);
		$rPicking = [];
		$rPicking['abc'] = (!empty(XCMS::$rRequest['abc']) ? XCMS::$rRequest['abc'] : '*');
		$rPicking['genre'] = (!empty(XCMS::$rRequest['genre']) ? XCMS::$rRequest['genre'] : '*');
		$rPicking['years'] = (!empty(XCMS::$rRequest['years']) ? XCMS::$rRequest['years'] : '*');
		exit(getSeries($rMovieID, $rCategory, $rFav, $rSortBy, $rSearch, $rPicking));
	case 'get_abc':
		exit(json_encode($rMagData['get_abc']));
	}

	break;
case 'account_info':
	switch ($rReqAction) {
	case 'get_main_info':
		if (empty($rDevice['exp_date'])) {
			$rExpiry = 'Unlimited';
		}
		else {
			$rExpiry = date('F j, Y, g:i a', $rDevice['exp_date']);
		}

		exit(json_encode([
			'js' => ['mac' => $rMAC, 'phone' => $rExpiry, 'message' => htmlspecialchars_decode(str_replace("\n", '<br/>', XCMS::$rSettings['mag_message']))]
		]));
	}

	break;
case 'radio':
	switch ($rReqAction) {
	case 'get_ordered_list':
		$rFav = (!empty(XCMS::$rRequest['fav']) ? 1 : NULL);
		$rSortBy = (!empty(XCMS::$rRequest['sortby']) ? XCMS::$rRequest['sortby'] : 'added');
		exit(getStations(NULL, $rFav, $rSortBy));
	case 'get_all_fav_radio':
		exit(getStations(NULL, 1, NULL));
	case 'set_fav':
		$rFav_radio = (empty(XCMS::$rRequest['fav_radio']) ? '' : XCMS::$rRequest['fav_radio']);
		$rFav_radio = array_filter(array_map('intval', explode(',', $rFav_radio)));
		$rDevice['fav_channels']['radio_streams'] = $rFav_radio;
		$db->query('UPDATE `mag_devices` SET `fav_channels` = ? WHERE `mag_id` = ?', json_encode($rDevice['fav_channels']), $rDevice['mag_id']);
		updateCache();
		exit(json_encode(['js' => true]));
	case 'get_fav_ids':
		exit(json_encode(['js' => $rDevice['fav_channels']['radio_streams']]));
	}

	break;
case 'tv_archive':
	switch ($rReqAction) {
	case 'get_next_part_url':
		if (!empty(XCMS::$rRequest['id'])) {
			$rID = XCMS::$rRequest['id'];
			$rStreamID = substr($rID, 0, strpos($rID, '_'));
			$rDate = strtotime(substr($rID, strpos($rID, '_') + 1));
			$rRow = getEPG($rStreamID, $rDate, $rDate + 86400)[0] ?: NULL;

			if ($rRow) {
				$rRow = $db->get_row();
				$rProgramStart = $rRow['start'];
				$rDuration = (int) (($rRow['end'] - $rRow['start']) / 60);
				$rTitle = $rRow['title'];
				$rEncData = 'ministra::timeshift/' . $rDevice['username'] . '/' . $rDevice['password'] . '/' . $rDuration . '/' . $rProgramStart . '/' . $rStreamID . '/' . $rDevice['token'];
				$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
				$rURL = (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken . '?&osd_title=' . $rTitle);

				if (XCMS::$rSettings['mag_keep_extension']) {
					$rURL .= '&ext=.ts';
				}

				exit(json_encode(['js' => $rPlayer . $rURL]));
			}
		}

		exit(json_encode(['js' => false]));
	case 'create_link':
		$rCommand = (empty(XCMS::$rRequest['cmd']) ? '' : XCMS::$rRequest['cmd']);
		list($rEPGDataID, $rStreamID) = explode('_', pathinfo($rCommand)['filename']);
		$rRow = getProgramme($rStreamID, $rEPGDataID) ?: NULL;

		if ($rRow) {
			$rStart = $rRow['start'];
			$rDuration = (int) (($rRow['end'] - $rRow['start']) / 60);
			$rEncData = 'ministra::timeshift/' . $rDevice['username'] . '/' . $rDevice['password'] . '/' . $rDuration . '/' . $rStart . '/' . $rStreamID . '/' . $rDevice['token'];
			$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
			$rURL = (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken);

			if (XCMS::$rSettings['mag_keep_extension']) {
				$rURL .= '?ext=.ts';
			}

			$rOutput['js'] = ['id' => 0, 'cmd' => $rPlayer . $rURL, 'storage_id' => '', 'load' => 0, 'error' => '', 'download_cmd' => $rURL, 'to_file' => ''];
			exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
		}

		break;
	case 'get_link_for_channel':
		$rOutput = [];
		$rChannelID = (!empty(XCMS::$rRequest['ch_id']) ? (int) XCMS::$rRequest['ch_id'] : 0);
		$rStart = strtotime(date('Ymd-H'));
		$rEncData = 'ministra::timeshift/' . $rDevice['username'] . '/' . $rDevice['password'] . '/60/' . $rStart . '/' . $rChannelID . '/' . $rDevice['token'];
		$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
		$rURL = (XCMS::$rSettings['mag_disable_ssl'] ? XCMS::$rServers[SERVER_ID]['http_url'] : XCMS::$rServers[SERVER_ID]['site_url']) . ('play/' . $rToken) . (XCMS::$rSettings['mag_keep_extension'] ? '?ext=.ts' : '') . ' position:' . (((int) date('i') * 60) + (int) date('s')) . ' media_len:' . (((int) date('H') * 3600) + ((int) date('i') * 60) + (int) date('s'));
		$rOutput['js'] = ['id' => 0, 'cmd' => $rPlayer . $rURL, 'storage_id' => '', 'load' => 0, 'error' => ''];
		exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
	}

	break;
case 'epg':
	switch ($rReqAction) {
	case 'get_week':
		$k = -16;
		$rEPGWeek = [];
		$rCurDate = strtotime(date('Y-m-d'));

		for ($i = 0; $k < 10; $i++) {
			$rThisDate = $rCurDate + ($k * 86400);
			$rEPGWeek['js'][$i]['f_human'] = date('D d F', $rThisDate);
			$rEPGWeek['js'][$i]['f_mysql'] = date('Y-m-d', $rThisDate);
			$rEPGWeek['js'][$i]['today'] = ($k == 0 ? 1 : 0);
			$k++;
		}

		exit(json_encode($rEPGWeek));
	case 'get_data_table':
		exit(json_encode([
			'js' => []
		], JSON_PARTIAL_OUTPUT_ON_ERROR));
	case 'get_simple_data_table':
		if (!empty(XCMS::$rRequest['ch_id']) && !empty(XCMS::$rRequest['date'])) {
			$rChannelID = XCMS::$rRequest['ch_id'];
			$rReqDate = XCMS::$rRequest['date'];
			$rPage = (int) XCMS::$rRequest['p'];
			$rPageItems = ($rTheme == 'xcms' ? 7 : 10);
			$rDefaultPage = false;
			$rEPGDatas = [];
			$rStartTime = strtotime($rReqDate . ' 00:00:00');
			$rEndTime = strtotime($rReqDate . ' 23:59:59');

			if (file_exists(EPG_PATH . 'stream_' . (int) $rChannelID)) {
				$rRows = igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rChannelID));

				foreach ($rRows as $rRow) {
					if (($rStartTime <= $rRow['start']) && ($rRow['start'] <= $rEndTime)) {
						$rRow['start_timestamp'] = $rRow['start'];
						$rRow['stop_timestamp'] = $rRow['end'];
						$rEPGDatas[] = $rRow;
					}
				}
			}

			if (file_exists(STREAMS_TMP_PATH . 'stream_' . (int) $rChannelID)) {
				$rStreamRow = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rChannelID))['info'];
			}
			else {
				$db->query('SELECT `tv_archive_duration` FROM `streams` WHERE `id` = ?;', XCMS::$rRequest['ch_id']);

				if (0 < $db->num_rows()) {
					$rStreamRow = $db->get_row();
				}
			}

			$rChannelIDx = 0;

			foreach ($rEPGDatas as $rKey => $rEPGData) {
				if (($rEPGData['start_timestamp'] <= time()) && (time() <= $rEPGData['stop_timestamp'])) {
					$rChannelIDx = $rKey + 1;
					break;
				}
			}

			if ($rPage == 0) {
				$rDefaultPage = true;
				$rPage = ceil($rChannelIDx / $rPageItems);

				if ($rPage == 0) {
					$rPage = 1;
				}

				if ($rReqDate != date('Y-m-d')) {
					$rPage = 1;
					$rDefaultPage = false;
				}
			}

			$rProgram = array_slice($rEPGDatas, $rPageItems * ($rPage - 1), $rPageItems);
			$rData = [];
			$rTimeDifference = XCMS::getDiffTimezone($rTimezone);

			for ($i = 0; $i < count($rProgram); $i++) {
				$open = 0;

				if (time() <= $rProgram[$i]['stop_timestamp']) {
					$open = 1;
				}

				$rStartTime = new DateTime();
				$rStartTime->setTimestamp($rProgram[$i]['start']);
				$rStartTime->modify($rTimeDifference . ' seconds');
				$rEndTime = new DateTime();
				$rEndTime->setTimestamp($rProgram[$i]['end']);
				$rEndTime->modify($rTimeDifference . ' seconds');
				$rData[$i]['id'] = $rProgram[$i]['id'] . '_' . $rChannelID;
				$rData[$i]['ch_id'] = $rChannelID;
				$rData[$i]['time'] = $rStartTime->format('Y-m-d H:i:s');
				$rData[$i]['time_to'] = $rEndTime->format('Y-m-d H:i:s');
				$rData[$i]['duration'] = $rProgram[$i]['stop_timestamp'] - $rProgram[$i]['start_timestamp'];
				$rData[$i]['name'] = $rProgram[$i]['title'];
				$rData[$i]['descr'] = $rProgram[$i]['description'];
				$rData[$i]['real_id'] = $rChannelID . '_' . $rProgram[$i]['start'];
				$rData[$i]['category'] = '';
				$rData[$i]['director'] = '';
				$rData[$i]['actor'] = '';
				$rData[$i]['start_timestamp'] = $rStartTime->getTimestamp();
				$rData[$i]['stop_timestamp'] = $rEndTime->getTimestamp();
				$rData[$i]['t_time'] = $rStartTime->format('H:i');
				$rData[$i]['t_time_to'] = $rEndTime->format('H:i');
				$rData[$i]['open'] = $open;
				$rData[$i]['mark_memo'] = 0;
				$rData[$i]['mark_rec'] = 0;
				$rData[$i]['mark_archive'] = (!empty($rStreamRow['tv_archive_duration']) && ($rEndTime->getTimestamp() < time()) && (strtotime('-' . $rStreamRow['tv_archive_duration'] . ' days') <= $rEndTime->getTimestamp()) ? 1 : 0);
			}

			if ($rDefaultPage) {
				$rCurrentPage = $rPage;
				$rSelectedItem = $rChannelIDx - ($rPageItems * ($rPage - 1));
			}
			else {
				$rCurrentPage = 0;
				$rSelectedItem = 0;
			}

			$rOutput = [];
			$rOutput['js']['cur_page'] = $rCurrentPage;
			$rOutput['js']['selected_item'] = $rSelectedItem;
			$rOutput['js']['total_items'] = count($rEPGDatas);
			$rOutput['js']['max_page_items'] = $rPageItems;
			$rOutput['js']['data'] = $rData;
			exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
		}

		exit();
	case 'get_all_program_for_ch':
		$rOutput = [];
		$rOutput['js'] = [];
		$rChannelID = (empty(XCMS::$rRequest['ch_id']) ? 0 : (int) XCMS::$rRequest['ch_id']);
		$rTimeDifference = XCMS::getDiffTimezone($rTimezone);

		if (file_exists(STREAMS_TMP_PATH . 'stream_' . (int) $rChannelID)) {
			$rStreamRow = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rChannelID))['info'];
		}
		else {
			$db->query('SELECT `tv_archive_duration` FROM `streams` WHERE `id` = ?;', XCMS::$rRequest['ch_id']);

			if (0 < $db->num_rows()) {
				$rStreamRow = $db->get_row();
			}
		}

		$rTime = strtotime(date('Y-m-d 00:00:00'));

		if (file_exists(EPG_PATH . 'stream_' . (int) $rChannelID)) {
			$rRows = igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rChannelID));

			foreach ($rRows as $rRow) {
				if ($rTime <= $rRow['start']) {
					$rRow['start_timestamp'] = $rRow['start'];
					$rRow['stop_timestamp'] = $rRow['end'];
					$rStartTime = new DateTime();
					$rStartTime->setTimestamp($rRow['start']);
					$rStartTime->modify($rTimeDifference . ' seconds');
					$rEndTime = new DateTime();
					$rEndTime->setTimestamp($rRow['end']);
					$rEndTime->modify($rTimeDifference . ' seconds');
					$rOutput['js'][] = ['start_timestamp' => $rStartTime->getTimestamp(), 'stop_timestamp' => $rEndTime->getTimestamp(), 'name' => $rRow['title']];
				}
			}
		}

		exit(json_encode($rOutput, JSON_PARTIAL_OUTPUT_ON_ERROR));
	}

	break;
}

?>