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

function loadCLI()
{
	global $db;
	global $rStreamID;
	global $rDelayDuration;
	$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id AND t2.server_id = ? WHERE t1.id = ?', SERVER_ID, $rStreamID);

	if ($db->num_rows() <= 0) {
		exit();
	}

	$rStreamInfo = $db->get_row();
	if (($rStreamInfo['delay_minutes'] == 0) || $rStreamInfo['parent_id']) {
		exit();
	}

	$rPID = (file_exists(STREAMS_PATH . $rStreamID . '_.pid') ? (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.pid') : $rStreamInfo['pid']);
	$rPlaylist = STREAMS_PATH . $rStreamID . '_.m3u8';
	$rPlaylistDelay = DELAY_PATH . $rStreamID . '_.m3u8';
	$rPlaylistOld = DELAY_PATH . $rStreamID . '_.m3u8_old';
	$db->query('UPDATE `streams_servers` SET delay_pid = ? WHERE stream_id = ? AND server_id = ?', getmypid(), $rStreamID, SERVER_ID);
	XCMS::updateStream($rStreamInfo['id']);
	$db->close_mysql();
	$rDelayDuration = (int) $rStreamInfo['delay_minutes'] + 5;
	cleanUpSegments();
	$rTotalSegments = (int) XCMS::$rSegmentSettings['seg_list_size'] + 5;
	$rOldSegments = [];

	if (file_exists($rPlaylistOld)) {
		$rOldSegments = getSegments($rPlaylistOld, -1);
	}

	$rPrevMD5 = NULL;
	$rMD5 = md5(file_get_contents($rPlaylistDelay));

	while (XCMS::isStreamRunning($rPID, $rStreamID) && file_exists($rPlaylistDelay)) {
		if ($rMD5 != $rPrevMD5) {
			if (file_exists(STREAMS_PATH . $rStreamID . '_.dur')) {
				$rDuration = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.dur');

				if (XCMS::$rSegmentSettings['seg_time'] < $rDuration) {
					XCMS::$rSegmentSettings['seg_time'] = $rDuration;
				}
			}

			$rM3U8 = [
				'vars'     => ['#EXTM3U' => '', '#EXT-X-VERSION' => 3, '#EXT-X-MEDIA-SEQUENCE' => '0', '#EXT-X-TARGETDURATION' => XCMS::$rSegmentSettings['seg_time']],
				'segments' => getData($rPlaylistDelay, $rOldSegments, $rTotalSegments)
			];

			if (!empty($rM3U8['segments'])) {
				$rData = '';
				$rSequence = 0;

				if (preg_match('/.*\\_(.*?)\\.ts/', $rM3U8['segments'][0]['file'], $rMatches)) {
					$rSequence = (int) $rMatches[1];
				}

				$rM3U8['vars']['#EXT-X-MEDIA-SEQUENCE'] = $rSequence;

				foreach ($rM3U8['vars'] as $rKey => $rValue) {
					$rData .= (!empty($rValue) ? $rKey . ':' . $rValue . "\n" : $rKey . "\n");
				}

				foreach ($rM3U8['segments'] as $rSegment) {
					copy(DELAY_PATH . $rSegment['file'], STREAMS_PATH . $rSegment['file']);
					$rData .= '#EXTINF:' . $rSegment['seconds'] . ',' . "\n" . $rSegment['file'] . "\n";
				}

				file_put_contents($rPlaylist, $rData, LOCK_EX);
				$rMD5 = $rPrevMD5;
				deleteSegments($rSequence - 2);
				cleanUpSegments();
			}
		}

		usleep(1000);
		$rPrevMD5 = md5(file_get_contents($rPlaylistDelay));
	}
}

function cleanUpSegments()
{
	global $rStreamID;
	global $rDelayDuration;
	shell_exec('find ' . DELAY_PATH . (int) $rStreamID . '_*' . (' -type f -cmin +' . $rDelayDuration . ' -delete'));
}

function deleteSegments($rSequence)
{
	global $rStreamID;

	if (file_exists(STREAMS_PATH . $rStreamID . '_' . $rSequence . '.ts')) {
		unlink(STREAMS_PATH . $rStreamID . '_' . $rSequence . '.ts');
	}

	if (file_exists(STREAMS_PATH . $rStreamID . '_' . $rSequence . '.ts.enc')) {
		unlink(STREAMS_PATH . $rStreamID . '_' . $rSequence . '.ts.enc');
	}
}

function getData($rPlaylistDelay, &$rOldSegments, $rTotalSegments)
{
	$rSegments = [];

	if (!empty($rOldSegments)) {
		$rSegments = array_shift($rOldSegments);
		unlink(DELAY_PATH . $rSegments['file']);

		for ($i = 0; ($i < $rTotalSegments) && ($i < count($rOldSegments)); $i++) {
			$rSegments[] = $rOldSegments[$i];
		}

		$rOldSegments = array_values($rOldSegments);
		$rSegments = array_shift($rOldSegments);
		updateOldPlaylist($rOldSegments);
	}

	if (file_exists($rPlaylistDelay)) {
		$rSegments = array_merge($rSegments, getSegments($rPlaylistDelay, $rTotalSegments - count($rSegments)));
	}

	return $rSegments;
}

function updateOldPlaylist($rOldSegments)
{
	global $rPlaylistOld;

	if (!empty($rOldSegments)) {
		$rData = '';

		foreach ($rOldSegments as $rSegment) {
			$rData .= '#EXTINF:' . $rSegment['seconds'] . ',' . "\n" . $rSegment['file'] . "\n";
		}

		file_put_contents($rPlaylistOld, $rData, LOCK_EX);
	}
	else {
		unlink($rPlaylistOld);
	}
}

function getSegments($rPlaylist, $rCounter = 0)
{
	$rSegments = [];

	if (file_exists($rPlaylist)) {
		$rFP = fopen($rPlaylist, 'r');

		while (!feof($rFP)) {
			if ($rCounter == count($rSegments)) {
				break;
			}

			$rLine = trim(fgets($rFP));

			if (stristr($rLine, 'EXTINF')) {
				list($rVar, $rSeconds) = explode(':', $rLine);
				$rSeconds = rtrim($rSeconds, ',');
				$rSegmentFile = trim(fgets($rFP));

				if (file_exists(DELAY_PATH . $rSegmentFile)) {
					$rSegments[] = ['seconds' => $rSeconds, 'file' => $rSegmentFile];
				}
			}
		}

		fclose($rFP);
	}

	return $rSegments;
}

function checkRunning($rStreamID)
{
	clearstatcache(true);

	if (file_exists(STREAMS_PATH . $rStreamID . '_.monitor_delay')) {
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor_delay');
	}

	if (empty($rPID)) {
		shell_exec('kill -9 `ps -ef | grep \'XCMSDelay\\[' . (int) $rStreamID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
	}
	else if (file_exists('/proc/' . $rPID)) {
		$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
		if (($rCommand == 'XCMSDelay[' . $rStreamID . ']') && is_numeric($rPID) && (0 < $rPID)) {
			posix_kill($rPID, 9);
		}
	}

	file_put_contents(STREAMS_PATH . $rStreamID . '_.monitor_delay', getmypid());
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
if (!@$argc || ($argc <= 1)) {
	exit(0);
}

$rStreamID = (int) $argv[1];
$rDelayDuration = 0;
register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
checkRunning($rStreamID);
set_time_limit(0);
cli_set_process_title('XCMSDelay[' . $rStreamID . ']');
loadcli();

?>