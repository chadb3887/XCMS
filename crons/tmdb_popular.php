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
	global $rIdentifier;

	if (is_object($db)) {
		$db->close_mysql();
	}

	@unlink($rIdentifier);
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
require_once XCMS_HOME . 'includes/libs/tmdb.php';
cli_set_process_title('XCMS[Popular]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);

if (0 < strlen(XCMS::$rSettings['tmdb_api_key'])) {
	if (0 < strlen(XCMS::$rSettings['tmdb_language'])) {
		$rTMDB = new TMDB(XCMS::$rSettings['tmdb_api_key'], XCMS::$rSettings['tmdb_language']);
	}
	else {
		$rTMDB = new TMDB(XCMS::$rSettings['tmdb_api_key']);
	}

	$rPages = 100;
	$rTMDBIDs = [];
	$db->query('SELECT `id`, `movie_properties` FROM `streams` WHERE `type` = 2 AND `movie_properties` IS NOT NULL AND LENGTH(`movie_properties`) > 0;');

	foreach ($db->get_rows() as $rRow) {
		$rProperties = json_decode($rRow['movie_properties'], true);

		if ($rProperties['tmdb_id']) {
			$rTMDBIDs[$rProperties['tmdb_id']] = $rRow['id'];
		}
	}

	$db->query('SELECT `id`, `tmdb_id` FROM `streams_series` WHERE `tmdb_id` IS NOT NULL AND LENGTH(`tmdb_id`) > 0;');

	foreach ($db->get_rows() as $rRow) {
		$rTMDBIDs[$rRow['tmdb_id']] = $rRow['id'];
	}

	$rReturn = [
		'movies' => [],
		'series' => []
	];

	foreach (range(1, $rPages) as $rPage) {
		$rItems = $rTMDB->getPopularMovies($rPage);

		foreach ($rItems as $rItem) {
			if (isset($rTMDBIDs[$rItem->getID()])) {
				$rReturn['movies'][] = $rTMDBIDs[$rItem->getID()];
			}
		}
	}

	foreach (range(1, $rPages) as $rPage) {
		$rItems = $rTMDB->getPopularTVShows($rPage);

		foreach ($rItems as $rItem) {
			if (isset($rTMDBIDs[$rItem->getID()])) {
				$rReturn['series'][] = $rTMDBIDs[$rItem->getID()];
			}
		}
	}

	file_put_contents(CONTENT_PATH . 'tmdb_popular', igbinary_serialize($rReturn));
	$db->query('SELECT COUNT(*) AS `count` FROM `streams` WHERE `type` = 2 AND `similar` IS NULL AND `tmdb_id` > 0;');
	$rCount = $db->get_row()['count'];

	if (0 < $rCount) {
		$rSteps = range(0, $rCount, 1000);

		if (!$rSteps) {
			$rSteps = [0];
		}

		foreach ($rSteps as $rStep) {
			$db->query('SELECT `id`, `tmdb_id` FROM `streams` WHERE `type` = 2 AND `similar` IS NULL AND `tmdb_id` > 0 LIMIT ' . $rStep . ', 1000;');

			foreach ($db->get_rows() as $rRow) {
				$rSimilar = [];

				foreach (range(1, 3) as $rPage) {
					foreach (json_decode(json_encode($rTMDB->getSimilarMovies($rRow['tmdb_id'], $rPage)), true) as $rItem) {
						$rSimilar[] = (int) $rItem['_data']['id'];
					}
				}

				$rSimilar = array_unique($rSimilar);
				$db->query('UPDATE `streams` SET `similar` = ? WHERE `id` = ?;', json_encode($rSimilar), $rRow['id']);
			}
		}
	}

	$db->query('SELECT COUNT(*) AS `count` FROM `streams_series` WHERE `similar` IS NULL AND `tmdb_id` > 0;');
	$rCount = $db->get_row()['count'];

	if (0 < $rCount) {
		$rSteps = range(0, $rCount, 1000);

		if (!$rSteps) {
			$rSteps = [0];
		}

		foreach ($rSteps as $rStep) {
			$db->query('SELECT `id`, `tmdb_id` FROM `streams_series` WHERE `similar` IS NULL AND `tmdb_id` > 0 LIMIT ' . $rStep . ', 1000;');

			foreach ($db->get_rows() as $rRow) {
				$rSimilar = [];

				foreach (range(1, 3) as $rPage) {
					foreach (json_decode(json_encode($rTMDB->getSimilarSeries($rRow['tmdb_id'], $rPage)), true) as $rItem) {
						$rSimilar[] = (int) $rItem['id'];
					}
				}

				$rSimilar = array_unique($rSimilar);
				$db->query('UPDATE `streams_series` SET `similar` = ? WHERE `id` = ?;', json_encode($rSimilar), $rRow['id']);
			}
		}
	}
}

$rPopularLive = [];
$db->query('SELECT `stream_id`, COUNT(`activity_id`) AS `count` FROM `lines_activity` LEFT JOIN `streams` ON `streams`.`id` = `lines_activity`.`stream_id` WHERE `type` = 1 AND `date_end` < UNIX_TIMESTAMP() - (86400*28) GROUP BY `stream_id` ORDER BY `count` DESC LIMIT 500;');

foreach ($db->get_rows() as $rRow) {
	$rPopularLive[] = $rRow['stream_id'];
}

file_put_contents(CONTENT_PATH . 'live_popular', igbinary_serialize($rPopularLive));

?>