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
    unset($context_u['lp']);
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

function createSelectPanelBuilder_2() {

    $baseUrl = "https://jebaxxmessage.appspot.com/images/menu02";
    $baseSize = new BaseSizeBuilder(400, 1040);
    $callbacks = array();
    array_push($callbacks, new ImagemapMessageActionBuilder("1", new AreaBuilder(  0,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("2", new AreaBuilder(190,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("3", new AreaBuilder(380,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("4", new AreaBuilder(570,   0, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("5", new AreaBuilder(  0, 200, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("6", new AreaBuilder(190, 200, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("7", new AreaBuilder(380, 200, 190, 195)));
    array_push($callbacks, new ImagemapMessageActionBuilder("8", new AreaBuilder(570, 200, 190, 195)));

    array_push($callbacks, new ImagemapMessageActionBuilder("次",     new AreaBuilder(770,   0, 190, 260)));
    array_push($callbacks, new ImagemapMessageActionBuilder("再検索", new AreaBuilder(770, 200, 190, 260)));

    return(new ImagemapMessageBuilder($baseUrl, "コマンド選択パネル", $baseSize, $callbacks));
}

//
//  Postbackイベントのエントリーポイント
//
function Postback_callback($category, $param, &$context_s, &$context_u) {

    //
    //  「場所を送信」が選択された際、Postbackメッセージをアプリに送る
    //  アプリは場所を示すlocation messageを送り、ユーザーにタップさせる
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
	    return(createStartPointMessage($context_u));      //  始点特定状態に戻す
	}

	$keys = array('1', '2', '3', '4', '5', '6', '7');

	if (($ofs = array_search($receivedMessage, $keys)) === FALSE) {
	$context_u['lp']['state'] = "ニュートラル";
	    return(null);
	}

	if (!array_key_exists($ofs + $context_u['lp']['ptr_lc'], $context_u['lp']['lc'])) {
	    return("なんか違ってる");
	}
        
	$context_u['lp']['state'] = "施設特定";
	return(createFacilityInfoResponce($ofs, $context_u));
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

	$keys = array('1', '2', '3', '4', '5', '6', '7');

	if (($ofs = array_search($receivedMessage, $keys)) === FALSE) {
	$context_u['lp']['state'] = "ニュートラル";
	    return(null);
	}

	if (!array_key_exists($ofs + $context_u['lp']['ptr_lc'], $context_u['lp']['lc'])) {
	    return("なんか違ってる");
	}

	return(createFacilityInfoResponce($ofs, $context_u));
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

    $query_param = array( "lat" => $context_u['lp']['latitude'],
		    	  "lon" => $context_u['lp']['longitude'],
			  "dist" => 3,
			  "sort" => "geo",
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
//  検索結果レスポンスレスポンスの作成
//
function createQueryResultResponce(&$context_u) {

    $keys = array('1', '2', '3', '4', '5', '6', '7');

    $textdata = "";
    $context_u['lp']['ptr_lc'] = isset($context_u['lp']['ptr_lc']) ? $context_u['lp']['ptr_lc'] + 7 : 0;

    for ($i = 0; $i < 7; $i++) {
	if (!array_key_exists($context_u['lp']['ptr_lc'] + $i, $context_u['lp']['lc'])) {
	    $textdata .= "もうない";
	    break;
	}

	$loc_item = $context_u['lp']['lc'][$context_u['lp']['ptr_lc'] + $i];
	$textdata .= $keys[$i] . " : " . $loc_item['name'] . PHP_EOL;
	$textdata .= "      " . mb_strimwidth($loc_item['address'], 0, 23, "&", "UTF-8") . PHP_EOL;
	/*
	if (isset($loc_item['direction'])) {
	    $textdata .= "     " . $loc_item['direction'] . sprintf(" %5.2f", $loc_item['distance']) . "km";
	    $textdata .= " Δ=" . sprintf("%+4.1f", $loc_item['delta']) . "m" . PHP_EOL;
	}
	*/
    }

    $textMessage = new TextMessageBuilder($textdata);

    $selectPanel = createSelectPanelBuilder_2();
    $replyMessage = new MultiMessageBuilder();
    $replyMessage->add($textMessage);
    $replyMessage->add($selectPanel);

    return ($replyMessage);
}


//
//  施設情報と地図へのリンクをまとめて返信する
//
function createFacilityInfoResponce($ofs, &$context_u) {

    if (!array_key_exists($ofs + $context_u['lp']['ptr_lc'], $context_u['lp']['lc'])) {
	syslog(LOG_ERR, "createFacilityInfo: illegal offset: " . $receivedMessage);
	return(null);
    }
    
    $loc_item = $context_u['lp']['lc'][$ofs + $context_u['lp']['ptr_lc']];

    $text = mb_strimwidth($loc_item['address'], 0, 32, "...", "UTF-8") . PHP_EOL;
    if (isset($loc_item['direction'])) {
	$text .= $loc_item['direction'] . sprintf(" %5.2f", $loc_item['distance']) . "km";
	$text .= " Δ=" . sprintf("%+4.1f", $loc_item['delta']) . "m" . PHP_EOL;
    }

    $actions[0] = new PostbackTemplateActionBuilder("場所を送信", "map," . $ofs);
    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
    $mapUrl1 = "https://map.yahooapis.jp/course/V1/routeMap?appid=" . $app_id . "&route=" . $context_u['lp']['latitude'] . "," . $context_u['lp']['longitude'] . "," . $loc_item['latitude'] . "," . $loc_item['longitude'] . "&width=400&height=600";
    $actions[1] = new UriTemplateActionBuilder("経路地図 (download)", $mapUrl1);
    $mapUrl2 = "https://www.google.com/maps/dir/" . $context_u['lp']['latitude'] . "," . $context_u['lp']['longitude'] . "/" . $loc_item['latitude'] . "," . $loc_item['longitude'] . "/";
    $actions[2] = new UriTemplateActionBuilder("経路確認（google Map）", $mapUrl2);
    $buttonBuilder = new ButtonTemplateBuilder($loc_item['name'], $text, null, $actions);
    $replyBuilder = new TemplateMessageBuilder("ButtonTemplate", $buttonBuilder);

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
//  calculate distance of specified two place
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
