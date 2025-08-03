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

include 'functions.php';

if (isset($_SESSION['reseller'])) {
	header('Location: dashboard');
	exit();
}

session_start();
$rIP = getIP();

if (0 < (int) $rSettings['login_flood']) {
	$db->query('SELECT COUNT(`id`) AS `count` FROM `login_logs` WHERE `status` = \'INVALID_LOGIN\' AND `login_ip` = ? AND TIME_TO_SEC(TIMEDIFF(NOW(), `date`)) <= 86400;', $rIP);

	if ($db->num_rows() == 1) {
		if ((int) $rSettings['login_flood'] <= (int) $db->get_row()['count']) {
			API::blockIP(['ip' => $rIP, 'notes' => 'LOGIN FLOOD ATTACK']);
			exit();
		}
	}
}

if (isset(XCMS::$rRequest['login'])) {
	$rReturn = ResellerAPI::processLogin(XCMS::$rRequest);
	$_STATUS = $rReturn['status'];

	if ($_STATUS == STATUS_SUCCESS) {
		if (0 < strlen(XCMS::$rRequest['referrer'])) {
			$rReferer = basename(XCMS::$rRequest['referrer']);

			if (substr($rReferer, 0, 6) == 'logout') {
				$rReferer = 'dashboard';
			}

			header('Location: ' . $rReferer);
			exit();
		}
		else {
			header('Location: dashboard');
			exit();
		}
	}
}

echo '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n" . '    <head>' . "\n" . '        <meta charset="utf-8" />' . "\n" . '        <title data-id="login">XCMS | ';
echo $_['login'];
echo '</title>' . "\n" . '        <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n" . '        <meta http-equiv="X-UA-Compatible" content="IE=edge" />' . "\n" . '        <link rel="shortcut icon" href="assets/images/favicon.ico">' . "\n\t\t" . '<link href="assets/css/icons.css" rel="stylesheet" type="text/css" />' . "\n" . '        ';
if (isset($_COOKIE['theme']) && ($_COOKIE['theme'] == 1)) {
	echo "\t\t" . '<link href="assets/css/bootstrap.dark.css" rel="stylesheet" type="text/css" />' . "\n" . '        <link href="assets/css/app.dark.css" rel="stylesheet" type="text/css" />' . "\n" . '        ';
}
else {
	echo '        <link href="assets/css/bootstrap.css" rel="stylesheet" type="text/css" />' . "\n" . '        <link href="assets/css/app.css" rel="stylesheet" type="text/css" />' . "\n" . '        ';
}

echo '        <link href="assets/css/extra.css" rel="stylesheet" type="text/css" />' . "\n\t\t" . '<style>' . "\n" . '            body {' . "\n" . 'background:  #2f3b43;' . "\n\n" . '            }' . "\n" . '        .g-recaptcha {' . "\n" . '           ' . "\n" . '        }' . "\n\n" . '        .g-recaptcha {' . "\n" . '            display: inline-block;' . "\n" . '        }' . "\n" . '        .vertical-center {' . "\n" . '            margin: 0;' . "\n" . '            position: absolute;' . "\n" . '            top: 50%;' . "\n" . '            -ms-transform: translateY(-50%);' . "\n" . '            transform: translateY(-50%);' . "\n" . '            width: 100%;' . "\n" . '        }' . "\n\t\t" . '</style>' . "\n" . '    </head>' . "\n" . '    <body class="bg-animate';
if (isset($_COOKIE['hue']) && (0 < strlen($_COOKIE['hue'])) && in_array($_COOKIE['hue'], array_keys($rHues))) {
	echo '-' . $_COOKIE['hue'];
}

echo '">' . "\n" . '        <div class="body-full navbar-custom">' . "\n" . '            <div class="account-pages vertical-center">' . "\n" . '                <div class="container">' . "\n" . '                    <div class="row justify-content-center">' . "\n" . '                        <div class="col-md-8 col-lg-6 col-xl-5">' . "\n" . '                            <div class="text-center w-75 m-auto">' . "\n" . '                                <span><img src="assets/images/logo-topbar.png" height="80px" alt=""></span>' . "\n" . '                                <p class="text-muted mb-4 mt-3"></p>' . "\n" . '                            </div>' . "\n" . '                            ';
if (isset($_STATUS) && ($_STATUS == STATUS_FAILURE)) {
	echo '                            <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">' . "\n" . '                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . "\n" . '                                ';
	echo $_['login_message_1'];
	echo '                            </div>' . "\n" . '                            ';
}
else if (isset($_STATUS) && ($_STATUS == STATUS_INVALID_CODE)) {
	echo '                            <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">' . "\n" . '                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . "\n" . '                                ';
	echo $_['login_message_2'];
	echo '                            </div>' . "\n" . '                            ';
}
else if (isset($_STATUS) && ($_STATUS == STATUS_NOT_RESELLER)) {
	echo '                            <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">' . "\n" . '                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . "\n" . '                                ';
	echo $_['login_message_3'];
	echo '                            </div>' . "\n" . '                            ';
}
else if (isset($_STATUS) && ($_STATUS == STATUS_DISABLED)) {
	echo '                            <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">' . "\n" . '                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . "\n" . '                                ';
	echo $_['login_message_4'];
	echo '                            </div>' . "\n" . '                            ';
}
else if (isset($_STATUS) && ($_STATUS == STATUS_INVALID_CAPTCHA)) {
	echo '                            <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">' . "\n" . '                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . "\n" . '                                ';
	echo $_['login_message_5'];
	echo '                            </div>' . "\n" . '                            ';
}

echo '                            <form action="./login" method="POST" data-parsley-validate="">' . "\n" . '                                <div class="card">' . "\n" . '                                    <div class="card-body p-4">' . "\n" . '                                        <input type="hidden" name="referrer" value="';
echo htmlspecialchars(XCMS::$rRequest['referrer']);
echo '" />' . "\n" . '                                        <div class="form-group mb-3" id="username_group">' . "\n" . '                                            <label for="username">';
echo $_['username'];
echo '</label>' . "\n" . '                                            <input class="form-control" autocomplete="off" type="text" id="username" name="username" required data-parsley-trigger="change" placeholder="">' . "\n" . '                                        </div>' . "\n" . '                                        <div class="form-group mb-3">' . "\n" . '                                            <label for="password">';
echo $_['password'];
echo '</label>' . "\n" . '                                            <input class="form-control" autocomplete="off" type="password" required data-parsley-trigger="change" id="password" name="password" placeholder="">' . "\n" . '                                        </div>' . "\n" . '                                        ';

if ($rSettings['recaptcha_enable']) {
	echo '                                        <h5 class="auth-title text-center" style="margin-bottom:0;">' . "\n" . '                                            <div class="g-recaptcha" data-callback="recaptchaCallback" id="verification" data-sitekey="';
	echo $rSettings['recaptcha_v2_site_key'];
	echo '"></div>' . "\n" . '                                        </h5>' . "\n" . '                                        ';
}

echo '                                    </div>' . "\n" . '                                </div>' . "\n" . '                                <div class="form-group mb-0 text-center">' . "\n" . '                                    <button style="border:0" class="btn btn-info ';
if (isset($_COOKIE['hue']) && (0 < strlen($_COOKIE['hue'])) && in_array($_COOKIE['hue'], array_keys($rHues))) {
	echo 'bg-animate-' . $_COOKIE['hue'];
}
else {
	echo 'bg-animate-info';
}

echo ' btn-block" type="submit" id="login_button" name="login"';

if ($rSettings['recaptcha_enable']) {
	echo ' disabled';
}

echo '>';
echo $_['login'];
echo '</button>' . "\n" . '                                </div>' . "\n" . '                            </form>' . "\n" . '                        </div>' . "\n" . '                    </div>' . "\n" . '                </div>' . "\n" . '            </div>' . "\n" . '        </div>' . "\n" . '        <script src="assets/js/vendor.min.js"></script>' . "\n" . '        <script src="assets/libs/parsleyjs/parsley.min.js"></script>' . "\n" . '        <script src="assets/js/app.min.js"></script>' . "\n\t\t";

if ($rSettings['recaptcha_enable']) {
	echo "\t\t" . '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' . "\n\t\t";
}

echo '        <script>' . "\n" . '        function recaptchaCallback() {' . "\n" . '            $(\'#login_button\').removeAttr(\'disabled\');' . "\n" . '        };' . "\n" . '        </script>' . "\n" . '    </body>' . "\n" . '</html>';

?>