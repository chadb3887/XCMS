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

$_TITLE = 'Enigma Devices';
include 'header.php';
echo '<div class="wrapper">' . "\r\n" . '    <div class="container-fluid">' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="page-title-box">' . "\r\n\t\t\t\t\t" . '<div class="page-title-right">' . "\r\n" . '                        ';
include 'topbar.php';
echo "\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t" . '<h4 class="page-title">';
echo $_['enigma_devices'];
echo '</h4>' . "\r\n\t\t\t\t" . '</div>' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>     ' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n" . '                ';
if (isset($_STATUS) && ($_STATUS == STATUS_SUCCESS)) {
	echo '                <div class="alert alert-success alert-dismissible fade show" role="alert">' . "\r\n" . '                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">' . "\r\n" . '                        <span aria-hidden="true">&times;</span>' . "\r\n" . '                    </button>' . "\r\n" . '                    Device has been added / modified.' . "\r\n" . '                </div>' . "\r\n" . '                ';
}

echo "\t\t\t\t" . '<div class="card">' . "\r\n\t\t\t\t\t" . '<div class="card-body" style="overflow-x:auto;">' . "\r\n" . '                        <div id="collapse_filters" class="';

if ($rMobile) {
	echo 'collapse';
}

echo ' form-group row mb-4">' . "\r\n" . '                            <div class="col-md-3">' . "\r\n" . '                                <input type="text" class="form-control" id="e2_search" value="';

if (isset(XCMS::$rRequest['search'])) {
	echo htmlspecialchars(XCMS::$rRequest['search']);
}

echo '" placeholder="';
echo $_['search_devices'];
echo '...">' . "\r\n" . '                            </div>' . "\r\n" . '                            <label class="col-md-2 col-form-label text-center" for="e2_reseller">';
echo $_['filter_results'];
echo '</label>' . "\r\n" . '                            <div class="col-md-3">' . "\r\n" . '                                <select id="e2_reseller" class="form-control" data-toggle="select2">' . "\r\n" . '                                    <optgroup label="Global">' . "\r\n" . '                                        <option value=""';

if (!isset(XCMS::$rRequest['owner'])) {
	echo ' selected';
}

echo '>All Owners</option>' . "\r\n" . '                                        <option value="';
echo $rUserInfo['id'];
echo '"';
if (isset(XCMS::$rRequest['owner']) && (XCMS::$rRequest['owner'] == $rUserInfo['id'])) {
	echo ' selected';
}

echo '>My Devices</option>' . "\r\n" . '                                    </optgroup>' . "\r\n" . '                                    ';

if (0 < count($rPermissions['direct_reports'])) {
	echo '                                    <optgroup label="Direct Reports">' . "\r\n" . '                                        ';

	foreach ($rPermissions['direct_reports'] as $rUserID) {
		$rRegisteredUser = $rPermissions['users'][$rUserID];
		echo '                                        <option value="';
		echo $rUserID;
		echo '"';
		if (isset(XCMS::$rRequest['owner']) && ($rUserID == XCMS::$rRequest['owner'])) {
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
			if (isset(XCMS::$rRequest['owner']) && ($rUserID == XCMS::$rRequest['owner'])) {
				echo ' selected';
			}

			echo '>';
			echo $rRegisteredUser['username'];
			echo '</option>' . "\r\n" . '                                            ';
		}
	}

	echo '                                    </optgroup>' . "\r\n" . '                                    ';
}

echo '                                </select>' . "\r\n" . '                            </div>' . "\r\n" . '                            <div class="col-md-2">' . "\r\n" . '                                <select id="e2_filter" class="form-control" data-toggle="select2">' . "\r\n" . '                                    <option value=""';

if (!isset(XCMS::$rRequest['filter'])) {
	echo ' selected';
}

echo '>';
echo $_['no_filter'];
echo '</option>' . "\r\n" . '                                    <option value="1"';
if (isset(XCMS::$rRequest['filter']) && (XCMS::$rRequest['filter'] == 1)) {
	echo ' selected';
}

echo '>';
echo $_['active'];
echo '</option>' . "\r\n" . '                                    <option value="2"';
if (isset(XCMS::$rRequest['filter']) && (XCMS::$rRequest['filter'] == 2)) {
	echo ' selected';
}

echo '>';
echo $_['disabled'];
echo '</option>' . "\r\n" . '                                    <option value="4"';
if (isset(XCMS::$rRequest['filter']) && (XCMS::$rRequest['filter'] == 3)) {
	echo ' selected';
}

echo '>';
echo $_['expired'];
echo '</option>' . "\r\n" . '                                    <option value="5"';
if (isset(XCMS::$rRequest['filter']) && (XCMS::$rRequest['filter'] == 4)) {
	echo ' selected';
}

echo '>';
echo $_['trial'];
echo '</option>' . "\r\n" . '                                </select>' . "\r\n" . '                            </div>' . "\r\n" . '                            <label class="col-md-1 col-form-label text-center" for="e2_show_entries">';
echo $_['show'];
echo '</label>' . "\r\n" . '                            <div class="col-md-1">' . "\r\n" . '                                <select id="e2_show_entries" class="form-control" data-toggle="select2">' . "\r\n" . '                                    ';

foreach ([10, 25, 50, 250, 500, 1000] as $rShow) {
	echo '                                    <option';

	if (isset(XCMS::$rRequest['entries'])) {
		if ($rShow == XCMS::$rRequest['entries']) {
			echo ' selected';
		}
	}
	else if ($rShow == $rSettings['default_entries']) {
		echo ' selected';
	}

	echo ' value="';
	echo $rShow;
	echo '">';
	echo $rShow;
	echo '</option>' . "\r\n" . '                                    ';
}

echo '                                </select>' . "\r\n" . '                            </div>' . "\r\n" . '                        </div>' . "\r\n\t\t\t\t\t\t" . '<table id="datatable-users" class="table table-striped table-borderless dt-responsive nowrap font-normal">' . "\r\n\t\t\t\t\t\t\t" . '<thead>' . "\r\n\t\t\t\t\t\t\t\t" . '<tr>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['id'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th>';
echo $_['username'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['mac_address'];
echo '</th>' . "\r\n" . '                                    <th class="text-center">Public IP</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th>';
echo $_['owner'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['status'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['online'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['trial'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['expiration'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">';
echo $_['actions'];
echo '</th>' . "\r\n\t\t\t\t\t\t\t\t" . '</tr>' . "\r\n\t\t\t\t\t\t\t" . '</thead>' . "\r\n\t\t\t\t\t\t\t" . '<tbody></tbody>' . "\r\n\t\t\t\t\t\t" . '</table>' . "\r\n\r\n\t\t\t\t\t" . '</div> ' . "\r\n\t\t\t\t" . '</div> ' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>' . "\r\n\t" . '</div>' . "\r\n" . '</div>' . "\r\n";
include 'footer.php';

?>