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
require './init.php';
$rDeny = true;

if (XCMS::$rSettings['disable_enigma2']) {
	$rDeny = false;
	generateError('E2_DISABLED');
}

class SimpleXMLExtended extends SimpleXMLElement
{
	public function addCData($rCData)
	{
		$rNode = dom_import_simplexml($this);
		$rRowner = $rNode->ownerDocument;
		$rNode->appendChild($rRowner->createCDATASection($rCData));
	}
}

$rUsername = XCMS::$rRequest['username'];
$rPassword = XCMS::$rRequest['password'];
$rType = (!empty(XCMS::$rRequest['type']) ? XCMS::$rRequest['type'] : NULL);
$rCatID = (!empty(XCMS::$rRequest['cat_id']) ? (int) XCMS::$rRequest['cat_id'] : NULL);
$sCatID = (!empty(XCMS::$rRequest['scat_id']) ? (int) XCMS::$rRequest['scat_id'] : NULL);
$rSeriesID = (!empty(XCMS::$rRequest['series_id']) ? (int) XCMS::$rRequest['series_id'] : NULL);
$rSeason = (!empty(XCMS::$rRequest['season']) ? (int) XCMS::$rRequest['season'] : NULL);
$rProtocol = (stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https://' : 'http://');
$rURL = (!empty($_SERVER['HTTP_HOST']) ? $rProtocol . $_SERVER['HTTP_HOST'] . '/' : XCMS::$rServers[SERVER_ID]['site_url']);
ini_set('memory_limit', -1);
if (empty($rUsername) || empty($rPassword)) {
	generateError('NO_CREDENTIALS');
}

if ($rUserInfo = XCMS::getUserInfo(NULL, $rUsername, $rPassword, true, false)) {
	$rDeny = false;
	$db = new Database();
	XCMS::$db = & $db;
	XCMS::checkAuthFlood($rUserInfo);
	$rLiveCategories = XCMS::getCategories('live');
	$rVODCategories = XCMS::getCategories('movie');
	$rSeriesCategories = XCMS::getCategories('series');
	$rLiveStreams = [];
	$rVODStreams = [];

	if (XCMS::$rCached) {
		$rChannels = $rUserInfo['channel_ids'];
	}
	else {
		$rChannels = [];

		if (0 < count($rUserInfo['channel_ids'])) {
			$rWhereV = $rWhere = [];
			$rWhere[] = '`id` IN (' . implode(',', $rUserInfo['channel_ids']) . ')';
			$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);
			$rOrder = 'FIELD(id,' . implode(',', $rUserInfo['channel_ids']) . ')';
			XCMS::$db->query('SELECT t1.id,t1.epg_id,t1.added,t1.allow_record,t1.year,t1.channel_id,t1.movie_properties,t1.stream_source,t1.tv_archive_server_id,t1.vframes_server_id,t1.tv_archive_duration,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t1.series_no,t1.direct_source,t2.type_output,t1.target_container,t2.live,t1.rtmp_output,t1.order,t2.type_key FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type ' . $rWhereString . ' ORDER BY ' . $rOrder . ';', ...$rWhereV);
			$rChannels = XCMS::$db->get_rows();
		}
	}

	$rUserInfo['channel_ids'] = XCMS::sortChannels($rUserInfo['channel_ids']);

	foreach ($rChannels as $rChannel) {
		if (XCMS::$rCached) {
			$rChannel = igbinary_unserialize(file_get_contents(STREAMS_TMP_PATH . 'stream_' . (int) $rChannel))['info'];
		}

		if ($rChannel['live'] == 0) {
			$rVODStreams[] = $rChannel;
		}
		else {
			$rLiveStreams[] = $rChannel;
		}
	}

	unset($rChannels);

	switch ($rType) {
	case 'get_live_categories':
		$rXML = new SimpleXMLExtended('<items/>');
		$rXML->addChild('playlist_name', 'Live [ ' . XCMS::$rSettings['server_name'] . ' ]');
		$rCategory = $rXML->addChild('category');
		$rCategory->addChild('category_id', 1);
		$rCategory->addChild('category_title', 'Live [ ' . XCMS::$rSettings['server_name'] . ' ]');
		$rChannels = $rXML->addChild('channel');
		$rChannels->addChild('title', base64_encode('All'));
		$rChannels->addChild('description', base64_encode('Live Streams Category [ ALL ]'));
		$rChannels->addChild('category_id', 0);
		$rCData = $rChannels->addChild('playlist_url');
		$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_live_streams&cat_id=0') . $rCategory['id']);

		foreach ($rLiveCategories as $rCategoryID => $rCategory) {
			$rChannels = $rXML->addChild('channel');
			$rChannels->addChild('title', base64_encode($rCategory['category_name']));
			$rChannels->addChild('description', base64_encode('Live Streams Category'));
			$rChannels->addChild('category_id', $rCategory['id']);
			$rCData = $rChannels->addChild('playlist_url');
			$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_live_streams&cat_id=') . $rCategory['id']);
		}

		header('Content-Type: application/xml; charset=utf-8');
		echo $rXML->asXML();
		break;
	case 'get_vod_categories':
		$rXML = new SimpleXMLExtended('<items/>');
		$rXML->addChild('playlist_name', 'Movie [ ' . XCMS::$rSettings['server_name'] . ' ]');
		$rCategory = $rXML->addChild('category');
		$rCategory->addChild('category_id', 1);
		$rCategory->addChild('category_title', 'Movie [ ' . XCMS::$rSettings['server_name'] . ' ]');
		$rChannels = $rXML->addChild('channel');
		$rChannels->addChild('title', base64_encode('All'));
		$rChannels->addChild('description', base64_encode('Movie Streams Category [ ALL ]'));
		$rChannels->addChild('category_id', 0);
		$rCData = $rChannels->addChild('playlist_url');
		$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_vod_streams&cat_id=0') . $rCategory['id']);

		foreach ($rVODCategories as $movie_category_id => $rCategory) {
			$rChannels = $rXML->addChild('channel');
			$rChannels->addChild('title', base64_encode($rCategory['category_name']));
			$rChannels->addChild('description', base64_encode('Movie Streams Category'));
			$rChannels->addChild('category_id', $rCategory['id']);
			$rCData = $rChannels->addChild('playlist_url');
			$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_vod_streams&cat_id=') . $rCategory['id']);
		}

		header('Content-Type: application/xml; charset=utf-8');
		echo $rXML->asXML();
		break;
	case 'get_series_categories':
		$rXML = new SimpleXMLExtended('<items/>');
		$rXML->addChild('playlist_name', 'SubCategory [ ' . XCMS::$rSettings['server_name'] . ' ]');
		$rCategory = $rXML->addChild('category');
		$rCategory->addChild('category_id', 1);
		$rCategory->addChild('category_title', 'SubCategory [ ' . XCMS::$rSettings['server_name'] . ' ]');
		$rChannels = $rXML->addChild('channel');
		$rChannels->addChild('title', base64_encode('All'));
		$rChannels->addChild('description', base64_encode('TV Series Category [ ALL ]'));
		$rChannels->addChild('category_id', 0);
		$rCData = $rChannels->addChild('playlist_url');
		$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_series&cat_id=0') . $rCategory['id']);

		foreach ($rSeriesCategories as $movie_category_id => $rCategory) {
			$rChannels = $rXML->addChild('channel');
			$rChannels->addChild('title', base64_encode($rCategory['category_name']));
			$rChannels->addChild('description', base64_encode('TV Series Category'));
			$rChannels->addChild('category_id', $rCategory['id']);
			$rCData = $rChannels->addChild('playlist_url');
			$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_series&cat_id=') . $rCategory['id']);
		}

		header('Content-Type: application/xml; charset=utf-8');
		echo $rXML->asXML();
		break;
	case 'get_series':
		if (isset($rCatID) || is_null($rCatID) || (isset($sCatID) || is_null($sCatID))) {
			$rCategoryID = (is_null($rCatID) ? NULL : $rCatID);

			if (is_null($rCategoryID)) {
				$rCategoryID = (is_null($sCatID) ? NULL : $sCatID);
				$rCatID = $sCatID;
			}

			$rCategoryName = (!empty($rSeriesCategories[$rCatID]) ? $rSeriesCategories[$rCatID]['category_name'] : 'ALL');
			$rXML = new SimpleXMLExtended('<items/>');
			$rXML->addChild('playlist_name', 'TV Series [ ' . $rCategoryName . ' ]');
			$rCategory = $rXML->addChild('category');
			$rCategory->addChild('category_id', 1);
			$rCategory->addChild('category_title', 'TV Series [ ' . $rCategoryName . ' ]');

			if (0 < count($rUserInfo['series_ids'])) {
				if (XCMS::$rSettings['vod_sort_newest']) {
					$db->query('SELECT * FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['series_ids'])) . ') ORDER BY `last_modified` DESC;');
				}
				else {
					$db->query('SELECT * FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rUserInfo['series_ids'])) . ') ORDER BY FIELD(`id`,' . implode(',', $rUserInfo['series_ids']) . ') ASC;');
				}

				$rSeries = $db->get_rows(true, 'id');

				foreach ($rSeries as $rSeriesID => $rSeriesInfo) {
					foreach (json_decode($rSeriesInfo['category_id'], true) as $rCategoryIDSearch) {
						if (!$rCategoryID || ($rCategoryID == $rCategoryIDSearch)) {
							$rChannels = $rXML->addChild('channel');
							$rChannels->addChild('title', base64_encode($rSeriesInfo['title']));
							$rChannels->addChild('description', '');
							$rChannels->addChild('category_id', $rSeriesID);
							$rCData = $rChannels->addChild('playlist_url');
							$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_seasons&series_id=') . $rSeriesID);
						}

						if (!$rCategoryID) {
							break;
						}
					}
				}
			}

			header('Content-Type: application/xml; charset=utf-8');
			echo $rXML->asXML();
		}

		break;
	case 'get_seasons':
		if (isset($rSeriesID)) {
			$db->query('SELECT * FROM `streams_series` WHERE `id` = ?', $rSeriesID);
			$rSeriesInfo = $db->get_row();
			$rCategoryName = $rSeriesInfo['title'];
			$rXML = new SimpleXMLExtended('<items/>');
			$rXML->addChild('playlist_name', 'TV Series [ ' . $rCategoryName . ' ]');
			$rCategory = $rXML->addChild('category');
			$rCategory->addChild('category_id', 1);
			$rCategory->addChild('category_title', 'TV Series [ ' . $rCategoryName . ' ]');
			$db->query('SELECT * FROM `streams_episodes` t1 INNER JOIN `streams` t2 ON t2.id=t1.stream_id WHERE t1.series_id = ? ORDER BY t1.season_num ASC, t1.episode_num ASC', $rSeriesID);
			$rRows = $db->get_rows(true, 'season_num', false);

			foreach (array_keys($rRows) as $rSeasonNum) {
				$rChannels = $rXML->addChild('channel');
				$rChannels->addChild('title', base64_encode('Season ' . $rSeasonNum));
				$rChannels->addChild('description', '');
				$rChannels->addChild('category_id', $rSeasonNum);
				$rCData = $rChannels->addChild('playlist_url');
				$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_series_streams&series_id=') . $rSeriesID . '&season=' . $rSeasonNum);
			}

			header('Content-Type: application/xml; charset=utf-8');
			echo $rXML->asXML();
		}

		break;
	case 'get_series_streams':
		if (isset($rSeriesID) && isset($rSeason)) {
			$db->query('SELECT * FROM `streams_series` WHERE `id` = ?', $rSeriesID);
			$rSeriesInfo = $db->get_row();
			$rXML = new SimpleXMLExtended('<items/>');
			$rXML->addChild('playlist_name', 'TV Series [ ' . $rSeriesInfo['title'] . ' Season ' . $rSeason . ' ]');
			$rCategory = $rXML->addChild('category');
			$rCategory->addChild('category_id', 1);
			$rCategory->addChild('category_title', 'TV Series [ ' . $rSeriesInfo['title'] . ' Season ' . $rSeason . ' ]');
			$db->query('SELECT t2.direct_source,t2.stream_source,t2.target_container,t2.id,t1.series_id,t1.season_num FROM `streams_episodes` t1 INNER JOIN `streams` t2 ON t2.id=t1.stream_id WHERE t1.series_id = ? AND t1.season_num = ? ORDER BY  t1.episode_num ASC', $rSeriesID, $rSeason);
			$rSeriesEpisodes = $db->get_rows();
			$rEpisodeNum = 0;

			foreach ($rSeriesEpisodes as $rEpisode) {
				$rChannels = $rXML->addChild('channel');
				$rChannels->addChild('title', base64_encode('Episode ' . sprintf('%02d', ++$rEpisodeNum)));
				$rDesc = '';
				$rDescChannel = $rChannels->addChild('desc_image');
				$rDescChannel->addCData(XCMS::validateImage($rSeriesInfo['cover']));
				$rChannels->addChild('description', base64_encode($rDesc));
				$rChannels->addChild('category_id', $rCatID);
				$rCDataURL = $rChannels->addChild('stream_url');
				$rEncData = 'movie/' . $rUsername . '/' . $rPassword . '/' . $rEpisode['id'] . '/' . $rEpisode['target_container'];
				$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
				$rSource = $rURL . ('play/' . $rToken);
				$rCDataURL->addCData($rSource);
			}

			header('Content-Type: application/xml; charset=utf-8');
			echo $rXML->asXML();
		}

		break;
	case 'get_live_streams':
		if (isset($rCatID) || is_null($rCatID)) {
			$rCategoryID = (is_null($rCatID) ? NULL : $rCatID);
			$rXML = new SimpleXMLExtended('<items/>');
			$rXML->addChild('playlist_name', 'Live [ ' . XCMS::$rSettings['server_name'] . ' ]');
			$rCategory = $rXML->addChild('category');
			$rCategory->addChild('category_id', 1);
			$rCategory->addChild('category_title', 'Live [ ' . XCMS::$rSettings['server_name'] . ' ]');

			foreach ($rLiveStreams as $rStream) {
				if (!$rCategoryID || in_array($rCategoryID, json_decode($rStream['category_id'], true))) {
					$rChannelEPGs = [];

					if (file_exists(EPG_PATH . 'stream_' . (int) $rStream['id'])) {
						foreach (igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStream['id'])) as $rRow) {
							if ($rRow['end'] < time()) {
								continue;
							}

							$rChannelEPGs[] = $rRow;

							if (2 <= count($rChannelEPGs)) {
								break;
							}
						}
					}

					$rDesc = '';
					$rShortEPG = '';
					$i = 0;

					foreach ($rChannelEPGs as $rRow) {
						$rDesc .= '[' . date('H:i', $rRow['start']) . '] ' . $rRow['title'] . "\n" . '( ' . $rRow['description'] . ')' . "\n";

						if ($i == 0) {
							$rShortEPG = '[' . date('H:i', $rRow['start']) . ' - ' . date('H:i', $rRow['end']) . '] + ' . round(($rRow['end'] - time()) / 60, 1) . ' min   ' . $rRow['title'];
							$i++;
						}
					}
				}

				foreach (json_decode($rStream['category_id'], true) as $rCategoryIDSearch) {
					if (!$rCategoryID || ($rCategoryID == $rCategoryIDSearch)) {
						$rChannels = $rXML->addChild('channel');
						$rChannels->addChild('title', base64_encode($rStream['stream_display_name'] . ' ' . $rShortEPG));
						$rChannels->addChild('description', base64_encode($rDesc));
						$rDescChannel = $rChannels->addChild('desc_image');
						$rDescChannel->addCData(XCMS::validateImage($rStream['stream_icon']));
						$rChannels->addChild('category_id', $rCategoryIDSearch);
						$rCData = $rChannels->addChild('stream_url');
						$rEncData = 'live/' . $rUsername . '/' . $rPassword . '/' . $rStream['id'];
						$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
						$rSource = $rURL . ('play/' . $rToken);
						$rCData->addCData($rSource);
					}

					if (!$rCategoryID) {
						break;
					}
				}
			}

			header('Content-Type: application/xml; charset=utf-8');
			echo $rXML->asXML();
		}

		break;
	case 'get_vod_streams':
		if (isset($rCatID) || is_null($rCatID)) {
			$rCategoryID = (is_null($rCatID) ? NULL : $rCatID);
			$rXML = new SimpleXMLExtended('<items/>');
			$rXML->addChild('playlist_name', 'Movie [ ' . XCMS::$rSettings['server_name'] . ' ]');
			$rCategory = $rXML->addChild('category');
			$rCategory->addChild('category_id', 1);
			$rCategory->addChild('category_title', 'Movie [ ' . XCMS::$rSettings['server_name'] . ' ]');

			foreach ($rVODStreams as $rStream) {
				foreach (json_decode($rStream['category_id'], true) as $rCategoryIDSearch) {
					if (!$rCategoryID || ($rCategoryID == $rCategoryIDSearch)) {
						$rProperties = json_decode($rStream['movie_properties'], true);
						$rChannels = $rXML->addChild('channel');
						$rChannels->addChild('title', base64_encode($rStream['stream_display_name']));
						$rDesc = '';

						if ($rProperties) {
							foreach ($rProperties as $rKey => $rProperty) {
								if ($rKey == 'movie_image') {
									continue;
								}

								$rDesc .= strtoupper($rKey) . ': ' . $rProperty . "\n";
							}
						}

						$rDescChannel = $rChannels->addChild('desc_image');
						$rDescChannel->addCData(XCMS::validateImage($rProperties['movie_image']));
						$rChannels->addChild('description', base64_encode($rDesc));
						$rChannels->addChild('category_id', $rCategoryIDSearch);
						$rCDataURL = $rChannels->addChild('stream_url');
						$rEncData = 'movie/' . $rUsername . '/' . $rPassword . '/' . $rStream['id'] . '/' . $rStream['target_container'];
						$rToken = Xcms\Functions::encrypt($rEncData, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
						$rSource = $rURL . ('play/' . $rToken);
						$rCDataURL->addCData($rSource);
					}

					if (!$rCategoryID) {
						break;
					}
				}
			}

			header('Content-Type: application/xml; charset=utf-8');
			echo $rXML->asXML();
		}

		break;
	default:
		$rXML = new SimpleXMLExtended('<items/>');
		$rXML->addChild('playlist_name', XCMS::$rSettings['server_name']);
		$rCategory = $rXML->addChild('category');
		$rCategory->addChild('category_id', 1);
		$rCategory->addChild('category_title', XCMS::$rSettings['server_name']);

		if (!empty($rLiveStreams)) {
			$rChannels = $rXML->addChild('channel');
			$rChannels->addChild('title', base64_encode('Live Streams'));
			$rChannels->addChild('description', base64_encode('Live Streams Category'));
			$rChannels->addChild('category_id', 0);
			$rCData = $rChannels->addChild('playlist_url');
			$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_live_categories'));
		}

		if (!empty($rVODStreams)) {
			$rChannels = $rXML->addChild('channel');
			$rChannels->addChild('title', base64_encode('VOD'));
			$rChannels->addChild('description', base64_encode('Video On Demand Category'));
			$rChannels->addChild('category_id', 1);
			$rCData = $rChannels->addChild('playlist_url');
			$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_vod_categories'));
		}

		$rChannels = $rXML->addChild('channel');
		$rChannels->addChild('title', base64_encode('TV Series'));
		$rChannels->addChild('description', base64_encode('TV Series Category'));
		$rChannels->addChild('category_id', 2);
		$rCData = $rChannels->addChild('playlist_url');
		$rCData->addCData($rURL . ('enigma2?username=' . $rUsername . '&password=' . $rPassword . '&type=get_series_categories'));
		header('Content-Type: application/xml; charset=utf-8');
		echo $rXML->asXML();
		break;
	}
}
else {
	XCMS::checkBruteforce(NULL, NULL, $rUsername);
	generateError('INVALID_CREDENTIALS');
}

?>