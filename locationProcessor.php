<?php

require_once(__DIR__."/vendor/autoload.php");

use \LINE\LINEBot;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\LocationMessageBuilder;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;

use \LINE\LINEBot\MessageBuilder\ImagemapMessageBuilder;
use \LINE\LINEBot\MessageBuilder\Imagemap\BaseSizeBuilder;
use \LINE\LINEBot\ImagemapActionBuilder\ImagemapMessageActionBuilder;
use \LINE\LINEBot\ImagemapActionBuilder\AreaBuilder;


//
//  ロケーションメッセージのエントリーポイント
//
function enterStartPoint($longitude, $latitude, &$context_s, &$context_u) {

    $context_u['current_apl']     = "loc_processor";
//    unset($context_u['lp']);
    $context_u['lp']['state']     = "始点特定";
    $context_u['lp']['altitude']  = getAltitude($longitude, $latitude);
    $context_u['lp']['longitude'] = $longitude;
    $context_u['lp']['latitude']  = $latitude;

    return(createStartPointMessage($context_u));
}

function createStartPointMessage(&$context_u) {

    unset($context_u['lp']['qc']);
    $context_u['lp']['qc']['寺']['name'] = "寺";
    $context_u['lp']['qc']['寺']['gc'] = "0424001";
    $context_u['lp']['qc']['神社']['name'] = "神社";
    $context_u['lp']['qc']['神社']['gc'] = "0424002";
    $context_u['lp']['qc']['駅']['name'] = "駅";
    $context_u['lp']['qc']['駅']['gc'] = "0306006";
    $context_u['lp']['qc']['コンビニ']['name'] = "コンビニ";
    $context_u['lp']['qc']['ラーメン']['name'] = "ラーメン";

/*
    foreach ($context_u['lp']['qc'] as $key=>$value) {
	$replyMessage .= $key . " : " . $value['name'] . PHP_EOL;
    }

    $replyMessage .= "これ以外…直接入力OK";
*/

    $textMessage = new TextMessageBuilder("この場所の標高は" . $context_u['lp']['altitude'] . "m". PHP_EOL . "ここで何か探してるの？");
    $selectPanel = createSelectPanelBuilder_1();
    $replyMessage = new MultiMessageBuilder();
    $replyMessage->add($textMessage);
    $replyMessage->add($selectPanel);

    return ($replyMessage);
}

function createSelectPanelBuilder_1() {

    $baseUrl = "https://jebaxxmessage.appspot.com/images/menu01";
    $baseSize = new BaseSizeBuilder(400, 1040);
    $callbacks = array();
    array_push($callbacks, new ImagemapMessageActionBuilder("寺", new AreaBuilder(0, 0, 345, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("神社", new AreaBuilder(348, 0, 345, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("駅", new AreaBuilder(693, 0, 345, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("コンビニ", new AreaBuilder(0, 200, 345, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("ラーメン", new AreaBuilder(348, 200, 345, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("これ以外", new AreaBuilder(693, 200, 345, 195)));

    return(new ImagemapMessageBuilder($baseUrl, "コマンド選択パネル", $baseSize, $callbacks));
}

function createSelectPanelBuilder_2($offset) {

    $baseUrl = "https://jebaxxmessage.appspot.com/images/menu02";
    $baseSize = new BaseSizeBuilder(400, 1040);
    $callbacks = array();
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+1,   new AreaBuilder(  0,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+2, new AreaBuilder(190,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+3, new AreaBuilder(380,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+4, new AreaBuilder(570,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+5, new AreaBuilder(  0, 200, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+6, new AreaBuilder(190, 200, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+7, new AreaBuilder(380, 200, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder($offset+8, new AreaBuilder(570, 200, 190, 195)));

    array_push($callbacks, new ImagemapMessageActionBuilder("次",     new AreaBuilder(770,   0, 190, 260)));
    array_push($callbacks, new ImagemapMessageActionBuilder("再検索", new AreaBuilder(770, 200, 190, 260)));

    return(new ImagemapMessageBuilder($baseUrl, "コマンド選択パネル", $baseSize, $callbacks));
}

//
//  Postbackイベントのエントリーポイント（現在未使用だが）
//
function Postback_callback($category, $param, &$context_s, &$context_u) {

    //
    //  「場所を送信」が選択されると、モバイルアプリからPostbackメッセージが送られて来る
    //  その応答として場所を示すlocation messageをモバイルアプリに送り、ユーザーにタップさせる
    //
    if ($category != "map") {
	syslog(LOG_ERR, "Illigal postback category.");
	return null;
    }

    $idx = intval($param) + $context_u['lp']['ptr_lc'];
    
    if (!array_key_exists($idx, $context_u['lp']['lc'])) {
	syslog(LOG_ERR, "Illegal postback parameter.");
	return null;
    }

    $loc_item = $context_u['lp']['lc'][$idx];

    $address = $loc_item['address'] . PHP_EOL;
    if (isset($loc_item['direction'])) {
	$address .= $loc_item['direction'] . sprintf(" %5.2f", $loc_item['distance']) . "km";
	$address .= " Δ=" . sprintf("%+4.1f", $loc_item['delta']) . "m";
    }

    return(new LocationMessageBuilder($loc_item['name'], $address, $loc_item['latitude'], $loc_item['longitude']));
}

//
//  textメッセージエントリーポイント
//
$locMessageProcessor = function($receivedMessage, $i, $matched, &$context_s, &$context_u) {

    syslog(LOG_INFO, "Message = " . $receivedMessage . " : state = " . $context_u['lp']['state']);
    echo "message = " . $receivedMessage . "<br>" . PHP_EOL;

//////////////////////////////////////////////////////////////////////////////////////////
    if ($i == 0) {
	//
	//  探索範囲の変更指示
	//

	if (!isset($context_u['lp']['area_width']))  $context_u['lp']['area_width'] = 3;

	if (($matched[1] == "広く") || ($matched[1] == "ひろく")) {
	    if ($context_u['lp']['area_width'] < 7) $context_u['lp']['area_width']++;
	}
	else if (($matched[1] == "狭く") || ($matched[1] == "せまく")) {
	    if ($context_u['lp']['area_width'] > 1) $context_u['lp']['area_width']--;
	}
	else if (($w = intval($matched[1])) > 0 && ($w < 8)) {
	    $context_u['lp']['area_width'] = intval($matched[1]);
	}

	return("探索領域を半経" . $context_u['lp']['area_width'] . "km に設定した");
    }

//////////////////////////////////////////////////////////////////////////////////////////
    if ($i == 1) {
	//
	//  探索範囲の確認
	//
	return("探索範囲は半径" . (isset($context_u['lp']['area_width']) ? $context_u['lp']['area_width'] : 3) . "km"); 
    }

//////////////////////////////////////////////////////////////////////////////////////////
    if ($i == 2) {
	//
	//  検索結果並び順の指定
	//

	if ($matched[1] == "近い")
	    $context_u['lp']['orderBy'] = "近い順";

	if ($matched[1] == "評判")
	    $context_u['lp']['orderBy'] = "評判順";

	return("結果を" . (isset($context_u['lp']['orderBy']) ? $context_u['lp']['orderBy'] : "近い順") . "に並べるよ"); 
    }
    if ($i == 3) {
	//
	//  並び順の確認
	//
	return("結果を" . $context_u['lp']['orderBy'] . "に並べるよ");
    }
    if ($context_u['lp']['state'] == "始点特定") {

	if ($receivedMessage == 'これ以外') {
	    $context_u['lp']['state'] = "ワード入力";
	    return('何を探すの？');
	}

	if (!array_key_exists($receivedMessage, $context_u['lp']['qc'])) {
	    //
	    //  期待と違うメッセージの場合
	    $context_u['lp']['state'] = "ニュートラル";
	    return(null);
	}

        $context_u['lp']['state'] = "施設一覧";

	//  検索実行
	if (($resultNum = execLocationQuery($receivedMessage, $context_u)) == 0) {
	    return("この辺には" . $context_u['lp']['target'] . "はない");
	}

	return(createQueryResultResponce($context_u));      //  結果メッセージを送信
    }

//////////////////////////////////////////////////////////////////////////////////////////
    if ($context_u['lp']['state'] == "ワード入力") {

	$context_u['lp']['state'] = "施設一覧";

	//  検索実行
	if (($resultNum = execLocationQuery($receivedMessage, $context_u)) == 0) {
	    return("この辺には" . $context_u['lp']['target'] . "はない");
	}

        return(createQueryResultResponce($context_u));      //  結果メッセージを送信
    }

//////////////////////////////////////////////////////////////////////////////////////////
    if ($context_u['lp']['state'] == "施設一覧") {
        
	if ($receivedMessage == '次' || $receivedMessage == 'つぎ') {
	    return(createQueryResultResponce($context_u));      //  結果メッセージ（続き）を送信
	}
        
	if ($receivedMessage == '再検索') {
	    $context_u['lp']['state']     = "始点特定";
	    return(createStartPointMessage($context_u));	//  始点特定状態に戻す
	}

	if (($num = intval($receivedMessage) - 1) == -1) {
	    return(null);					//  無効な入力値
	}

	if (!array_key_exists($num, $context_u['lp']['lc'])) {
	    return("なんか番号が違ってる");
	}
        
	$context_u['lp']['state'] = "施設特定";
	return(createDestinationInfoResponce($num, $context_u));
    }
    
//////////////////////////////////////////////////////////////////////////////////////////
    if ($context_u['lp']['state'] == "施設特定") {

	if ($receivedMessage == '次' || $receivedMessage == 'つぎ') {
	$context_u['lp']['state'] = "施設一覧";
	    return(createQueryResultResponce($context_u));      //  結果メッセージ（続き）を送信
	}
        
	if ($receivedMessage == '再検索') {
	    $context_u['lp']['state']     = "始点特定";
	    return(createStartPointMessage($context_u));      //  始点特定状態に戻す
	}

	if (($num = intval($receivedMessage) - 1) == -1) {
	    return(null);					//  無効な入力値
	}

	if (!array_key_exists($num, $context_u['lp']['lc'])) {
	    return("なんか番号が違ってる");
	}

	return(createDestinationInfoResponce($num, $context_u));
    }
};

//
//  近隣施設検索を実行
//  （結果はcontext変数に格納し、関数値としてヒット件数を返す）
//
function execLocationQuery($receivedMessage, &$context_u) {
        
    syslog(LOG_INFO, "loc_query : ". $receivedMessage);
    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
    $app_url = "https://map.yahooapis.jp/search/local/V1/localSearch";

    $area_width = isset($context_u['lp']['area_width']) ? $context_u['lp']['area_width'] : 3;
    $orderBy    = isset($context_u['lp']['orderBy'])    ? ($context_u['lp']['orderBy'] == '評判順' ? 'hybrid' : 'geo') : 'geo';

    $query_param = array( "lat" => $context_u['lp']['latitude'],
		    	  "lon" => $context_u['lp']['longitude'],
		    	  "results" => 20,
			  "dist" => $area_width,
			  "sort" => $orderBy,
			  "output" => "json");

    if (!array_key_exists($receivedMessage, $context_u['lp']['qc'])) {
	//
	//  直接直接キーワード入力
	//
	$query_param['query'] = $target = $receivedMessage;
    }
    else if (array_key_exists('gc', $context_u['lp']['qc'][$receivedMessage])) {
	//
	//  コード指定検索
	//
	$query_param['gc'] = $context_u['lp']['qc'][$receivedMessage]['gc'];
	$target = $context_u['lp']['qc'][$receivedMessage]['name'];
    }
    else {
	//
	//  間接間接キーワード検索
	//
	$query_param['query'] = $target = $context_u['lp']['qc'][$receivedMessage]['name'];
    }

    $context_u['lp']['taget'] = $target;
    $app_param = $app_url . "?" . http_build_query($query_param);

    echo $app_param . "<br>". PHP_EOL;

    syslog(LOG_INFO, $app_param);

    $ch = curl_init($app_param);

    curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => "Yahoo AppID: $app_id"));
    $result = curl_exec($ch);
    curl_close($ch);

    $resultSet = json_decode($result, true);

    //
    //  検索結果を整形して、コンテキスト変数に保存する
    //
    unset($context_u['lp']['lc']);
    unset($context_u['lp']['ptr_lc']);

    for ($i = 0; ; $i++) {
	if (!array_key_exists($i, $resultSet['Feature'])) {
	    break;
	}

	$loc_item = array();
	$loc_item['name']    = $resultSet['Feature'][$i]['Name'];
	$loc_item['address'] = $resultSet['Feature'][$i]['Property']['Address'];

	$coordinates = str_getcsv($resultSet['Feature'][$i]['Geometry']['Coordinates']);
	$loc_item['longitude'] = $coordinates[0];
	$loc_item['latitude']  = $coordinates[1];

	$dist = measureDistance($context_u['lp']['longitude'], $context_u['lp']['latitude'], $loc_item['longitude'], $loc_item['latitude']);
        if ($dist['dir_val'] != null) {
	    $alt = getAltitude($loc_item['longitude'], $loc_item['latitude']);
	    $loc_item['delta']  = floatval($alt) - floatval($context_u['lp']['altitude']);
	    $loc_item['direction'] = $dist['dir_val'];
	    $loc_item['distance'] = $dist['dist'];
	}

	$context_u['lp']['lc'][$i] = $loc_item;
    }

    return($context_u['lp']['num_lc'] = $i);
}

//
//  検索結果一覧リストを送信
//
function createQueryResultResponce(&$context_u) {

    $textdata = "";
    $context_u['lp']['ptr_lc'] = isset($context_u['lp']['ptr_lc']) ? $context_u['lp']['ptr_lc'] + 8 : 0;

    for ($i = 0; $i < 8; $i++) {
        if ($textdata != "") $textdata .= PHP_EOL;
	if (!array_key_exists($context_u['lp']['ptr_lc'] + $i, $context_u['lp']['lc'])) {
	    $textdata .= "もうない";
	    break;
	}

	$loc_item = $context_u['lp']['lc'][$context_u['lp']['ptr_lc'] + $i];
	$textdata .= $i+1 . " : " . $loc_item['name'] . PHP_EOL;
	$textdata .= "      " . mb_strimwidth($loc_item['address'], 0, 25, "&", "UTF-8");
	/*
	if (isset($loc_item['direction'])) {
	    $textdata .= "     " . $loc_item['direction'] . sprintf(" %5.2f", $loc_item['distance']) . "km";
	    $textdata .= " Δ=" . sprintf("%+4.1f", $loc_item['delta']) . "m" . PHP_EOL;
	}
	*/
    }

    $textMessage = new TextMessageBuilder($textdata);

    $selectPanel = createSelectPanelBuilder_2($context_u['lp']['ptr_lc']);
    $replyMessage = new MultiMessageBuilder();
    $replyMessage->add($textMessage);
    $replyMessage->add($selectPanel);

    return ($replyMessage);
}


//
//  施設詳細情報データを送信
//
function createDestinationInfoResponce($num, &$context_u) {

    if (!array_key_exists($num  , $context_u['lp']['lc'])) {
	syslog(LOG_ERR, "createDestinationInfo: illegal item number: " . $num);
	return(null);
    }
    
    $loc_item = $context_u['lp']['lc'][$num];

    $addr_text = $loc_item['address'] . PHP_EOL;
    if (isset($loc_item['direction'])) {
	$addr_text .= $loc_item['direction'] . sprintf(" %5.2f", $loc_item['distance']) . "km";
	$addr_text .= " Δ=" . sprintf("%+4.1f", $loc_item['delta']) . "m";
    }

    $locMessage = new LocationMessageBuilder($loc_item['name'], $addr_text, $loc_item['latitude'], $loc_item['longitude']);

//    $actions[0] = new PostbackTemplateActionBuilder("場所を送信", "map," . $num);
    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
    $mapUrl1 = "https://map.yahooapis.jp/course/V1/routeMap?appid=" . $app_id . "&route=" . $context_u['lp']['latitude'] . "," . $context_u['lp']['longitude'] . "," . $loc_item['latitude'] . "," . $loc_item['longitude'] . "&width=600&height=900";
    $actions[0] = new UriTemplateActionBuilder("経路地図 (download)", $mapUrl1);
    $mapUrl2 = "https://www.google.com/maps/dir/" . $context_u['lp']['latitude'] . "," . $context_u['lp']['longitude'] . "/" . $loc_item['latitude'] . "," . $loc_item['longitude'] . "/";
    $actions[1] = new UriTemplateActionBuilder("経路確認（google Map）", $mapUrl2);
    $buttonBuilder = new ButtonTemplateBuilder($loc_item['name'], $addr_text, null, $actions);
    $buttonTemplateMessage = new TemplateMessageBuilder("ButtonTemplate", $buttonBuilder);
    $replyBuilder = new MultiMessageBuilder();
    $replyBuilder->add($buttonTemplateMessage);
    $replyBuilder->add($locMessage);
    return($replyBuilder);
}

//
//  query altitude of specified place
//
//	input:	longitude and latitude
//	output:	latitude
//
function getAltitude($loc_longitude, $loc_latitude) {

    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
    $app_url = "https://map.yahooapis.jp/alt/V1/getAltitude";		// Altitude API

    $app_params = array ( "coordinates" => $loc_longitude . ',' . $loc_latitude,
			"output" => "json");

    $ch = curl_init($app_url . '?' . http_build_query($app_params));

    curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => "Yahoo AppID: $app_id"));
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return ($result['Feature'][0]['Property']['Altitude']);
}

//
//  calculate distance of given two places
//
//	input:	source point and destination point (of longitude and latitude)
//	output:	distance and direction of distination point
//
function measureDistance($src_lon, $src_lat, $dst_lon, $dst_lat) {
    $dy = ($dst_lat - $src_lat) * 6356.75 * 2 * M_PI / 360;
    $dx = ($dst_lon - $src_lon) * 6378.13 * 2 * M_PI / 360 * cos(M_PI * $src_lat / 180);

    $dist['dx']   = $dx;
    $dist['dy']   = $dy;
    $dist['dist'] = sqrt($dy * $dy + $dx * $dx);

    if ($dist['dist'] < 0.01) {
	$dist['dir_num'] = -1;
	$dist['dir_val'] = null;
    }
    else {
	$dist['dir_num'] = atan2($dy, $dx) * 16 / M_PI + 17;
	$dir = intval(atan2($dy, $dx) * 16 / M_PI + 17) / 2;

	$dir_name = array("西", "西南西", "南西", "南南西", "南", "南南東", "南東", "東南東", "東", "東北東", "北東", "北北東", "北", "北北西", "北西", "西北西", "西" );
	$dist['dir_val'] = $dir_name[$dir];
    }

    return($dist);
}

?>
