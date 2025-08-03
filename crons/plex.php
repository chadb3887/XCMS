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

function makeArray($rArray)
{
	if (isset($rArray['@attributes'])) {
		$rArray = [$rArray];
	}

	return $rArray;
}

function getPlexCategories($rType = NULL)
{
	global $db;
	$rReturn = [];

	if ($rType) {
		$db->query('SELECT * FROM `watch_categories` WHERE `type` = ? ORDER BY `genre_id` ASC;', $rType);
	}
	else {
		$db->query('SELECT * FROM `watch_categories` ORDER BY `genre_id` ASC;');
	}

	foreach ($db->get_rows() as $rRow) {
		$rReturn[$rRow['genre']] = $rRow;
	}

	return $rReturn;
}

function getPlexLogin($rUsername, $rPassword)
{
	$rHeaders = ['Content-Type: application/xml; charset=utf-8', 'X-Plex-Client-Identifier: 526e163c-8dbd-11eb-8dcd-0242ac130003', 'X-Plex-Product: XCMS', 'X-Plex-Version: v' . XCMS_VERSION];
	$rCurl = curl_init('https://plex.tv/users/sign_in.json');
	curl_setopt($rCurl, CURLOPT_HTTPHEADER, $rHeaders);
	curl_setopt($rCurl, CURLOPT_HEADER, 0);
	curl_setopt($rCurl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($rCurl, CURLOPT_USERPWD, $rUsername . ':' . $rPassword);
	curl_setopt($rCurl, CURLOPT_TIMEOUT, 30);
	curl_setopt($rCurl, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($rCurl, CURLOPT_POST, 1);
	curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
	return json_decode(curl_exec($rCurl), true);
}

function readURL($rURL)
{
	$rCurl = curl_init($rURL);
	curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($rCurl, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($rCurl, CURLOPT_TIMEOUT, 10);
	return curl_exec($rCurl);
}

function checkToken($rIP, $rPort, $rToken)
{
	$rCheckURL = 'http://' . $rIP . ':' . $rPort . '/myplex/account?X-Plex-Token=' . $rToken;
	$rResponse = json_decode(json_encode(simplexml_load_string(readurl($rCheckURL))), true);
	return $rResponse['@attributes']['signInState'] == 'ok' ? $rToken : '';
}

function loadCron()
{
	global $db;
	global $rScanOffset;
	global $rForce;
	$rPlexCategories = [3 => getplexcategories(3), 4 => getplexcategories(4)];
	checkBouquets();
	checkCategories();

	if (!$rForce) {
		$db->query('SELECT * FROM `watch_folders` WHERE `type` = \'plex\' AND `server_id` = ? AND `active` = 1 AND (UNIX_TIMESTAMP() - `last_run` > ? OR `last_run` IS NULL) ORDER BY `id` ASC;', SERVER_ID, $rScanOffset);
	}
	else {
		$db->query('SELECT * FROM `watch_folders` WHERE `type` = \'plex\' AND `server_id` = ? AND `id` = ?;', SERVER_ID, $rForce);
	}

	$rRows = $db->get_rows();

	if (0 < count($rRows)) {
		shell_exec('rm -f ' . WATCH_TMP_PATH . '*.ppid');
		$rLeafCount = $rUUIDs = $rSeriesTMDB = $rStreamDatabase = [];
		$rTMDBDatabase = [
			'movie'  => [],
			'series' => []
		];
		$rPlexDatabase = [
			'movie'  => [],
			'series' => []
		];
		echo 'Generating cache...' . "\n";
		$db->query('SELECT `id`, `tmdb_id`, `plex_uuid` FROM `streams_series` WHERE `tmdb_id` IS NOT NULL AND `tmdb_id` > 0;');

		foreach ($db->get_rows() as $rRow) {
			$rSeriesTMDB[$rRow['id']] = $rRow['tmdb_id'];

			if (!empty($rRow['plex_uuid'])) {
				$rUUIDs[] = $rRow['plex_uuid'];
			}
		}

		$db->query('SELECT `streams`.`id`, `streams_series`.`plex_uuid`, `streams_episodes`.`series_id`, `streams_episodes`.`season_num`, `streams_episodes`.`episode_num`, `streams`.`stream_source` FROM `streams_episodes` LEFT JOIN `streams` ON `streams`.`id` = `streams_episodes`.`stream_id` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` LEFT JOIN `streams_series` ON `streams_series`.`id` = `streams_episodes`.`series_id` WHERE `streams_servers`.`server_id` = ?;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rStreamDatabase[] = $rRow['stream_source'];
			$rTMDBID = $rSeriesTMDB[$rRow['series_id']] ?: NULL;
			$rSource = json_decode($rRow['stream_source'], true)[0];

			if ($rTMDBID) {
				$rTMDBDatabase['series'][$rTMDBID][$rRow['season_num'] . '_' . $rRow['episode_num']] = ['id' => $rRow['id'], 'source' => $rSource];
			}

			if (!empty($rRow['plex_uuid'])) {
				$rPlexDatabase['series'][$rRow['plex_uuid']][$rRow['season_num'] . '_' . $rRow['episode_num']] = ['id' => $rRow['id'], 'source' => $rSource];
				$rLeafCount[$rRow['plex_uuid']]++;
			}
		}

		$db->query('SELECT `streams`.`id`, `streams`.`plex_uuid`, `streams`.`stream_source`, `streams`.`movie_properties` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams`.`type` = 2 AND `streams_servers`.`server_id` = ?;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rStreamDatabase[] = $rRow['stream_source'];
			$rTMDBID = json_decode($rRow['movie_properties'], true)['tmdb_id'] ?: NULL;
			$rSource = json_decode($rRow['stream_source'], true)[0];

			if ($rTMDBID) {
				$rTMDBDatabase['movie'][$rTMDBID] = ['id' => $rRow['id'], 'source' => $rSource];
			}

			if (!empty($rRow['plex_uuid'])) {
				$rPlexDatabase['movie'][$rRow['plex_uuid']] = ['id' => $rRow['id'], 'source' => $rSource];
				$rUUIDs[] = $rRow['plex_uuid'];
			}
		}

		exec('find ' . WATCH_TMP_PATH . ' -maxdepth 1 -name "*.pcache" -print0 | xargs -0 rm');
		file_put_contents(WATCH_TMP_PATH . 'stream_database.pcache', json_encode($rStreamDatabase));

		foreach ($rTMDBDatabase['series'] as $rTMDBID => $rData) {
			file_put_contents(WATCH_TMP_PATH . 'series_' . $rTMDBID . '.pcache', json_encode($rData));
		}

		foreach ($rTMDBDatabase['movie'] as $rTMDBID => $rData) {
			file_put_contents(WATCH_TMP_PATH . 'movie_' . $rTMDBID . '.pcache', json_encode($rData));
		}

		foreach ($rPlexDatabase['series'] as $rPlexID => $rData) {
			file_put_contents(WATCH_TMP_PATH . 'series_' . $rPlexID . '.pcache', json_encode($rData));
		}

		foreach ($rPlexDatabase['movie'] as $rPlexID => $rData) {
			file_put_contents(WATCH_TMP_PATH . 'movie_' . $rPlexID . '.pcache', json_encode($rData));
		}

		unset($rTMDBDatabase, $rPlexDatabase);
		echo 'Finished generating cache!' . "\n";
	}

	foreach ($rRows as $rRow) {
		$rLimit = 100;
		$rThreadData = [];
		$rToken = NULL;

		if (!empty($rRow['plex_token'])) {
			$rToken = checktoken($rRow['plex_ip'], $rRow['plex_port'], $rRow['plex_token']);
		}

		if (!$rToken) {
			$rData = getplexlogin($rRow['plex_username'], $rRow['plex_password']);

			if (!isset($rData['user']['authToken'])) {
				echo 'Failed to login to plex!' . "\n";
				continue;
			}

			$rToken = checktoken($rRow['plex_ip'], $rRow['plex_port'], $rData['user']['authToken']);
		}

		$db->query('UPDATE `watch_folders` SET `last_run` = UNIX_TIMESTAMP(), `plex_token` = ? WHERE `id` = ?;', $rToken, $rRow['id']);

		if ($rToken) {
			$rSectionURL = 'http://' . $rRow['plex_ip'] . ':' . $rRow['plex_port'] . '/library/sections?X-Plex-Token=' . $rToken;
			$rSections = json_decode(json_encode(simplexml_load_string(readurl($rSectionURL))), true);
			$rThreadCount = 1;

			foreach (makearray($rSections['Directory']) as $rSection) {
				if ($rSection['@attributes']['type'] == 'movie') {
					$rThreadCount = (int) XCMS::$rSettings['thread_count_movie'] ?: 25;
				}
				else {
					$rThreadCount = (int) XCMS::$rSettings['thread_count_show'] ?: 5;
				}

				$rKey = $rSection['@attributes']['key'];

				if ($rKey == $rRow['directory']) {
					$rCountURL = 'http://' . $rRow['plex_ip'] . ':' . $rRow['plex_port'] . '/library/sections/' . $rKey . '/all?X-Plex-Token=' . $rToken . '&X-Plex-Container-Start=0&X-Plex-Container-Size=1';
					$rCount = (int) json_decode(json_encode(simplexml_load_string(readurl($rCountURL))), true)['@attributes']['totalSize'] ?: 0;
					echo 'Count: ' . $rCount . "\n";

					if (0 < $rCount) {
						$rSteps = range(0, $rCount, $rLimit);

						if (!$rSteps) {
							$rSteps = [0];
						}

						foreach ($rSteps as $rStart) {
							$rContentURL = 'http://' . $rRow['plex_ip'] . ':' . $rRow['plex_port'] . '/library/sections/' . $rKey . '/all?X-Plex-Token=' . $rToken . '&X-Plex-Container-Start=' . $rStart . '&X-Plex-Container-Size=' . $rLimit . '&sort=updatedAt%3Adesc';
							$rContent = json_decode(json_encode(simplexml_load_string(readurl($rContentURL))), true);

							if (!isset($rContent['Video'])) {
								$rContent['Video'] = $rContent['Directory'];
							}

							foreach (makearray($rContent['Video']) as $rItem) {
								$rUUID = $rKey . '_' . $rItem['@attributes']['ratingKey'];

								if ($rSection['@attributes']['type'] == 'movie') {
									if (!$rRow['last_run'] || !$rItem['@attributes']['updatedAt'] || ($rRow['last_run'] < (int) $rItem['@attributes']['updatedAt']) || (!in_array($rUUID, $rUUIDs) && $rRow['scan_missing'])) {
										$rThreadData[] = ['folder_id' => $rRow['id'], 'type' => $rSection['@attributes']['type'], 'key' => $rItem['@attributes']['ratingKey'], 'uuid' => $rUUID, 'plex_categories' => $rPlexCategories, 'read_native' => $rRow['read_native'], 'movie_symlink' => $rRow['movie_symlink'], 'remove_subtitles' => $rRow['remove_subtitles'], 'auto_encode' => $rRow['auto_encode'], 'auto_upgrade' => $rRow['auto_upgrade'], 'transcode_profile_id' => $rRow['transcode_profile_id'], 'max_genres' => (int) XCMS::$rSettings['max_genres'], 'plex' => true, 'ip' => $rRow['plex_ip'], 'port' => $rRow['plex_port'], 'token' => $rToken, 'fb_bouquets' => $rRow['fb_bouquets'], 'store_categories' => $rRow['store_categories'], 'category_id' => $rRow['category_id'], 'bouquets' => $rRow['bouquets'], 'fb_category_id' => $rRow['fb_category_id'], 'check_tmdb' => $rRow['check_tmdb'], 'target_container' => $rRow['target_container'], 'server_add' => $rRow['server_add'], 'direct_proxy' => $rRow['direct_proxy']];
									}
									else if ($rRow['last_run'] && $rItem['@attributes']['updatedAt'] && ((int) $rItem['@attributes']['updatedAt'] <= $rRow['last_run']) && !$rRow['scan_missing']) {
										break 2;
									}
								}
								else if (!$rRow['last_run'] || !$rItem['@attributes']['updatedAt'] || ($rRow['last_run'] < (int) $rItem['@attributes']['updatedAt']) || (isset($rLeafCount[$rUUID]) && ($rLeafCount[$rUUID] != $rItem['@attributes']['leafCount'])) || $rRow['scan_missing']) {
									$rThreadData[] = ['folder_id' => $rRow['id'], 'type' => $rSection['@attributes']['type'], 'key' => $rItem['@attributes']['ratingKey'], 'uuid' => $rUUID, 'plex_categories' => $rPlexCategories, 'read_native' => $rRow['read_native'], 'movie_symlink' => $rRow['movie_symlink'], 'remove_subtitles' => $rRow['remove_subtitles'], 'auto_encode' => $rRow['auto_encode'], 'auto_upgrade' => $rRow['auto_upgrade'], 'transcode_profile_id' => $rRow['transcode_profile_id'], 'max_genres' => (int) XCMS::$rSettings['max_genres'], 'plex' => true, 'ip' => $rRow['plex_ip'], 'port' => $rRow['plex_port'], 'token' => $rToken, 'fb_bouquets' => $rRow['fb_bouquets'], 'store_categories' => $rRow['store_categories'], 'category_id' => $rRow['category_id'], 'bouquets' => $rRow['bouquets'], 'fb_category_id' => $rRow['fb_category_id'], 'check_tmdb' => $rRow['check_tmdb'], 'target_container' => $rRow['target_container'], 'server_add' => $rRow['server_add'], 'direct_proxy' => $rRow['direct_proxy']];
								}
							}
						}
					}

					break;
				}
			}

			if (0 < count($rThreadData)) {
				echo 'Scan complete! Adding ' . count($rThreadData) . ' files...' . "\n";
			}
		}
		else {
			echo 'Could not connect to Plex server and obtain a valid token.';
		}

		$rThreads = [];

		foreach ($rThreadData as $rData) {
			if ($rData['type'] == 'movie') {
				$rCommand = '/usr/bin/timeout 20 ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/plex_item.php "' . base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . '"';
			}
			else {
				$rCommand = '/usr/bin/timeout 300 ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/plex_item.php "' . base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . '"';
			}

			$rThreads[] = $rCommand;
		}

		unset($rThreadData);
		$db->close_mysql();

		if ($rThreadCount <= 1) {
			foreach ($rThreads as $rCommand) {
				shell_exec($rCommand);
			}
		}
		else {
			$rProc = new Multithread($rThreads, $rThreadCount);
			$rProc->run();
		}

		$db->db_connect();
		checkBouquets();
		checkCategories();
	}
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

function checkCategories()
{
	global $db;
	$rPlexCategories = ['movie' => getplexcategories(3), 'show' => getplexcategories(4)];
	$rCategories = glob(WATCH_TMP_PATH . '*.pcat');
	$rCatID = ['movie' => 1, 'show' => 1];
	$db->query('SELECT MAX(`genre_id`) AS `max` FROM `watch_categories` WHERE `type` = 3;');
	$rCatID['movie'] = (int) $db->get_row()['max'];
	$db->query('SELECT MAX(`genre_id`) AS `max` FROM `watch_categories` WHERE `type` = 4;');
	$rCatID['show'] = (int) $db->get_row()['max'];

	foreach ($rCategories as $rCategoryFile) {
		$rCategory = json_decode(file_get_contents($rCategoryFile), true);

		if (!in_array($rCategory['title'], array_keys($rPlexCategories[$rCategory['type']]))) {
			$rCatID[$rCategory['type']] += 1;
			$db->query('INSERT INTO `watch_categories` (`type`, `genre_id`, `genre`, `category_id`, `bouquets`) VALUES (?, ?, ?, 0, \'[]\');', ['movie' => 3, 'show' => 4][$rCategory['type']], $rCatID[$rCategory['type']], $rCategory['title']);
		}

		unlink($rCategoryFile);
	}
}

function checkBouquets()
{
	global $db;
	$rAddToBouquets = [];
	$rBouquets = glob(WATCH_TMP_PATH . '*.pbouquet');

	foreach ($rBouquets as $rBouquetFile) {
		$rBouquet = json_decode(file_get_contents($rBouquetFile), true);

		if (!isset($rAddToBouquets[$rBouquet['bouquet_id']])) {
			$rAddToBouquets[$rBouquet['bouquet_id']] = [
				'movie'  => [],
				'series' => []
			];
		}

		$rAddToBouquets[$rBouquet['bouquet_id']][$rBouquet['type']][] = $rBouquet['id'];
		unlink($rBouquetFile);
	}

	foreach ($rAddToBouquets as $rBouquetID => $rBouquetData) {
		$rBouquet = getbouquet($rBouquetID);

		if ($rBouquet) {
			foreach (array_keys($rBouquetData) as $rType) {
				if ($rType == 'movie') {
					$rColumn = 'bouquet_movies';
				}
				else {
					$rColumn = 'bouquet_series';
				}

				$rChannels = json_decode($rBouquet[$rColumn], true);

				foreach ($rBouquetData[$rType] as $rID) {
					if ((0 < (int) $rID) && !in_array($rID, $rChannels)) {
						$rChannels[] = $rID;
					}
				}

				$db->query('UPDATE `bouquets` SET `' . $rColumn . '` = ? WHERE `id` = ?;', '[' . implode(',', array_map('intval', $rChannels)) . ']', $rBouquetID);
			}
		}
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

ini_set('memory_limit', -1);
setlocale(LC_ALL, 'en_US.UTF-8');
putenv('LC_ALL=en_US.UTF-8');

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(30711);
$rForce = NULL;

if (count($argv) == 2) {
	$rForce = (int) $argv[1];
}

if (!$rForce) {
	if (file_exists(CACHE_TMP_PATH . 'plex_pid')) {
		$rPrevPID = (int) file_get_contents(CACHE_TMP_PATH . 'plex_pid');
	}
	else {
		$rPrevPID = NULL;
	}
	if ($rPrevPID && XCMS::isProcessRunning($rPrevPID, 'php')) {
		echo 'Plex Sync is already running. Please wait until it finishes.' . "\n";
		exit();
	}
}

file_put_contents(CACHE_TMP_PATH . 'plex_pid', getmypid());
cli_set_process_title('XCMS[Plex Sync]');
$rScanOffset = (int) XCMS::$rSettings['scan_seconds'] ?: 3600;
set_time_limit(0);

if (strlen(XCMS::$rSettings['tmdb_api_key']) == 0) {
	exit('No TMDb API key.');
}

loadcron();

?>