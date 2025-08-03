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

function startLoopback($rStreamID, $rServerID, $rSegListSize, $rSegDeleteThreshold)
{
	global $rServers;
	global $rSettings;
	global $rSegmentStatus;
	global $rSegmentFile;
	global $rFP;
	global $rCurPTS;
	global $rLastPTS;
	$rLoopURL = (!is_null($rServers[SERVER_ID]['private_url_ip']) && !is_null($rServers[$rServerID]['private_url_ip']) ? $rServers[$rServerID]['private_url_ip'] : $rServers[$rServerID]['public_url_ip']);
	$rFP = @fopen($rLoopURL . 'admin/live?stream=' . (int) $rStreamID . '&password=' . urlencode($rSettings['live_streaming_pass']) . '&extension=ts&prebuffer=1', 'rb');

	if ($rFP) {
		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*.ts');
		stream_set_blocking($rFP, true);
		$rExcessBuffer = $rPrebuffer = $rBuffer = $rPacket = '';
		$rPATHeaders = [];
		$rNewSegment = $rPAT = false;
		$rFirstWrite = true;
		$rLastPacket = time();
		$rLastSegment = round(microtime(true) * 1000);
		$rSegment = 0;
		$rSegmentFile = fopen(STREAMS_PATH . $rStreamID . '_' . $rSegment . '.ts', 'wb');
		$rSegmentStatus[$rSegment] = true;
		echo 'PID: ' . getmypid() . "\n";

		while (!feof($rFP)) {
			stream_set_timeout($rFP, TIMEOUT_READ);
			$rBuffer = $rBuffer . $rExcessBuffer . fread($rFP, BUFFER_SIZE - strlen($rBuffer . $rExcessBuffer));
			$rExcessBuffer = '';
			$rPacketNum = floor(strlen($rBuffer) / PACKET_SIZE);

			if (0 < $rPacketNum) {
				$rLastPacket = time();

				if (strlen($rBuffer) != $rPacketNum * PACKET_SIZE) {
					$rExcessBuffer = substr($rBuffer, $rPacketNum * PACKET_SIZE, strlen($rBuffer) - ($rPacketNum * PACKET_SIZE));
					$rBuffer = substr($rBuffer, 0, $rPacketNum * PACKET_SIZE);
				}

				$rPacketNo = 0;

				foreach (str_split($rBuffer, PACKET_SIZE) as $rPacket) {
					$rHeader = unpack('N', substr($rPacket, 0, 4))[1];
					$rSync = ($rHeader >> 24) & 255;

					if ($rSync == 71) {
						if (substr($rPacket, 6, 4) == PAT_HEADER) {
							$rPAT = true;
							$rPATHeaders = [];
						}
						else {
							$rAdaptationField = ($rHeader >> 4) & 3;

							if (($rAdaptationField & 2) === 2) {
								if ((0 < count($rPATHeaders)) && ((unpack('C', $rPacket[4])[1] == 7) && (substr($rPacket, 4, 2) == KEYFRAME_HEADER))) {
									$rPrebuffer = implode('', $rPATHeaders);
									$rNewSegment = true;
									$rPAT = false;
									$rPATHeaders = [];
									$rHandler = new TS();
									$rHandler->setPacket($rPacket);
									$rPacketInfo = $rHandler->parsePacket();

									if (isset($rPacketInfo['pts'])) {
										$rLastPTS = $rCurPTS;
										$rCurPTS = $rPacketInfo['pts'];
									}

									unset($rHandler);
								}
							}
						}
					}
					else {
						writeError($rStreamID, '[Loopback] No sync byte detected! Stream is out of sync.');

						for ($i = 0; $i < strlen($rPacket); $i++) {
							if (substr($rPacket, $i, 2) == 'G' . "\x1") {
								if ($i == strlen(fread($rFP, $i))) {
									writeError($rStreamID, '[Loopback] Resynchronised stream. Continuing...');
									$rLastPacket = time();
									break 2;
								}
							}
						}

						writeError($rStreamID, '[Loopback] Couldn\'t rectify out-of-sync data. Exiting.');
						exit();
					}
					if ($rPAT && (count($rPATHeaders) < 10)) {
						$rPATHeaders[] = $rPacket;
					}

					if ($rNewSegment) {
						$rPrebuffer .= $rPacket;
					}

					$rPacketNo++;
				}

				if ($rNewSegment) {
					$rLastSegment = round(microtime(true) * 1000);
					$rPosition = strpos($rBuffer, $rPrebuffer);

					if (0 < $rPosition) {
						$rLastBuffer = substr($rBuffer, 0, $rPosition);

						if (!$rFirstWrite) {
							fwrite($rSegmentFile, $rLastBuffer, strlen($rLastBuffer));
						}
					}

					if (!$rFirstWrite) {
						fclose($rSegmentFile);
						$rSegment++;
						$rSegmentFile = fopen(STREAMS_PATH . $rStreamID . '_' . $rSegment . '.ts', 'wb');
						$rSegmentStatus[$rSegment] = true;
						$rSegmentsRemaining = deleteOldSegments($rStreamID, $rSegListSize, $rSegDeleteThreshold);
						updateSegments($rStreamID, $rSegmentsRemaining);
					}

					$rFirstWrite = false;
					fwrite($rSegmentFile, $rPrebuffer, strlen($rPrebuffer));
					$rPrebuffer = '';
					$rNewSegment = false;
				}
				else {
					fwrite($rSegmentFile, $rBuffer, strlen($rBuffer));
				}

				$rBuffer = '';
			}

			if (TIMEOUT <= time() - $rLastPacket) {
				echo 'No data, timeout reached' . "\n";
				writeError($rStreamID, '[Loopback] No data received for ' . TIMEOUT . ' seconds, closing source.');
				break;
			}
		}

		if ((time() - $rLastPacket) < TIMEOUT) {
			writeError($rStreamID, '[Loopback] Connection to source closed unexpectedly.');
		}

		fclose($rSegmentFile);
		fclose($rFP);
	}
}

function checkRunning($rStreamID)
{
	clearstatcache(true);

	if (file_exists(STREAMS_PATH . $rStreamID . '_.monitor')) {
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
	}

	if (empty($rPID)) {
		shell_exec('kill -9 `ps -ef | grep \'Loopback\\[' . (int) $rStreamID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
	}
	else if (file_exists('/proc/' . $rPID)) {
		$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
		if (($rCommand == 'Loopback[' . $rStreamID . ']') && is_numeric($rPID) && (0 < $rPID)) {
			posix_kill($rPID, 9);
		}
	}
}

function deleteOldSegments($rStreamID, $rKeep, $rThreshold)
{
	global $rSegmentStatus;
	$rReturn = [];
	$rCurrentSegment = max(array_keys($rSegmentStatus));

	foreach ($rSegmentStatus as $rSegmentID => $rStatus) {
		if ($rStatus) {
			if ($rSegmentID < (($rCurrentSegment - ($rKeep + $rThreshold)) + 1)) {
				$rSegmentStatus[$rSegmentID] = false;
				@unlink(STREAMS_PATH . $rStreamID . '_' . $rSegmentID . '.ts');
			}
			else if ($rSegmentID != $rCurrentSegment) {
				$rReturn[] = $rSegmentID;
			}
		}
	}

	if ($rKeep < count($rReturn)) {
		$rReturn = array_slice($rReturn, count($rReturn) - $rKeep, $rKeep);
	}

	return $rReturn;
}

function updateSegments($rStreamID, $rSegmentsRemaining)
{
	global $rSegmentDuration;
	global $rLastPTS;
	global $rCurPTS;
	$rHLS = '#EXTM3U' . "\n" . '#EXT-X-VERSION:3' . "\n" . '#EXT-X-TARGETDURATION:4' . "\n" . '#EXT-X-MEDIA-SEQUENCE:';
	$rSequence = false;

	foreach ($rSegmentsRemaining as $rSegment) {
		if (file_exists(STREAMS_PATH . $rStreamID . '_' . $rSegment . '.ts')) {
			if (!$rSequence) {
				$rHLS .= $rSegment . "\n";
				$rSequence = true;
			}
			if (!isset($rSegmentDuration[$rSegment]) && $rLastPTS) {
				$rSegmentDuration[$rSegment] = ($rCurPTS - $rLastPTS) / 90000.0;
			}

			$rHLS .= '#EXTINF:' . round(isset($rSegmentDuration[$rSegment]) ? $rSegmentDuration[$rSegment] : 10, 0) . '.000000,' . "\n" . $rStreamID . '_' . $rSegment . '.ts' . "\n";
		}
	}

	file_put_contents(STREAMS_PATH . $rStreamID . '_.m3u8', $rHLS);
}

function writeError($rStreamID, $rError)
{
	echo $rError . "\n";
	file_put_contents(STREAMS_PATH . $rStreamID . '.errors', $rError . "\n", FILE_APPEND | LOCK_EX);
}

function keygen($rEnc, $rString)
{
	$rKey = 'AYZP6IulCXgssFBfJH68fN8SKxQPZlh5aSAfzxMzD1F473ivbunfgMNTI5ep5Vyy';

	for ($i = 0; $i < strlen($rString); $i++) {
		$rString[$i] = $rString[$i] ^ $rKey[$i % strlen($rKey)];
	}

	return hash($rEnc, $rString);
}

function shutdown()
{
	global $rFP;
	global $rSegmentFile;
	global $rStreamID;

	if (is_resource($rSegmentFile)) {
		@fclose($rSegmentFile);
	}

	if (is_resource($rFP)) {
		@fclose($rFP);
	}
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}
if (!@$argc || ($argc <= 2)) {
	echo 'Loopback cannot be directly run!' . "\n";
	exit(0);
}

error_reporting(0);
ini_set('display_errors', 0);
$rStreamID = (int) $argv[1];
$rServerID = (int) $argv[2];
define('XCMS_HOME', '/home/xcms/');
define('STREAMS_PATH', XCMS_HOME . 'content/streams/');
define('INCLUDES_PATH', XCMS_HOME . 'includes/');
define('FFMPEG', XCMS_HOME . 'bin/ffmpeg_bin/4.0/ffmpeg');
define('FFPROBE', XCMS_HOME . 'bin/ffmpeg_bin/4.0/ffprobe');
define('CACHE_TMP_PATH', XCMS_HOME . 'tmp/cache/');
define('CONFIG_PATH', XCMS_HOME . 'config/');
const PAT_HEADER = "\xb0" . '' . "\r" . '' . "\0" . '' . "\x1";
const KEYFRAME_HEADER = "\x7" . 'P';
const PACKET_SIZE = 188;
const BUFFER_SIZE = 12032;
const PAT_PERIOD = 2;
const TIMEOUT = 20;
const TIMEOUT_READ = 1;

if (!file_exists(CONFIG_PATH . 'config.ini')) {
	echo 'Config file missing!' . "\n";
	exit(0);
}

if (!file_exists(CACHE_TMP_PATH . 'settings')) {
	echo 'Settings not cached!' . "\n";
	exit(0);
}

if (!file_exists(CACHE_TMP_PATH . 'servers')) {
	echo 'Servers not cached!' . "\n";
	exit(0);
}

$rConfig = parse_ini_file(CONFIG_PATH . 'config.ini');
define('SERVER_ID', (int) $rConfig['server_id']);
checkRunning($rStreamID);
register_shutdown_function('shutdown');
set_time_limit(0);
cli_set_process_title('Loopback[' . $rStreamID . ']');
require INCLUDES_PATH . 'ts.php';
$rFP = $rSegmentFile = NULL;
$rSegmentDuration = $rSegmentStatus = [];
$rSettings = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'settings'));
$rServers = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'servers'));
$rSegListSize = $rSettings['seg_list_size'];
$rSegDeleteThreshold = $rSettings['seg_delete_threshold'];
$rLastPTS = $rCurPTS = NULL;
startloopback($rStreamID, $rServerID, $rSegListSize, $rSegDeleteThreshold);

?>