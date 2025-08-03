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
$_TITLE = 'Dashboard';
$rRegisteredUsers = getResellers($rUserInfo['id'], true);
$rGroups = getMemberGroups();
$rNotice = html_entity_decode($rGroups[$rUserInfo['member_group_id']]['notice_html']);
$rNotice = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $rNotice);
$rNotice = preg_replace('#</*\\w+:\\w[^>]*+>#i', '', $rNotice);
$rNotice = str_replace(['&amp;', '&lt;', '&gt;'], ['&amp;amp;', '&amp;lt;', '&amp;gt;'], $rNotice);
$rNotice = preg_replace('/(&#*\\w+)[\\x00-\\x20]+;/u', '$1;', $rNotice);
$rNotice = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $rNotice);
$rNotice = html_entity_decode($rNotice, ENT_COMPAT, 'UTF-8');
$rNotice = preg_replace('#(<[^>]+?[\\x00-\\x20"\'])(?:on|xmlns)[^>]*+[>\\b]?#iu', '$1>', $rNotice);
$rNotice = preg_replace('#([a-z]*)[\\x00-\\x20]*=[\\x00-\\x20]*([`\'"]*)[\\x00-\\x20]*j[\\x00-\\x20]*a[\\x00-\\x20]*v[\\x00-\\x20]*a[\\x00-\\x20]*s[\\x00-\\x20]*c[\\x00-\\x20]*r[\\x00-\\x20]*i[\\x00-\\x20]*p[\\x00-\\x20]*t[\\x00-\\x20]*:#iu', '$1=$2nojavascript...', $rNotice);
$rNotice = preg_replace('#([a-z]*)[\\x00-\\x20]*=([\'"]*)[\\x00-\\x20]*v[\\x00-\\x20]*b[\\x00-\\x20]*s[\\x00-\\x20]*c[\\x00-\\x20]*r[\\x00-\\x20]*i[\\x00-\\x20]*p[\\x00-\\x20]*t[\\x00-\\x20]*:#iu', '$1=$2novbscript...', $rNotice);
$rNotice = preg_replace('#([a-z]*)[\\x00-\\x20]*=([\'"]*)[\\x00-\\x20]*-moz-binding[\\x00-\\x20]*:#u', '$1=$2nomozbinding...', $rNotice);
$rNotice = preg_replace('#(<[^>]+?)style[\\x00-\\x20]*=[\\x00-\\x20]*[`\'"]*.*?expression[\\x00-\\x20]*\\([^>]*+>#i', '$1>', $rNotice);
$rNotice = preg_replace('#(<[^>]+?)style[\\x00-\\x20]*=[\\x00-\\x20]*[`\'"]*.*?behaviour[\\x00-\\x20]*\\([^>]*+>#i', '$1>', $rNotice);
$rNotice = preg_replace('#(<[^>]+?)style[\\x00-\\x20]*=[\\x00-\\x20]*[`\'"]*.*?s[\\x00-\\x20]*c[\\x00-\\x20]*r[\\x00-\\x20]*i[\\x00-\\x20]*p[\\x00-\\x20]*t[\\x00-\\x20]*:*[^>]*+>#iu', '$1>', $rNotice);
include 'header.php';
echo '<div class="wrapper">' . "\n" . '    <div class="container-fluid">' . "\n" . '        <div class="row">' . "\n" . '            <div class="col-12">' . "\n" . '                <div class="page-title-box">' . "\n" . '                    <h4 class="page-title">Welcome ';
echo htmlspecialchars($rUserInfo['username']);
echo '</h4>' . "\n" . '                </div>' . "\n" . '                ';

if (!empty($rNotice)) {
	echo '                <div class="card" style="padding: 1em 1em 0 1em;">' . "\n" . '                    ';
	echo $rNotice;
	echo '                </div>' . "\n" . '                ';
}

echo '            </div>' . "\n" . '        </div>' . "\n" . '        <div class="row">' . "\n" . '        <div class="col-md-3">' . "\n" . '                <a href="';
echo $rPermissions['reseller_client_connection_logs'] ? 'live_connections' : 'javascript: void(0);';
echo '">' . "\n" . '                <div class="card border-primary mb-3 bg-light text-black">' . "\n" . '                        <div class="card-body active-connections" style="padding: 0.15rem;">' . "\n" . '                            <div class="media align-items-center">' . "\n" . '                                <div class="col-9">' . "\n" . '                                    <div class="text-left">' . "\n" . '                                        <h3 class="text-black my-1"><span data-plugin="counterup" class="entry">0</span></h3>' . "\n" . '                                        <p class="text-black mb-1 text-truncate">Connections</p>' . "\n" . '                                    </div>' . "\n" . '                                </div>' . "\n" . '                            </div>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </a>' . "\n" . '            </div>' . "\n" . '            <div class="col-md-3">' . "\n" . '                <a href="';
echo $rPermissions['reseller_client_connection_logs'] ? 'live_connections' : 'javascript: void(0);';
echo '">' . "\n" . '                <div class="card border-primary mb-3 bg-light text-black">' . "\n" . '                        <div class="card-body online-users" style="padding: 0.15rem;">' . "\n" . '                            <div class="media align-items-center">' . "\n" . '                                <div class="col-9">' . "\n" . '                                    <div class="text-left">' . "\n" . '                                        <h3 class="text-black my-1"><span data-plugin="counterup" class="entry">0</span></h3>' . "\n" . '                                        <p class="text-black mb-1 text-truncate">Lines Online</p>' . "\n" . '                                    </div>' . "\n" . '                                </div>' . "\n" . '                            </div>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </a>' . "\n" . '            </div>' . "\n" . '            <div class="col-md-3">' . "\n" . '                <a href="javascript:void(0);" id="manage_lines">' . "\n" . '                <div class="card border-primary mb-3 bg-light text-black">' . "\n" . '                        <div class="card-body active-accounts" style="padding: 0.15rem;">' . "\n" . '                                <div class="col-9">' . "\n" . '                                    <div class="text-left">' . "\n" . '                                        <h3 class="text-black my-1"><span data-plugin="counterup" class="entry">0</span></h3>' . "\n" . '                                        <p class="text-black mb-1 text-truncate">Active Lines</p>' . "\n" . '                                    </div>' . "\n" . '                                </div>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </a>' . "\n" . '            </div>' . "\n" . '            <div class="col-md-3">' . "\n" . '                <a href="';
echo $rPermissions['create_sub_resellers'] ? 'users' : 'javascript: void(0);';
echo '">' . "\n" . '                <div class="card border-primary mb-3 bg-light text-black">' . "\n" . '                        <div class="card-body credits" style="padding: 0.15rem;">' . "\n" . '                            <div class="media align-items-center">' . "\n" . '                                <div class="col-9">' . "\n" . '                                    <div class="text-left">' . "\n" . '                                        <h3 class="text-black my-1"><span data-plugin="counterup" class="entry">0</span></h3>' . "\n" . '                                        <p class="text-black mb-1 text-truncate">';

if (1 < count($rRegisteredUsers)) {
	echo 'Assigned Credits';
}
else {
	echo 'Total Credits';
}

echo '</p>' . "\n" . '                                    </div>' . "\n" . '                                </div>' . "\n" . '                            </div>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </a>' . "\n" . '            </div>' . "\n" . '        </div>' . "\n" . '        <div class="row">' . "\n" . '            <div class="col-xl-6">' . "\n" . '                <div class="card">' . "\n" . '                    <div class="card-body">' . "\n" . '                        <a href="user_logs"><h4 class="header-title mb-4">Recent Activity</h4></a>' . "\n" . '                        <div style="height: 350px; overflow-y: auto;">' . "\n" . '                            <table class="table table-striped table-borderless m-0 table-centered dt-responsive nowrap w-100" id="users-table">' . "\n" . '                                <thead>' . "\n" . '                                    <tr>' . "\n" . '                                        <th class="text-center">Reseller</th>' . "\n" . '                                        <th class="text-center">Line / User</th>' . "\n" . '                                        <th>Action</th>' . "\n" . '                                        <th class="text-center">Date</th>' . "\n" . '                                    </tr>' . "\n" . '                                </thead>' . "\n" . '                                <tbody>' . "\n" . '                                    ';
$rPackages = getPackages();
$db->query('SELECT * FROM `users_logs` LEFT JOIN `users` ON `users`.`id` = `users_logs`.`owner` WHERE `users_logs`.`owner` IN (' . implode(',', array_map('intval', array_merge([$rUserInfo['id']], $rPermissions['all_reports']))) . ') ORDER BY `date` DESC LIMIT 250;');

foreach ($db->get_rows() as $rRow) {
	$rOwner = '<a class=\'text-dark\' href=\'user?id=' . $rRow['owner'] . '\'>' . $rRow['username'] . '</a>';
	$rDevice = ['line' => 'User Line', 'mag' => 'MAG Device', 'enigma' => 'Enigma2 Device', 'user' => 'Reseller'][$rRow['type']];
	$rText = '';

	switch ($rRow['action']) {
	case 'new':
		if ($rRow['package_id']) {
			$rText = 'Created New ' . $rDevice . ' with Package:<br/>' . $rPackages[$rRow['package_id']]['package_name'];
		}
		else {
			$rText = 'Created New ' . $rDevice;
		}

		break;
	case 'extend':
		if ($rRow['package_id']) {
			$rText = 'Extended ' . $rDevice . ' with Package:<br/>' . $rPackages[$rRow['package_id']]['package_name'];
		}
		else {
			$rText = 'Extended ' . $rDevice;
		}

		break;
	case 'convert':
		$rText = 'Converted Device to User Line';
		break;
	case 'edit':
		$rText = 'Edited ' . $rDevice;
		break;
	case 'enable':
		$rText = 'Enabled ' . $rDevice;
		break;
	case 'disable':
		$rText = 'Disabled ' . $rDevice;
		break;
	case 'delete':
		$rText = 'Deleted ' . $rDevice;
		break;
	case 'send_event':
		$rText = 'Sent Event to ' . $rDevice;
		break;
	case 'adjust_credits':
		$rText = 'Adjusted Credits by ' . $rRow['cost'];
		break;
	case 'connection':
		$rText = 'Additional Connection Added';
		break;
	}

	$rLineInfo = NULL;

	switch ($rRow['type']) {
	case 'line':
		$rLine = getUser($rRow['log_id']);

		if ($rLine) {
			$rLineInfo = '<a class=\'text-dark\' href=\'line?id=' . $rRow['log_id'] . '\'>' . $rLine['username'] . '</a>';
		}

		break;
	case 'user':
		$rLine = getRegisteredUser($rRow['log_id']);

		if ($rLine) {
			$rLineInfo = '<a class=\'text-dark\' href=\'user?id=' . $rRow['log_id'] . '\'>' . $rLine['username'] . '</a>';
		}

		break;
	case 'mag':
		$rLine = getMag($rRow['log_id']);

		if ($rLine) {
			$rLineInfo = '<a class=\'text-dark\' href=\'mag?id=' . $rRow['log_id'] . '\'>' . $rLine['mac'] . '</a>';
		}

		break;
	case 'enigma':
		$rLine = getEnigma($rRow['log_id']);

		if ($rLine) {
			$rLineInfo = '<a class=\'text-dark\' href=\'enigma?id=' . $rRow['log_id'] . '\'>' . $rLine['mac'] . '</a>';
		}

		break;
	}

	if (!$rLineInfo) {
		$rDeletedInfo = json_decode($rRow['deleted_info'], true);

		if (is_array($rDeletedInfo)) {
			if (isset($rDeletedInfo['mac'])) {
				$rLineInfo = '<span class=\'text-secondary\'>' . $rDeletedInfo['mac'] . '</span>';
			}
			else {
				$rLineInfo = '<span class=\'text-secondary\'>' . $rDeletedInfo['username'] . '</span>';
			}
		}
		else {
			$rLineInfo = '<span class=\'text-secondary\'>DELETED</span>';
		}
	}

	echo '                                    <tr>' . "\n" . '                                        <td class="text-center">';
	echo $rOwner;
	echo '</td>' . "\n" . '                                        <td class="text-center">';
	echo $rLineInfo;
	echo '</td>' . "\n" . '                                        <td>';
	echo $rText;
	echo '</td>' . "\n" . '                                        <td class="text-center">';
	echo date($rSettings['date_format'] . ' H:i', $rRow['date']);
	echo '</td>' . "\n" . '                                    </tr>' . "\n" . '                                    ';
}

echo '                                </tbody>' . "\n" . '                            </table>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </div>' . "\n" . '            </div>' . "\n" . '            <div class="col-xl-6">' . "\n" . '                <div class="card">' . "\n" . '                    <div class="card-body">' . "\n" . '                        <a href="lines"><h4 class="header-title mb-4">Expiring Lines</h4></a>' . "\n" . '                        <div style="height: 350px; overflow-y: auto;">' . "\n" . '                            <table class="table table-striped table-borderless m-0 table-centered dt-responsive nowrap w-100" id="users-table">' . "\n" . '                                <thead>' . "\n" . '                                    <tr>' . "\n" . '                                        <th class="text-center">Type</th>' . "\n" . '                                        <th class="text-center">Identity</th>' . "\n" . '                                        <th class="text-center">Owner</th>' . "\n" . '                                        <th class="text-center">Expires</th>' . "\n" . '                                    </tr>' . "\n" . '                                </thead>' . "\n" . '                                <tbody>' . "\n" . '                                    ';

foreach (getExpiring() as $rUser) {
	echo '                                    <tr>' . "\n" . '                                        ';

	if ($rUser['is_mag']) {
		echo '                                        <td class="text-center">MAG Device</td>' . "\n" . '                                        <td class="text-center"><a class="text-dark" href="mag?id=';
		echo (int) $rUser['mag_id'];
		echo '">';
		echo htmlspecialchars($rUser['mag_mac']);
		echo !empty($rUser['reseller_notes']) ? ' &nbsp; <button type="button" class="btn btn-light waves-effect waves-light btn-xs tooltip" title="' . htmlspecialchars($rUser['reseller_notes']) . '"><i class="mdi mdi-note"></i></button>' : '';
		echo '</a></td>' . "\n" . '                                        ';
	}
	else if ($rUser['is_e2']) {
		echo '                                        <td class="text-center">Enigma2 Device</td>' . "\n" . '                                        <td class="text-center"><a class="text-dark" href="enigma?id=';
		echo (int) $rUser['e2_id'];
		echo '">';
		echo htmlspecialchars($rUser['e2_mac']);
		echo !empty($rUser['reseller_notes']) ? ' &nbsp; <button type="button" class="btn btn-light waves-effect waves-light btn-xs tooltip" title="' . htmlspecialchars($rUser['reseller_notes']) . '"><i class="mdi mdi-note"></i></button>' : '';
		echo '</a></td>' . "\n" . '                                        ';
	}
	else {
		echo '                                        <td class="text-center">User Line</td>' . "\n" . '                                        <td class="text-center"><a class="text-dark" href="line?id=';
		echo (int) $rUser['line_id'];
		echo '">';
		echo htmlspecialchars($rUser['username']);
		echo !empty($rUser['reseller_notes']) ? ' &nbsp; <button type="button" class="btn btn-light waves-effect waves-light btn-xs tooltip" title="' . htmlspecialchars($rUser['reseller_notes']) . '"><i class="mdi mdi-note"></i></button>' : '';
		echo '</a></td>' . "\n" . '                                        ';
	}

	echo '                                        <td class="text-center"><a class="text-dark" href="user?id=';
	echo (int) $rUser['member_id'];
	echo '">';
	echo htmlspecialchars($rRegisteredUsers[$rUser['member_id']]['username']);
	echo '</td>' . "\n" . '                                        <td class="text-center">';
	echo date($rSettings['date_format'] . ' H:i', $rUser['exp_date']);
	echo '</td>' . "\n" . '                                    </tr>' . "\n" . '                                    ';
}

echo '                                </tbody>' . "\n" . '                            </table>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </div>' . "\n" . '            </div>' . "\n" . '        </div>' . "\n\t" . '</div>' . "\n" . '</div>' . "\n";
include 'footer.php';

?>