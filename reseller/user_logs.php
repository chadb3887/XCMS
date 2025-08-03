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

include 'session.php';
include 'functions.php';

if (!checkResellerPermissions()) {
	goHome();
}

$_TITLE = 'User Logs';
include 'header.php';
echo '<div class="wrapper">' . "\r\n" . '    <div class="container-fluid">' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="page-title-box">' . "\r\n\t\t\t\t\t" . '<div class="page-title-right">' . "\r\n" . '                        ';
include 'topbar.php';
echo "\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t" . '<h4 class="page-title">Reseller Logs</h4>' . "\r\n\t\t\t\t" . '</div>' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>     ' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="card">' . "\r\n\t\t\t\t\t" . '<div class="card-body" style="overflow-x:auto;">' . "\r\n\t\t\t\t\t\t" . '<div class="form-group row mb-4">' . "\r\n\t\t\t\t\t\t\t" . '<div class="col-md-3">' . "\r\n\t\t\t\t\t\t\t\t" . '<input type="text" class="form-control" id="log_search" value="" placeholder="Search Logs...">' . "\r\n\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t" . '<label class="col-md-1 col-form-label text-center" for="reseller">Reseller</label>' . "\r\n\t\t\t\t\t\t\t" . '<div class="col-md-3">' . "\r\n\t\t\t\t\t\t\t\t" . '<select id="reseller" class="form-control" data-toggle="select2">' . "\r\n\t\t\t\t\t\t\t\t\t" . '<optgroup label="Global">' . "\r\n" . '                                        <option value=""';

if (!isset(XCMS::$rRequest['user_id'])) {
	echo ' selected';
}

echo '>All Users</option>' . "\r\n" . '                                        <option value="';
echo $rUserInfo['id'];
echo '"';
if (isset(XCMS::$rRequest['user_id']) && (XCMS::$rRequest['user_id'] == $rUserInfo['id'])) {
	echo ' selected';
}

echo '>My Logs</option>' . "\r\n" . '                                    </optgroup>' . "\r\n" . '                                    ';

if (0 < count($rPermissions['direct_reports'])) {
	echo '                                    <optgroup label="Direct Reports">' . "\r\n" . '                                        ';

	foreach ($rPermissions['direct_reports'] as $rUserID) {
		$rRegisteredUser = $rPermissions['users'][$rUserID];
		echo '                                        <option value="';
		echo $rUserID;
		echo '"';
		if (isset(XCMS::$rRequest['user_id']) && ($rUserID == XCMS::$rRequest['user_id'])) {
			echo ' selected';
		}

		echo '>';
		echo $rRegisteredUser['username'];
		echo '</option>' . "\r\n" . '                                        ';
	}

	echo '                                    </optgroup>' . "\r\n" . '                                    ';
}

if (count($rPermissions['direct_reports']) < count($rPermissions['all_reports'])) {
	echo '                                    <optgroup label="Indirect Reports">' . "\r\n" . '                                        ';

	foreach ($rPermissions['all_reports'] as $rUserID) {
		if (!in_array($rUserID, $rPermissions['direct_reports'])) {
			$rRegisteredUser = $rPermissions['users'][$rUserID];
			echo '                                            <option value="';
			echo $rUserID;
			echo '"';
			if (isset(XCMS::$rRequest['user_id']) && ($rUserID == XCMS::$rRequest['user_id'])) {
				echo ' selected';
			}

			echo '>';
			echo $rRegisteredUser['username'];
			echo '</option>' . "\r\n" . '                                            ';
		}
	}

	echo '                                    </optgroup>' . "\r\n" . '                                    ';
}

echo "\t\t\t\t\t\t\t\t" . '</select>' . "\r\n\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t" . '<label class="col-md-1 col-form-label text-center" for="range">Dates</label>' . "\r\n\t\t\t\t\t\t\t" . '<div class="col-md-2">' . "\r\n\t\t\t\t\t\t\t\t" . '<input type="text" class="form-control text-center date" id="range" name="range" data-toggle="date-picker" data-single-date-picker="true" autocomplete="off" placeholder="All Dates">' . "\r\n\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t" . '<label class="col-md-1 col-form-label text-center" for="show_entries">Show</label>' . "\r\n\t\t\t\t\t\t\t" . '<div class="col-md-1">' . "\r\n\t\t\t\t\t\t\t\t" . '<select id="show_entries" class="form-control" data-toggle="select2">' . "\r\n\t\t\t\t\t\t\t\t\t";

foreach ([10, 25, 50, 250, 500, 1000] as $rShow) {
	echo "\t\t\t\t\t\t\t\t\t" . '<option';

	if ($rShow == $rSettings['default_entries']) {
		echo ' selected';
	}

	echo ' value="';
	echo $rShow;
	echo '">';
	echo $rShow;
	echo '</option>' . "\r\n\t\t\t\t\t\t\t\t\t";
}

echo "\t\t\t\t\t\t\t\t" . '</select>' . "\r\n\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t" . '<table id="datatable-activity" class="table table-striped table-borderless dt-responsive nowrap">' . "\r\n\t\t\t\t\t\t\t" . '<thead>' . "\r\n\t\t\t\t\t\t\t\t" . '<tr>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">ID</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th>Reseller</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th>Line / User</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th>Action</th>' . "\r\n" . '                                    <th class="text-center">Cost</th>' . "\r\n" . '                                    <th class="text-center">Credits Remaining</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">Date</th>' . "\r\n\t\t\t\t\t\t\t\t" . '</tr>' . "\r\n\t\t\t\t\t\t\t" . '</thead>' . "\r\n\t\t\t\t\t\t\t" . '<tbody></tbody>' . "\r\n\t\t\t\t\t\t" . '</table>' . "\r\n\t\t\t\t\t" . '</div> ' . "\r\n\t\t\t\t" . '</div> ' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>' . "\r\n\t" . '</div>' . "\r\n" . '</div>' . "\r\n";
include 'footer.php';

?>