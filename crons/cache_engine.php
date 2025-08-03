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

class Thread
{
	public $process = null;
	public $pipes = null;
	public $buffer = null;
	public $output = null;
	public $error = null;
	public $timeout = null;
	public $start_time = null;

	public function __construct()
	{
		$this->process = 0;
		$this->buffer = '';
		$this->pipes = (array) NULL;
		$this->output = '';
		$this->error = '';
		$this->start_time = time();
		$this->timeout = 0;
	}

	static public function create($command)
	{
		$t = new Thread();
		$descriptor = [
			['pipe', 'r'],
			['pipe', 'w'],
			['pipe', 'w']
		];
		$t->process = proc_open($command, $descriptor, $t->pipes);
		stream_set_blocking($t->pipes[1], 0);
		stream_set_blocking($t->pipes[2], 0);
		return $t;
	}

	public function isActive()
	{
		$this->buffer .= $this->listen();
		$f = stream_get_meta_data($this->pipes[1]);
		return !$f['eof'];
	}

	public function close()
	{
		$r = proc_close($this->process);
		$this->process = NULL;
		return $r;
	}

	public function tell($thought)
	{
		fwrite($this->pipes[0], $thought);
	}

	public function listen()
	{
		$buffer = $this->buffer;
		$this->buffer = '';

		while ($r = fgets($this->pipes[1], 1024)) {
			$buffer .= $r;
			$this->output .= $r;
		}

		return $buffer;
	}

	public function getStatus()
	{
		return proc_get_status($this->process);
	}

	public function isBusy()
	{
		return (0 < $this->timeout) && (($this->start_time + $this->timeout) < time());
	}

	public function getError()
	{
		$buffer = '';

		while ($r = fgets($this->pipes[2], 1024)) {
			$buffer .= $r;
		}

		return $buffer;
	}
}

class Multithread
{
	public $output = [];
	public $error = [];
	public $thread = null;
	public $commands = [];
	public $hasPool = false;
	public $toExecuted = [];

	public function __construct($commands, $sizePool = 0)
	{
		$this->hasPool = 0 < $sizePool;

		if ($this->hasPool) {
			$this->toExecuted = array_splice($commands, $sizePool);
		}

		$this->commands = $commands;

		foreach ($this->commands as $key => $command) {
			$this->thread[$key] = Thread::create($command);
		}
	}

	public function run()
	{
		while (0 < count($this->commands)) {
			foreach ($this->commands as $key => $command) {
				@$this->output[$command] .= $this->thread[$key]->listen();
				@$this->error[$command] .= $this->thread[$key]->getError();

				if ($this->thread[$key]->isActive()) {
					$this->output[$command] .= $this->thread[$key]->listen();

					if ($this->thread[$key]->isBusy()) {
						$this->thread[$key]->close();
						unset($this->commands[$key]);
						self::launchNextInQueue();
					}
				}
				else {
					$this->thread[$key]->close();
					unset($this->commands[$key]);
					self::launchNextInQueue();
				}
			}
		}

		return $this->output;
	}

	public function launchNextInQueue()
	{
		if (count($this->toExecuted) == 0) {
			return true;
		}

		reset($this->toExecuted);
		$keyToExecuted = key($this->toExecuted);
		$this->commands[$keyToExecuted] = $this->toExecuted[$keyToExecuted];
		$this->thread[$keyToExecuted] = Thread::create($this->toExecuted[$keyToExecuted]);
		unset($this->toExecuted[$keyToExecuted]);
	}
}

function generateLines($rStart = NULL, $rCount = NULL, $rForceIDs = [])
{
	global $db;
	global $rSplit;
	global $rForce;

	if (is_null($rCount)) {
		$rCount = count($rForceIDs);
	}

	if (0 < $rCount) {
		if (!is_null($rStart)) {
			$rSteps = range($rStart, ($rStart + $rCount) - 1, $rSplit);

			if (!$rSteps) {
				$rSteps = [$rStart];
			}
		}
		else {
			$rSteps = [NULL];
		}

		$rExists = [];

		foreach ($rSteps as $rStep) {
			if (!is_null($rStep)) {
				if (($rStart + $rCount) < ($rStep + $rSplit)) {
					$rMax = ($rStart + $rCount) - $rStep;
				}
				else {
					$rMax = $rSplit;
				}

				$db->query('SELECT `id`, `username`, `password`, `exp_date`, `created_at`, `admin_enabled`, `enabled`, `bouquet`, `allowed_outputs`, `max_connections`, `is_trial`, `is_restreamer`, `is_stalker`, `is_mag`, `is_e2`, `is_isplock`, `allowed_ips`, `allowed_ua`, `pair_id`, `force_server_id`, `isp_desc`, `forced_country`, `bypass_ua`, `last_expiration_video`, `access_token`, `mag_devices`.`token` AS `mag_token`, `admin_notes`, `reseller_notes` FROM `lines` LEFT JOIN `mag_devices` ON `mag_devices`.`user_id` = `lines`.`id` LIMIT ' . $rStep . ', ' . $rMax . ';');
			}
			else {
				$db->query('SELECT `id`, `username`, `password`, `exp_date`, `created_at`, `admin_enabled`, `enabled`, `bouquet`, `allowed_outputs`, `max_connections`, `is_trial`, `is_restreamer`, `is_stalker`, `is_mag`, `is_e2`, `is_isplock`, `allowed_ips`, `allowed_ua`, `pair_id`, `force_server_id`, `isp_desc`, `forced_country`, `bypass_ua`, `last_expiration_video`, `access_token`, `mag_devices`.`token` AS `mag_token`, `admin_notes`, `reseller_notes` FROM `lines` LEFT JOIN `mag_devices` ON `mag_devices`.`user_id` = `lines`.`id` WHERE `id` IN (' . implode(',', $rForceIDs) . ');');
			}

			if ($db->result) {
				if (0 < $db->result->rowCount()) {
					foreach ($db->result->fetchAll(PDO::FETCH_ASSOC) as $rUserInfo) {
						$rExists[] = $rUserInfo['id'];
						file_put_contents(LINES_TMP_PATH . 'line_i_' . $rUserInfo['id'], igbinary_serialize($rUserInfo));
						$rKey = (XCMS::$rSettings['case_sensitive_line'] ? $rUserInfo['username'] . '_' . $rUserInfo['password'] : strtolower($rUserInfo['username'] . '_' . $rUserInfo['password']));
						file_put_contents(LINES_TMP_PATH . 'line_c_' . $rKey, $rUserInfo['id']);

						if (!empty($rUserInfo['access_token'])) {
							file_put_contents(LINES_TMP_PATH . 'line_t_' . $rUserInfo['access_token'], $rUserInfo['id']);
						}
					}
				}

				$db->result = NULL;
			}
		}

		if (0 < count($rForceIDs)) {
			foreach ($rForceIDs as $rForceID) {
				if (!in_array($rForceID, $rExists) && file_exists(LINES_TMP_PATH . 'line_i_' . $rForceID)) {
					unlink(LINES_TMP_PATH . 'line_i_' . $rForceID);
				}
			}
		}
	}
}

function generateStreams($rStart = NULL, $rCount = NULL, $rForceIDs = [])
{
	global $db;
	global $rSplit;
	global $rForce;

	if (is_null($rCount)) {
		$rCount = count($rForceIDs);
	}

	if (0 < $rCount) {
		$rBouquetMap = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'bouquet_map'));

		if (!is_null($rStart)) {
			$rSteps = range($rStart, ($rStart + $rCount) - 1, $rSplit);

			if (!$rSteps) {
				$rSteps = [$rStart];
			}
		}
		else {
			$rSteps = [NULL];
		}

		$rExists = [];

		foreach ($rSteps as $rStep) {
			if (!is_null($rStep)) {
				if (($rStart + $rCount) < ($rStep + $rSplit)) {
					$rMax = ($rStart + $rCount) - $rStep;
				}
				else {
					$rMax = $rSplit;
				}

				$db->query('SELECT t1.id,t1.epg_id,t1.added,t1.allow_record,t1.year,t1.channel_id,t1.movie_properties,t1.stream_source,t1.tv_archive_server_id,t1.vframes_server_id,t1.tv_archive_duration,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t1.series_no,t1.direct_source,t1.direct_proxy,t2.type_output,t1.target_container,t2.live,t1.rtmp_output,t1.order,t2.type_key,t1.tmdb_id,t1.adaptive_link FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type LIMIT ' . $rStep . ', ' . $rMax . ';');
			}
			else {
				$db->query('SELECT t1.id,t1.epg_id,t1.added,t1.allow_record,t1.year,t1.channel_id,t1.movie_properties,t1.stream_source,t1.tv_archive_server_id,t1.vframes_server_id,t1.tv_archive_duration,t1.stream_icon,t1.custom_sid,t1.category_id,t1.stream_display_name,t1.series_no,t1.direct_source,t1.direct_proxy,t2.type_output,t1.target_container,t2.live,t1.rtmp_output,t1.order,t2.type_key,t1.tmdb_id,t1.adaptive_link FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type WHERE `t1`.`id` IN (' . implode(',', $rForceIDs) . ');');
			}

			if ($db->result) {
				if (0 < $db->result->rowCount()) {
					$rRows = $db->result->fetchAll(PDO::FETCH_ASSOC);
					$rStreamMap = $rStreamIDs = [];

					foreach ($rRows as $rRow) {
						$rStreamIDs[] = $rRow['id'];
					}

					if (0 < count($rStreamIDs)) {
						$db->query('SELECT `stream_id`, `server_id`, `pid`, `to_analyze`, `stream_status`, `monitor_pid`, `on_demand`, `delay_available_at`, `bitrate`, `parent_id`, `on_demand`, `stream_info`, `video_codec`, `audio_codec`, `resolution`, `compatible` FROM `streams_servers` WHERE `stream_id` IN (' . implode(',', $rStreamIDs) . ')');

						if ($db->result) {
							if (0 < $db->result->rowCount()) {
								foreach ($db->result->fetchAll(PDO::FETCH_ASSOC) as $rRow) {
									$rStreamMap[(int) $rRow['stream_id']][(int) $rRow['server_id']] = $rRow;
								}
							}

							$db->result = NULL;
						}
					}

					foreach ($rRows as $rStreamInfo) {
						$rExists[] = $rStreamInfo['id'];

						if (!$rStreamInfo['direct_source']) {
							unset($rStreamInfo['stream_source']);
						}

						$rOutput = ['info' => $rStreamInfo, 'bouquets' => $rBouquetMap[(int) $rStreamInfo['id']] ?: [], 'servers' => $rStreamMap[(int) $rStreamInfo['id']] ?? []];
						file_put_contents(STREAMS_TMP_PATH . 'stream_' . $rStreamInfo['id'], igbinary_serialize($rOutput));
					}

					unset($rRows, $rStreamMap, $rStreamIDs);
				}

				$db->result = NULL;
			}
		}

		if (0 < count($rForceIDs)) {
			foreach ($rForceIDs as $rForceID) {
				if (!in_array($rForceID, $rExists) && file_exists(STREAMS_TMP_PATH . 'stream_' . $rForceID)) {
					unlink(STREAMS_TMP_PATH . 'stream_' . $rForceID);
				}
			}
		}
	}
}

function generateSeries($rStart, $rCount)
{
	global $db;
	global $rSplit;
	$rSeriesMap = [];
	$rSeriesEpisodes = [];

	if (0 < $rCount) {
		$rSteps = range($rStart, ($rStart + $rCount) - 1, $rSplit);

		if (!$rSteps) {
			$rSteps = [$rStart];
		}

		foreach ($rSteps as $rStep) {
			if (($rStart + $rCount) < ($rStep + $rSplit)) {
				$rMax = ($rStart + $rCount) - $rStep;
			}
			else {
				$rMax = $rSplit;
			}

			$db->query('SELECT `stream_id`, `series_id`, `season_num`, `episode_num` FROM `streams_episodes` WHERE `stream_id` IN (SELECT `id` FROM `streams` WHERE `type` = 5) ORDER BY `series_id` ASC, `season_num` ASC, `episode_num` ASC LIMIT ' . $rStep . ', ' . $rMax . ';');

			foreach ($db->get_rows() as $rRow) {
				if ($rRow['stream_id'] && $rRow['series_id']) {
					$rSeriesMap[(int) $rRow['stream_id']] = (int) $rRow['series_id'];

					if (!isset($rSeriesEpisodes[$rRow['series_id']])) {
						$rSeriesEpisodes[$rRow['series_id']] = [];
					}

					$rSeriesEpisodes[$rRow['series_id']][$rRow['season_num']][] = ['episode_num' => $rRow['episode_num'], 'stream_id' => $rRow['stream_id']];
				}
			}
		}
	}

	file_put_contents(SERIES_TMP_PATH . 'series_episodes_' . $rStart, igbinary_serialize($rSeriesEpisodes));
	file_put_contents(SERIES_TMP_PATH . 'series_map_' . $rStart, igbinary_serialize($rSeriesMap));
	unset($rSeriesMap);
}

function generateGroups()
{
	global $db;
	$db->query('SELECT `group_id` FROM `users_groups`;');

	foreach ($db->get_rows() as $rGroup) {
		$rBouquets = $rReturn = [];
		$db->query('SELECT * FROM `users_packages` WHERE JSON_CONTAINS(`groups`, ?, \'$\');', $rGroup['group_id']);

		foreach ($db->get_rows() as $rRow) {
			foreach (json_decode($rRow['bouquets'], true) as $rID) {
				if (!in_array($rID, $rBouquets)) {
					$rBouquets[] = $rID;
				}
			}

			if ($rRow['is_line']) {
				$rReturn['create_line'] = true;
			}

			if ($rRow['is_mag']) {
				$rReturn['create_mag'] = true;
			}

			if ($rRow['is_e2']) {
				$rReturn['create_enigma'] = true;
			}
		}

		if (0 < count($rBouquets)) {
			$db->query('SELECT * FROM `bouquets` WHERE `id` IN (' . implode(',', array_map('intval', $rBouquets)) . ');');
			$rSeriesIDs = [];
			$rStreamIDs = [];

			foreach ($db->get_rows() as $rRow) {
				if ($rRow['bouquet_channels']) {
					$rStreamIDs = array_merge($rStreamIDs, json_decode($rRow['bouquet_channels'], true));
				}

				if ($rRow['bouquet_movies']) {
					$rStreamIDs = array_merge($rStreamIDs, json_decode($rRow['bouquet_movies'], true));
				}

				if ($rRow['bouquet_radios']) {
					$rStreamIDs = array_merge($rStreamIDs, json_decode($rRow['bouquet_radios'], true));
				}

				foreach (json_decode($rRow['bouquet_series'], true) as $rSeriesID) {
					$rSeriesIDs[] = $rSeriesID;
					$db->query('SELECT `stream_id` FROM `streams_episodes` WHERE `series_id` = ?;', $rSeriesID);

					foreach ($db->get_rows() as $rEpisode) {
						$rStreamIDs[] = $rEpisode['stream_id'];
					}
				}
			}

			$rReturn['stream_ids'] = array_unique($rStreamIDs);
			$rReturn['series_ids'] = array_unique($rSeriesIDs);
			$rCategories = [];

			if (0 < count($rReturn['stream_ids'])) {
				$db->query('SELECT DISTINCT(`category_id`) AS `category_id` FROM `streams` WHERE `id` IN (' . implode(',', array_map('intval', $rReturn['stream_ids'])) . ');');

				foreach ($db->get_rows() as $rRow) {
					if ($rRow['category_id']) {
						$rCategories = array_merge($rCategories, json_decode($rRow['category_id'], true));
					}
				}
			}

			if (0 < count($rReturn['series_ids'])) {
				$db->query('SELECT DISTINCT(`category_id`) AS `category_id` FROM `streams_series` WHERE `id` IN (' . implode(',', array_map('intval', $rReturn['series_ids'])) . ');');

				foreach ($db->get_rows() as $rRow) {
					if ($rRow['category_id']) {
						$rCategories = array_merge($rCategories, json_decode($rRow['category_id'], true));
					}
				}
			}

			$rReturn['category_ids'] = array_unique($rCategories);
		}

		file_put_contents(CACHE_TMP_PATH . 'permissions_' . (int) $rGroup['group_id'], igbinary_serialize($rReturn));
	}
}

function generateLinesPerIP()
{
	global $db;
	$rLinesPerIP = [
		3600   => [],
		86400  => [],
		604800 => [],
		0      => []
	];

	foreach (array_keys($rLinesPerIP) as $rTime) {
		if (0 < $rTime) {
			$db->query('SELECT `lines_activity`.`user_id`, COUNT(DISTINCT(`lines_activity`.`user_ip`)) AS `ip_count`, `lines`.`username` FROM `lines_activity` LEFT JOIN `lines` ON `lines`.`id` = `lines_activity`.`user_id` WHERE `date_start` >= ? AND `lines`.`is_mag` = 0 AND `lines`.`is_e2` = 0 AND `lines`.`is_restreamer` = 0 GROUP BY `lines_activity`.`user_id` ORDER BY `ip_count` DESC LIMIT 1000;', time() - $rTime);
		}
		else {
			$db->query('SELECT `lines_activity`.`user_id`, COUNT(DISTINCT(`lines_activity`.`user_ip`)) AS `ip_count`, `lines`.`username` FROM `lines_activity` LEFT JOIN `lines` ON `lines`.`id` = `lines_activity`.`user_id` WHERE `lines`.`is_mag` = 0 AND `lines`.`is_e2` = 0 AND `lines`.`is_restreamer` = 0 GROUP BY `lines_activity`.`user_id` ORDER BY `ip_count` DESC LIMIT 1000;');
		}

		foreach ($db->get_rows() as $rRow) {
			$rLinesPerIP[$rTime][] = $rRow;
		}
	}

	file_put_contents(CACHE_TMP_PATH . 'lines_per_ip', igbinary_serialize($rLinesPerIP));
}

function generateTheftDetection()
{
	global $db;
	$rTheftDetection = [
		3600   => [],
		86400  => [],
		604800 => [],
		0      => []
	];

	foreach (array_keys($rTheftDetection) as $rTime) {
		if (0 < $rTime) {
			$db->query('SELECT `lines_activity`.`user_id`, COUNT(DISTINCT(`lines_activity`.`stream_id`)) AS `vod_count`, `lines`.`username` FROM `lines_activity` LEFT JOIN `lines` ON `lines`.`id` = `lines_activity`.`user_id` WHERE `date_start` >= ? AND `lines`.`is_mag` = 0 AND `lines`.`is_e2` = 0 AND `lines`.`is_restreamer` = 0 AND `stream_id` IN (SELECT `id` FROM `streams` WHERE `type` IN (2,5)) GROUP BY `lines_activity`.`user_id` ORDER BY `vod_count` DESC LIMIT 1000;', time() - $rTime);
		}
		else {
			$db->query('SELECT `lines_activity`.`user_id`, COUNT(DISTINCT(`lines_activity`.`stream_id`)) AS `vod_count`, `lines`.`username` FROM `lines_activity` LEFT JOIN `lines` ON `lines`.`id` = `lines_activity`.`user_id` WHERE `lines`.`is_mag` = 0 AND `lines`.`is_e2` = 0 AND `lines`.`is_restreamer` = 0 AND `stream_id` IN (SELECT `id` FROM `streams` WHERE `type` IN (2,5)) GROUP BY `lines_activity`.`user_id` ORDER BY `vod_count` DESC LIMIT 1000;');
		}

		foreach ($db->get_rows() as $rRow) {
			$rTheftDetection[$rTime][] = $rRow;
		}
	}

	file_put_contents(CACHE_TMP_PATH . 'theft_detection', igbinary_serialize($rTheftDetection));
}

function getChangedLines()
{
	global $db;
	$rReturn = [
		'changes'  => [],
		'delete_i' => [],
		'delete_c' => [],
		'delete_t' => []
	];
	$rFilesI = glob(LINES_TMP_PATH . 'line_i_*');
	$rFilesC = glob(LINES_TMP_PATH . 'line_c_*');
	$rFilesT = glob(LINES_TMP_PATH . 'line_t_*');
	$rExistingI = $rExistingC = $rExistingT = [];
	$db->query('SELECT `id`, `username`, `password`, `access_token`, UNIX_TIMESTAMP(`updated`) AS `updated` FROM `lines`;');
	if ($db->dbh && $db->result) {
		if (0 < $db->result->rowCount()) {
			foreach ($db->result->fetchAll(PDO::FETCH_ASSOC) as $rRow) {
				if (!file_exists(LINES_TMP_PATH . 'line_i_' . $rRow['id']) || ((filemtime(LINES_TMP_PATH . 'line_i_' . $rRow['id']) ?: 0) < $rRow['updated'])) {
					$rReturn['changes'][] = $rRow['id'];
				}

				$rExistingI[] = $rRow['id'];
				$rExistingC[] = (XCMS::$rSettings['case_sensitive_line'] ? $rRow['username'] . '_' . $rRow['password'] : strtolower($rRow['username'] . '_' . $rRow['password']));

				if ($rRow['access_token']) {
					$rExistingT[] = $rRow['access_token'];
				}
			}
		}
	}

	$rExistingI = array_flip($rExistingI);

	foreach ($rFilesI as $rFile) {
		$rUserID = (int) explode('line_i_', $rFile, 2)[1] ?: NULL;
		if ($rUserID && !isset($rExistingI[$rUserID])) {
			$rReturn['delete_i'][] = $rUserID;
		}
	}

	$rExistingC = array_flip($rExistingC);

	foreach ($rFilesC as $rFile) {
		$rCredentials = explode('line_c_', $rFile, 2)[1] ?: NULL;
		if ($rCredentials && !isset($rExistingC[$rCredentials])) {
			$rReturn['delete_c'][] = $rCredentials;
		}
	}

	$rExistingT = array_flip($rExistingT);

	foreach ($rFilesT as $rFile) {
		$rToken = explode('line_t_', $rFile, 2)[1] ?: NULL;
		if ($rToken && !isset($rExistingT[$rToken])) {
			$rReturn['delete_t'][] = $rToken;
		}
	}

	return $rReturn;
}

function getChangedStreams()
{
	global $db;
	$rReturn = [
		'changes' => [],
		'delete'  => []
	];
	$rExisting = [];
	$db->query('SELECT `id`, GREATEST(IFNULL(UNIX_TIMESTAMP(`streams`.`updated`), 0), IFNULL(MAX(UNIX_TIMESTAMP(`streams_servers`.`updated`)), 0)) AS `updated` FROM `streams` LEFT JOIN `streams_servers` ON `streams`.`id` = `streams_servers`.`stream_id` GROUP BY `id`;');
	if ($db->dbh && $db->result) {
		if (0 < $db->result->rowCount()) {
			foreach ($db->result->fetchAll(PDO::FETCH_ASSOC) as $rRow) {
				if (!file_exists(STREAMS_TMP_PATH . 'stream_' . $rRow['id']) || ((filemtime(STREAMS_TMP_PATH . 'stream_' . $rRow['id']) ?: 0) < $rRow['updated'])) {
					$rReturn['changes'][] = $rRow['id'];
				}

				$rExisting[] = $rRow['id'];
			}
		}
	}

	$rExisting = array_flip($rExisting);

	foreach (glob(STREAMS_TMP_PATH . 'stream_*') as $rFile) {
		$rStreamID = (int) end(explode('_', $rFile));

		if (!isset($rExisting[$rStreamID])) {
			$rReturn['delete'][] = $rStreamID;
		}
	}

	return $rReturn;
}

function loadCron($rType, $rGroupStart, $rGroupMax)
{
	global $db;
	global $rSplit;
	global $rUpdateIDs;
	global $rThreadCount;
	global $rForce;
	$rStartTime = time();

	if (!XCMS::isRunning()) {
		echo 'XCMS not running...' . "\n";
		exit();
	}
	if (!XCMS::$rCached && !isset($rUpdateIDs)) {
		echo 'Cache is disabled.' . "\n";
		echo 'Generating group permissions...' . "\n";
		generategroups();
		echo 'Generating lines per ip...' . "\n";
		generatelinesperip();
		echo 'Detecting theft of VOD...' . "\n";
		generatetheftdetection();
		echo 'Clearing old data...' . "\n";

		foreach ([STREAMS_TMP_PATH, LINES_TMP_PATH, SERIES_TMP_PATH] as $rTmpPath) {
			foreach (scandir($rTmpPath) as $rFile) {
				unlink($rTmpPath . $rFile);
			}
		}

		file_put_contents(CACHE_TMP_PATH . 'cache_complete', time());
		exit();
	}

	switch ($rType) {
	case 'lines':
		generatelines($rGroupStart, $rGroupMax);
		break;
	case 'lines_update':
		generatelines(NULL, NULL, $rUpdateIDs);
		break;
	case 'series':
		generateseries($rGroupStart, $rGroupMax);
		break;
	case 'streams':
		generatestreams($rGroupStart, $rGroupMax);
		break;
	case 'streams_update':
		generatestreams(NULL, NULL, $rUpdateIDs);
		break;
	case 'groups':
		generategroups();
		break;
	case 'lines_per_ip':
		generatelinesperip();
		break;
	case 'theft_detection':
		generatetheftdetection();
		break;
	default:
		$rSeriesUpdated = $rSeriesCategories = [];
		$db->query('SELECT `series_id`, MAX(`streams`.`added`) AS `last_modified` FROM `streams_episodes` LEFT JOIN `streams` ON `streams`.`id` = `streams_episodes`.`stream_id` GROUP BY `series_id`;');

		foreach ($db->get_rows() as $rRow) {
			$rSeriesUpdated[$rRow['series_id']] = $rRow['last_modified'];
		}

		$db->query('SELECT * FROM `streams_series`;');

		if ($db->result) {
			if (0 < $db->result->rowCount()) {
				foreach ($db->result->fetchAll(PDO::FETCH_ASSOC) as $rRow) {
					if (isset($rSeriesUpdated[$rRow['id']])) {
						$rRow['last_modified'] = $rSeriesUpdated[$rRow['id']];
					}

					$rSeriesCategories[$rRow['id']] = json_decode($rRow['category_id'], true);
					file_put_contents(SERIES_TMP_PATH . 'series_' . $rRow['id'], igbinary_serialize($rRow));
				}
			}
		}

		file_put_contents(SERIES_TMP_PATH . 'series_categories', igbinary_serialize($rSeriesCategories));
		$rDelete = [
			'streams' => [],
			'lines_i' => [],
			'lines_c' => [],
			'lines_t' => []
		];
		$rThreads = [];

		if (XCMS::$rSettings['cache_changes']) {
			$rChanges = getchangedlines();
			$rDelete['lines_i'] = $rChanges['delete_i'];
			$rDelete['lines_c'] = $rChanges['delete_c'];
			$rDelete['lines_t'] = $rChanges['delete_t'];

			if (0 < count($rChanges['changes'])) {
				foreach (array_chunk($rChanges['changes'], $rSplit) as $rChunk) {
					$rThreads[] = PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "lines_update" "' . implode(',', $rChunk) . '"';
				}
			}
		}
		else {
			$db->query('SELECT COUNT(*) AS `count` FROM `lines`;');
			$rLinesCount = $db->get_row()['count'];
			$rLineGroups = range(0, $rLinesCount, $rSplit);

			if (!$rLineGroups) {
				$rLineGroups = [0];
			}

			foreach ($rLineGroups as $rStart) {
				$rMax = $rSplit;

				if ($rLinesCount < ($rStart + $rMax)) {
					$rMax = $rLinesCount - $rStart;
				}

				$rThreads[] = PHP_BIN . ' ' . CRON_PATH . ('cache_engine.php "lines" ' . $rStart . ' ' . $rMax);
			}
		}

		$db->query('SELECT COUNT(*) AS `count` FROM `streams_episodes` WHERE `stream_id` IN (SELECT `id` FROM `streams` WHERE `type` = 5);');
		$rEpisodesCount = $db->get_row()['count'];
		$rEpisodeGroups = range(0, $rEpisodesCount, $rSplit);

		if (!$rEpisodeGroups) {
			$rEpisodeGroups = [0];
		}

		foreach ($rEpisodeGroups as $rStart) {
			$rMax = $rSplit;

			if ($rEpisodesCount < ($rStart + $rMax)) {
				$rMax = $rEpisodesCount - $rStart;
			}

			$rThreads[] = PHP_BIN . ' ' . CRON_PATH . ('cache_engine.php "series" ' . $rStart . ' ' . $rMax);
		}

		if (XCMS::$rSettings['cache_changes']) {
			$rChanges = getchangedstreams();
			$rDelete['streams'] = $rChanges['delete'];

			if (0 < count($rChanges['changes'])) {
				foreach (array_chunk($rChanges['changes'], $rSplit) as $rChunk) {
					$rThreads[] = PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "streams_update" "' . implode(',', $rChunk) . '"';
				}
			}
		}
		else {
			$db->query('SELECT COUNT(*) AS `count` FROM `streams`;');
			$rStreamsCount = $db->get_row()['count'];
			$rStreamGroups = range(0, $rStreamsCount, $rSplit);

			if (!$rStreamGroups) {
				$rStreamGroups = [0];
			}

			foreach ($rStreamGroups as $rStart) {
				$rMax = $rSplit;

				if ($rStreamsCount < ($rStart + $rMax)) {
					$rMax = $rStreamsCount - $rStart;
				}

				$rThreads[] = PHP_BIN . ' ' . CRON_PATH . ('cache_engine.php "streams" ' . $rStart . ' ' . $rMax);
			}
		}

		$rThreads[] = PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "groups"';
		$rThreads[] = PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "lines_per_ip"';
		$rThreads[] = PHP_BIN . ' ' . CRON_PATH . 'cache_engine.php "theft_detection"';
		$rProc = new Multithread($rThreads, $rThreadCount);
		$rProc->run();
		unset($rThreads);
		$rSeriesEpisodes = $rSeriesMap = [];

		foreach ($rEpisodeGroups as $rStart) {
			if (file_exists(SERIES_TMP_PATH . 'series_map_' . $rStart)) {
				foreach (igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'series_map_' . $rStart)) as $rStreamID => $rSeriesID) {
					$rSeriesMap[$rStreamID] = $rSeriesID;
				}

				unlink(SERIES_TMP_PATH . 'series_map_' . $rStart);
			}

			if (file_exists(SERIES_TMP_PATH . 'series_episodes_' . $rStart)) {
				$rSeasonData = igbinary_unserialize(file_get_contents(SERIES_TMP_PATH . 'series_episodes_' . $rStart));

				foreach (array_keys($rSeasonData) as $rSeriesID) {
					if (!isset($rSeriesEpisodes[$rSeriesID])) {
						$rSeriesEpisodes[$rSeriesID] = [];
					}

					foreach (array_keys($rSeasonData[$rSeriesID]) as $rSeasonNum) {
						foreach ($rSeasonData[$rSeriesID][$rSeasonNum] as $rEpisode) {
							$rSeriesEpisodes[$rSeriesID][$rSeasonNum][] = $rEpisode;
						}
					}
				}

				unlink(SERIES_TMP_PATH . 'series_episodes_' . $rStart);
			}
		}

		file_put_contents(SERIES_TMP_PATH . 'series_map', igbinary_serialize($rSeriesMap));

		foreach ($rSeriesEpisodes as $rSeriesID => $rSeasons) {
			file_put_contents(SERIES_TMP_PATH . 'episodes_' . $rSeriesID, igbinary_serialize($rSeasons));
		}

		if (XCMS::$rSettings['cache_changes']) {
			foreach ($rDelete['streams'] as $rStreamID) {
				@unlink(STREAMS_TMP_PATH . 'stream_' . $rStreamID);
			}

			foreach ($rDelete['lines_i'] as $rUserID) {
				@unlink(LINES_TMP_PATH . 'line_i_' . $rUserID);
			}

			foreach ($rDelete['lines_c'] as $rCredentials) {
				@unlink(LINES_TMP_PATH . 'line_c_' . $rCredentials);
			}

			foreach ($rDelete['lines_t'] as $rToken) {
				@unlink(LINES_TMP_PATH . 'line_t_' . $rToken);
			}
		}
		else {
			foreach ([STREAMS_TMP_PATH, LINES_TMP_PATH, SERIES_TMP_PATH] as $rTmpPath) {
				foreach (scandir($rTmpPath) as $rFile) {
					if (filemtime($rTmpPath . $rFile) < ($rStartTime - 1)) {
						unlink($rTmpPath . $rFile);
					}
				}
			}
		}

		echo 'Cache updated!' . "\n";
		file_put_contents(CACHE_TMP_PATH . 'cache_complete', time());
		$db->query('UPDATE `settings` SET `last_cache` = ?, `last_cache_taken` = ?;', time(), time() - $rStartTime);
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

$rPID = getmypid();
register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
ini_set('memory_limit', -1);
ini_set('max_execution_time', 0);
XCMS::$rSettings = XCMS::getSettings(true);
$rSplit = 10000;
$rThreadCount = XCMS::$rSettings['cache_thread_count'] ?: 10;
$rForce = false;
$rGroupStart = $rGroupMax = $rType = NULL;

if (1 < count($argv)) {
	$rType = $argv[1];
	if (($rType == 'streams_update') || ($rType == 'lines_update')) {
		$rUpdateIDs = array_map('intval', explode(',', $argv[2]));
	}
	else if (2 < count($argv)) {
		$rGroupStart = (int) $argv[2];
		$rGroupMax = (int) $argv[3];
	}

	if ($rType == 'force') {
		echo 'Forcing cache regen...' . "\n";
		XCMS::$rSettings['cache_changes'] = false;
		$rForce = true;
	}
}
else {
	shell_exec('kill -9 $(ps aux | grep \'cache_engine\' | grep -v grep | grep -v ' . $rPID . ' | awk \'{print $2}\')');
}

loadcron($rType, $rGroupStart, $rGroupMax);

?>