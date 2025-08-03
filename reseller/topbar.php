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

if (count(get_included_files()) == 1) {
	exit();
}

$rPage = getPageName();
$rID = (isset(XCMS::$rRequest['id']) ? (int) XCMS::$rRequest['id'] : NULL);
$rDropdown = [
	'lines'            => [
	'Add Line'         => ['line', 'create_line'],
	'Live Connections' => ['live_connections', 'reseller_client_connection_logs'],
	'Activity Logs'    => ['line_activity', 'reseller_client_connection_logs']
],
	'live_connections' => [
	'Activity Logs' => ['line_activity', 'reseller_client_connection_logs']
],
	'line_activity'    => [
	'Live Connections' => ['live_connections', 'reseller_client_connection_logs']
],
	'mags'             => [
	'Add Device'       => ['mag', 'create_mag'],
	'Live Connections' => ['live_connections', 'reseller_client_connection_logs'],
	'Activity Logs'    => ['line_activity', 'reseller_client_connection_logs']
],
	'enigmas'          => [
	'Add Device'       => ['enigma', 'create_enigma'],
	'Live Connections' => ['live_connections', 'reseller_client_connection_logs'],
	'Activity Logs'    => ['line_activity', 'reseller_client_connection_logs']
],
	'users'            => [
	'Add User'      => ['user', 'create_sub_resellers'],
	'Client Logs'   => ['client_logs', 'reseller_client_connection_logs'],
	'Reseller Logs' => ['user_logs', NULL]
],
	'line'             => [
	'Manage Lines' => ['lines', 'create_line']
],
	'user'             => [
	'Manage Users' => ['users', 'create_sub_resellers']
],
	'mag'              => [
	'MAG Devices' => ['mags', 'create_mag']
],
	'enigma'           => [
	'Enigma Devices' => ['enigmas', 'create_enigma']
],
	'ticket'           => [
	'View Ticket'  => ['ticket_view?id=' . $rID, NULL],
	'View Tickets' => ['tickets', NULL]
],
	'ticket_view'      => [
	'Add Response' => ['ticket?id=' . $rID, NULL],
	'View Tickets' => ['tickets', NULL]
],
	'tickets'          => [
	'Create Ticket' => ['ticket', NULL]
]
];

switch ($rPage) {
case 'ticket':
	if (!isset($rTicket)) {
		unset($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]]);
	}

	break;
case 'ticket_view':
	if ($rTicketInfo['status'] == 0) {
		unset($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]]);
	}

	break;
}

$rDropdownPage = [];

if (isset($rDropdown[$rPage])) {
	foreach ($rDropdown[$rPage] as $rName => $rData) {
		if ($rName && (!$rData[1] || hasResellerPermissions($rData[1]))) {
			if (count($rData) == 3) {
				$rDropdownPage[$rName] = 'javascript: void(0);" ' . $rData[2];
			}
			else {
				$rDropdownPage[$rName] = $rData[0];
			}
		}
	}
}

switch ($rPage) {
case 'streams':
case 'created_channels':
case 'movies':
case 'users':
case 'mags':
case 'client_logs':
case 'line_activity':
case 'live_connections':
case 'lines':
case 'radios':
case 'enigmas':
case 'episodes':
	echo '<div class="btn-group">';
	if (!$rMobile && (!$rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][1] || hasResellerPermissions($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][1])) && (0 < strlen(array_keys($rDropdown[$rPage])[0]))) {
		if ($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][0]) {
			echo '<button type="button" onClick="navigate(\'' . $rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][0] . '\');" class="btn btn-sm btn-info waves-effect waves-light">' . array_keys($rDropdown[$rPage])[0] . '</button>';
		}
		else {
			if (isset($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][2])) {
				echo '<button type="button" ' . $rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][2] . ' class="btn btn-sm btn-info waves-effect waves-light">' . array_keys($rDropdown[$rPage])[0] . '</button>';
			}
			else {
				echo '<button type="button" onClick="showModal();" class="btn btn-sm btn-info waves-effect waves-light">' . array_keys($rDropdown[$rPage])[0] . '</button>';
			}
		}

		echo '<span class="gap"></span>';
	}

	if ($rMobile) {
		echo '<a class="btn btn-success waves-effect waves-light btn-sm btn-fixed-sm" data-toggle="collapse" href="#collapse_filters" role="button" aria-expanded="false">' . "\r\n" . '                    <i class="mdi mdi-filter"></i>' . "\r\n" . '                </a>';
	}

	echo '<button onClick="clearFilters();" type="button" class="btn btn-warning waves-effect waves-light btn-sm btn-fixed-sm" id="clearFilters">' . "\r\n" . '                <i class="mdi mdi-filter-remove"></i>' . "\r\n" . '            </button>' . "\r\n" . '            <button onClick="refreshTable();" type="button" class="btn btn-pink waves-effect waves-light btn-sm btn-fixed-sm">' . "\r\n" . '                <i class="mdi mdi-refresh"></i>' . "\r\n" . '            </button>';

	if (0 < count(array_slice($rDropdownPage, $rMobile ? 0 : 1, count($rDropdownPage)))) {
		echo '<button type="button" class="btn btn-sm btn-dark waves-effect waves-light dropdown-toggle btn-fixed-sm" data-toggle="dropdown" aria-expanded="false"><i class="fas fa-caret-down"></i></button>' . "\r\n" . '                <div class="dropdown-menu">';

		foreach (array_slice($rDropdownPage, $rMobile ? 0 : 1, count($rDropdownPage)) as $rName => $rURL) {
			if ($rName) {
				if ($rURL) {
					echo '<a class="dropdown-item" href="javascript: void(0);" onClick="navigate(\'' . $rURL . '\');">' . $rName . '</a>';
				}
				else {
					echo '<a class="dropdown-item" href="javascript: void(0);" onClick="showModal();">' . $rName . '</a>';
				}
			}
		}

		echo '</div>';
	}

	echo '</div>';
	break;
case 'user_logs':
	echo '<div class="btn-group">' . "\r\n" . '            <button onClick="refreshTable();" type="button" class="btn btn-pink waves-effect waves-light btn-sm btn-fixed-sm">' . "\r\n" . '                <i class="mdi mdi-refresh"></i>' . "\r\n" . '            </button>' . "\r\n" . '        </div>';
	break;
default:
	echo '<div class="btn-group">';
	if (!$rMobile && (!$rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][1] || hasResellerPermissions($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][1])) && (0 < strlen(array_keys($rDropdown[$rPage])[0]))) {
		if ($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][0]) {
			echo '<button type="button" onClick="navigate(\'' . $rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][0] . '\');" class="btn btn-sm btn-info waves-effect waves-light">' . array_keys($rDropdown[$rPage])[0] . '</button>';
		}
		else {
			if (isset($rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][2])) {
				echo '<button type="button" ' . $rDropdown[$rPage][array_keys($rDropdown[$rPage])[0]][2] . ' class="btn btn-sm btn-info waves-effect waves-light">' . array_keys($rDropdown[$rPage])[0] . '</button>';
			}
			else {
				echo '<button type="button" onClick="showModal();" class="btn btn-sm btn-info waves-effect waves-light">' . array_keys($rDropdown[$rPage])[0] . '</button>';
			}
		}
	}

	if (0 < count(array_slice($rDropdownPage, $rMobile ? 0 : 1, count($rDropdownPage)))) {
		echo '<span class="gap"></span><button type="button" class="btn btn-sm btn-dark waves-effect waves-light dropdown-toggle btn-fixed' . ($rMobile ? '-xl' : '-sm') . '" data-toggle="dropdown" aria-expanded="false">' . ($rMobile ? 'Options &nbsp; ' : '') . '<i class="fas fa-caret-down"></i></button>' . "\r\n" . '                <div class="dropdown-menu">';

		foreach (array_slice($rDropdownPage, $rMobile ? 0 : 1, count($rDropdownPage)) as $rName => $rURL) {
			if ($rName) {
				echo '<a class="dropdown-item" href="javascript: void(0);" onClick="navigate(\'' . $rURL . '\');">' . $rName . '</a>';
			}
		}

		echo '</div>';
	}

	echo '</div>';
	break;
}

?>