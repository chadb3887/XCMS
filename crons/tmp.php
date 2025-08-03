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

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

if (!@$argc) {
	exit(0);
}

set_time_limit(0);
require str_replace('\\', '/', dirname($argv[0])) . '/../www/init.php';
$db->close_mysql();
cli_set_process_title('XCMS[TMP]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);

foreach ([TMP_PATH, CRONS_TMP_PATH, DIVERGENCE_TMP_PATH, FLOOD_TMP_PATH, MINISTRA_TMP_PATH, SIGNALS_TMP_PATH, LOGS_TMP_PATH] as $rTmpPath) {
	foreach (scandir($rTmpPath) as $rFile) {
		if ((600 <= time() - filemtime($rTmpPath . $rFile)) && (stripos($rFile, 'ministra_') === false)) {
			unlink($rTmpPath . $rFile);
		}
	}
}

foreach (scandir(PLAYLIST_PATH) as $rFile) {
	if (XCMS::$rSettings['cache_playlists'] <= time() - filemtime(PLAYLIST_PATH . $rFile)) {
		unlink(PLAYLIST_PATH . $rFile);
	}
}

clearstatcache();
@unlink($rIdentifier);

?>