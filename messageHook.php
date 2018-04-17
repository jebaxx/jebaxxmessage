<html lang="ja">
<head>
<meta charset="UTF-8">
<title>Acquire message</title>
</head>
<body>

<?php

require_once(__DIR__."/vendor/autoload.php");
require_once(__DIR__."/reportSensData.php");
require_once(__DIR__."/stationQuery.php");
require_once(__DIR__."/locationProcessor.php");

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Event\MessageEvent;
use \LINE\LINEBot\Event\PostbackEvent;
use \LINE\LINEBot\Response;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
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

    include(__DIR__."/accesstoken.php");

    // create HTTPClient instance
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

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

	if ($event instanceof PostbackEvent) {
	    $replyMessage = PostbackeventDispatcher($event->getPostbackData(), $context_s, $context_u);	
	}
	else if ($event->getType() != 'message') {
	    $replyMessage = "なに、それ？ ". $event->getType();
	}
	else if ($event->getMessageType() == 'location') {
	    $loc_latitude = $event->getLatitude();
	    $loc_longitude = $event->getLongitude();
	    syslog(LOG_INFO, "LAT:".$loc_latitude."  LON:".$loc_longitude);
	    $replyMessage = enterStartPoint($loc_longitude, $loc_latitude, $context_s, $context_u);
	}
	else if ($event->getMessageType() == 'text') {

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
	    $ReplyBuilder = new TextMessageBuilder($replyMessage);
	}
	else {
	    $ReplyBuilder = $replyMessage;
	}

	$LineResponse = $Bot->replyMessage($event->getReplyToken(), $ReplyBuilder);

	if(!$LineResponse->isSucceeded()) {
	    syslog(LOG_ERR, sprintf("stat: %d  response: %s" , $LineResponse->getHTTPStatus(), $LineResponse->getRawBody()));
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

    if (array_key_exists('latitude', $_POST)) {
    	$replyMessage = enterStartPoint($_POST['longitude'], $_POST['latitude'], $context_s, $context_u);
    }
    else {
	if (($replyMessage = messageDispatcher($_POST['queryMessage'], $context_s, $context_u)) == null) {
	    echo "なに？";
	}
    }

    var_export($replyMessage);

    file_put_contents($gs_context_s, serialize($context_s));
    file_put_contents($gs_context_u, serialize($context_u));
}

function messageDispatcher($receivedMessage, &$context_s, &$context_u) {

    global $respondTrainQuery, $reportSensData, $locMessageProcessor;

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
		),
	'loc_processor' => array(
		'func' => $locMessageProcessor ,
		'keyword' => array ("/範囲.*(広く|ひろく|狭く|せまく|1|2|3|4|5|6|7)/", "/範囲/", "/(近い|評判)順/", "/並べ方/"),
		),
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
	    if (($replyMessage = $aplTable['func']($receivedMessage, 999, null, $context_s, $context_u)) != null)
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

function PostbackeventDispatcher($postbackData, &$context_s, &$context_u) {

    $postbackParams = str_getcsv($postbackData);

    if ($postbackParams[0] == "map") {
    	$replyMessage = Postback_callback($postbackParams[0], $postbackParams[1], $context_s, $context_u);
    }

    return $replyMessage;
}

?>

</body>
</html>

