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

$rStatusArray = ['CLOSED', 'OPEN', 'RESPONDED TO', 'READ BY ME', 'NEW RESPONSE', 'READ BY ADMIN', 'READ BY USER'];
$_TITLE = 'Tickets';
include 'header.php';
echo '<div class="wrapper">' . "\r\n" . '    <div class="container-fluid">' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="page-title-box">' . "\r\n\t\t\t\t\t" . '<div class="page-title-right">' . "\r\n" . '                        ';
include 'topbar.php';
echo "\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t" . '<h4 class="page-title">Tickets</h4>' . "\r\n\t\t\t\t" . '</div>' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>     ' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="card-box">' . "\r\n\t\t\t\t\t" . '<table class="table table-striped table-borderless dt-responsive nowrap w-100" id="tickets-table">' . "\r\n\t\t\t\t\t\t" . '<thead>' . "\r\n\t\t\t\t\t\t\t" . '<tr>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">ID</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th>Reseller</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th>Subject</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Status</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Created Date</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Last Reply</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Action</th>' . "\r\n\t\t\t\t\t\t\t" . '</tr>' . "\r\n\t\t\t\t\t\t" . '</thead>' . "\r\n\t\t\t\t\t\t" . '<tbody>' . "\r\n\t\t\t\t\t\t\t";
$rTickets = getTickets($rUserInfo['id']);

foreach ($rTickets as $rTicket) {
	echo "\t\t\t\t\t\t\t" . '<tr id="ticket-';
	echo (int) $rTicket['id'];
	echo '">' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center"><a href="./ticket_view?id=';
	echo (int) $rTicket['id'];
	echo '">';
	echo (int) $rTicket['id'];
	echo '</a></td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td>';
	echo $rTicket['username'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td>';
	echo $rTicket['title'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center"><span class="badge badge-';
	echo ['secondary', 'warning', 'success', 'warning', 'info', 'purple', 'warning'][$rTicket['status']];
	echo '">';
	echo $rStatusArray[$rTicket['status']];
	echo '</span></td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center">';
	echo $rTicket['created'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center">';
	echo $rTicket['last_reply'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center">' . "\r\n\t\t\t\t\t\t\t\t\t" . '<div class="btn-group dropdown">' . "\r\n\t\t\t\t\t\t\t\t\t\t" . '<a href="javascript: void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false"><i class="mdi mdi-dots-horizontal"></i></a>' . "\r\n\t\t\t\t\t\t\t\t\t\t" . '<div class="dropdown-menu dropdown-menu-right">' . "\r\n\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="./ticket_view?id=';
	echo (int) $rTicket['id'];
	echo '"><i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>View Ticket</a>' . "\r\n\t\t\t\t\t\t\t\t\t\t\t";

	if (0 < $rTicket['status']) {
		echo "\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="javascript:void(0);" onClick="api(';
		echo (int) $rTicket['id'];
		echo ', \'close\');"><i class="mdi mdi-check-all mr-2 text-muted font-18 vertical-middle"></i>Close</a>' . "\r\n\t\t\t\t\t\t\t\t\t\t\t";
	}
	else if ($rTicket['member_id'] != $rUserInfo['id']) {
		echo "\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="javascript:void(0);" onClick="api(';
		echo (int) $rTicket['id'];
		echo ', \'reopen\');"><i class="mdi mdi-check-all mr-2 text-muted font-18 vertical-middle"></i>Re-Open</a>' . "\r\n\t\t\t\t\t\t\t\t\t\t\t";
	}

	echo "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t\t" . '</td>' . "\r\n\t\t\t\t\t\t\t" . '</tr>' . "\r\n\t\t\t\t\t\t\t";
}

echo "\t\t\t\t\t\t" . '</tbody>' . "\r\n\t\t\t\t\t" . '</table>' . "\r\n\t\t\t\t" . '</div>' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>' . "\r\n\t" . '</div>' . "\r\n" . '</div>' . "\r\n";
include 'footer.php';

?>