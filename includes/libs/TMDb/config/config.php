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

$cnf['apikey'] = '';
$cnf['lang'] = 'en';
$cnf['adult'] = false;
$cnf['debug'] = false;
$cnf['appender']['movie'] = [
	'default' => ['trailers', 'images', 'credits', 'translations', 'reviews'],
	'all'     => ['account_states', 'alternative_titles', 'credits', 'images', 'keywords', 'release_dates', 'videos', 'translations', 'similar', 'reviews', 'lists', 'changes', 'rating']
];
$cnf['appender']['tvshow'] = [
	'default' => ['trailers', 'images', 'credits', 'translations', 'keywords'],
	'all'     => ['account_states', 'alternative_titles', 'changes', 'content_rating', 'credits', 'external_ids', 'images', 'keywords', 'rating', 'similar', 'translations', 'videos']
];
$cnf['appender']['season'] = [
	'default' => ['trailers', 'images', 'credits', 'translations'],
	'all'     => ['changes', 'account_states', 'credits', 'external_ids', 'images', 'videos']
];
$cnf['appender']['episode'] = [
	'default' => ['trailers', 'images', 'credits', 'translations'],
	'all'     => ['changes', 'account_states', 'credits', 'external_ids', 'images', 'rating', 'videos']
];
$cnf['appender']['person'] = [
	'default' => ['movie_credits', 'tv_credits', 'images'],
	'all'     => ['movie_credits', 'tv_credits', 'combined_credits', 'external_ids', 'images', 'tagged_images', 'changes']
];
$cnf['appender']['collection'] = [
	'default' => ['images'],
	'all'     => ['images']
];
$cnf['appender']['company'] = [
	'default' => ['movies'],
	'all'     => ['movies']
];

?>