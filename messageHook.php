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

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;

//
//  時刻表検索アプリ
//
$respondTrainQuery = function($receivedMessage, $i, $matched) {

    global $context;
    $user_id = $context['current_user'];

    if ($i == 0) {
    	// 通常の問い合わせ
	return(normalTrainQuery($receivedMessage, null, null, null));
    }
    else if ($i == 1) {
	// 前回の続きの電車 (続き|その次)
	if (!isset($context['tq'][$user_id]['station'])) return(null);
	$time = $context['tq'][$user_id]['next'];
	$station = $context['tq'][$user_id]['station'];
	$route = $context['tq'][$user_id]['route'];
	return(normalTrainQuery($receivedMessage, $time, $station, $route));
    }
    else if ($i == 2) {
	// 前回と同じ質問 (次|つぎ)
	if (!isset($context['tq'][$user_id]['station'])) return(null);
	$time = null;
	$station = $context['tq'][$user_id]['station'];
	$route = $context['tq'][$user_id]['route'];
	return(normalTrainQuery($receivedMessage, $time, $station, $route));
    }
    else {
        return(null);
    }
};

function normalTrainQuery($receivedMessage, $_time, $_station, $_route) {

    global $context;
    $user_id = $context['current_user'];

    //
    //    Aquire station info
    //
    $gs_station = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_station.pac";
    $packedStation = unserialize(file_get_contents($gs_station));

    /////////////////////////////////////////////////////////////////////////////////////////
    //    対象時刻をセットする
    //
    if ($_time == null) {
        // 現在時刻とtimestamp(曜日算出用）を取得
        //
        $currentTime = date("H")*60+date("i");
        $timestamp   = time();

        if ($currentTime < 120) {
	    //
	    //  午前2時までは前日の時刻表を参照（24時、25時）
	    //
	    $timestamp   -= 24 * 3600;
	    $currentTime += 24 * 60;
	}

	if (isset($context['tq']['day_of_the_week']))		// CONTEXT (day_of_the_week)
	    $day_of_the_week = $context['tq']['day_of_the_week'];
	else {
	    $day_of_the_week = ($w = date("w", $timestamp)) == 0 ? 0 : ($w == 6 ? 6 : 1);
	    $context['tq']['day_of_the_week'] = $day_of_the_week;
	}
    }
    else {
	$currentTime = $_time;
	$day_of_the_week = $context['tq']['day_of_the_week'];
    }

    /////////////////////////////////////////////////////////////////////////////////////////
    //    対象駅をセットする
    //
    if ($_station == null) {
	// 
	//
	$station_list = "(";
	foreach ($packedStation as $stationName => $stationInfo) {
	    if ($station_list != "(") $station_list .= "|";
	    $station_list .= $stationName;
	}
	$station_list .= ")";

	if (preg_match("/".$station_list."/", $receivedMessage, $result) == FALSE) {
	    return("その駅はしらない");
	}

	$station_name = $result[1];
    }
    else {
	$station_name = $_station;
    }

    $context['tq'][$user_id]['station'] = $station_name;	// CONTEXT (station)

    ////////////////////////////////////////////////////////////////////////////////////////
    //    対象ルートをセットする
    //
    if ($_route == null) {

    	$route_list = "(";
	foreach($packedStation[$station_name]['routes'] as $route_name => $urls) {
	    if ($route_list != "(") $route_list .= "|";
	    $route_list .= $route_name;
	}
	$route_list .= ")";

	if (preg_match("/".$route_list."/", $receivedMessage, $result)) {
	    $route_name = $result[1];
	}
	else {
	    $route_name = $packedStation[$station_name]['primary_route'];
	}

    }
    else {
	$route_name = $_route;
    }

    $context['tq'][$user_id]['route'] = $route_name;		// CONTEXT (route)


    ////////////////////////////////////////////////////////////////////////////////////////
    //    時刻表を検索
    //
    $gs_timetable = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/timetable/_".$station_name."-".$route_name."-".$day_of_the_week.".csv";

    if (($r_hndl = fopen($gs_timetable, "r")) == FALSE) {
	return("時刻表が見つからない");
    }

    while (1) {
	if (($train = fgetcsv($r_hndl)) == FALSE) {
	    fclose($r_hndl);
	    return("もう電車がない");
	}

	if ($train[0] * 60 + $train[1] < $currentTime) continue;

	$message  = $route_name. " ";
	$message .= ($day_of_the_week == 0) ? '休日' : (($day_of_the_week == 1) ? '平日' : '土曜');
	$message .= "ダイヤ".PHP_EOL;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	$context['tq'][$user_id]['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)

	if (($train = fgetcsv($r_hndl)) == FALSE) break;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	$context['tq'][$user_id]['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)

	if (($train = fgetcsv($r_hndl)) == FALSE) break;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	$context['tq'][$user_id]['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)
	
	if (($train = fgetcsv($r_hndl)) == FALSE) break;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2];
	$context['tq'][$user_id]['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)

	break;
	}

    fclose($r_hndl);
    return $message;
}

//
//  User and Application context data
//
$context = "";

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

    $gs_context = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_context.pac";
    $context = unserialize(file_get_contents($gs_context));

    foreach($Events as $event){

	$context['current_user'] = $event->getEventSourceId();		// user_id or group_id or room_id
	$context['timestamp']    = $event->getTimestamp();

	if (($event->getType() != 'message') || ($event->getMessageType() != 'text')) {
	    $replyMessage = "なに、それ？ ". $event->getType()." : ".$event->getMessageType();
	}
	else {

	    $receivedMessage = $event->getText();

	    if (($replyMessage = messageDispatcher($receivedMessage)) == null) {
		$replyMessage = "なに？";
	    }

	}

	$SendMessage = new MultiMessageBuilder();
	$TextMessageBuilder = new TextMessageBuilder($replyMessage);
	$SendMessage->add($TextMessageBuilder);
	$Bot->replyMessage($event->getReplyToken(), $SendMessage);

    }

    file_put_contents($gs_context, serialize($context));
}
else {

    $gs_context = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_context.pac";
    $context = unserialize(file_get_contents($gs_context));

    $context['current_user'] = 'uid_xxx';
    $context['timestamp']    = 0;

    if (($replyMessage = messageDispatcher($_POST['queryMessage'])) == null) {
	echo "なに？";
    }

    echo $replyMessage;

    file_put_contents($gs_context, serialize($context));
}


function messageDispatcher($receivedMessage) {

    global $context;
    global $respondTrainQuery, $reportSensData;

    //
    //  Messages Entries to whitch each application respond to
    //
    $messageTbl = array(
	'trainTable' => array(
		'func' => $respondTrainQuery, 
		'keyword' => array ("/^時刻表/", "/^(その次|続き|つづき)/", "/^(つぎ|次)/", "/今日.*(休日|祭日|祝日|土曜)/"),
		),
	'sensorRec'  => array(
		'func' => $reportSensData, 
		'keyword' => array ("/^気温/")
		)
	);

    $user_id = $context['current_user'];

    //
    //  前回応答したアプリのパターンを先に確認する
    //
    if (isset($context['current_apl'][$user_id])) {
    	
    	$aplTable = $messageTbl[$curent_apl = $context['current_apl'][$user_id]];

	for ($i = 0; isset($aplTable['keyword'][$i]) ; $i++) {

	    if (preg_match($aplTable['keyword'][$i], $receivedMessage, $result) == FALSE) continue;

	    if (($replyMessage = $aplTable['func']($receivedMessage, $i, $result)) != null) return($replyMessage);
	}

	// 一致するパターンがない場合でも、優先アプリに問い合わせる
	if ($replyMessagge == null) $replyMessage = $aplTable['func']($receivedMessage, 999, $result);
    }

    //
    // それ以外のアプリのパターンを確認する
    //
    foreach ($messageTbl as $aplName => $aplTable) {

	if ($aplName == $current_apl) continue;

	for ($i = 0; isset($aplTable['keyword'][$i]) ; $i++) {

	    if (preg_match($aplTable['keyword'][$i], $receivedMessage, $result) == FALSE) continue;

	    if (($replyMessage = $aplTable['func']($receivedMessage, $i, $result)) != null) {
		$context['current_apl'][$user_id] = $aplName;
	    }

	    return($replyMessage);
	}
    }

    return(null);
}

?>

</body>
</html>

