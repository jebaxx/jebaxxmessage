<?php

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

//
//  時刻表検索アプリ
//
$respondTrainQuery = function($receivedMessage, $i, $matched, &$context_s, &$context_u) {

    if ($i == 0) {
    	// 通常の問い合わせ
	return(normalTrainQuery($receivedMessage, null, null, null, $context_s, $context_u));
    }
    else if ($i == 1) {
    	// 曜日の指定
    	if (($matched[1]=="休日") || ($matched[1]=="祭日") || ($matched[1]=="祝日")) {
	    $context_s['tq']['day_of_the_week'] = 0;
	    $message = "お休み";
	}
	else if ($matched[1]=="平日") {
	    $context_s['tq']['day_of_the_week'] = 1;
	    $message = "ふつうの日";
	}
	else if ($matched[1]=="土曜") {
	    $context_s['tq']['day_of_the_week'] = 6;
	    $message = "土曜日";
	}

    	return("ようするに今日は".$message."なんだね");
    }
    else {
	// その他の問い合わせ
	if (preg_match("/(続き|つづき|その次)/", $receivedMessage, $result) != FALSE) {
	    // 前回の続きの電車 (続き|その次)
	    if (!isset($context_u['tq']['station'])) return(null);
	    $time = $context_u['tq']['next'];
	    $station = $context_u['tq']['station'];
	    $route = $context_u['tq']['route'];
	    return(normalTrainQuery($receivedMessage, $time, $station, $route, $context_s, $context_u));
	}

	if (preg_match("/(つぎ|次)/", $receivedMessage, $result) != FALSE) {
	    // 前回と同じ質問 (次|つぎ)
	    if (!isset($context_u['tq']['station'])) return(null);
	    $time = null;
	    $station = $context_u['tq']['station'];
	    $route = $context_u['tq']['route'];
	    return(normalTrainQuery($receivedMessage, $time, $station, $route, $context_s, $context_u));
	}
	
	if (isset($context_u['tq']['route_list'])) {
	    // ルート変更依頼
	    if (preg_match("/".$context_u['tq']['route_list']."/", $receivedMessage, $result)) {
		$time = null;
		$station = $context_u['tq']['station'];
		$route = $result[1];
		return(normalTrainQuery($receivedMessage, $time, $station, $route, $context_s, $context_u));
	    }
	}

        return(null);
    }
};

//
//  祝日チェック
//
function getHoliday_info($time) {
    $gs_holidayinfo = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/holiday_info.csv";

    $today = $time->format("Y-m-d");

    if (($r_hndl = fopen($gs_holidayinfo, "r")) == FALSE) {
	// local gsのファイルをオープンできない時
	// 内閣府のHPから祝祭日情報を取ってくる
	//
	$holiday_inf_csv = file_get_contents("http://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv");

	if ($holiday_inf_csv != FALSE) {
	    //
	    // エンコーディングをUTF-8に
	    //
	    if (($code_name = mb_detect_encoding($holiday_inf_csv, "SJIS, SJIS-win, UTF-16, UTF-8, JIS, EUC-JP")) != FALSE) {
	    	syslog(LOG_INFO, "detected encoding is ".$code_name);
		$holiday_inf_csv = mb_convert_encoding($holiday_inf_csv, "UTF-8", $code_name);
	    }
	    file_put_contents($gs_holidayinfo, $holiday_inf_csv);
	    syslog(LOG_NOTICE, $gs_holidayinfo." acquired.");
	}

	if (($r_hndl = fopen($gs_holidayinfo, "r")) == FALSE) {
	    syslog(LOG_WARN, $gs_holidayinfo.": cannot open");
	    return([1, null]);		//エラー発生時は平日扱い
	}
    }

    while (1) {
	if (($holiday_rec = fgetcsv($r_hndl)) == FALSE) break;

	if ($holiday_rec[0] == $today) {
	    syslog(LOG_INFO, "HOLIDAY:".$holiday_rec[1]);
	    return([0, $holiday_rec[1]]);
	}

	if ($holiday_rec[0] > $today) break;
    }

    fclose($r_hndl);
    return([1, null]);
}

function normalTrainQuery($receivedMessage, $_time, $_station, $_route, &$context_s, &$context_u) {

    //
    //    Aquire station info
    //
    $gs_station = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_station.pac";
    $packedStation = unserialize(file_get_contents($gs_station));

    /////////////////////////////////////////////////////////////////////////////////////////
    //    対象時刻をセットする
    //
    if ($_time == null) {

	// context_sの有効期限を超えていたら曜日情報を破棄
	// ついでに期限情報も再設定するために一旦破棄
	//
	$t_now     = new DateTime();
	$timestamp = $t_now->getTimestamp();

	if (isset($context_s['tq']['expire_time'])) {
	    if ($context_s['tq']['expire_time'] <= $timestamp) {
		syslog(LOG_NOTICE, "exceed expire time ");
		$context_s['tq']['day_of_the_week'] = -1;
		$context_s['tq']['expire_time']     = null;
	    }
	}

	// 有効期限が未設定の時、有効期限を次の午前2時に(再)設定する
	//
	if ($context_s['tq']['expire_time'] == null) {
	    $t_next_limit = new DateTime((($t_now->format('H') < 2) ? "today 02:00" : "tomorrow 02:00"));
	    $context_s['tq']['expire_time'] = $t_next_limit->getTimestamp();
	    syslog(LOG_NOTICE, "reset expire time". $t_next_limit->format("Y/m/d H:i"));
	}

	// 現在時刻情報を設定する
	$currentTime = $t_now->format("H")*60+$t_now->format("i");
 
	if ($currentTime < 120) {
	    //
	    //  午前2時までは前日の時刻表を参照（24時、25時）
	    //
	    $timestamp   -= 24 * 3600;
	    $currentTime += 24 * 60;
	}

	// 曜日情報が未設定の時、曜日情報を(再)設定する
	//
	if ($context_s['tq']['day_of_the_week'] == -1) {

	    // 祝祭日のチェック
	    $holiday_info = getHoliday_info($t_now);
	    if ($holiday_info[0] == 0) {
		$context_s['tq']['day_of_the_week'] = 0;
		$context_s['tq']['holiday_name'] = $holiday_info[1];
		syslog(LOG_NOTICE, "reset day_of_the_week". $context_s['tq']['holiday_name']);
	    }
	    else {
		$context_s['tq']['day_of_the_week'] = ($w = date("w", $timestamp)) == 0 ? 0 : ($w == 6 ? 6 : 1);
		$context_s['tq']['holiday_name'] = null;
		syslog(LOG_NOTICE, "reset day_of_the_week to ". $context_s['tq']['day_of_the_week']);
	    }
	}

	$day_of_the_week = $context_s['tq']['day_of_the_week'];
    }

    else {
	$currentTime = $_time;
	$day_of_the_week = $context_s['tq']['day_of_the_week'];
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

    $context_u['tq']['station'] = $station_name;	// CONTEXT (station)

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
	$context_u['tq']['route_list'] = $route_list;

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

    $context_u['tq']['route'] = $route_name;		// CONTEXT (route)


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
	if ($context_s['tq']['holiday_name'] != null) {
	    $message .= $context_s['tq']['holiday_name'].PHP_EOL;
	}
	else {
	    $message .= ($day_of_the_week == 0) ? '休日' : (($day_of_the_week == 1) ? '平日' : '土曜');
	    $message .= "ダイヤ".PHP_EOL;
	}
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	$context_u['tq']['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)

	if (($train = fgetcsv($r_hndl)) == FALSE) break;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	$context_u['tq']['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)

	if (($train = fgetcsv($r_hndl)) == FALSE) break;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2].PHP_EOL;
	$context_u['tq']['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)
	
	if (($train = fgetcsv($r_hndl)) == FALSE) break;
	$message .= sprintf("%02d", $train[0]).":".sprintf("%02d", $train[1])." ".$train[2];
	$context_u['tq']['next'] = $train[0] * 60 + $train[1] + 1;	// CONTEXT (next)

	break;
    }

    fclose($r_hndl);
    return $message;
}

?>
