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

function deleteStreams($rIDs)
{
	global $db;
	$db->query('DELETE FROM `lines_logs` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `mag_claims` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams` WHERE `id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_episodes` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_errors` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_logs` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_options` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_stats` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `watch_refresh` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `watch_logs` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `lines_live` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `recordings` WHERE `created_id` IN (' . implode(',', $rIDs) . ') OR `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('UPDATE `lines_activity` SET `stream_id` = 0 WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('SELECT `server_id` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', $rIDs) . ');');
	$db->query('DELETE FROM `streams_servers` WHERE `parent_id` IS NOT NULL AND `parent_id` > 0 AND `parent_id` NOT IN (SELECT `id` FROM `servers` WHERE `server_type` = 0);');
	$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES(?, 1, ?, ?);', SERVER_ID, time(), json_encode(['type' => 'update_streams', 'id' => $rIDs]));

	foreach (array_keys(XCMS::$rServers) as $rServerID) {
		$db->query('INSERT INTO `signals`(`server_id`, `time`, `custom_data`, `cache`) VALUES(?, ?, ?, 1);', $rServerID, time(), json_encode(['type' => 'delete_vods', 'id' => $rIDs]));
	}

	return true;
}

function loadCLI()
{
	global $db;
	global $rMethod;
	global $rID;

	switch ($rMethod) {
	case 'images':
		$rImages = [];
		$db->query('SELECT COUNT(*) AS `count` FROM `streams`;');
		$rCount = $db->get_row()['count'];

		if (0 < $rCount) {
			$rSteps = range(0, $rCount, 1000);

			if (!$rSteps) {
				$rSteps = [0];
			}

			foreach ($rSteps as $rStep) {
				try {
					$db->query('SELECT `stream_icon`, `movie_properties` FROM `streams` LIMIT ' . $rStep . ', 1000;');
					$rResults = $db->get_rows();

					foreach ($rResults as $rResult) {
						$rProperties = json_decode($rResult['movie_properties'], true);
						if (!empty($rResult['stream_icon']) && (substr($rResult['stream_icon'], 0, 2) == 's:')) {
							$rImages[] = $rResult['stream_icon'];
						}
						if (!empty($rProperties['movie_image']) && (substr($rProperties['movie_image'], 0, 2) == 's:')) {
							$rImages[] = $rProperties['movie_image'];
						}
						if (!empty($rProperties['cover_big']) && (substr($rProperties['cover_big'], 0, 2) == 's:')) {
							$rImages[] = $rProperties['cover_big'];
						}
						if (!empty($rProperties['backdrop_path'][0]) && (substr($rProperties['backdrop_path'][0], 0, 2) == 's:')) {
							$rImages[] = $rProperties['backdrop_path'][0];
						}
					}
				}
				catch (Exception $e) {
					echo 'Error: ' . $e . "\n";
				}
			}
		}

		$db->query('SELECT COUNT(*) AS `count` FROM `streams_series`;');
		$rCount = $db->get_row()['count'];

		if (0 < $rCount) {
			$rSteps = range(0, $rCount, 1000);

			if (!$rSteps) {
				$rSteps = [0];
			}

			foreach ($rSteps as $rStep) {
				try {
					$db->query('SELECT `cover`, `cover_big` FROM `streams_series` LIMIT ' . $rStep . ', 1000;');
					$rResults = $db->get_rows();

					foreach ($rResults as $rResult) {
						if (!empty($rResult['cover']) && (substr($rResult['cover'], 0, 2) == 's:')) {
							$rImages[] = $rResult['cover'];
						}
						if (!empty($rResult['cover_big']) && (substr($rResult['cover_big'], 0, 2) == 's:')) {
							$rImages[] = $rResult['cover_big'];
						}
					}
				}
				catch (Exception $e) {
					echo 'Error: ' . $e . "\n";
				}
			}
		}

		$rImages = array_unique($rImages);

		foreach ($rImages as $rImage) {
			$rSplit = explode(':', $rImage, 3);

			if ((int) $rSplit[1] == SERVER_ID) {
				$rImageSplit = explode('/', $rSplit[2]);
				$rPathInfo = pathinfo($rImageSplit[count($rImageSplit) - 1]);
				$rImage = $rPathInfo['filename'];
				$rOriginalURL = Xcms\Functions::decrypt($rImage, XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA);
				if (!empty($rOriginalURL) && (substr($rOriginalURL, 0, 4) == 'http')) {
					if (!file_exists(IMAGES_PATH . $rPathInfo['basename'])) {
						echo 'Downloading: ' . $rOriginalURL . "\n";
						XCMS::downloadImage($rOriginalURL);
					}
				}
			}
		}

		break;
	case 'duplicates':
		$rGroups = $rStreamIDs = [];
		$db->query('SELECT `a`.`id`, `a`.`stream_source` FROM `streams` `a` INNER JOIN (SELECT  `stream_source`, COUNT(*) `totalCount` FROM `streams` WHERE `type` IN (2,5) GROUP BY `stream_source`) `b` ON `a`.`stream_source` = `b`.`stream_source` WHERE `b`.`totalCount` > 1;');

		foreach ($db->get_rows() as $rRow) {
			$rGroups[md5($rRow['stream_source'])][] = $rRow['id'];
		}

		foreach ($rGroups as $rID => $rGroupIDs) {
			array_shift($rGroupIDs);

			foreach ($rGroupIDs as $rStreamID) {
				$rStreamIDs[] = (int) $rStreamID;
			}
		}

		if (0 < count($rStreamIDs)) {
			foreach (array_chunk($rStreamIDs, 100) as $rChunk) {
				deletestreams($rChunk);
			}
		}

		break;
	case 'bouquets':
		$rStreamIDs = [
			[],
			[]
		];
		$db->query('SELECT `id` FROM `streams`;');

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rRow) {
				$rStreamIDs[0][] = (int) $rRow['id'];
			}
		}

		$db->query('SELECT `id` FROM `streams_series`;');

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rRow) {
				$rStreamIDs[1][] = (int) $rRow['id'];
			}
		}

		$db->query('SELECT * FROM `bouquets` ORDER BY `bouquet_order` ASC;');

		if (0 < $db->num_rows()) {
			foreach ($db->get_rows() as $rBouquet) {
				$rUpdate = [
					[],
					[],
					[],
					[]
				];

				foreach (json_decode($rBouquet['bouquet_channels'], true) as $rID) {
					if ((0 < (int) $rID) && in_array((int) $rID, $rStreamIDs[0])) {
						$rUpdate[0][] = (int) $rID;
					}
				}

				foreach (json_decode($rBouquet['bouquet_movies'], true) as $rID) {
					if ((0 < (int) $rID) && in_array((int) $rID, $rStreamIDs[0])) {
						$rUpdate[1][] = (int) $rID;
					}
				}

				foreach (json_decode($rBouquet['bouquet_radios'], true) as $rID) {
					if ((0 < (int) $rID) && in_array((int) $rID, $rStreamIDs[0])) {
						$rUpdate[2][] = (int) $rID;
					}
				}

				foreach (json_decode($rBouquet['bouquet_series'], true) as $rID) {
					if ((0 < (int) $rID) && in_array((int) $rID, $rStreamIDs[1])) {
						$rUpdate[3][] = (int) $rID;
					}
				}

				$db->query('UPDATE `bouquets` SET `bouquet_channels` = \'[' . implode(',', array_map('intval', $rUpdate[0])) . ']\', `bouquet_movies` = \'[' . implode(',', array_map('intval', $rUpdate[1])) . ']\', `bouquet_radios` = \'[' . implode(',', array_map('intval', $rUpdate[2])) . ']\', `bouquet_series` = \'[' . implode(',', array_map('intval', $rUpdate[3])) . ']\' WHERE `id` = ?;', $rBouquet['id']);
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
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

set_time_limit(0);
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
$rMethod = (1 < count($argv) ? $argv[1] : NULL);
loadcli();

?>