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

    foreach($Events as $event){
    	$receivedMessage = $event->getText();

	if (($replyMessage = messageDispatcher($receivedMessage)) == null) {
	    $replyMessage = "なに？";
	}

	$SendMessage = new MultiMessageBuilder();
	$TextMessageBuilder = new TextMessageBuilder($replyMessage);
	$SendMessage->add($TextMessageBuilder);
	$Bot->replyMessage($event->getReplyToken(), $SendMessage);
    }
}
else {

    if (($replyMessage = messageDispatcher($_POST['queryMessage'])) == null) {
	echo "なに？";
    }

    echo $replyMessage;
}

    
function messageDispatcher($receivedMessage) {

    if (preg_match("/^時刻表/", $receivedMessage) != FALSE) {
        //
        // 時刻表検索のリクエスト
        //
        return(respondTrainQuery($receivedMessage));
    }

    if (preg_match("/^気温/", $receivedMessage) != FALSE) {
	//
	// センサー記録の検索
	//
	return(reportSensData($receivedMessage));
    }

    return(null);
}

//
//  時刻表検索アプリ
//
function respondTrainQuery($receivedMessage) {

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

    $DayOfTheWeek = ($w = date("w", $timestamp)) == 0 ? 0 : ($w == 6 ? 6 : 1);

    //
    //    Aquire station info
    //
    $gs_station = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_station.pac";
    $packedStation = unserialize(file_get_contents($gs_station));
    $station_list = "(";
    foreach ($packedStation as $stationName => $stationInfo) {

	if ($station_list != "(") $station_list .= "|";
	$station_list .= $stationName;
    }
    $station_list .= ")";

    //
    //  Analize request message
    //
    if (preg_match("/".$station_list."/", $receivedMessage, $result) == FALSE) {
        return("その駅はしらない");
    }
    else {
	$station_name = $result[1];
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

	//
	// open timetable file and search train
	//
	$gs_timetable = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/timetable/_".$station_name."-".$route_name."-".$DayOfTheWeek.".csv";

	if (($r_hndl = fopen($gs_timetable, "r")) == FALSE) {
	    return("時刻表が見つからない");
	}

	while (1) {
	    if (($train = fgetcsv($r_hndl)) == FALSE) {
		fclose($r_hndl);
		return("もう電車がない");
	    }

	    if ($train[0] * 60 + $train[1] < $currentTime) continue;

	    $message = ($DayOfTheWeek == 0) ? '休日' : (($DayOfTheWeek == 1) ? '平日' : '土曜');
	    $message .= "ダイヤ".PHP_EOL;
	    $message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;

	    if (($train = fgetcsv($r_hndl)) == FALSE) break;
	    $message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;

	    if (($train = fgetcsv($r_hndl)) == FALSE) break;
	    $message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	
	    if (($train = fgetcsv($r_hndl)) == FALSE) break;
	    $message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2];

	    break;
	}

	fclose($r_hndl);
	return $message;

    }
}


?>

</body>
</html>

