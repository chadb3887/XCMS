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
return ['decimal-integer' => 'strval', 'hexadecimal-sequence' => 'strval', 'decimal-floating-point' => 'strval', 'signed-decimal-floating-point' => 'strval', 'quoted-string' => function($value) {
	return sprintf('"%s"', $value);
}, 'enumerated-string' => 'strval', 'decimal-resolution' => 'strval', 'datetime' => function($value) {
	return sprintf('"%s"', Iso8601Transformer::toString($value));
}, 'byterange' => function($value) {
	return sprintf('"%s"', $value);
}];

?>