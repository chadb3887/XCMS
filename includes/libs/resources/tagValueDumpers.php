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

$attributeListDumper = new AttributeListDumper(new Config(require __DIR__ . '/attributeValueDumpers.php'));
return [
	'int'            => 'strval',
	'bool'           => NULL,
	'enum'           => NULL,
	'attribute-list' => [$attributeListDumper, 'dump'],
	'inf'            => 'strval',
	'byterange'      => 'strval',
	'datetime'       => ['Iso8601Transformer', 'toString']
];

?>