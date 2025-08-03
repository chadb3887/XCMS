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

if ($rMobile) {
	header('Location: dashboard');
}

$rPageInt = (0 < (int) XCMS::$rRequest['page'] ? (int) XCMS::$rRequest['page'] : 1);
$rLimit = (0 < (int) XCMS::$rRequest['entries'] ? (int) XCMS::$rRequest['entries'] : XCMS::$rSettings['default_entries']);
$rStart = $rLimit * ($rPageInt - 1);

if (0 < count($rPermissions['stream_ids'])) {
	$rWhere = $rWhereV = [];
	$rWhere[] = '`type` = 1 AND `epg_id` IS NOT NULL AND `channel_id` IS NOT NULL';
	$rWhere[] = '`id` IN (' . implode(',', array_map('intval', $rPermissions['stream_ids'])) . ')';
	if (isset(XCMS::$rRequest['category']) && (0 < (int) XCMS::$rRequest['category'])) {
		$rWhere[] = 'JSON_CONTAINS(`category_id`, ?, \'$\')';
		$rWhereV[] = XCMS::$rRequest['category'];
	}

	if (!empty(XCMS::$rRequest['search'])) {
		$rWhere[] = '(`stream_display_name` LIKE ? OR `id` LIKE ?';
		$rWhereV[] = $rWhereV[] = '%' . XCMS::$rRequest['search'] . '%';
		$rWhereV[] = XCMS::$rRequest['search'];
	}

	if (0 < count($rWhere)) {
		$rWhereString = 'WHERE ' . implode(' AND ', $rWhere);
	}
	else {
		$rWhereString = '';
	}

	$rOrder = ['name' => '`stream_display_name` ASC', 'added' => '`added` DESC'];
	if (!empty(XCMS::$rRequest['sort']) && isset($rOrder[XCMS::$rRequest['sort']])) {
		$rOrderBy = $rOrder[XCMS::$rRequest['sort']];
	}
	else {
		$rChannelOrder = igbinary_unserialize(file_get_contents(CACHE_TMP_PATH . 'channel_order'));
		if ((XCMS::$rSettings['channel_number_type'] != 'manual') && (0 < count($rChannelOrder))) {
			$rOrderBy = 'FIELD(`id`,' . implode(',', $rChannelOrder) . ')';
		}
		else {
			$rOrderBy = '`order` ASC';
		}
	}

	$rStreamIDs = [];
	$db->query('SELECT COUNT(`id`) AS `count` FROM `streams` ' . $rWhereString . ';', ...$rWhereV);
	$rCount = $db->get_row()['count'];
	$db->query('SELECT `id` FROM `streams` ' . $rWhereString . ' ORDER BY ' . $rOrderBy . ' LIMIT ' . $rStart . ', ' . $rLimit . ';', ...$rWhereV);

	foreach ($db->get_rows() as $rRow) {
		$rStreamIDs[] = $rRow['id'];
	}
}
else {
	$rStreamIDs = [];
	$rCount = 0;
}

$rPages = ceil($rCount / $rLimit);
$rPagination = [];

foreach (range($rPageInt - 2, $rPageInt + 2) as $i) {
	if ((1 <= $i) && ($i <= $rPages)) {
		$rPagination[] = $i;
	}
}

$_TITLE = 'TV Guide';
include 'header.php';
echo '<div class="wrapper "';
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
	echo ' style="display: none;"';
}

echo '>' . "\n" . '    <div class="container-fluid">' . "\n\t\t" . '<div class="row">' . "\n\t\t\t" . '<div class="col-12">' . "\n\t\t\t\t" . '<div class="page-title-box">' . "\n\t\t\t\t\t" . '<div class="page-title-right">' . "\n" . '                        ';
include 'topbar.php';
echo "\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t" . '<h4 class="page-title">TV Guide</h4>' . "\n\t\t\t\t" . '</div>' . "\n\t\t\t\t" . '<form method="GET" action="epg_view">' . "\n\t\t\t\t\t" . '<div class="card">' . "\n\t\t\t\t\t\t" . '<div class="card-body">' . "\n\t\t\t\t\t\t\t" . '<div id="collapse_filters" class="form-group row" style="margin-bottom: 0;">' . "\n\t\t\t\t\t\t\t\t" . '<div class="col-md-3">' . "\n\t\t\t\t\t\t\t\t\t" . '<input type="text" class="form-control" id="search" name="search" value="';

if (isset(XCMS::$rRequest['search'])) {
	echo htmlspecialchars(XCMS::$rRequest['search']);
}

echo '" placeholder="Search Streams...">' . "\n\t\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t\t" . '<div class="col-md-3">' . "\n\t\t\t\t\t\t\t\t\t" . '<select id="category" name="category" class="form-control" data-toggle="select2">' . "\n\t\t\t\t\t\t\t\t\t\t" . '<option value=""';

if (!isset(XCMS::$rRequest['category'])) {
	echo ' selected';
}

echo '>';
echo $_['all_categories'];
echo '</option>' . "\n\t\t\t\t\t\t\t\t\t\t";

foreach (getCategories('live') as $rCategory) {
	echo "\t\t\t\t\t\t\t\t\t\t" . '<option value="';
	echo (int) $rCategory['id'];
	echo '"';
	if (isset(XCMS::$rRequest['category']) && (XCMS::$rRequest['category'] == $rCategory['id'])) {
		echo ' selected';
	}

	echo '>';
	echo $rCategory['category_name'];
	echo '</option>' . "\n\t\t\t\t\t\t\t\t\t\t";
}

echo "\t\t\t\t\t\t\t\t\t" . '</select>' . "\n\t\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t\t" . '<label class="col-md-1 col-form-label text-center" for="user_show_entries">Sort</label>' . "\n\t\t\t\t\t\t\t\t" . '<div class="col-md-1">' . "\n\t\t\t\t\t\t\t\t\t" . '<select id="sort" name="sort" class="form-control" data-toggle="select2">' . "\n\t\t\t\t\t\t\t\t\t\t";

foreach (['' => 'Default', 'name' => 'A to Z', 'added' => 'Date Added'] as $rSort => $rText) {
	echo "\t\t\t\t\t\t\t\t\t\t" . '<option value="';
	echo $rSort;
	echo '"';
	if (isset(XCMS::$rRequest['sort']) && ($rSort == XCMS::$rRequest['sort'])) {
		echo ' selected';
	}

	echo '>';
	echo $rText;
	echo '</option>' . "\n\t\t\t\t\t\t\t\t\t\t";
}

echo "\t\t\t\t\t\t\t\t\t" . '</select>' . "\n\t\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t\t" . '<label class="col-md-1 col-form-label text-center" for="user_show_entries">Show</label>' . "\n\t\t\t\t\t\t\t\t" . '<div class="col-md-1">' . "\n\t\t\t\t\t\t\t\t\t" . '<select id="entries" name="entries" class="form-control" data-toggle="select2">' . "\n\t\t\t\t\t\t\t\t\t\t";

foreach ([10, 25, 50, 250, 500, 1000] as $rShow) {
	echo "\t\t\t\t\t\t\t\t\t\t" . '<option';

	if ($rLimit == $rShow) {
		echo ' selected';
	}

	echo ' value="';
	echo $rShow;
	echo '">';
	echo $rShow;
	echo '</option>' . "\n\t\t\t\t\t\t\t\t\t\t";
}

echo "\t\t\t\t\t\t\t\t\t" . '</select>' . "\n\t\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t\t" . '<div class="btn-group col-md-2">' . "\n\t\t\t\t\t\t\t\t\t" . '<button type="submit" class="btn btn-info">Search</button>' . "\n\t\t\t\t\t\t\t\t\t" . '<button type="button" onClick="clearForm()" class="btn btn-warning"><i class="mdi mdi-filter-remove"></i></button>' . "\n\t\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t" . '</div>' . "\n\t\t\t\t" . '</form>' . "\n\t\t\t" . '</div>' . "\n\t\t" . '</div>' . "\n\t\t" . '<div class="row">' . "\n\t\t\t" . '<div class="col-12">' . "\n\t\t\t\t";

if (0 < count($rStreamIDs)) {
	echo "\t\t\t\t" . '<div class="listings-grid-container">' . "\n\t\t\t\t\t" . '<a href="#" class="listings-direction-link left day-nav-arrow js-day-nav-arrow" data-direction="prev"><span class="isvg isvg-left-dir"></span></a>' . "\n\t\t\t\t\t" . '<a href="#" class="listings-direction-link right day-nav-arrow js-day-nav-arrow" data-direction="next"><span class="isvg isvg-right-dir"></span></a>' . "\n\t\t\t\t\t" . '<div class="listings-day-slider-wrapper">' . "\n\t\t\t\t\t\t" . '<div class="listings-day-slider js-listings-day-slider">' . "\n\t\t\t\t\t\t\t" . '<div class="js-listings-day-nav-inner"></div>' . "\n\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t" . '<div class="js-billboard-fix-point"></div>' . "\n\t\t\t\t\t" . '<div class="listings-grid-inner">' . "\n\t\t\t\t\t\t" . '<div class="time-nav-bar cf js-time-nav-bar">' . "\n\t\t\t\t\t\t\t" . '<div class="listings-mobile-nav">' . "\n\t\t\t\t\t\t\t\t" . '<a class="listings-now-btn js-now-btn" href="#">NOW</a>' . "\n\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t" . '<div class="listings-times-wrapper">' . "\n\t\t\t\t\t\t\t\t" . '<a href="#" class="listings-direction-link left js-time-nav-arrow" data-direction="prev"><span class="isvg isvg-left-dir text-white"></span></a>' . "\n\t\t\t\t\t\t\t\t" . '<a href="#" class="listings-direction-link right js-time-nav-arrow" data-direction="next"><span class="isvg isvg-right-dir text-white"></span></a>' . "\n\t\t\t\t\t\t\t\t" . '<div class="times-slider js-times-slider"></div>' . "\n\t\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t\t" . '<div class="listings-loader js-listings-loader"><span class="isvg isvg-loader animate-spin"></span></div>' . "\n\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t\t" . '<div class="listings-wrapper cf js-listings-wrapper">' . "\n\t\t\t\t\t\t\t" . '<div class="listings-timeline js-listings-timeline"></div>' . "\n\t\t\t\t\t\t\t" . '<div class="js-listings-container"></div>' . "\n\t\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t" . '</div>' . "\n\t\t\t\t\t";

	if (1 < $rPages) {
		echo "\t\t\t\t\t" . '<ul class="paginator">' . "\n\t\t\t\t\t\t";

		if (1 < $rPageInt) {
			echo '<li class="paginator__item paginator__item--prev">' . "\n\t\t\t\t\t\t\t\t" . '<a href="epg_view?search=' . (urlencode(XCMS::$rRequest['search']) ?: '') . '&category=' . (XCMS::$rRequest['category'] ? (int) XCMS::$rRequest['category'] : '') . '&sort=' . (XCMS::$rRequest['sort'] ? urlencode(XCMS::$rRequest['sort']) : '') . '&entries=' . (XCMS::$rRequest['entries'] ? (int) XCMS::$rRequest['entries'] : '') . '&page=' . ($rPageInt - 1) . '"><i class="mdi mdi-chevron-left"></i></a>' . "\n\t\t\t\t\t\t\t" . '</li>';
		}

		if (1 < $rPagination[0]) {
			echo '<li class="paginator__item' . ($rPageInt == 1 ? ' paginator__item--active' : '') . '"><a href="epg_view?search=' . (urlencode(XCMS::$rRequest['search']) ?: '') . '&category=' . (XCMS::$rRequest['category'] ? (int) XCMS::$rRequest['category'] : '') . '&sort=' . (XCMS::$rRequest['sort'] ? urlencode(XCMS::$rRequest['sort']) : '') . '&entries=' . (XCMS::$rRequest['entries'] ? (int) XCMS::$rRequest['entries'] : '') . '&page=1">1</a></li>';

			if (1 < count($rPagination)) {
				echo '<li class=\'paginator__item\'><a href=\'javascript: void(0);\'>...</a></li>';
			}
		}

		foreach ($rPagination as $i) {
			echo '<li class="paginator__item' . ($rPageInt == $i ? ' paginator__item--active' : '') . '"><a href="epg_view?search=' . (urlencode(XCMS::$rRequest['search']) ?: '') . '&category=' . (XCMS::$rRequest['category'] ? (int) XCMS::$rRequest['category'] : '') . '&sort=' . (XCMS::$rRequest['sort'] ? urlencode(XCMS::$rRequest['sort']) : '') . '&entries=' . (XCMS::$rRequest['entries'] ? (int) XCMS::$rRequest['entries'] : '') . '&page=' . $i . '">' . $i . '</a></li>';
		}

		if ($rPagination[count($rPagination) - 1] < $rPages) {
			if (1 < count($rPagination)) {
				echo '<li class=\'paginator__item\'><a href=\'javascript: void(0);\'>...</a></li>';
			}

			echo '<li class="paginator__item' . ($rPageInt == $rPages ? ' paginator__item--active' : '') . '"><a href="epg_view?search=' . (urlencode(XCMS::$rRequest['search']) ?: '') . '&category=' . (XCMS::$rRequest['category'] ? (int) XCMS::$rRequest['category'] : '') . '&sort=' . (XCMS::$rRequest['sort'] ? urlencode(XCMS::$rRequest['sort']) : '') . '&entries=' . (XCMS::$rRequest['entries'] ? (int) XCMS::$rRequest['entries'] : '') . '&page=' . $rPages . '">' . $rPages . '</a></li>';
		}

		if ($rPageInt < $rPages) {
			echo '<li class="paginator__item paginator__item--next">' . "\n\t\t\t\t\t\t\t\t" . '<a href="epg_view?search=' . (urlencode(XCMS::$rRequest['search']) ?: '') . '&category=' . (XCMS::$rRequest['category'] ? (int) XCMS::$rRequest['category'] : '') . '&sort=' . (XCMS::$rRequest['sort'] ? urlencode(XCMS::$rRequest['sort']) : '') . '&entries=' . (XCMS::$rRequest['entries'] ? (int) XCMS::$rRequest['entries'] : '') . '&page=' . ($rPageInt + 1) . '"><i class="mdi mdi-chevron-right"></i></a>' . "\n\t\t\t\t\t\t\t" . '</li>';
		}

		echo "\t\t\t\t\t" . '</ul>' . "\n\t\t\t\t\t";
	}

	echo "\t\t\t\t" . '</div>' . "\n\t\t\t\t";
}
else {
	echo "\t\t\t\t" . '<div class="alert alert-warning alert-dismissible fade show" role="alert">' . "\n" . '                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">' . "\n" . '                        <span aria-hidden="true">×</span>' . "\n" . '                    </button>' . "\n" . '                    No Live Streams or Programmes have been found matching your search terms.' . "\n\t\t\t\t" . '</div>' . "\n\t\t\t\t";
}

echo "\t\t\t" . '</div>' . "\n\t\t" . '</div>' . "\n" . '    </div>' . "\n" . '</div>' . "\n";
include 'footer.php';

?>