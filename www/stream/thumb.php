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

require_once 'init.php';
header('Access-Control-Allow-Origin: *');

if (!empty(XCMS::$rSettings['send_server_header'])) {
	header('Server: ' . XCMS::$rSettings['send_server_header']);
}

if (XCMS::$rSettings['send_protection_headers']) {
	header('X-XSS-Protection: 0');
	header('X-Content-Type-Options: nosniff');
}

if (XCMS::$rSettings['send_altsvc_header']) {
	header('Alt-Svc: h3-29=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-T051=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q050=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q046=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,h3-Q043=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000,quic=":' . XCMS::$rServers[SERVER_ID]['https_broadcast_port'] . '"; ma=2592000; v="46,43"');
}
if (empty(XCMS::$rSettings['send_unique_header_domain']) && !filter_var(HOST, FILTER_VALIDATE_IP)) {
	XCMS::$rSettings['send_unique_header_domain'] = '.' . HOST;
}

if (!empty(XCMS::$rSettings['send_unique_header'])) {
	$rExpires = new DateTime('+6 months', new DateTimeZone('GMT'));
	header('Set-Cookie: ' . XCMS::$rSettings['send_unique_header'] . '=' . XCMS::generateString(11) . '; Domain=' . XCMS::$rSettings['send_unique_header_domain'] . '; Expires=' . $rExpires->format(DATE_RFC2822) . '; Path=/; Secure; HttpOnly; SameSite=none');
}

$rStreamID = NULL;

if (isset(XCMS::$rRequest['token'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['token'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);
	if (!is_array($rTokenData) || (isset($rTokenData['expires']) && ($rTokenData['expires'] < (time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'])))) {
		generateError('TOKEN_EXPIRED');
	}

	$rStreamID = $rTokenData['stream'];
}
if ($rStreamID && file_exists(STREAMS_PATH . $rStreamID . '_.jpg') && ((time() - filemtime(STREAMS_PATH . $rStreamID . '_.jpg')) < 60)) {
	header('Age: ' . (int) (time() - filemtime(STREAMS_PATH . $rStreamID . '_.jpg')));
	header('Content-type: image/jpg');
	echo file_get_contents(STREAMS_PATH . $rStreamID . '_.jpg');
	exit();
}
else {
	generateError('THUMBNAIL_DOESNT_EXIST');
}

?>