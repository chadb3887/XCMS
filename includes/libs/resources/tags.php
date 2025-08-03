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

$definitions = [
	'EXT-X-VERSION'                => ['category' => 'playlist', 'type' => 'int', 'position' => -3500],
	'EXT-X-TARGETDURATION'         => ['category' => 'media-playlist', 'type' => 'int', 'position' => -2900],
	'EXT-X-MEDIA-SEQUENCE'         => ['category' => 'media-playlist', 'type' => 'int', 'position' => -2800],
	'EXT-X-DISCONTINUITY-SEQUENCE' => ['category' => 'media-playlist', 'type' => 'int', 'position' => -2700],
	'EXT-X-PLAYLIST-TYPE'          => ['category' => 'media-playlist', 'type' => 'enum', 'position' => -2600],
	'EXT-X-I-FRAMES-ONLY'          => ['category' => 'media-playlist', 'type' => 'bool', 'position' => -2500],
	'EXT-X-ENDLIST'                => ['category' => 'media-playlist', 'type' => 'bool', 'position' => 1000],
	'EXT-X-MEDIA'                  => [
	'category' => 'master-playlist',
	'type'     => ['TYPE' => 'enumerated-string', 'URI' => 'quoted-string', 'GROUP-ID' => 'quoted-string', 'LANGUAGE' => 'quoted-string', 'ASSOC-LANGUAGE' => 'quoted-string', 'NAME' => 'quoted-string', 'DEFAULT' => 'enumerated-string', 'AUTOSELECT' => 'enumerated-string', 'FORCED' => 'enumerated-string', 'INSTREAM-ID' => 'quoted-string', 'CHARACTERISTICS' => 'quoted-string', 'CHANNELS' => 'quoted-string'],
	'position' => -1900,
	'multiple' => true
],
	'EXT-X-STREAM-INF'             => [
	'category' => 'master-playlist',
	'multiple' => true,
	'type'     => ['BANDWIDTH' => 'decimal-integer', 'AVERAGE-BANDWIDTH' => 'decimal-integer', 'CODECS' => 'quoted-string', 'RESOLUTION' => 'decimal-resolution', 'FRAME-RATE' => 'decimal-floating-point', 'HDCP-LEVEL' => 'enumerated-string', 'AUDIO' => 'quoted-string', 'VIDEO' => 'quoted-string', 'SUBTITLES' => 'quoted-string', 'CLOSED-CAPTIONS' => 'quoted-string'],
	'position' => -1800,
	'uriAware' => true
],
	'EXT-X-I-FRAME-STREAM-INF'     => ['category' => 'master-playlist', 'multiple' => true, 'position' => -1700],
	'EXT-X-SESSION-DATA'           => [
	'category' => 'master-playlist',
	'type'     => ['DATA-ID' => 'quoted-string', 'VALUE' => 'quoted-string', 'URI' => 'quoted-string', 'LANGUAGE' => 'quoted-string'],
	'position' => -1600
],
	'EXT-X-SESSION-KEY'            => ['category' => 'master-playlist', 'position' => -1500, 'multiple' => true],
	'EXT-X-INDEPENDENT-SEGMENTS'   => ['category' => 'playlist', 'type' => 'bool', 'position' => -900],
	'EXT-X-START'                  => [
	'category' => 'playlist',
	'type'     => ['TIME-OFFSET' => 'signed-decimal-floating-point', 'PRECISE' => 'enumerated-string'],
	'position' => -800
],
	'EXT-X-KEY'                    => [
	'category' => 'media-segment',
	'type'     => ['METHOD' => 'enumerated-string', 'URI' => 'quoted-string', 'IV' => 'hexadecimal-sequence', 'KEYFORMAT' => 'quoted-string', 'KEYFORMATVERSIONS' => 'quoted-string'],
	'position' => 100,
	'multiple' => true
],
	'EXT-X-MAP'                    => [
	'category' => 'media-segment',
	'type'     => ['URI' => 'quoted-string', 'BYTERANGE' => 'byterange'],
	'position' => 200
],
	'EXT-X-BYTERANGE'              => ['category' => 'media-segment', 'type' => 'byterange', 'position' => 300],
	'EXT-X-DISCONTINUITY'          => ['category' => 'media-segment', 'position' => 400, 'type' => 'bool'],
	'EXT-X-PROGRAM-DATE-TIME'      => ['category' => 'media-segment', 'position' => 500, 'type' => 'datetime'],
	'EXT-X-DATERANGE'              => [
	'category' => 'media-segment',
	'position' => 600,
	'type'     => ['ID' => 'quoted-string', 'CLASS' => 'quoted-string', 'START-DATE' => 'datetime', 'END-DATE' => 'datetime', 'DURATION' => 'decimal-floating-point', 'PLANNED-DURATION' => 'decimal-floating-point', 'SCTE35-CMD' => 'hexadecimal-sequence', 'SCTE35-OUT' => 'hexadecimal-sequence', 'SCTE35-IN' => 'hexadecimal-sequence', 'END-ON-NEXT' => 'enumerated-string']
],
	'EXTINF'                       => ['category' => 'media-segment', 'type' => 'inf', 'position' => 1000]
];
$definitions['EXT-X-I-FRAME-STREAM-INF']['type'] = $definitions['EXT-X-STREAM-INF']['type'];
unset($definitions['EXT-X-I-FRAME-STREAM-INF']['type']['FRAME-RATE']);
unset($definitions['EXT-X-I-FRAME-STREAM-INF']['type']['AUDIO']);
unset($definitions['EXT-X-I-FRAME-STREAM-INF']['type']['SUBTITLES']);
unset($definitions['EXT-X-I-FRAME-STREAM-INF']['type']['CLOSED-CAPTIONS']);
$definitions['EXT-X-I-FRAME-STREAM-INF']['type']['URI'] = 'quoted-string';
$definitions['EXT-X-SESSION-KEY']['type'] = $definitions['EXT-X-KEY']['type'];
return $definitions;

?>