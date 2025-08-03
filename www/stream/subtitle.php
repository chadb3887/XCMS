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

function convertVTT($rSubtitle)
{
	$rLines = explode("\n", $rSubtitle);
	$rLength = count($rLines);

	for ($rIndex = 1; $rIndex < $rLength; $rIndex++) {
		if (($rIndex === 1) || (trim($rLines[$rIndex - 2]) === '')) {
			$rLines[$rIndex] = str_replace(',', '.', $rLines[$rIndex]);
		}
	}

	$rHeader = 'WEBVTT' . "\n\n";
	return $rHeader . implode("\n", $rLines);
}

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
$rSubID = 0;

if (isset(XCMS::$rRequest['token'])) {
	$rTokenData = json_decode(Xcms\Functions::decrypt(XCMS::$rRequest['token'], XCMS::$rSettings['live_streaming_pass'], OPENSSL_EXTRA), true);
	if (!is_array($rTokenData) || (isset($rTokenData['expires']) && ($rTokenData['expires'] < (time() - (int) XCMS::$rServers[SERVER_ID]['time_offset'])))) {
		generateError('TOKEN_EXPIRED');
	}

	$rStreamID = $rTokenData['stream_id'];
	$rSubID = (int) $rTokenData['sub_id'] ?: 0;
	$rWebVTT = (int) $rTokenData['webvtt'] ?: 0;
}
if ($rStreamID && file_exists(VOD_PATH . $rStreamID . '_' . $rSubID . '.srt')) {
	header('Content-Description: File Transfer');
	header('Content-type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $rStreamID . '_' . $rSubID . '.' . ($rWebVTT ? 'vtt' : 'srt') . '"');
	$rOutput = file_get_contents(VOD_PATH . $rStreamID . '_' . $rSubID . '.srt');

	if ($rWebVTT) {
		$rOutput = convertvtt($rOutput);
	}

	header('Content-Length: ' . strlen($rOutput));
	echo $rOutput;
	exit();
}
else {
	generateError('THUMBNAIL_DOESNT_EXIST');
}

?>