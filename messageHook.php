<html lang="ja">
<head>
<meta charset="UTF-8">
<title>Acquire message</title>
</head>
<body>

<?php

DEFINE("ACCESS_TOKEN","u4hwbRiAxa6YB+Rc3xHI2M6I1uvHWcMwjl+9OhGyZfMxcMv0aG1e5v7ZBua3Y7Z5Y0ZPhJDw33oTMeQooXJoOs5EMsXsQ7961p73M84aThS8CDm5pm/k3nHw6yyXOYxHDb3Mnworv3QYCr3DenzSIwdB04t89/1O/w1cDnyilFU=");

DEFINE("SECRET_TOKEN","f0f343f5498fdfd0edc1cb9846723fd0");

require_once(__DIR__."/vendor/autoload.php");
require_once(__DIR__."/reportSensData.php");
require_once(__DIR__."/stationQuery.php");

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;

//
//  User and Application context data
//
$context_s = "";
$context_u = "";

/////////////////////////////////////////////////////
//
// Main function of request message responder
//
//
if (isset($_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE])) {

    $signature = $_SERVER["HTTP_".HTTPHeader::LINE_SIGNATURE]; 
    $inputData = file_get_contents("php://input");
    syslog(LOG_INFO, $inputData);

    // create HTTPClient instance
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new \LINE\LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    // Check request with signature and parse request
    try {
	$Events = $Bot->parseEventRequest($inputData, $signature);
    } catch (\LINE\LINEBot\Exception\InvalidSignatureException $e) {
	syslog(LOG_ERR, var_export($e, true));
	return;
    } catch (\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
	syslog(LOG_ERR, var_export($e, true));
	return;
    } catch (\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
	syslog(LOG_ERR, var_export($e, true));
	return;
    } catch (\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
	syslog(LOG_ERR, var_export($e, true));
	return;
    }

    $gs_context_s = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/context_s.pac";
    $context_s = unserialize(file_get_contents($gs_context_s));

    foreach($Events as $event){

	$current_user = $event->getEventSourceId();
	$gs_context_u = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/context_".$current_user.".pac";
	$context_u = unserialize(file_get_contents($gs_context_u));
	$context_u['user_id'] = $current_user;
	$context_u['timestamp'] = $event->getTimestamp();

	if ($event->getType() != 'message') {
	    $replyMessage = "なに、それ？ ". $event->getType()." : ".$event->getMessageType();
	}

	if ($event->getMessageType() == 'location')) {
	    $loc_latitude = $event->getLatitude();
	    $loc_longitude = $event->getLongitude();
	    $replyMessage = locationProseccor($loc_latitude, $loc_longitude, $context_s, $context_u);
	}
	else if ($event->getMessageType() == 'text')) {

	    $receivedMessage = $event->getText();

	    if (($replyMessage = messageDispatcher($receivedMessage, $context_s, $context_u)) == null) {
		$replyMessage = "なに？";
	    }
	}
	else {
	    $replyMessage = "なに、それ？ ". $event->getType()." : ".$event->getMessageType();
	}

	file_put_contents($gs_context_u, serialize($context_u));

	if (is_string($replyMessage)) {
	    $SendMessage = new MultiMessageBuilder();
	    $TextMessageBuilder = new TextMessageBuilder($replyMessage);
	    $SendMessage->add($TextMessageBuilder);
	    $Bot->replyMessage($event->getReplyToken(), $SendMessage);
	}
	else if (is_object($replyMessage) ) {
	    $SendMessage = new MultiMessageBuilder();
	    $ButtonMessageBuilder = new ButtonMessageBuilder($replyMessage);
	}
    }

    file_put_contents($gs_context_s, serialize($context_s));
}
else {
    //
    //  ローカルテスト時のイベントハンドラ
    //  Webページからhttp postされた疑似メッセージ（$_POST['queryMessage']）を処理する
    //
    $gs_context_s = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/context_s.pac";
    $context_s = unserialize(file_get_contents($gs_context_s));
    $gs_context_u = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/context_uid_xxx.pac";
    $context_u = unserialize(file_get_contents($gs_context_u));

    $context_u['user_id'] = 'uid_xxx';
    $context_u['timestamp'] = 1111;

    if (($replyMessage = messageDispatcher($_POST['queryMessage'], $context_s, $context_u)) == null) {
	echo "なに？";
    }

    echo $replyMessage;

    file_put_contents($gs_context_s, serialize($context_s));
    file_put_contents($gs_context_u, serialize($context_u));
}


function locationProcessor($loc_latitude, $loc_longitude, &$context_s, &$context_u) {
    //
    //
    //
    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
    $app_url = "https://map.yahooapis.jp/alt/V1/getAltitude";		// Altitude API

    $app_params = array ( "coordinates" => $loc_latitude . ',' . $loc_longitude,
			"output" => "json");

    $ch = curl_init($app_url . '?' . http_build_query($app_params));

    curl_setopt_array($ch, array(
	        CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => "Yahoo AppID: $app_id"));
    $result = json_decode(curl_exec($ch));
    curl_close($ch);

    var_dump($result);
    $replyMessage = "ここの標高は" . $result['Feature']['Property']['Altitude'] . "m". PHP_EOL . "ここで何か探してるの？" . PHP_EOL;

    unset($context_u['qc']);
    $context_u['qc']['あ'] = "寺";
    $context_u['qc']['か'] = "神社";
    $context_u['qc']['さ'] = "駅";
    $context_u['qc']['た'] = "コンビニ";
    $context_u['qc']['な'] = "ラーメン";
    $context_u['qc']['は'] = "これ以外";

    foreach ($context_u['qc'] as $key=>$name) {
	$replyMessage .= $key . " : " . $name . PHP_EOL;
    }

    return ($replyMessage);
}

function messageDispatcher($receivedMessage, &$context_s, &$context_u) {

    global $respondTrainQuery, $reportSensData;

    //
    //  Messages Entries to whitch each application respond to
    //
    $messageTbl = array(
	'trainTable' => array(
		'func' => $respondTrainQuery, 
		'keyword' => array ("/^時刻表/",  "/今日.*(休日|祭日|祝日|平日|土曜)/"),
		),
	'sensorRec'  => array(
		'func' => $reportSensData, 
		'keyword' => array ("/^気温/")
		)
	);

    $user_id = $context_u['user_id'];
    $replyMessage = null;
    $current_apl = null;

    //
    //  前回応答したアプリのパターンを先に確認する
    //
    if (isset($context_u['current_apl'])) {
    	
    	$aplTable = $messageTbl[$current_apl = $context_u['current_apl']];

	for ($i = 0; isset($aplTable['keyword'][$i]) ; $i++) {

	    if (preg_match($aplTable['keyword'][$i], $receivedMessage, $result) == FALSE) continue;

	    if (($replyMessage = $aplTable['func']($receivedMessage, $i, $result, $context_s, $context_u)) != null) 
		return($replyMessage);
	}

	// 一致するパターンがない場合でも、一度優先アプリに問い合わせる
	if ($replyMessage == null) 
	    if (($replyMessage = $aplTable['func']($receivedMessage, 999, $result, $context_s, $context_u)) != null)
		return($replyMessage);
    }

    //
    // それ以外のアプリのパターンを確認する
    //
    foreach ($messageTbl as $aplName => $aplTable) {

	if ($aplName == $current_apl) continue;

	for ($i = 0; isset($aplTable['keyword'][$i]) ; $i++) {

	    if (preg_match($aplTable['keyword'][$i], $receivedMessage, $result) == FALSE) continue;

	    if (($replyMessage = $aplTable['func']($receivedMessage, $i, $result, $context_s, $context_u)) != null) {
		$context_u['current_apl'] = $aplName;
	    }

	    return($replyMessage);
	}
    }

    if (($receivedMessage == "いせ") || ($receivedMessage == "伊勢")) {
    	return("https://jebaxxMessage.appspot.com/travel_plan.html");
    }

    return(null);
}

?>

</body>
</html>

