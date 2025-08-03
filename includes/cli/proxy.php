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

function startProxy($rStreamID, $rStreamInfo, $rStreamArguments)
{
	global $rFP;
	global $db;

	if (!file_exists(CONS_TMP_PATH . $rStreamID . '/')) {
		mkdir(CONS_TMP_PATH . $rStreamID);
	}

	$rUserAgent = (isset($rStreamArguments['user_agent']) ? $rStreamArguments['user_agent']['value'] ?: $rStreamArguments['user_agent']['argument_default_value'] : 'Mozilla/5.0');
	$rOptions = [
		'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
		'http' => ['method' => 'GET', 'user_agent' => $rUserAgent, 'timeout' => TIMEOUT, 'header' => '']
	];

	if (isset($rStreamArguments['proxy'])) {
		$rOptions['http']['proxy'] = 'tcp://' . $rStreamArguments['proxy']['value'];
		$rOptions['http']['request_fulluri'] = true;
	}

	if (isset($rStreamArguments['cookie'])) {
		$rOptions['http']['header'] .= 'Cookie: ' . $rStreamArguments['cookie']['value'] . "\r\n";
	}

	if (XCMS::$rSettings['request_prebuffer']) {
		$rOptions['http']['header'] .= 'X-XCMS-Prebuffer: 1' . "\r\n";
	}

	$rContext = stream_context_create($rOptions);
	$rURLs = json_decode($rStreamInfo['stream_source'], true);
	$rFP = getActiveStream($rURLs, $rContext);

	if (!is_resource($rFP)) {
		$rHeaders = (!empty($rOptions['http']['header']) ? '-headers ' . escapeshellarg($rOptions['http']['header']) : '');
		$rProxy = (!empty($rStreamArguments['proxy']) ? '-http_proxy ' . escapeshellarg($rStreamArguments['proxy']) : '');
		$rCommand = XCMS::$rFFMPEG_CPU . ' -copyts -vsync 0 -nostats -nostdin -hide_banner -loglevel quiet -y -user_agent ' . escapeshellarg($rUserAgent) . ' ' . $rHeaders . ' ' . $rProxy . ' -i ' . escapeshellarg($rFP) . ' -map 0 -c copy -mpegts_flags +initial_discontinuity -pat_period ' . PAT_PERIOD . ' -f mpegts -';
		$rFP = popen($rCommand, 'rb');
	}

	if ($rFP) {
		$db->query('UPDATE `streams_servers` SET `monitor_pid` = ?, `pid` = ?, `stream_started` = ?, `stream_status` = 0, `to_analyze` = 0 WHERE `server_stream_id` = ?', getmypid(), getmypid(), time(), $rStreamInfo['server_stream_id']);

		if (XCMS::$rSettings['enable_cache']) {
			XCMS::updateStream($rStreamID);
		}

		shell_exec('rm -f ' . STREAMS_PATH . (int) $rStreamID . '_*.ts');
		file_put_contents(STREAMS_PATH . $rStreamID . '_.pid', getmypid());
		$db->close_mysql();
		$rLastSocket = NULL;
		stream_set_blocking($rFP, false);
		$rExcessBuffer = $rAnalyseBuffer = $rPrebuffer = $rBuffer = $rPacket = '';
		$rHasPrebuffer = $rPATHeaders = [];
		$rAnalysed = $rPAT = false;
		$rFirstKeyframe = false;

		while (!feof($rFP)) {
			stream_set_timeout($rFP, TIMEOUT);
			$rBuffer = $rBuffer . $rExcessBuffer . fread($rFP, BUFFER_SIZE - strlen($rBuffer . $rExcessBuffer));
			$rExcessBuffer = '';
			$rPacketNum = floor(strlen($rBuffer) / PACKET_SIZE);

			if (0 < $rPacketNum) {
				if (strlen($rBuffer) != $rPacketNum * PACKET_SIZE) {
					$rExcessBuffer = substr($rBuffer, $rPacketNum * PACKET_SIZE, strlen($rBuffer) - ($rPacketNum * PACKET_SIZE));
					$rBuffer = substr($rBuffer, 0, $rPacketNum * PACKET_SIZE);
				}

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
								if ((0 < count($rPATHeaders)) && ((unpack('C', $rPacket[4])[1] == 7) && (substr($rPacket, 4, 2) == "\x7" . 'P'))) {
									if (!$rPrebuffer || (STORE_PREBUFFER <= strlen($rPrebuffer))) {
										$rPrebuffer = implode('', $rPATHeaders) . $rPacket;
									}

									$rFirstKeyframe = true;
									$rPAT = false;
									$rPATHeaders = [];
								}
							}
						}
					}
					if ($rPAT && (count($rPATHeaders) < 10)) {
						$rPATHeaders[] = $rPacket;
					}
					if ((strlen($rPrebuffer) < MAX_PREBUFFER) && $rFirstKeyframe) {
						$rPrebuffer .= $rPacket;
					}

					if (!$rAnalysed) {
						$rAnalyseBuffer .= $rPacket;

						if ((PACKET_SIZE * 3000) <= strlen($rAnalyseBuffer)) {
							echo 'Write analysis buffer' . "\n";
							file_put_contents(STREAMS_PATH . $rStreamID . '.analyse', $rAnalyseBuffer);
							$rAnalyseBuffer = NULL;
							$rAnalysed = true;
						}
					}
				}

				$rSockets = getSockets();

				if (0 < count($rSockets)) {
					$rLastSocket = round(microtime(true) * 1000);

					foreach ($rSockets as $rSocketID) {
						$rSocketFile = CONS_TMP_PATH . $rStreamID . '/' . $rSocketID;
						if (file_exists($rSocketFile) && (!isset($rHasPrebuffer[$rSocketID]) || !empty($rBuffer))) {
							$rSocket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
							socket_set_nonblock($rSocket);

							if (!isset($rHasPrebuffer[$rSocketID])) {
								if (!empty($rPrebuffer)) {
									echo 'Send prebuffer: ' . strlen($rPrebuffer) . ' bytes' . "\n";
									$rHasPrebuffer[$rSocketID] = true;

									foreach (str_split($rPrebuffer, BUFFER_SIZE) as $rChunk) {
										socket_sendto($rSocket, $rChunk, BUFFER_SIZE, 0, $rSocketFile);
									}
								}
							}
							else if (!empty($rBuffer)) {
								socket_sendto($rSocket, $rBuffer, BUFFER_SIZE, 0, $rSocketFile);
							}

							socket_close($rSocket);
						}
					}
				}
				else {
					if (!$rLastSocket) {
						$rLastSocket = round(microtime(true) * 1000);
					}

					if (CLOSE_EMPTY <= round(microtime(true) * 1000) - $rLastSocket) {
						echo 'No sockets waiting, close stream' . "\n";
						break;
					}
				}

				$rBuffer = '';
			}
			else if (!$rLastSocket || (100000 < (round(microtime(true) * 1000) - $rLastSocket))) {
				$rSockets = getSockets();

				if (0 < count($rSockets)) {
					$rLastSocket = round(microtime(true) * 1000);

					if (!empty($rPrebuffer)) {
						foreach ($rSockets as $rSocketID) {
							if (!isset($rHasPrebuffer[$rSocketID])) {
								$rSocket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
								socket_set_nonblock($rSocket);
								echo 'Send prebuffer: ' . strlen($rPrebuffer) . ' bytes' . "\n";
								$rHasPrebuffer[$rSocketID] = true;

								foreach (str_split($rPrebuffer, BUFFER_SIZE) as $rChunk) {
									socket_sendto($rSocket, $rChunk, BUFFER_SIZE, 0, CONS_TMP_PATH . $rStreamID . '/' . $rSocketID);
								}

								socket_close($rSocket);
							}
						}
					}
				}
				else {
					if (!$rLastSocket) {
						$rLastSocket = round(microtime(true) * 1000);
					}

					if (CLOSE_EMPTY <= round(microtime(true) * 1000) - $rLastSocket) {
						echo 'No sockets waiting, close stream' . "\n";
						break;
					}
				}
			}

			if ($rPacketNum == 0) {
				usleep(10000);
			}
		}

		fclose($rFP);
		$db->db_connect();
		$db->query('UPDATE `streams_servers` SET `monitor_pid` = null, `pid` = null, `stream_status` = 1 WHERE `server_stream_id` = ?;', $rStreamInfo['server_stream_id']);

		if (XCMS::$rSettings['enable_cache']) {
			XCMS::updateStream($rStreamID);
		}

		exit();
	}
	else {
		echo 'Failed!' . "\n";
		XCMS::streamLog($rStreamID, SERVER_ID, 'STREAM_START_FAIL');
		$db->query('UPDATE `streams_servers` SET `monitor_pid` = null, `pid` = null, `stream_status` = 1 WHERE `server_stream_id` = ?;', $rStreamInfo['server_stream_id']);

		if (XCMS::$rSettings['enable_cache']) {
			XCMS::updateStream($rStreamID);
		}
	}
}

function getSockets()
{
	global $rStreamID;
	$rSockets = [];

	if ($rHandle = opendir(CONS_TMP_PATH . $rStreamID . '/')) {
		while (($rFilename = readdir($rHandle)) !== false) {
			if (($rFilename != '.') && ($rFilename != '..')) {
				$rSockets[] = $rFilename;
			}
		}

		closedir($rHandle);
	}

	return $rSockets;
}

function getActiveStream($rURLs, $rContext)
{
	foreach ($rURLs as $rURL) {
		$rURL = XCMS::parseStreamURL($rURL);
		$rFP = @fopen($rURL, 'rb', false, $rContext);

		if ($rFP) {
			$rMetadata = stream_get_meta_data($rFP);
			$rHeaders = [];

			foreach ($rMetadata['wrapper_data'] as $rLine) {
				if (strpos($rLine, 'HTTP') === 0) {
					$rHeaders[0] = $rLine;
					continue;
				}

				list($rKey, $rValue) = explode(': ', $rLine);
				$rHeaders[$rKey] = $rValue;
			}

			$rContentType = (is_array($rHeaders['Content-Type']) ? $rHeaders['Content-Type'][count($rHeaders['Content-Type']) - 1] : $rHeaders['Content-Type']);

			if (strtolower($rContentType) == 'video/mp2t') {
				return $rFP;
			}
			else {
				fclose($rFP);

				if (in_array(strtolower($rContentType), ['application/x-mpegurl' => true, 'application/vnd.apple.mpegurl' => true, 'audio/x-mpegurl' => true])) {
					return $rURL;
				}
			}
		}
	}

	return NULL;
}

function checkRunning($rStreamID)
{
	clearstatcache(true);

	if (file_exists(STREAMS_PATH . $rStreamID . '_.monitor')) {
		$rPID = (int) file_get_contents(STREAMS_PATH . $rStreamID . '_.monitor');
	}

	if (empty($rPID)) {
		shell_exec('kill -9 `ps -ef | grep \'XCMSProxy\\[' . (int) $rStreamID . '\\]\' | grep -v grep | awk \'{print $2}\'`;');
	}
	else if (file_exists('/proc/' . $rPID)) {
		$rCommand = trim(file_get_contents('/proc/' . $rPID . '/cmdline'));
		if (($rCommand == 'XCMSProxy[' . $rStreamID . ']') && is_numeric($rPID) && (0 < $rPID)) {
			posix_kill($rPID, 9);
		}
	}
}

function shutdown()
{
	global $rStreamID;
	global $rFP;
	@unlink(STREAMS_PATH . $rStreamID . '_.monitor');
	@unlink(STREAMS_PATH . $rStreamID . '_.pid');
	shell_exec('rm -rf ' . CONS_TMP_PATH . $rStreamID . '/');

	if (is_resource($rFP)) {
		@fclose($rFP);
	}
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}
if (!@$argc || ($argc <= 1)) {
	exit(0);
}

$rStreamID = (int) $argv[1];
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
checkRunning($rStreamID);
register_shutdown_function('shutdown');
set_time_limit(0);
cli_set_process_title('XCMSProxy[' . $rStreamID . ']');
$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_servers` t2 ON t2.stream_id = t1.id AND t2.server_id = ? WHERE t1.id = ?', SERVER_ID, $rStreamID);

if ($db->num_rows() <= 0) {
	XCMS::stopStream($rStreamID);
	exit();
}

file_put_contents(STREAMS_PATH . $rStreamID . '_.monitor', getmypid());
@unlink(STREAMS_PATH . $rStreamID . '_.pid');
$rStreamInfo = $db->get_row();
$db->query('SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 WHERE t1.stream_id = ? AND t1.argument_id = t2.id', $rStreamID);
$rStreamArguments = $db->get_rows(true, 'argument_key');
const PAT_HEADER = "\xb0" . '' . "\r" . '' . "\0" . '' . "\x1";
const PACKET_SIZE = 188;
const BUFFER_SIZE = 12032;
const PAT_PERIOD = 2;
const TIMEOUT = 20;
const CLOSE_EMPTY = 3000;
const STORE_PREBUFFER = 1128000;
const MAX_PREBUFFER = 10528000;
$rFP = NULL;
startproxy($rStreamID, $rStreamInfo, $rStreamArguments);

?>