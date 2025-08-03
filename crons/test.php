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

foreach ($db->get_rows() as $rRow) {
	$db->query('DELETE FROM `epg_data` WHERE `start` < ?', time() - ($rRow['days_keep'] * 86400));
	$rEPG = new EPG($rRow['epg_file']);

	if ($rEPG->rValid) {
		$rData = $rEPG->getData();
		$db->query('UPDATE `epg` SET `data` = ?, `last_updated` = ? WHERE `id` = ?', json_encode($rData, JSON_UNESCAPED_UNICODE), time(), $rRow['id']);

		foreach ($rData as $rID => $rArray) {
			$db->query('INSERT INTO `epg_channels`(`epg_id`, `channel_id`, `name`, `langs`) VALUES(?, ?, ?, ?);', $rRow['id'], $rID, $rArray['display_name'], json_encode($rArray['langs']));
		}
	}
}

$rDupe = [];
$db->query('SELECT `start`, `channel_id` FROM `epg_data` WHERE `epg_id` = ?;', $rRow['id']);

foreach ($db->get_rows() as $rDupeCheck) {
	$rDupe[$rDupeCheck['channel_id']][] = $rDupeCheck['start'];
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
				if (!in_array($rResult['start'], $rDupe[$rChannelID])) {
					echo 'Adding ';
					$db->query('INSERT INTO `epg_data` (`epg_id`, `title`, `lang`, `start`, `end`, `description`, `channel_id`) VALUES (?, ?, ?, ?, ?, ?, ?);', $rResult['epg_id'], base64_encode($rResult['title']), json_encode($rArray['langs']), $rResult['start'], $rResult['stop'], base64_encode($rResult['description']), $rChannelID);
				}
			}
		}

		$db->query('UPDATE `epg` SET `last_updated` = ? WHERE `id` = ?', time(), $rData['epg_id']);
	}
}

?>