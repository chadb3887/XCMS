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

function loadCron()
{
	global $db;

	if ((time() - XCMS::$rSettings['cc_time']) < 3600) {
		exit();
	}
	else {
		$db->query('UPDATE `settings` SET `cc_time` = ?;', time());
	}

	$db->query('SELECT `id`, `stream_display_name`, `series_no`, `stream_source` FROM `streams` WHERE `type` = 3 AND `series_no` <> 0;');

	if (0 < $db->num_rows()) {
		foreach ($db->get_rows() as $rRow) {
			$rPlaylist = generateSeriesPlaylist((int) $rRow['series_no']);

			if ($rPlaylist['success']) {
				$rSourceArray = json_decode($rRow['stream_source'], true);
				$rUpdate = false;

				foreach ($rPlaylist['sources'] as $rSource) {
					if (!in_array($rSource, $rSourceArray)) {
						$rUpdate = true;
					}
				}

				if ($rUpdate) {
					$db->query('UPDATE `streams` SET `stream_source` = ? WHERE `id` = ?;', json_encode($rPlaylist['sources'], JSON_UNESCAPED_UNICODE), $rRow['id']);
					echo 'Updated: ' . $rRow['stream_display_name'] . "\n";
				}
			}
		}
	}

	scanBouquets();
}

if (posix_getpwuid(posix_geteuid())['name'] != 'xcms') {
	exit('Please run as XCMS!' . "\n");
}

set_time_limit(0);

if (!@$argc) {
	exit(0);
}

require str_replace('\\', '/', dirname($argv[0])) . '/../includes/admin.php';
cli_set_process_title('XCMS[Series]');
$rIdentifier = CRONS_TMP_PATH . md5(XCMS::generateUniqueCode() . __FILE__);
XCMS::checkCron($rIdentifier);
loadcron();
@unlink($rIdentifier);

?>