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

echo 'GNU nano 4.8                                                                                                                                                                        fixtmdb.php                                                                                                                                                                                   ' . "\r\n";
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
cli_set_process_title('XCMS[Relink TMDB Images]');
global $db;
$db->query('SELECT streams_episodes.stream_id, streams_episodes.season_num, streams_episodes.episode_num, streams_series.tmdb_id FROM streams_episodes LEFT JOIN streams_series ON streams_episodes.series_id = streams_series.id' . "\r\n");

foreach ($db->get_rows() as $rRow) {
	$url = 'https://api.themoviedb.org/3/tv/' . $rRow['tmdb_id'] . '/season/' . $rRow['season_num'] . '/episode/' . $rRow['episode_num'] . '?api_key=' . XCMS::$rSettings['tmdb_api_key'];
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	$result = curl_exec($ch);
	curl_close($ch);
	$tmdb = json_decode($result, true);

	if ($tmdb['still_path']) {
		$moviePath = 'https://image.tmdb.org/t/p/original/' . $tmdb['still_path'];
		$moviePath = str_replace('"', '', $moviePath);
		$db->query('update streams set movie_properties = JSON_REPLACE(movie_properties,\'$.movie_image\', ?) WHERE id = ?;', $moviePath, $rRow['stream_id']);
		echo $url . 'Updated Stream ' . $rRow['stream_id'] . ' To: ' . $moviePath . PHP_EOL;
	}

	$url = '';
}

?>