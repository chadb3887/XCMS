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
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
header('Access-Control-Allow-Origin: *');
set_time_limit(0);
require '../init.php';
$rIP = XCMS::getUserIP();

if (!empty(XCMS::$rRequest['uitoken'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['uitoken'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);
	XCMS::$rRequest['stream'] = $rTokenData['stream_id'];
	$rIPMatch = (XCMS::$rSettings['ip_subnet_match'] ? implode('.', array_slice(explode('.', $rTokenData['ip']), 0, -1)) == implode('.', array_slice(explode('.', XCMS::getUserIP()), 0, -1)) : XCMS::getUserIP() == $rTokenData['ip']);
	if (($rTokenData['expires'] < time()) || !$rIPMatch) {
		generate404();
	}
}
else {
	generate404();
}

$db = new Database();
XCMS::$db = & $db;
$rStreamID = (int) XCMS::$rRequest['stream'];
$rStream = [];
$db->query('SELECT * FROM `streams` t1 INNER JOIN `streams_types` t2 ON t2.type_id = t1.type AND t2.live = 1 LEFT JOIN `profiles` t4 ON t1.transcode_profile_id = t4.profile_id WHERE t1.direct_source = 0 AND t1.id = ?', $rStreamID);

if ($db->num_rows() <= 0) {
	generate404();
}

$rStream = $db->get_row();

if (SERVER_ID == $rStream['vframes_server_id']) {
	if (file_exists(STREAMS_PATH . $rStreamID . '_.jpg') && ((time() - filemtime(STREAMS_PATH . $rStreamID . '_.jpg')) < 60)) {
		header('Age: ' . (int) (time() - filemtime(STREAMS_PATH . $rStreamID . '_.jpg')));
		header('Content-type: image/jpg');
		echo file_get_contents(STREAMS_PATH . $rStreamID . '_.jpg');
		exit();
	}
	else {
		generate404();
	}
}
else {
	$rURL = XCMS::$rServers[$rStream['vframes_server_id']]['site_url'];
	header('Location: ' . $rURL . 'admin/thumb?stream=' . $rStreamID . '&aid=' . (int) XCMS::$rRequest['aid'] . '&uitoken=' . urlencode(XCMS::$rRequest['uitoken']) . '&expires=' . (int) XCMS::$rRequest['expires']);
	exit();
}

?>