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

$attributeListParser = new AttributeListParser(new Config(require __DIR__ . '/attributeValueParsers.php'), new AttributeStringToArray());
return [
	'int'            => 'intval',
	'bool'           => NULL,
	'enum'           => NULL,
	'attribute-list' => [$attributeListParser, 'parse'],
	'inf'            => ['Inf', 'fromString'],
	'byterange'      => ['Byterange', 'fromString'],
	'datetime'       => ['Iso8601Transformer', 'fromString']
];

?>