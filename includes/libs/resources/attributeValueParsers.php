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
return [
	'decimal-integer'               => 'intval',
	'hexadecimal-sequence'          => 'strval',
	'decimal-floating-point'        => 'floatval',
	'signed-decimal-floating-point' => 'floatval',
	'quoted-string'                 => function($value) {
	return trim($value, '"');
},
	'enumerated-string'             => 'strval',
	'decimal-resolution'            => ['Resolution', 'fromString'],
	'datetime'                      => function($value) {
	return Iso8601Transformer::fromString(trim($value, '"'));
},
	'byterange'                     => function($value) {
	return Byterange::fromString(trim($value, '"'));
}
];

?>