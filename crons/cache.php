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
	global $rStartup;

	if (!defined('CACHE_TMP_PATH')) {
		exit();
	}
	if ($rStartup && file_exists(CACHE_TMP_PATH . 'settings')) {
		echo 'Checking cache readability...' . "\n";
		$rSerialize = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'settings'));
		if (!is_array($rSerialize) || !isset($rSerialize['server_name'])) {
			echo 'Clearing cache...' . "\n\n";

			foreach ([STREAMS_TMP_PATH, LINES_TMP_PATH, SERIES_TMP_PATH] as $rTmpPath) {
				foreach (scandir($rTmpPath) as $rFile) {
					unlink($rTmpPath . $rFile);
				}
			}

			exec('sudo rm -rf ' . TMP_PATH . '*');
			exec('sudo rm -rf ' . SIGNALS_PATH . '*');
		}
	}

	foreach ([EPG_PATH, VOD_PATH, ARCHIVE_PATH, CREATED_PATH, DELAY_PATH, VIDEO_PATH, PLAYLIST_PATH, CONS_TMP_PATH, CRONS_TMP_PATH, PLAYER_TMP_PATH, CACHE_TMP_PATH, DIVERGENCE_TMP_PATH, FLOOD_TMP_PATH, MINISTRA_TMP_PATH, SIGNALS_TMP_PATH, LOGS_TMP_PATH, WATCH_TMP_PATH, CIDR_TMP_PATH, STREAMS_TMP_PATH, LINES_TMP_PATH, SERIES_TMP_PATH] as $rPath) {
		if (!file_exists($rPath)) {
			mkdir($rPath);
		}
	}

	XCMS::setCache('settings', XCMS::getSettings(true));
	XCMS::setCache('bouquets', XCMS::getBouquets(true));
	$rServers = XCMS::getServers(true);
	unset($rServers['php_pids']);
	XCMS::setCache('servers', $rServers);
	XCMS::setCache('proxy_servers', XCMS::getProxyIPs(true));
	XCMS::setCache('blocked_servers', XCMS::getBlockedServers(true));
	XCMS::setCache('blocked_isp', XCMS::getBlockedISP(true));
	XCMS::setCache('blocked_ua', XCMS::getBlockedUA(true));
	XCMS::setCache('blocked_ips', XCMS::getBlockedIPs(true));
	XCMS::setCache('allowed_ips', XCMS::getAllowedIPs(true));
	XCMS::setCache('categories', XCMS::getCategories(NULL, true));

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		$rOutputFormats = [];
		$db->query('SELECT `access_output_id`, `output_key` FROM `output_formats`;');

		foreach ($db->get_rows() as $rRow) {
			$rOutputFormats[] = $rRow;
		}

		file_put_contents(CACHE_TMP_PATH . 'output_formats', igbinary_serialize($rOutputFormats));
		$rHMACKeys = [];
		$db->query('SELECT `id`, `key` FROM `hmac_keys` WHERE `enabled` = 1;');

		foreach ($db->get_rows() as $rRow) {
			$rHMACKeys[] = $rRow;
		}

		file_put_contents(CACHE_TMP_PATH . 'hmac_keys', igbinary_serialize($rHMACKeys));
		$rRTMPIPs = [];
		$db->query('SELECT `ip`, `password`, `push`, `pull` FROM `rtmp_ips`');

		foreach ($db->get_rows() as $rRow) {
			$rRTMPIPs[gethostbyname($rRow['ip'])] = ['password' => $rRow['password'], 'push' => (bool) $rRow['push'], 'pull' => (bool) $rRow['pull']];
		}

		file_put_contents(CACHE_TMP_PATH . 'rtmp_ips', igbinary_serialize($rRTMPIPs));

		if (file_exists(BIN_PATH . 'maxmind/cidr.db')) {
			exec('ls ' . CIDR_TMP_PATH . ' | wc -l', $rOutput);

			if ((int) $rOutput[0] == 0) {
				$rDatabase = json_decode(file_get_contents(BIN_PATH . 'maxmind/cidr.db'), true);

				foreach ($rDatabase as $rASN => $rData) {
					file_put_contents(CIDR_TMP_PATH . $rASN, json_encode($rData));
				}
			}
		}

		$rChannelOrder = [];

		if (XCMS::$rSettings['channel_number_type'] == 'manual') {
			$db->query('SELECT `id`, `order` FROM `streams` ORDER BY `order` ASC;');

			foreach ($db->get_rows() as $rRow) {
				$rChannelOrder[] = (int) $rRow['id'];
			}
		}

		$rCategoryMap = [];
		$rBouquetMap = [];
		$rStreamIDs = [
			'channels' => [],
			'radios'   => [],
			'movies'   => [],
			'episodes' => [],
			'series'   => []
		];
		$db->query('SELECT *, IF(`bouquet_order` > 0, `bouquet_order`, 999) AS `order` FROM `bouquets` ORDER BY `order` ASC;');

		foreach ($db->get_rows(true, 'id') as $rID => $rChannels) {
			$rAllowedCategories = [];
			$rAllChannels = [];

			foreach (json_decode($rChannels['bouquet_channels'], true) as $rStreamID) {
				if ((0 < (int) $rStreamID) && !in_array($rStreamID, $rStreamIDs['channels'])) {
					$rStreamIDs['channels'][] = $rStreamID;
				}

				if (!isset($rBouquetMap[(int) $rStreamID])) {
					$rBouquetMap[(int) $rStreamID] = [];
				}

				$rBouquetMap[(int) $rStreamID][] = $rID;
			}

			foreach (json_decode($rChannels['bouquet_radios'], true) as $rStreamID) {
				if ((0 < (int) $rStreamID) && !in_array($rStreamID, $rStreamIDs['radios'])) {
					$rStreamIDs['radios'][] = $rStreamID;
				}

				if (!isset($rBouquetMap[(int) $rStreamID])) {
					$rBouquetMap[(int) $rStreamID] = [];
				}

				$rBouquetMap[(int) $rStreamID][] = $rID;
			}

			foreach (json_decode($rChannels['bouquet_movies'], true) as $rStreamID) {
				if ((0 < (int) $rStreamID) && !in_array($rStreamID, $rStreamIDs['movies'])) {
					$rStreamIDs['movies'][] = $rStreamID;
				}

				if (!isset($rBouquetMap[(int) $rStreamID])) {
					$rBouquetMap[(int) $rStreamID] = [];
				}

				$rBouquetMap[(int) $rStreamID][] = $rID;
			}

			foreach (json_decode($rChannels['bouquet_series'], true) as $rSeriesID) {
				if ((0 < (int) $rSeriesID) && !in_array($rSeriesID, $rStreamIDs['series'])) {
					$db->query('SELECT `stream_id` FROM `streams_episodes` WHERE `series_id` = ? ORDER BY `season_num` ASC, `episode_num` ASC;', $rSeriesID);

					foreach ($db->get_rows() as $rEpisode) {
						if (0 < (int) $rEpisode['stream_id']) {
							$rStreamIDs['episodes'][] = $rEpisode['stream_id'];
						}

						if (!isset($rBouquetMap[(int) $rEpisode['stream_id']])) {
							$rBouquetMap[(int) $rEpisode['stream_id']] = [];
						}

						$rBouquetMap[(int) $rEpisode['stream_id']][] = $rID;
					}
				}
			}

			$rAllChannels = array_map('intval', array_unique(array_merge(json_decode($rChannels['bouquet_channels'], true) ?: [], json_decode($rChannels['bouquet_radios'], true) ?: [], json_decode($rChannels['bouquet_movies'], true) ?: [])));
			$rAllSeries = array_map('intval', array_unique(json_decode($rChannels['bouquet_series'], true) ?: []));

			if (0 < count($rAllChannels)) {
				$db->query('SELECT DISTINCT(`category_id`) AS `category_id` FROM `streams` WHERE `id` IN (' . implode(',', $rAllChannels) . ');');

				foreach ($db->get_rows() as $rRow) {
					$rAllowedCategories = array_merge($rAllowedCategories, json_decode($rRow['category_id'], true) ?: []);
				}
			}

			if (0 < count($rAllSeries)) {
				$db->query('SELECT DISTINCT(`category_id`) AS `category_id` FROM `streams_series` WHERE `id` IN (' . implode(',', $rAllSeries) . ');');

				foreach ($db->get_rows() as $rRow) {
					$rAllowedCategories = array_merge($rAllowedCategories, json_decode($rRow['category_id'], true) ?: []);
				}
			}

			$rCategoryMap[$rID] = array_unique($rAllowedCategories);
		}

		if (XCMS::$rSettings['channel_number_type'] != 'manual') {
			foreach (['channels', 'radios', 'movies', 'episodes'] as $rKey) {
				if (0 < count($rStreamIDs[$rKey])) {
					$rWhere = 'AND `id` NOT IN (' . implode(',', array_map('intval', $rStreamIDs[$rKey])) . ')';
				}
				else {
					$rWhere = '';
				}

				switch ($rKey) {
				case 'channels':
					$rType = [1, 3];
					break;
				case 'radios':
					$rType = [4];
					break;
				case 'movies':
					$rType = [2];
					break;
				case 'episodes':
					$rType = [5];
					break;
				}

				if (0 < count($rType)) {
					$db->query('SELECT `id` FROM `streams` WHERE `type` IN (' . implode(',', $rType) . (') ' . $rWhere . ' ORDER BY `order` ASC;'));

					foreach ($db->get_rows() as $rRow) {
						$rStreamIDs[$rKey][] = $rRow['id'];
					}
				}
			}

			if (XCMS::$rSettings['vod_sort_newest']) {
				$rStreamIDs['movies'] = [];
				$rStreamIDs['episodes'] = [];
				$db->query('SELECT `type`, `id` FROM `streams` WHERE `type` IN (2,5) ORDER BY `added` DESC, `id` DESC;');

				foreach ($db->get_rows() as $rRow) {
					$rStreamIDs[[2 => 'movies', 5 => 'episodes'][$rRow['type']]][] = $rRow['id'];
				}

				$rSeriesOrder = [];
				$db->query('SELECT `id`, (SELECT MAX(`streams`.`added`) FROM `streams_episodes` LEFT JOIN `streams` ON `streams`.`id` = `streams_episodes`.`stream_id` WHERE `streams_episodes`.`series_id` = `streams_series`.`id`) AS `last_modified_stream` FROM `streams_series` ORDER BY `last_modified_stream` DESC, `last_modified` DESC, `id` DESC;');

				foreach ($db->get_rows() as $rRow) {
					$rSeriesOrder[] = (int) $rRow['id'];
				}

				file_put_contents(CACHE_TMP_PATH . 'series_order', igbinary_serialize($rSeriesOrder));
			}

			foreach (['channels', 'radios', 'movies', 'episodes'] as $rKey) {
				foreach ($rStreamIDs[$rKey] as $rStreamID) {
					$rChannelOrder[] = (int) $rStreamID;
				}
			}

			$rChannelOrder = array_unique($rChannelOrder);
		}

		$rCategoryChannels = [];
		$db->query('SELECT `id`, `category_id` FROM `streams`;');
		if ($db->dbh && $db->result) {
			if (0 < $db->result->rowCount()) {
				foreach ($db->result->fetchAll(PDO::FETCH_ASSOC) as $rStreamInfo) {
					$rCategoryChannels[$rStreamInfo['id']] = json_decode($rStreamInfo['category_id'], true);
				}
			}
		}

		$rResellerDomains = [];
		$db->query('SELECT `reseller_dns` FROM `users` WHERE `status` = 1 AND `reseller_dns` IS NOT NULL;');

		foreach ($db->get_rows() as $rRow) {
			$rResellerDomains[] = strtolower($rRow['reseller_dns']);
		}

		file_put_contents(CACHE_TMP_PATH . 'reseller_domains', igbinary_serialize($rResellerDomains));
		file_put_contents(CACHE_TMP_PATH . 'channel_order', igbinary_serialize($rChannelOrder));
		file_put_contents(CACHE_TMP_PATH . 'bouquet_map', igbinary_serialize($rBouquetMap));
		file_put_contents(CACHE_TMP_PATH . 'category_map', igbinary_serialize($rCategoryMap));
		file_put_contents(STREAMS_TMP_PATH . 'channels_categories', igbinary_serialize($rCategoryChannels));
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
ini_set('memory_limit', -1);
$rStartup = false;

if (count($argv) == 2) {
	$rStartup = true;
}

cli_set_process_title('XCMS[Cache Builder]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();

?>