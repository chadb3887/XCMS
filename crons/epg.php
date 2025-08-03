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

class EPG
{
	public $rValid = false;
	public $rEPGSource = null;
	public $rFilename = null;

	public function __construct($rSource, $rCache = false)
	{
		$this->loadEPG($rSource, $rCache);
	}

	public function getData()
	{
		$rOutput = [];

		while ($rNode = $this->rEPGSource->getNode()) {
			$rData = simplexml_load_string($rNode);

			if ($rData) {
				if ($rData->getName() == 'channel') {
					$rChannelID = trim((string) $rData->attributes()->id);
					$rDisplayName = (!empty($rData->{'display-name'}) ? trim((string) $rData->{'display-name'}) : '');

					if (array_key_exists($rChannelID, $rOutput)) {
						continue;
					}

					$rOutput[$rChannelID] = [];
					$rOutput[$rChannelID]['display_name'] = $rDisplayName;
					$rOutput[$rChannelID]['langs'] = [];
					continue;
				}

				if ($rData->getName() == 'programme') {
					$rChannelID = trim((string) $rData->attributes()->channel);

					if (!array_key_exists($rChannelID, $rOutput)) {
						continue;
					}

					$rTitles = $rData->title;

					foreach ($rTitles as $rTitle) {
						$rLang = (string) $rTitle->attributes()->lang;
						if (!in_array($rLang, $rOutput[$rChannelID]['langs']) && !empty($rLang)) {
							$rOutput[$rChannelID]['langs'][] = $rLang;
						}
					}
				}
			}
		}

		return $rOutput;
	}

	public function parseEPG($rEPGID, $rChannelInfo, $rOffset = 0)
	{
		global $db;
		$rInsertQuery = [];

		while ($rNode = $this->rEPGSource->getNode()) {
			$rData = simplexml_load_string($rNode);

			if ($rData) {
				if ($rData->getName() == 'programme') {
					$rChannelID = (string) $rData->attributes()->channel;

					if (!array_key_exists($rChannelID, $rChannelInfo)) {
						continue;
					}

					$rLangTitle = $rLangDesc = '';
					$rStart = strtotime(strval($rData->attributes()->start)) + ($rOffset * 60);
					$rStop = strtotime(strval($rData->attributes()->stop)) + ($rOffset * 60);

					if (empty($rData->title)) {
						continue;
					}

					$rTitles = $rData->title;

					if (is_object($rTitles)) {
						$rFound = false;

						foreach ($rTitles as $rTitle) {
							if ($rTitle->attributes()->lang == $rChannelInfo[$rChannelID]['epg_lang']) {
								$rFound = true;
								$rLangTitle = $rTitle;
								break;
							}
						}

						if (!$rFound) {
							$rLangTitle = $rTitles[0];
						}
					}
					else {
						$rLangTitle = $rTitles;
					}

					if (!empty($rData->desc)) {
						$rDescriptions = $rData->desc;

						if (is_object($rDescriptions)) {
							$rFound = false;

							foreach ($rDescriptions as $rDescription) {
								if ($rDescription->attributes()->lang == $rChannelInfo[$rChannelID]['epg_lang']) {
									$rFound = true;
									$rLangDesc = $rDescription;
									break;
								}
							}

							if (!$rFound) {
								$rLangDesc = $rDescriptions[0];
							}
						}
						else {
							$rLangDesc = $rData->desc;
						}
					}

					$rInsertQuery[$rChannelID][] = ['epg_id' => $rEPGID, 'start' => $rStart, 'stop' => $rStop, 'lang' => $rChannelInfo[$rChannelID]['epg_lang'], 'title' => strval($rLangTitle), 'description' => strval($rLangDesc)];
				}
			}
		}

		return $rInsertQuery;
	}

	public function downloadFile($rSource, $rFilename)
	{
		$rExtension = pathinfo($rSource, PATHINFO_EXTENSION);
		$rDecompress = '';

		if ($rExtension == 'gz') {
			$rDecompress = ' | gunzip -c';
		}
		else if ($rExtension == 'xz') {
			$rDecompress = ' | unxz -c';
		}

		shell_exec('wget -U "Mozilla/5.0" -O - "' . $rSource . '"' . $rDecompress . ' > ' . $rFilename);
		if (file_exists($rFilename) && (0 < filesize($rFilename))) {
			return true;
		}

		return false;
	}

	public function loadEPG($rSource, $rCache)
	{
		try {
			$this->rFilename = TMP_PATH . md5($rSource) . '.xml';
			if (!file_exists($this->rFilename) || !$rCache) {
				$this->downloadFile($rSource, $this->rFilename);
			}

			if ($this->rFilename) {
				$rXML = XmlStringStreamer::createStringWalkerParser($this->rFilename);

				if ($rXML) {
					$this->rEPGSource = $rXML;
					$this->rValid = true;
				}
				else {
					XCMS::saveLog('epg', 'Not a valid EPG source: ' . $rSource);
				}
			}
			else {
				XCMS::saveLog('epg', 'No XML found at: ' . $rSource);
			}
		}
		catch (Exception $e) {
			XCMS::saveLog('epg', 'EPG failed to process: ' . $rSource);
		}
	}
}

function build_epg_data($rChan, $rStream)
{
	global $db;
	$rEPGData = [];
	$db->query('SELECT * FROM `epg_data` WHERE `channel_id` = ? ORDER BY `start`;', $rChan);

	foreach ($db->get_rows() as $rEpg) {
		$rEPGData[] = ['id' => $rEpg['start'], 'epg_id' => $rEpg['epg_id'], 'channel_id' => $rEpg['channel_id'], 'start' => $rEpg['start'], 'end' => $rEpg['end'], 'lang' => $rEpg['lang'], 'title' => base64_decode($rEpg['title']), 'description' => base64_decode($rEpg['description'])];
	}

	if (file_exists(EPG_PATH . 'stream_' . $rStream)) {
		unlink(EPG_PATH . 'stream_' . $rStream);
	}

	file_put_contents(EPG_PATH . 'stream_' . $rStream, igbinary_serialize($rEPGData));
}

function getEPG($rStreamID)
{
	return file_exists(EPG_PATH . 'stream_' . $rStreamID) ? igbinary_unserialize(file_get_contents(EPG_PATH . 'stream_' . $rStreamID)) : [];
}

function getBouquetGroups()
{
	global $db;
	$db->query('SELECT DISTINCT(`bouquet`) AS `bouquet` FROM `lines`;');
	$rBouquetGroups = [
		'all' => [
		'streams'  => [],
		'bouquets' => []
	]
	];

	foreach ($db->get_rows() as $rRow) {
		$rBouquets = json_decode($rRow['bouquet'], true);
		sort($rBouquets);
		$rBouquetGroups[implode('_', $rBouquets)] = [
			'streams'  => [],
			'bouquets' => $rBouquets
		];
	}

	foreach ($rBouquetGroups as $rGroup => $rGroupArray) {
		$rBouquetExists = [];

		foreach ($rGroupArray['bouquets'] as $rBouquetID) {
			$db->query('SELECT `bouquet_channels` FROM `bouquets` WHERE `id` = ?;', $rBouquetID);

			foreach ($db->get_rows() as $rRow) {
				$rBouquetExists[] = $rBouquetID;
				$rBouquetGroups[$rGroup]['streams'] = array_merge($rBouquetGroups[$rGroup]['streams'], json_decode($rRow['bouquet_channels'], true));
			}

			$rBouquetGroups[$rGroup]['streams'] = array_unique($rBouquetGroups[$rGroup]['streams']);
		}

		$rBouquetGroups[$rGroup]['bouquets'] = $rBouquetExists;
	}

	return $rBouquetGroups;
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

$rChannelID = $rStreamID = $rEPGID = NULL;

if (count($argv) == 2) {
	$rEPGID = (int) $argv[1];
}

set_time_limit(0);
ini_set('memory_limit', -1);
register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
require INCLUDES_PATH . 'libs/XmlStringStreamer.php';
$rProcessed = [];
shell_exec('kill -9 `ps -ef | grep \'XCMS\\[EPG\\]\' | grep -v grep | awk \'{print $2}\'`;');
cli_set_process_title('XCMS[EPG]');

if (XCMS::$rSettings['force_epg_timezone']) {
	date_default_timezone_set('UTC');
}

if ($rEPGID) {
	$db->query('DELETE FROM `epg_channels` WHERE `epg_id` = ?;', $rEPGID);
	$db->query('SELECT * FROM `epg` WHERE `id` = ?;', $rEPGID);
}
else {
	$db->query('TRUNCATE `epg_channels`;');
	$db->query('SELECT * FROM `epg`');
}

$db->query('DELETE FROM `epg_data` WHERE `start` < ?', (time() - ($rRow['days_keep'] * 86400)) + 3600);

foreach ($db->get_rows() as $rRow) {
	$rEPG = new EPG($rRow['epg_file']);

	if ($rEPG->rValid) {
		$rData = $rEPG->getData();
		$db->query('UPDATE `epg` SET `data` = ?, `last_updated` = ? WHERE `id` = ?', json_encode($rData, JSON_UNESCAPED_UNICODE), time(), $rRow['id']);

		foreach ($rData as $rID => $rArray) {
			$db->query('INSERT INTO `epg_channels`(`epg_id`, `channel_id`, `name`, `langs`) VALUES(?, ?, ?, ?);', $rRow['id'], $rID, $rArray['display_name'], json_encode($rArray['langs']));
		}
	}
}

if ($rEPGID) {
	$db->query('SELECT DISTINCT(t1.`epg_id`), t2.* FROM `streams` t1 INNER JOIN `epg` t2 ON t2.id = t1.epg_id WHERE t1.`epg_id` IS NOT NULL AND t2.id = ?;', $rEPGID);
}
else {
	$db->query('SELECT DISTINCT(t1.`epg_id`), t2.* FROM `streams` t1 INNER JOIN `epg` t2 ON t2.id = t1.epg_id WHERE t1.`epg_id` IS NOT NULL;');
}

$rEPGData = $db->get_rows();

foreach ($rEPGData as $rData) {
	$rEPG = new EPG($rData['epg_file'], true);

	if ($rEPG->rValid) {
		$db->query('SELECT `id`, `channel_id`, `epg_lang`, `epg_offset` FROM `streams` WHERE `epg_id` = ?;', $rData['epg_id']);
		$rEPGOffset = $rStreamMap = $rChannels = [];

		foreach ($db->get_rows() as $rRow) {
			$rStreamMap[$rRow['channel_id']][] = $rRow['id'];
			$rEPGOffset[$rRow['id']][] = (int) $rRow['epg_offset'] ?: 0;
			unset($rRow['id']);
			$rChannels[$rRow['channel_id']] = $rRow;
		}

		$rUpdate = $rEPG->parseEPG($rData['epg_id'], $rChannels, (int) $rData['offset'] ?: 0);

		foreach ($rUpdate as $rChannelID => $rResults) {
			foreach ($rResults as $rResult) {
				$db->query('SELECT * FROM `epg_data` WHERE `start`= ? AND `channel_id`= ?;', $rResult['start'], $rChannelID);

				if ($db->num_rows() == 0) {
					$db->query('INSERT INTO `epg_data` (`epg_id`, `title`, `lang`, `start`, `end`, `description`, `channel_id`) VALUES (?, ?, ?, ?, ?, ?, ?);', $rResult['epg_id'], base64_encode($rResult['title']), json_encode($rArray['langs']), $rResult['start'], $rResult['stop'], base64_encode($rResult['description']), $rChannelID);
				}
			}
		}

		$db->query('UPDATE `epg` SET `last_updated` = ? WHERE `id` = ?', time(), $rData['epg_id']);
	}
}

$db->query('SELECT id, `channel_id` FROM `streams` WHERE `channel_id` IS NOT NULL');

foreach ($db->get_rows() as $rProcess) {
	build_epg_data($rProcess['channel_id'], $rProcess['id']);
}

$rBouquetGroups = getbouquetgroups();

foreach ($rBouquetGroups as $rBouquet => $rBouquetArray) {
	if ((0 < strlen($rBouquet)) && ((0 < count($rBouquetArray['streams'])) || ($rBouquet == 'all'))) {
		$rUnique = [];
		$rOutput = '';
		$rServerName = htmlspecialchars(XCMS::$rSettings['server_name'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
		$rOutput .= '<?xml version="1.0" encoding="utf-8" ?><!DOCTYPE tv SYSTEM "xmltv.dtd">' . "\n";
		$rOutput .= '<tv generator-info-name="' . $rServerName . '">' . "\n";

		if ($rBouquet == 'all') {
			$db->query('SELECT `id`, `stream_display_name`,`stream_icon`,`channel_id`,`epg_id`,`tv_archive_duration` FROM `streams` WHERE `epg_id` IS NOT NULL AND `channel_id` IS NOT NULL;');
		}
		else {
			$db->query('SELECT `id`, `stream_display_name`,`stream_icon`,`channel_id`,`epg_id`,`tv_archive_duration` FROM `streams` WHERE `epg_id` IS NOT NULL AND `channel_id` IS NOT NULL AND `id` IN (' . implode(',', array_map('intval', $rBouquetArray['streams'])) . ');');
		}

		$rRows = $db->get_rows();

		foreach ($rRows as $rRow) {
			if (!in_array($rRow['channel_id'], $rUnique)) {
				$rUnique[] = $rRow['channel_id'];
				$rStreamName = htmlspecialchars($rRow['stream_display_name'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
				$rStreamIcon = htmlspecialchars(XCMS::validateImage($rRow['stream_icon']), ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
				$rChannelID = htmlspecialchars($rRow['channel_id'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
				$rOutput .= "\t" . '<channel id="' . $rChannelID . '">' . "\n";
				$rOutput .= "\t\t" . '<display-name>' . $rStreamName . '</display-name>' . "\n";

				if (!empty($rRow['stream_icon'])) {
					$rOutput .= "\t\t" . '<icon src="' . $rStreamIcon . '" />' . "\n";
				}

				$rOutput .= "\t" . '</channel>' . "\n";
				$rEPG = getepg($rRow['id']);
				$rStartTimes = [];

				foreach ($rEPG as $rItem) {
					if (!in_array($rItem['start'], $rStartTimes)) {
						$rStartTimes[] = $rItem['start'];
						$rTitle = htmlspecialchars($rItem['title'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
						$rDescription = htmlspecialchars($rItem['description'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
						$rChannelID = htmlspecialchars($rRow['channel_id'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
						$rStart = date('YmdHis', $rItem['start']) . ' ' . str_replace(':', '', date('P'));
						$rEnd = date('YmdHis', $rItem['end']) . ' ' . str_replace(':', '', date('P'));
						$rOutput .= "\t" . '<programme start="' . $rStart . '" stop="' . $rEnd . '" start_timestamp="' . $rItem['start'] . '" stop_timestamp="' . $rItem['end'] . '" channel="' . $rChannelID . '" >' . "\n";
						$rOutput .= "\t\t" . '<title>' . $rTitle . '</title>' . "\n";
						$rOutput .= "\t\t" . '<desc>' . $rDescription . '</desc>' . "\n";
						$rOutput .= "\t" . '</programme>' . "\n";
					}
				}
			}
		}

		$rOutput .= '</tv>';
		$rBouquetName = ($rBouquet == 'all' ? 'all' : md5($rBouquet));
		file_put_contents(EPG_PATH . 'epg_' . $rBouquetName . '.xml', $rOutput);
		$rFile = gzopen(EPG_PATH . 'epg_' . $rBouquetName . '.xml.gz', 'w9');
		gzwrite($rFile, $rOutput);
		gzclose($rFile);
		break;
	}
}

?>