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

function sendTelegram($rMsg, $rServerInfo, $rUserID, $rBotid)
{
	$curl = curl_init();
	$curlPost = ['parse_mode' => 'HTML', 'text' => '<b>' . $rServerInfo . '</b> : ' . $rMsg, 'chat_id' => $rUserID];
	curl_setopt_array($curl, [
		CURLOPT_URL => 'https://api.telegram.org/' . $rBotid . '/sendMessage',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => json_encode($curlPost),
		CURLOPT_HTTPHEADER => ['User-Agent: Telegram Bot SDK', 'accept: application/json', 'content-type: application/json']
	]);
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if ($err) {
		echo 'cURL Error #:' . $err . PHP_EOL;
	}
	else {
		echo $response . PHP_EOL;
	}
}

function shutdown()
{
	global $db;

	if (is_object($db)) {
		$db->close_mysql();
	}
}

register_shutdown_function('shutdown');
require str_replace('\\', '/', dirname($argv[0])) . '/../../www/init.php';
cli_set_process_title('XCMS Telegram]');
XCMS::$rSettings = XCMS::getSettings(true);

if (XCMS::$rSettings['enable_telegram']) {
	$rUserID = json_decode(XCMS::$rSettings['telegram_user_ids'], true);

	if ($argv[2]) {
		$rServerInfo = base64_decode($argv[2]);
	}
	else {
		$rServer = $db->query('SELECT `server_name` FROM `servers` WHERE id = ?', SERVER_ID);

		if ($db->num_rows() <= 0) {
			exit();
		}

		$rServerInfo = $db->get_row();
		$rServerInfo = $rServerInfo['server_name'];
	}

	foreach ($rUserID as $rUser) {
		echo sendTelegram(base64_decode($argv[1]), $rServerInfo, $rUser, 'bot' . XCMS::$rSettings['telegram_bot_id']);
	}
}

?>