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
use \LINE\LINEBot\Event\MessageEvent\ImageMessage;;
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
    syslog(LOG_INFO, "inputData = " . $inputData);

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

	if ($event->getType() == 'message')  {
	    syslog(LOG_INFO, 'type = ' . $event->getMessageType() . ' dump : ' . print_r($event, true));
	}

	if ($event->getType() == 'leave') continue;	// Ignore leave event

	if ($event->isUserEvent()) {
	    $source_type = 'user';
	    $current_source = $event->getUserId();
	}
	else if ($event->isGroupEvent()) {
	    $source_type = 'group';
	    $current_source = $event->getGroupId();
	}
	else {
	    $source_type = 'room';
	    $current_source = $event->getRoomId();
	}

	$gs_context_u = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/context_".$current_source.".pac";
	$context_u = unserialize(file_get_contents($gs_context_u));
	$context_u['user_id'] = $current_source;
	$context_u['timestamp'] = $event->getTimestamp();
	$context_u['type'] = $source_type;

	/*****************************/
	if (!array_key_exists('photo', $context_u)) {
	    //
	    // アルバム登録の準備
	    // connector側で、LINEのsource_idとAlbumIDの結び付けを行えるようにする準備として、source_idのリストを更新する
	    //
	    if ($context_u['type'] == 'user') {
		$response = $Bot->getProfile($current_source);
		syslog(LOG_INFO, "RAW profile: ".print_r($response, true));
		if ($response->isSucceeded()) {
		    $profile = $response->getJSONDecodedBody();
		    $displayName = $profile['displayName'];
		}
		else
		    syslog(LOG_ERR, "cannot be acquired User Profile");
	    }
	    else if ($context_u['type'] == 'group') {
		$e_time = new DateTime();
		$e_time->setTimeStamp(intval($event->getTimestamp() / 1000));
		$displayName = $e_time->format('Y/m/d H:i');
		syslog(LOG_INFO, "member's name: ". $displayName);
	    }
	    else $displayName = "";

	    $gs_file = "gs://jebaxxconnector.appspot.com/sourcelist.json";
	    $packedData = json_decode(file_get_contents($gs_file), true);
	    syslog(LOG_INFO, "sourcelist.json :: " . print_r($packedData, true));
	    $packedData[$current_source]['name'] = $displayName;
	    $packedData[$current_source]['counter'] = 0; 
	    file_put_contents($gs_file, json_encode($packedData));
	    $context_u['photo'] = 0;
	}
	/*****************************/

	if ($event instanceof PostbackEvent) {
	    $replyMessage = PostbackeventDispatcher($event->getPostbackData(), $context_s, $context_u);	
	}
	else if ($event->getType() == 'join') {
	    $replyMessage = "ともだち！ ともだち！";
	}
	else if ($event->getType() != 'message') {
	    // メッセージじゃない
	    $replyMessage = null;
	    if ($context_u['type'] == 'user') {
		$replyMessage = "なに、これ？ ". $event->getType();
	    }
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
	        // 応答パターンのミスマッチ
		if ($context_u['type'] == 'user') {
		    $replyMessage = "なに？";
		}
	    }
	}
	else if ($event->getMessageType() == 'image' || $event->getMessageType() == 'video') {
	    //-#-###- 画像 or 動画

	    $messageId = $event->getMessageId();
	    try {
		$contentResponse = $Bot->getMessageContent($messageId);
	    } catch (Exception $e) {
		syslog(LOG_ERR, "contents cannot recieved.");
		$replyMessage = "時間内に引き取れなかった。もう一度送れる？";
	    }

	    $replyMessage = contentMessageProcessor($contentResponse, $event->getMessageType(), $context_s, $context_u);
	}
	else {
	    // メッセージタイプが違う
	    if ($context_u['type'] == 'user') {
		$replyMessage = "なに、それ？ ". $event->getType()." : ".$event->getMessageType();
	    }
	}

	file_put_contents($gs_context_u, serialize($context_u));

	if ($replyMessage != null) {	 // 何も返信しないケースは以下をスキップ
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

function contentMessageProcessor($messageResponse, $type, $context_s, $context_u) {

    $gs_file = "gs://jebaxxconnector.appspot.com/sourcelist.json";
    $packedData = json_decode(file_get_contents($gs_file), true);
    syslog(LOG_INFO, "sourcelist.json :: " . print_r($packedData, true));

    $user_id = $context_u['user_id'];

    if (array_key_exists('album_id', $packedData[$user_id])) {

	$counter = $packedData[$user_id]['counter']++; 
	if ($type == 'image') {
	    $contentFileName = $user_id . "_" . $counter . ".jpeg";
	}
	else {
	    $contentFileName = $user_id . "_" . $counter . ".mp4";
	}
	$contentFilePath = "gs://jebaxxconnector.appspot.com/photo_queue/" . $contentFileName;
	syslog(LOG_INFO, "contentFilePath : " . $contentFilePath);
	file_put_contents($contentFilePath, $messageResponse->getRawBody());

	file_put_contents($gs_file, json_encode($packedData));

	$url = 'https://jebaxxconnector.appspot.com/uploadRequestPoint';
	$params = http_build_query([ 'filename' => $contentFileName, 'source' => $user_id, 'counter' => $counter ]);
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_POST, TRUE);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($curl);
#	$errorno  = curl_errno($curl);
#	$errorcode  = curl_error($curl);
	curl_close($curl);

	$curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	syslog(LOG_INFO, "response data = " . print_r($response, true));
	syslog(LOG_INFO, "status code = " . $curl_status);
	if (intval($curl_status / 100) * 100 != 200 ) {
	    syslog(LOG_ERR, "curl_exec error");
	    return("#" . $counter . "がうまく登録できないみたい。「" .$response. "」と言われたよ");
	}

	return("#" . $counter . "を受付けた。");
    }
    else
	return("アルバムを登録しておいてくれたら写真を届けてあげる。". PHP_EOL . "https://jebaxxconnector.appspot.com/config");

}

function PushMessage($Line_id, $message) {

    // create HTTPClient instance
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    $pushMessageBuilder = new TextMessageBuilder($message);
    $response = $Bot->pushMessage($lineId, $pushMessageBuilder);

    if ($response->getHTTPStatus() != 200) {
	syslog(LOG_ERR, "Failed to sending a push message and status code is ". $response->getHTTPStatus());
    }
}

?>

</body>
</html>

