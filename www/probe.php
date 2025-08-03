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

function shutdown()
{
	if (is_object(XCMS::$db)) {
		XCMS::$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
include './stream/init.php';

if (isset(XCMS::$rRequest['data'])) {
	$rIP = XCMS::getUserIP();
	$rPath = base64_decode(XCMS::$rRequest['data']);
	$rPathSize = count(explode('/', $rPath));
	$rUserInfo = $rStreamID = NULL;

	if ($rPathSize == 3) {
		if (!$rStreamID) {
			$rQuery = '/\\/auth\\/(.*)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 2) {
				$rData = json_decode(Xcms\Functions::decrypt($rMatches[1], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);
				$rStreamID = (int) $rData['stream_id'];
				$rUserInfo = XCMS::getUserInfo(NULL, $rData['username'], $rData['password'], true);
			}
		}

		if (!$rStreamID) {
			$rQuery = '/\\/play\\/(.*)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 2) {
				$rData = explode('/', Xcms\Functions::decrypt($rMatches[1], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA));

				if ($rData[0] == 'live') {
					$rStreamID = (int) $rData[3];
					$rUserInfo = XCMS::getUserInfo(NULL, $rData[1], $rData[2], true);
				}
			}
		}
	}
	else if ($rPathSize == 4) {
		if (!$rStreamID) {
			$rQuery = '/\\/play\\/(.*)\\/(.*)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 3) {
				$rData = explode('/', Xcms\Functions::decrypt($rMatches[1], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA));

				if ($rData[0] == 'live') {
					$rStreamID = (int) $rData[3];
					$rUserInfo = XCMS::getUserInfo(NULL, $rData[1], $rData[2], true);
				}
			}
		}

		if (!$rStreamID) {
			$rQuery = '/\\/live\\/(.*)\\/(\\d+)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 3) {
				$rStreamID = (int) $rMatches[2];
				$rUserInfo = XCMS::getUserInfo(NULL, $rMatches[1], NULL, true);
			}
		}

		if (!$rStreamID) {
			$rQuery = '/\\/live\\/(.*)\\/(\\d+)\\.(.*)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 4) {
				$rStreamID = (int) $rMatches[2];
				$rUserInfo = XCMS::getUserInfo(NULL, $rMatches[1], NULL, true);
			}
		}

		if (!$rStreamID) {
			$rQuery = '/\\/(.*)\\/(.*)\\/(\\d+)\\.(.*)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 5) {
				$rStreamID = (int) $rMatches[3];
				$rUserInfo = XCMS::getUserInfo(NULL, $rMatches[1], $rMatches[2], true);
			}
		}

		if (!$rStreamID) {
			$rQuery = '/\\/(.*)\\/(.*)\\/(\\d+)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 4) {
				$rStreamID = (int) $rMatches[3];
				$rUserInfo = XCMS::getUserInfo(NULL, $rMatches[1], $rMatches[2], true);
			}
		}
	}
	else if ($rPathSize == 5) {
		if (!$rStreamID) {
			$rQuery = '/\\/live\\/(.*)\\/(.*)\\/(\\d+)\\.(.*)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 5) {
				$rStreamID = (int) $rMatches[3];
				$rUserInfo = XCMS::getUserInfo(NULL, $rMatches[1], $rMatches[2], true);
			}
		}

		if (!$rStreamID) {
			$rQuery = '/\\/live\\/(.*)\\/(.*)\\/(\\d+)$/m';
			preg_match($rQuery, $rPath, $rMatches);

			if (count($rMatches) == 4) {
				$rStreamID = (int) $rMatches[3];
				$rUserInfo = XCMS::getUserInfo(NULL, $rMatches[1], $rMatches[2], true);
			}
		}
	}
	if ($rStreamID && $rUserInfo) {
		if (!is_null($rUserInfo['exp_date']) && ($rUserInfo['exp_date'] <= time())) {
			generate404();
		}

		if ($rUserInfo['admin_enabled'] == 0) {
			generate404();
		}

		if ($rUserInfo['enabled'] == 0) {
			generate404();
		}

		if (!$rUserInfo['is_restreamer']) {
			generate404();
		}

		$rChannelInfo = XCMS::redirectStream($rStreamID, 'ts', $rUserInfo, NULL, '', 'live');
		if (isset($rChannelInfo['redirect_id']) && ($rChannelInfo['redirect_id'] != SERVER_ID)) {
			$rServerID = $rChannelInfo['redirect_id'];
		}
		else {
			$rServerID = SERVER_ID;
		}
		if ((0 < $rChannelInfo['monitor_pid']) && (0 < $rChannelInfo['pid']) && (XCMS::$rServers[$rServerID]['last_status'] == 1)) {
			if (file_exists(STREAMS_PATH . $rStreamID . '_.stream_info')) {
				$rInfo = file_get_contents(STREAMS_PATH . $rStreamID . '_.stream_info');
			}
			else {
				$rInfo = $rChannelInfo['stream_info'];
			}

			$rInfo = json_decode($rInfo, true);
			echo json_encode(['codecs' => $rInfo['codecs'], 'container' => $rInfo['container'], 'bitrate' => $rInfo['bitrate']]);
			exit();
		}
	}
}

generate404();

?>