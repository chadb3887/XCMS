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
				@$this->output[$key] .= $this->thread[$key]->listen();
				@$this->error[$key] .= $this->thread[$key]->getError();

				if ($this->thread[$key]->isActive()) {
					$this->output[$key] .= $this->thread[$key]->listen();

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

function getWatchCategories($rType = NULL)
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
		$rReturn[$rRow['genre_id']] = $rRow;
	}

	return $rReturn;
}

function loadCron()
{
	global $db;
	global $rThreadCount;
	global $rScanOffset;
	global $rMaxItems;
	global $rForce;
	$rWatchCategories = [1 => getwatchcategories(1), 2 => getwatchcategories(2)];

	if (0 < count(glob(WATCH_TMP_PATH . '*.bouquet'))) {
		checkBouquets();
	}

	if (!$rForce) {
		$db->query('SELECT * FROM `watch_folders` WHERE `type` <> \'plex\' AND `server_id` = ? AND `active` = 1 AND (UNIX_TIMESTAMP() - `last_run` > ? OR `last_run` IS NULL) ORDER BY `id` ASC;', SERVER_ID, $rScanOffset);
	}
	else {
		$db->query('SELECT * FROM `watch_folders` WHERE `type` <> \'plex\' AND `server_id` = ? AND `id` = ?;', SERVER_ID, $rForce);
	}

	$rRows = $db->get_rows();

	if (0 < count($rRows)) {
		shell_exec('rm -f ' . WATCH_TMP_PATH . '*.wpid');
		$rSeriesTMDB = $rStreamDatabase = [];
		$rTMDBDatabase = [
			'movie'  => [],
			'series' => []
		];
		echo 'Generating cache...' . "\n";
		$db->query('SELECT `id`, `tmdb_id` FROM `streams_series` WHERE `tmdb_id` IS NOT NULL AND `tmdb_id` > 0;');

		foreach ($db->get_rows() as $rRow) {
			$rSeriesTMDB[$rRow['id']] = $rRow['tmdb_id'];
		}

		$db->query('SELECT `streams`.`id`, `streams_episodes`.`series_id`, `streams_episodes`.`season_num`, `streams_episodes`.`episode_num`, `streams`.`stream_source` FROM `streams_episodes` LEFT JOIN `streams` ON `streams`.`id` = `streams_episodes`.`stream_id` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams_servers`.`server_id` = ?;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rStreamDatabase[] = $rRow['stream_source'];
			$rTMDBID = $rSeriesTMDB[$rRow['series_id']];

			if ($rTMDBID) {
				$rSource = json_decode($rRow['stream_source'], true)[0];
				$rTMDBDatabase['series'][$rTMDBID][$rRow['season_num'] . '_' . $rRow['episode_num']] = ['id' => $rRow['id'], 'source' => $rSource];
			}
		}

		$db->query('SELECT `streams`.`id`, `streams`.`stream_source`, `streams`.`movie_properties` FROM `streams` LEFT JOIN `streams_servers` ON `streams_servers`.`stream_id` = `streams`.`id` WHERE `streams`.`type` = 2 AND `streams_servers`.`server_id` = ?;', SERVER_ID);

		foreach ($db->get_rows() as $rRow) {
			$rStreamDatabase[] = $rRow['stream_source'];
			$rTMDBID = json_decode($rRow['movie_properties'], true)['tmdb_id'] ?: NULL;

			if ($rTMDBID) {
				$rSource = json_decode($rRow['stream_source'], true)[0];
				$rTMDBDatabase['movie'][$rTMDBID] = ['id' => $rRow['id'], 'source' => $rSource];
			}
		}

		exec('find ' . WATCH_TMP_PATH . ' -maxdepth 1 -name "*.cache" -print0 | xargs -0 rm');

		foreach ($rTMDBDatabase['series'] as $rTMDBID => $rData) {
			file_put_contents(WATCH_TMP_PATH . 'series_' . $rTMDBID . '.cache', json_encode($rData));
		}

		foreach ($rTMDBDatabase['movie'] as $rTMDBID => $rData) {
			file_put_contents(WATCH_TMP_PATH . 'movie_' . $rTMDBID . '.cache', json_encode($rData));
		}

		unset($rTMDBDatabase);
		echo 'Finished generating cache!' . "\n";
	}

	foreach ($rRows as $rRow) {
		$db->query('UPDATE `watch_folders` SET `last_run` = UNIX_TIMESTAMP() WHERE `id` = ?;', $rRow['id']);
		$rExtensions = json_decode($rRow['allowed_extensions'], true);

		if (!$rExtensions) {
			$rExtensions = [];
		}

		if (count($rExtensions) == 0) {
			$rExtensions = ['mp4', 'mkv', 'avi', 'mpg', 'flv', '3gp', 'm4v', 'flv', 'wmv', 'mov', 'ts'];
		}

		$rSubtitles = $rFiles = [];

		if (0 < strlen($rRow['rclone_dir'])) {
			$rCommand = 'rclone --config "' . CONFIG_PATH . 'rclone.conf" lsjson ' . escapeshellarg($rRow['rclone_dir']) . ' -R --fast-list --files-only';
			exec($rCommand, $rRclone, $rReturnVal);
			$rData = implode(' ', $rRclone);

			if (!substr($rData, 0, 1) == '[') {
				$rData = '[' . explode('[', $rData, 1)[1];
			}

			$rRclone = json_decode($rData, true);

			foreach ($rRclone as $rFile) {
				$rFile['Path'] = rtrim($rRow['directory'], '/') . '/' . $rFile['Path'];
				if ((count($rExtensions) == 0) || in_array(strtolower(pathinfo($rFile['Name'])['extension']), $rExtensions)) {
					$rFiles[] = $rFile['Path'];
				}

				if (isset($rRow['auto_subtitles'])) {
					if (in_array(strtolower(pathinfo($rFile['Path'])['extension']), ['srt' => true, 'sub' => true, 'sbv' => true])) {
						$rSubtitles[] = $rFile['Path'];
					}
				}
			}
		}
		else {
			if (0 < count($rExtensions)) {
				$rExtensions = escapeshellcmd(implode('|', $rExtensions));
				$rCommand = '/usr/bin/find "' . escapeshellcmd($rRow['directory']) . '" -regex ".*\\.\\(' . $rExtensions . '\\)"';
			}
			else {
				$rCommand = '/usr/bin/find "' . escapeshellcmd($rRow['directory']) . '"';
			}

			exec($rCommand, $rFiles, $rReturnVal);

			if (isset($rRow['auto_subtitles'])) {
				$rExtensions = escapeshellcmd(implode('|', ['srt', 'sub', 'sbv']));
				$rCommand = '/usr/bin/find "' . escapeshellcmd($rRow['directory']) . '" -regex ".*\\.\\(' . $rExtensions . '\\)"';
				exec($rCommand, $rSubtitles, $rReturnVal);
			}
			else {
				$rSubtitles = [];
			}
		}

		$rThreadData = [];

		foreach ($rFiles as $rFile) {
			if ((time() - filemtime($rFile)) < 30) {
				continue;
			}

			if (!in_array(json_encode(['s:' . SERVER_ID . ':' . $rFile], JSON_UNESCAPED_UNICODE), $rStreamDatabase)) {
				$rPathInfo = pathinfo($rFile);
				$rSubArray = [];

				if (isset($rRow['auto_subtitles'])) {
					foreach (['srt', 'sub', 'sbv'] as $rExt) {
						$rSubtitle = $rPathInfo['dirname'] . '/' . $rPathInfo['filename'] . '.' . $rExt;

						if (in_array($rSubtitle, $rSubtitles)) {
							$rSubArray = [
								'files'    => [$rSubtitle],
								'names'    => ['Subtitles'],
								'charset'  => ['UTF-8'],
								'location' => SERVER_ID
							];
							break;
						}
					}
				}

				$rThreadData[] = ['folder_id' => $rRow['id'], 'type' => $rRow['type'], 'directory' => $rRow['directory'], 'file' => $rFile, 'subtitles' => $rSubArray, 'category_id' => $rRow['category_id'], 'bouquets' => $rRow['bouquets'], 'disable_tmdb' => $rRow['disable_tmdb'], 'ignore_no_match' => $rRow['ignore_no_match'], 'fb_bouquets' => $rRow['fb_bouquets'], 'fb_category_id' => $rRow['fb_category_id'], 'language' => $rRow['language'], 'watch_categories' => $rWatchCategories, 'read_native' => $rRow['read_native'], 'movie_symlink' => $rRow['movie_symlink'], 'remove_subtitles' => $rRow['remove_subtitles'], 'auto_encode' => $rRow['auto_encode'], 'auto_upgrade' => $rRow['auto_upgrade'], 'fallback_title' => $rRow['fallback_title'], 'ffprobe_input' => $rRow['ffprobe_input'], 'transcode_profile_id' => $rRow['transcode_profile_id'], 'max_genres' => (int) XCMS::$rSettings['max_genres'], 'duplicate_tmdb' => $rRow['duplicate_tmdb'], 'target_container' => $rRow['target_container'], 'alternative_titles' => XCMS::$rSettings['alternative_titles'], 'fallback_parser' => XCMS::$rSettings['fallback_parser']];
				if ((0 < $rMaxItems) && ($rMaxItems == count($rThreadData))) {
					break;
				}
			}
		}

		if (0 < count($rThreadData)) {
			echo 'Scan complete! Adding ' . count($rThreadData) . ' files...' . "\n";
		}

		$rThreads = [];

		foreach ($rThreadData as $rData) {
			$rCommand = '/usr/bin/timeout 60 ' . PHP_BIN . ' ' . INCLUDES_PATH . 'cli/watch_item.php "' . base64_encode(json_encode($rData, JSON_UNESCAPED_UNICODE)) . '"';
			$rThreads[] = $rCommand;
		}

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

function checkBouquets()
{
	global $db;
	$rAddToBouquets = [];
	$rBouquets = glob(WATCH_TMP_PATH . '*.bouquet');

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
			foreach (['movie', 'series'] as $rType) {
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
$rForce = NULL;

if (count($argv) == 2) {
	$rForce = (int) $argv[1];
}

if (!$rForce) {
	if (file_exists(CACHE_TMP_PATH . 'watch_pid')) {
		$rPrevPID = (int) file_get_contents(CACHE_TMP_PATH . 'watch_pid');
	}
	else {
		$rPrevPID = NULL;
	}
	if ($rPrevPID && XCMS::isProcessRunning($rPrevPID, 'php')) {
		echo 'Watch folder is already running. Please wait until it finishes.' . "\n";
		exit();
	}
}

file_put_contents(CACHE_TMP_PATH . 'watch_pid', getmypid());
cli_set_process_title('XCMS[Watch Folder]');
$rScanOffset = (int) XCMS::$rSettings['scan_seconds'] ?: 3600;
$rThreadCount = (int) XCMS::$rSettings['thread_count'] ?: 50;
$rMaxItems = (int) XCMS::$rSettings['max_items'] ?: 0;
set_time_limit(0);

if (strlen(XCMS::$rSettings['tmdb_api_key']) == 0) {
	exit('No TMDb API key.');
}

loadcron();

?>