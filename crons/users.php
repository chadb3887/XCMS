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

function processDeletions($rDelete, $rDelStream = [])
{
	global $db;
	$rTime = time();

	if (XCMS::$rSettings['redis_handler']) {
		if (0 < $rDelete['count']) {
			$rRedis = XCMS::$redis->multi();

			foreach ($rDelete['line'] as $rUserID => $rUUIDs) {
				$rRedis->zRem('LINE#' . $rUserID, ...$rUUIDs);
				$rRedis->zRem('LINE_ALL#' . $rUserID, ...$rUUIDs);
			}

			foreach ($rDelete['stream'] as $rStreamID => $rUUIDs) {
				$rRedis->zRem('STREAM#' . $rStreamID, ...$rUUIDs);
			}

			foreach ($rDelete['server'] as $rServerID => $rUUIDs) {
				$rRedis->zRem('SERVER#' . $rServerID, ...$rUUIDs);
				$rRedis->zRem('SERVER_LINES#' . $rServerID, ...$rUUIDs);
			}

			foreach ($rDelete['proxy'] as $rProxyID => $rUUIDs) {
				$rRedis->zRem('PROXY#' . $rProxyID, ...$rUUIDs);
			}

			if (0 < count($rDelete['uuid'])) {
				$rRedis->zRem('CONNECTIONS', ...$rDelete['uuid']);
				$rRedis->zRem('LIVE', ...$rDelete['uuid']);
				$rRedis->sRem('ENDED', ...$rDelete['uuid']);
				$rRedis->del(...$rDelete['uuid']);
			}

			$rRedis->exec();
		}
	}
	else {
		foreach ($rDelete as $rServerID => $rConnections) {
			if (0 < count($rConnections)) {
				$db->query('DELETE FROM `lines_live` WHERE `uuid` IN (\'' . implode('\',\'', $rConnections) . '\')');
			}
		}
	}

	foreach (XCMS::$rSettings['redis_handler'] ? $rDelete['server'] : $rDelete as $rServerID => $rConnections) {
		if ($rServerID != SERVER_ID) {
			$rQuery = '';

			foreach ($rConnections as $rConnection) {
				$rQuery .= '(' . $rServerID . ',1,' . $rTime . ',' . $db->escape(json_encode(['type' => 'delete_con', 'uuid' => $rConnection])) . '),';
			}

			$rQuery = rtrim($rQuery, ',');

			if (!empty($rQuery)) {
				$db->query('INSERT INTO `signals`(`server_id`, `cache`, `time`, `custom_data`) VALUES ' . $rQuery . ';');
			}
		}
	}

	foreach ($rDelStream as $rStreamID => $rConnections) {
		foreach ($rConnections as $rConnection) {
			@unlink(CONS_TMP_PATH . $rStreamID . '/' . $rConnection);
		}
	}

	if (XCMS::$rSettings['redis_handler']) {
		return [
			'line'         => [],
			'server'       => [],
			'server_lines' => [],
			'proxy'        => [],
			'stream'       => [],
			'uuid'         => [],
			'count'        => 0
		];
	}
	else {
		return [];
	}
}

function loadCron()
{
	global $db;
	global $rPHPPIDs;

	if (XCMS::$rSettings['redis_handler']) {
		XCMS::connectRedis();
	}

	$rStartTime = time();
	if (!XCMS::$rSettings['redis_handler'] || XCMS::$rServers[SERVER_ID]['is_main']) {
		$rAutoKick = XCMS::$rSettings['user_auto_kick_hours'] * 3600;
		$rLiveKeys = $rDelete = $rDeleteStream = [];

		if (XCMS::$rSettings['redis_handler']) {
			$rRedisDelete = [
				'line'         => [],
				'server'       => [],
				'server_lines' => [],
				'proxy'        => [],
				'stream'       => [],
				'uuid'         => [],
				'count'        => 0
			];
			$rUsers = [];
			list($rKeys, $rConnections) = XCMS::getConnections();
			$rSize = count($rConnections);

			for ($i = 0; $i < $rSize; ++$i) {
				$rConnection = $rConnections[$i];

				if (is_array($rConnection)) {
					$rUsers[$rConnection['identity']][] = $rConnection;
					$rLiveKeys[] = $rConnection['uuid'];
				}
				else {
					$rRedisDelete['count']++;
					$rRedisDelete['uuid'][] = $rKeys[$i];
				}
			}

			unset($rConnections);
		}
		else {
			$rUsers = XCMS::getConnections(XCMS::$rServers[SERVER_ID]['is_main'] ? NULL : SERVER_ID);
		}

		$rRestreamerArray = $rMaxConnectionsArray = [];
		$rUserIDs = XCMS::confirmIDs(array_keys($rUsers));

		if (0 < count($rUserIDs)) {
			$db->query('SELECT `id`, `max_connections`, `is_restreamer` FROM `lines` WHERE `id` IN (' . implode(',', $rUserIDs) . ');');

			foreach ($db->get_rows() as $rRow) {
				$rMaxConnectionsArray[$rRow['id']] = $rRow['max_connections'];
				$rRestreamerArray[$rRow['id']] = $rRow['is_restreamer'];
			}
		}
		if (XCMS::$rSettings['redis_handler'] && XCMS::$rServers[SERVER_ID]['is_main']) {
			foreach (XCMS::getEnded() as $rConnection) {
				if (is_array($rConnection)) {
					if (!in_array($rConnection['container'], ['ts' => true, 'hls' => true, 'rtmp' => true]) && ((time() - $rConnection['hls_last_read']) < 300)) {
						$rClose = false;
					}
					else {
						$rClose = true;
					}

					if ($rClose) {
						echo 'Close connection: ' . $rConnection['uuid'] . "\n";
						XCMS::closeConnection($rConnection, false, false);
						$rRedisDelete['count']++;
						$rRedisDelete['line'][$rConnection['identity']][] = $rConnection['uuid'];
						$rRedisDelete['stream'][$rConnection['stream_id']][] = $rConnection['uuid'];
						$rRedisDelete['server'][$rConnection['server_id']][] = $rConnection['uuid'];
						$rRedisDelete['uuid'][] = $rConnection['uuid'];

						if ($rConnection['proxy_id']) {
							$rRedisDelete['proxy'][$rConnection['proxy_id']][] = $rConnection['uuid'];
						}
					}
				}
			}

			if (1000 <= $rRedisDelete['count']) {
				$rRedisDelete = processdeletions($rRedisDelete, $rRedisDelete['stream']);
			}
		}

		foreach ($rUsers as $rUserID => $rConnections) {
			$rActiveCount = 0;
			$rMaxConnections = $rMaxConnectionsArray[$rUserID];
			$rIsRestreamer = $rRestreamerArray[$rUserID] ?: false;

			foreach ($rConnections as $rKey => $rConnection) {
				if (($rConnection['server_id'] == SERVER_ID) || XCMS::$rSettings['redis_handler']) {
					if (!is_null($rConnection['exp_date']) && ($rConnection['exp_date'] < $rStartTime)) {
						echo 'Close connection: ' . $rConnection['uuid'] . "\n";
						XCMS::closeConnection($rConnection, false, false);

						if (XCMS::$rSettings['redis_handler']) {
							$rRedisDelete['count']++;
							$rRedisDelete['line'][$rConnection['identity']][] = $rConnection['uuid'];
							$rRedisDelete['stream'][$rConnection['stream_id']][] = $rConnection['uuid'];
							$rRedisDelete['server'][$rConnection['server_id']][] = $rConnection['uuid'];
							$rRedisDelete['uuid'][] = $rConnection['uuid'];

							if ($rConnection['user_id']) {
								$rRedisDelete['server_lines'][$rConnection['server_id']][] = $rConnection['uuid'];
							}

							if ($rConnection['proxy_id']) {
								$rRedisDelete['proxy'][$rConnection['proxy_id']][] = $rConnection['uuid'];
							}
						}
						else {
							$rDeleteStream[$rConnection['stream_id']] = $rDelete[$rConnection['server_id']][] = $rConnection['uuid'];
						}

						continue;
					}

					$rTotalTime = $rStartTime - $rConnection['date_start'];
					if (($rAutoKick != 0) && ($rAutoKick <= $rTotalTime) && !$rIsRestreamer) {
						echo 'Close connection: ' . $rConnection['uuid'] . "\n";
						XCMS::closeConnection($rConnection, false, false);

						if (XCMS::$rSettings['redis_handler']) {
							$rRedisDelete['count']++;
							$rRedisDelete['line'][$rConnection['identity']][] = $rConnection['uuid'];
							$rRedisDelete['stream'][$rConnection['stream_id']][] = $rConnection['uuid'];
							$rRedisDelete['server'][$rConnection['server_id']][] = $rConnection['uuid'];
							$rRedisDelete['uuid'][] = $rConnection['uuid'];

							if ($rConnection['user_id']) {
								$rRedisDelete['server_lines'][$rConnection['server_id']][] = $rConnection['uuid'];
							}

							if ($rConnection['proxy_id']) {
								$rRedisDelete['proxy'][$rConnection['proxy_id']][] = $rConnection['uuid'];
							}
						}
						else {
							$rDeleteStream[$rConnection['stream_id']] = $rDelete[$rConnection['server_id']][] = $rConnection['uuid'];
						}

						continue;
					}

					if ($rConnection['container'] == 'hls') {
						if ((30 <= $rStartTime - $rConnection['hls_last_read']) || ($rConnection['hls_end'] == 1)) {
							echo 'Close connection: ' . $rConnection['uuid'] . "\n";
							XCMS::closeConnection($rConnection, false, false);

							if (XCMS::$rSettings['redis_handler']) {
								$rRedisDelete['count']++;
								$rRedisDelete['line'][$rConnection['identity']][] = $rConnection['uuid'];
								$rRedisDelete['stream'][$rConnection['stream_id']][] = $rConnection['uuid'];
								$rRedisDelete['server'][$rConnection['server_id']][] = $rConnection['uuid'];
								$rRedisDelete['uuid'][] = $rConnection['uuid'];

								if ($rConnection['user_id']) {
									$rRedisDelete['server_lines'][$rConnection['server_id']][] = $rConnection['uuid'];
								}

								if ($rConnection['proxy_id']) {
									$rRedisDelete['proxy'][$rConnection['proxy_id']][] = $rConnection['uuid'];
								}
							}
							else {
								$rDeleteStream[$rConnection['stream_id']] = $rDelete[$rConnection['server_id']][] = $rConnection['uuid'];
							}

							continue;
						}
					}
					else if ($rConnection['container'] != 'rtmp') {
						if ($rConnection['server_id'] == SERVER_ID) {
							$rIsRunning = XCMS::isProcessRunning($rConnection['pid'], 'php-fpm');
						}
						else if (($rConnection['date_start'] <= XCMS::$rServers[$rConnection['server_id']]['last_check_ago'] - 1) && (0 < count($rPHPPIDs[$rConnection['server_id']]))) {
							$rIsRunning = in_array((int) $rConnection['pid'], $rPHPPIDs[$rConnection['server_id']]);
						}
						else {
							$rIsRunning = true;
						}
						if ((($rConnection['hls_end'] == 1) && (300 <= $rStartTime - $rConnection['hls_last_read'])) || !$rIsRunning) {
							echo 'Close connection: ' . $rConnection['uuid'] . "\n";
							XCMS::closeConnection($rConnection, false, false);

							if (XCMS::$rSettings['redis_handler']) {
								$rRedisDelete['count']++;
								$rRedisDelete['line'][$rConnection['identity']][] = $rConnection['uuid'];
								$rRedisDelete['stream'][$rConnection['stream_id']][] = $rConnection['uuid'];
								$rRedisDelete['server'][$rConnection['server_id']][] = $rConnection['uuid'];
								$rRedisDelete['uuid'][] = $rConnection['uuid'];

								if ($rConnection['user_id']) {
									$rRedisDelete['server_lines'][$rConnection['server_id']][] = $rConnection['uuid'];
								}

								if ($rConnection['proxy_id']) {
									$rRedisDelete['proxy'][$rConnection['proxy_id']][] = $rConnection['uuid'];
								}
							}
							else {
								$rDeleteStream[$rConnection['stream_id']] = $rDelete[$rConnection['server_id']][] = $rConnection['uuid'];
							}

							continue;
						}
					}
				}

				if (!$rConnection['hls_end']) {
					$rActiveCount++;
				}
			}
			if (XCMS::$rServers[SERVER_ID]['is_main'] && (0 < $rMaxConnections) && ($rMaxConnections < $rActiveCount)) {
				foreach ($rConnections as $rKey => $rConnection) {
					if (!$rConnection['hls_end']) {
						echo 'Close connection: ' . $rConnection['uuid'] . "\n";
						XCMS::closeConnection($rConnection, false, false);

						if (XCMS::$rSettings['redis_handler']) {
							$rRedisDelete['count']++;
							$rRedisDelete['line'][$rConnection['identity']][] = $rConnection['uuid'];
							$rRedisDelete['stream'][$rConnection['stream_id']][] = $rConnection['uuid'];
							$rRedisDelete['server'][$rConnection['server_id']][] = $rConnection['uuid'];
							$rRedisDelete['uuid'][] = $rConnection['uuid'];

							if ($rConnection['user_id']) {
								$rRedisDelete['server_lines'][$rConnection['server_id']][] = $rConnection['uuid'];
							}

							if ($rConnection['proxy_id']) {
								$rRedisDelete['proxy'][$rConnection['proxy_id']][] = $rConnection['uuid'];
							}
						}
						else {
							$rDeleteStream[$rConnection['stream_id']] = $rDelete[$rConnection['server_id']][] = $rConnection['uuid'];
						}

						$rActiveCount--;
					}

					if ($rActiveCount <= $rMaxConnections) {
						break;
					}
				}
			}
			if (XCMS::$rSettings['redis_handler'] && (1000 <= $rRedisDelete['count'])) {
				$rRedisDelete = processdeletions($rRedisDelete, $rRedisDelete['stream']);
			}
			else if (!XCMS::$rSettings['redis_handler'] && (1000 <= count($rDelete))) {
				$rDelete = processdeletions($rDelete, $rDeleteStream);
			}
		}
		if (XCMS::$rSettings['redis_handler'] && (0 < $rRedisDelete['count'])) {
			processdeletions($rRedisDelete, $rRedisDelete['stream']);
		}
		else if (!XCMS::$rSettings['redis_handler'] && (0 < count($rDelete))) {
			processdeletions($rDelete, $rDeleteStream);
		}
	}

	$rConnectionSpeeds = glob(DIVERGENCE_TMP_PATH . '*');

	if (0 < count($rConnectionSpeeds)) {
		if (XCMS::$rSettings['redis_handler']) {
			$rStreamMap = $rBitrates = [];
			$db->query('SELECT `stream_id`, `bitrate` FROM `streams_servers` WHERE `server_id` = ? AND `bitrate` IS NOT NULL;', SERVER_ID);

			foreach ($db->get_rows() as $rRow) {
				$rStreamMap[(int) $rRow['stream_id']] = (int) (($rRow['bitrate'] / 8) * 0.92);
			}

			$rUUIDs = [];

			foreach ($rConnectionSpeeds as $rConnectionSpeed) {
				if (empty($rConnectionSpeed)) {
					continue;
				}

				$rUUIDs[] = basename($rConnectionSpeed);
			}

			if (0 < count($rUUIDs)) {
				$rConnections = array_map('igbinary_unserialize', XCMS::$redis->mGet($rUUIDs));

				foreach ($rConnections as $rConnection) {
					if (is_array($rConnection)) {
						$rBitrates[$rConnection['uuid']] = $rStreamMap[(int) $rConnection['stream_id']];
					}
				}
			}

			unset($rStreamMap);
		}
		else {
			$rBitrates = [];
			$db->query('SELECT `lines_live`.`uuid`, `streams_servers`.`bitrate` FROM `lines_live` LEFT JOIN `streams_servers` ON `lines_live`.`stream_id` = `streams_servers`.`stream_id` AND `lines_live`.`server_id` = `streams_servers`.`server_id` WHERE `lines_live`.`server_id` = ?;', SERVER_ID);

			foreach ($db->get_rows() as $rRow) {
				$rBitrates[$rRow['uuid']] = (int) (($rRow['bitrate'] / 8) * 0.92);
			}
		}

		if (!XCMS::$rSettings['redis_handler']) {
			$rUUIDMap = [];
			$db->query('SELECT `uuid`, `activity_id` FROM `lines_live`;');

			foreach ($db->get_rows() as $rRow) {
				$rUUIDMap[$rRow['uuid']] = $rRow['activity_id'];
			}
		}

		$rLiveQuery = $rDivergenceUpdate = [];

		foreach ($rConnectionSpeeds as $rConnectionSpeed) {
			if (empty($rConnectionSpeed)) {
				continue;
			}

			$rUUID = basename($rConnectionSpeed);
			$rAverageSpeed = (int) file_get_contents($rConnectionSpeed);
			$rDivergence = (int) ((($rAverageSpeed - $rBitrates[$rUUID]) / $rBitrates[$rUUID]) * 100);

			if (0 < $rDivergence) {
				$rDivergence = 0;
			}

			$rDivergenceUpdate[] = '(\'' . $rUUID . '\', ' . abs($rDivergence) . ')';
			if (!XCMS::$rSettings['redis_handler'] && isset($rUUIDMap[$rUUID])) {
				$rLiveQuery[] = '(' . $rUUIDMap[$rUUID] . ', ' . abs($rDivergence) . ')';
			}
		}

		if (0 < count($rDivergenceUpdate)) {
			$rUpdateQuery = implode(',', $rDivergenceUpdate);
			$db->query('INSERT INTO `lines_divergence`(`uuid`,`divergence`) VALUES ' . $rUpdateQuery . ' ON DUPLICATE KEY UPDATE `divergence`=VALUES(`divergence`);');
		}
		if (!XCMS::$rSettings['redis_handler'] && (0 < count($rLiveQuery))) {
			$rLiveQuery = implode(',', $rLiveQuery);
			$db->query('INSERT INTO `lines_live`(`activity_id`,`divergence`) VALUES ' . $rLiveQuery . ' ON DUPLICATE KEY UPDATE `divergence`=VALUES(`divergence`);');
		}

		shell_exec('rm -f ' . DIVERGENCE_TMP_PATH . '*');
	}

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		if (XCMS::$rSettings['redis_handler']) {
			$db->query('DELETE FROM `lines_divergence` WHERE `uuid` NOT IN (\'' . implode('\',\'', $rLiveKeys) . '\');');
		}
		else {
			$db->query('DELETE FROM `lines_divergence` WHERE `uuid` NOT IN (SELECT `uuid` FROM `lines_live`);');
		}
	}

	if (XCMS::$rServers[SERVER_ID]['is_main']) {
		$db->query('DELETE FROM `lines_live` WHERE `uuid` IS NULL;');
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

set_time_limit(0);
ini_set('memory_limit', -1);
ini_set('default_socket_timeout', -1);

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
cli_set_process_title('XCMS[Users]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
$rSync = NULL;
if ((count($argv) == 2) && XCMS::$rServers[SERVER_ID]['is_main']) {
	XCMS::connectRedis();

	if (!is_object(XCMS::$redis)) {
		exit('Couldn\'t connect to Redis.' . "\n");
	}

	$rSync = (int) $argv[1];

	if ($rSync == 1) {
		$rDeSync = $rRedisUsers = $rRedisUpdate = $rRedisSet = [];
		$db->query('SELECT * FROM `lines_live` WHERE `hls_end` = 0;');
		$rRows = $db->get_rows();

		if (0 < count($rRows)) {
			$rStreamIDs = [];

			foreach ($rRows as $rRow) {
				if (!in_array($rRow['stream_id'], $rStreamIDs) && (0 < $rRow['stream_id'])) {
					$rStreamIDs[] = (int) $rRow['stream_id'];
				}
			}

			$rOnDemand = [];

			if (0 < count($rStreamIDs)) {
				$db->query('SELECT `stream_id`, `server_id`, `on_demand` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', $rStreamIDs) . ');');

				foreach ($db->get_rows() as $rRow) {
					$rOnDemand[$rRow['stream_id']][$rRow['server_id']] = (int) $rRow['on_demand'];
				}
			}

			$rRedis = XCMS::$redis->multi();

			foreach ($rRows as $rRow) {
				echo 'Resynchronising UUID: ' . $rRow['uuid'] . "\n";

				if (empty($rRow['hmac_id'])) {
					$rRow['identity'] = $rRow['user_id'];
				}
				else {
					$rRow['identity'] = $rRow['hmac_id'] . '_' . $rRow['hmac_identifier'];
				}

				$rRow['on_demand'] = $rOnDemand[$rRow['stream_id']][$rRow['server_id']] ?: 0;
				$rRedis->zAdd('LINE#' . $rRow['identity'], $rRow['date_start'], $rRow['uuid']);
				$rRedis->zAdd('LINE_ALL#' . $rRow['identity'], $rRow['date_start'], $rRow['uuid']);
				$rRedis->zAdd('STREAM#' . $rRow['stream_id'], $rRow['date_start'], $rRow['uuid']);
				$rRedis->zAdd('SERVER#' . $rRow['server_id'], $rRow['date_start'], $rRow['uuid']);

				if ($rRow['user_id']) {
					$rRedis->zAdd('SERVER_LINES#' . $rRow['server_id'], $rRow['user_id'], $rRow['uuid']);
				}

				if ($rRow['proxy_id']) {
					$rRedis->zAdd('PROXY#' . $rRow['proxy_id'], $rRow['date_start'], $rRow['uuid']);
				}

				$rRedis->zAdd('CONNECTIONS', $rRow['date_start'], $rRow['uuid']);
				$rRedis->zAdd('LIVE', $rRow['date_start'], $rRow['uuid']);
				$rRedis->set($rRow['uuid'], igbinary_serialize($rRow));
				$rDeSync[] = $rRow['uuid'];
			}

			$rRedis->exec();

			if (0 < count($rDeSync)) {
				$db->query('DELETE FROM `lines_live` WHERE `uuid` IN (\'' . implode('\',\'', $rDeSync) . '\');');
			}
		}
	}
}
if (XCMS::$rSettings['redis_handler'] && XCMS::$rServers[SERVER_ID]['is_main']) {
	XCMS::$rServers = XCMS::getServers(true);
	$rPHPPIDs = [];

	foreach (XCMS::$rServers as $rServer) {
		$rPHPPIDs[$rServer['id']] = array_map('intval', json_decode($rServer['php_pids'], true)) ?: [];
	}
}

loadcron();

?>