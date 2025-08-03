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

$_TITLE = 'Episodes';
include 'header.php';
echo '<div class="wrapper boxed-layout-ext">' . "\n" . '    <div class="container-fluid">' . "\n" . '        <div class="row">' . "\n" . '            <div class="col-12">' . "\n" . '                <div class="page-title-box">' . "\n" . '                    <div class="page-title-right">' . "\n" . '                        ';
include 'topbar.php';
echo "\t\t\t\t\t" . '</div>' . "\n" . '                    <h4 class="page-title">';
echo $_['episodes'];
echo '</h4>' . "\n" . '                </div>' . "\n" . '            </div>' . "\n" . '        </div>     ' . "\n" . '        <div class="row">' . "\n" . '            <div class="col-12">' . "\n" . '                <div class="card">' . "\n" . '                    <div class="card-body" style="overflow-x:auto;">' . "\n" . '                        <div id="collapse_filters" class="';

if ($rMobile) {
	echo 'collapse';
}

echo ' form-group row mb-4">' . "\n" . '                            <div class="col-md-3">' . "\n" . '                                <input type="text" class="form-control" id="episodes_search" value="';

if (isset(XCMS::$rRequest['search'])) {
	echo htmlspecialchars(XCMS::$rRequest['search']);
}

echo '" placeholder="';
echo $_['search_episodes'];
echo '...">' . "\n" . '                            </div>' . "\n" . '                            <div class="col-md-3">' . "\n" . '                                <select id="episodes_series" class="form-control" data-toggle="select2">' . "\n" . '                                    <option value=""';

if (!isset(XCMS::$rRequest['series'])) {
	echo ' selected';
}

echo '>';
echo $_['all_series'];
echo '</option>' . "\n" . '                                    ';

foreach (getSeriesList() as $rSeriesArr) {
	if (in_array($rSeriesArr['id'], $rPermissions['series_ids'])) {
		echo '                                    <option value="';
		echo $rSeriesArr['id'];
		echo '"';
		if (isset(XCMS::$rRequest['series']) && (XCMS::$rRequest['series'] == $rSeriesArr['id'])) {
			echo ' selected';
		}

		echo '>';
		echo $rSeriesArr['title'];
		echo '</option>' . "\n" . '                                    ';
	}
}

echo '                                </select>' . "\n" . '                            </div>' . "\n" . '                            <div class="col-md-3">' . "\n" . '                                <select id="series_category_id" class="form-control" data-toggle="select2">' . "\n" . '                                    <option value=""';

if (!isset(XCMS::$rRequest['category'])) {
	echo ' selected';
}

echo '>';
echo $_['all_categories'];
echo '</option>' . "\n" . '                                    ';

foreach (getCategories('series') as $rCategory) {
	if (in_array($rCategory['id'], $rPermissions['category_ids'])) {
		echo '                                    <option value="';
		echo $rCategory['id'];
		echo '"';
		if (isset(XCMS::$rRequest['category']) && (XCMS::$rRequest['category'] == $rCategory['id'])) {
			echo ' selected';
		}

		echo '>';
		echo $rCategory['category_name'];
		echo '</option>' . "\n" . '                                    ';
	}
}

echo '                                </select>' . "\n" . '                            </div>' . "\n" . '                            <label class="col-md-1 col-form-label text-center" for="episodes_show_entries">';
echo $_['show'];
echo '</label>' . "\n" . '                            <div class="col-md-2">' . "\n" . '                                <select id="episodes_show_entries" class="form-control" data-toggle="select2">' . "\n" . '                                    ';

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
	echo '</option>' . "\n" . '                                    ';
}

echo '                                </select>' . "\n" . '                            </div>' . "\n" . '                        </div>' . "\n" . '                        <table id="datatable-streampage" class="table table-striped table-borderless dt-responsive nowrap font-normal">' . "\n" . '                            <thead>' . "\n" . '                                <tr>' . "\n" . '                                    <th class="text-center">ID</th>' . "\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">Image</th>' . "\n\t\t\t\t\t\t\t\t\t" . '<th>Name</th>' . "\n" . '                                    <th>Category</th>' . "\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">Connections</th>' . "\n\t\t\t\t\t\t\t\t\t" . '<th class="text-center">Kill</th>' . "\n" . '                                </tr>' . "\n" . '                            </thead>' . "\n" . '                            <tbody></tbody>' . "\n" . '                        </table>' . "\n" . '                    </div> ' . "\n" . '                </div> ' . "\n" . '            </div>' . "\n" . '        </div>' . "\n" . '    </div>' . "\n" . '</div>' . "\n";
include 'footer.php';

?>