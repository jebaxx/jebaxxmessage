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

//
// location message responder initial message responder
//
//	input : location message
//	output: Altitude
//		Search prompt
//
function locationProcessor($loc_longitude, $loc_latitude, &$context_s, &$context_u) {

    $context_u['current_apl'] = "loc_processor";


//    var_dump($result);

    unset($context_u['lp']);
    $context_u['lp']['altitude']  = getAltitude($loc_longitude, $loc_latitude);
    $context_u['lp']['latitude']  = $loc_latitude;
    $context_u['lp']['longitude'] = $loc_longitude;

    $context_u['lp']['qc']['あ']['query'] = "寺";
    $context_u['lp']['qc']['あ']['gc'] = "0424001";
    $context_u['lp']['qc']['か']['query'] = "神社";
    $context_u['lp']['qc']['か']['gc'] = "0424002";
    $context_u['lp']['qc']['さ']['query'] = "駅";
    $context_u['lp']['qc']['さ']['gc'] = "0306006";
    $context_u['lp']['qc']['た']['query'] = "コンビニ";
    $context_u['lp']['qc']['な']['query'] = "ラーメン";
    $context_u['lp']['qc']['わ']['query'] = "なにも探さない";

    $replyMessage = "この場所の標高は" . $context_u['lp']['altitude'] . "m". PHP_EOL . "ここで何か探してるの？" . PHP_EOL;
    $replyMessage .= "キーワードでも探せる…";

    foreach ($context_u['lp']['qc'] as $key=>$value) {
	$replyMessage .= $key . " : " . $value['query'] . PHP_EOL;
    }

    return ($replyMessage);
};

//
// location search responder
//
//	input:	Search request character
//	output:	Search result (text base or location message)
//
$locationSearch = function($receivedMessage, $i, $matched, &$context_s, &$context_u) {


    if (array_key_exists('lc' , $context_u['lp'])) {
    	//
    	// 検索結果を返答済みの状態
    	//

	// "いきしちにひみ"に対応してbuttonResponseを生成する
	//
	if (preg_match("/^(い|き|し|ち|に|ひ|み)$/", $receivedMessage, $result) != FALSE) {
	    $keys = array("い", "き", "し", "ち", "に", "ひ", "み");

	    $ofs = array_search($result[1], $keys);
	    $loc_item = $context_u['lp']['lc'][$ofs + $context_u['lp']['ptr_lc']];

	    $text = mb_strimwidth($loc_item['address'], 0, 30, "...", "UTF-8") . PHP_EOL;

	    $dist = measureDistance($context_u['lp']['longitude'], $context_u['lp']['latitude'], $loc_item['longitude'], $loc_item['latitude']);
	    if ($dist['dir_val'] != null) {
		$text .= $dist['dir_val'] . sprintf("%5.2f", $dist['dist']) . "km";

		$alt = getAltitude($loc_item['longitude'], $loc_item['latitude']);
		$text .= "  Δ=" . sprintf("%+4.1f",(floatval($alt) - floatval($context_u['lp']['altitude']))) . "m" . PHP_EOL;
	    }

	    $actions[0] = new PostbackTemplateActionBuilder("地図（ファイル）", "map," . $receivedMessage);
	    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
	    $mapUrl1 = "https://map.yahooapis.jp/course/V1/routeMap?appid=" . $app_id . "&route=" . $context_u['lp']['latitude'] . "," . $context_u['lp']['longitude'] . "," . $loc_item['latitude'] . "," . $loc_item['longitude'] . "&width=400&height=600";
	    $actions[1] = new UriTemplateActionBuilder("経路地図 (download)", $mapUrl1);
	    $mapUrl2 = "https://www.google.com/maps/dir/" . $context_u['lp']['latitude'] . "," . $context_u['lp']['longitude'] . "/" . $loc_item['latitude'] . "," . $loc_item['longitude'] . "/";
	    $actions[2] = new UriTemplateActionBuilder("経路確認（google Map）", $mapUrl2);
	    $buttonBuilder = new ButtonTemplateBuilder($loc_item['name'], $text, null, $actions);
	    $replyBuilder = new TemplateMessageBuilder("ButtonTemplate", $buttonBuilder);
	    syslog(LOG_INFO, print_r($replyBuilder, true));

	    return($replyBuilder);
	}

//	if ($receivedMessage == "次" || $receivedMessage == "つぎ") {
//	    もう一回 createResponceBuilders_inScope_xxを呼ぶ
//	}
//	あかさたなだけは受け付ける？
	return null;
    }
    else if (array_key_exists('qc', $context_u['lp'])) {
    	//
    	// 検索対象候補が表示されている状態
    	//

	$context_u['current_apl'] = "loc_processor";

	if ($receivedMessage == "わ") return "そう。";		// なにも探さない　を選択

	$app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
	$app_url = "https://map.yahooapis.jp/search/local/V1/localSearch";

	$query_param = array( "lat" => $context_u['lp']['latitude'],
		    	  "lon" => $context_u['lp']['longitude'],
			  "dist" => 3,
			  "sort" => "geo",
			  "output" => "json");


	if (!array_key_exists($receivedMessage, $context_u['lp']['qc'])) {
	    $query_param['query'] = $target = $receivedMessage;
	}
	else if (array_key_exists('gc', $context_u['lp']['qc'][$receivedMessage])) {
	    $query_param['gc'] = $context_u['lp']['qc'][$receivedMessage]['gc'];
	    $target = $context_u['lp']['qc'][$receivedMessage]['query'];
	}
	else {
	    $query_param['query'] = $target = $context_u['lp']['qc'][$receivedMessage]['query'];
	}

	$app_param = $app_url . "?" . http_build_query($query_param);

	echo $app_param . "<br>". PHP_EOL;

	$ch = curl_init($app_param);

	curl_setopt_array($ch, array(
	        CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => "Yahoo AppID: $app_id"));
	$result = curl_exec($ch);
	curl_close($ch);

//	unset($context_u['lp']['qc']);

	return (createResponceBuilders($target, json_decode($result, true), $context_s, $context_u));
    }

    return null;

};

//
//  Construct Search result message (or builder objects)
//
//	input:	query result set
//	output:	response text (or message builder objects)
//	* this function split the rsult set fit to the respond format
//
function createResponceBuilders($target, $queryResult, &$context_s, &$context_u) {

    unset($context_u['lp']['lc']);
    unset($context_u['lp']['ptr_lc']);

    if ($queryResult['ResultInfo']['Count'] == 0) {
	return ("この辺には" . $target . "はない");
    }

    for ($i = 0; ; $i++) {
	if (!array_key_exists($i, $queryResult['Feature'])) {
	    $context_u['lp']['num_lc'] = $i;
	    break;
	}

	$loc_item = array();
	$loc_item['name']    = $queryResult['Feature'][$i]['Name'];
	$loc_item['address'] = $queryResult['Feature'][$i]['Property']['Address'];

	$coordinates = str_getcsv($queryResult['Feature'][$i]['Geometry']['Coordinates']);
	$loc_item['longitude'] = $coordinates[0];
	$loc_item['latitude']  = $coordinates[1];

	$context_u['lp']['lc'][$i] = $loc_item;
    }

    return(createResponceBuilders_inScope_1($context_u));
}

//
//  Create Search result message text
//
//	input:	search result (in a user context)
//	output:	message text
//
function createResponceBuilders_inScope_1(&$context_u) {

    $keys = array('い', 'き', 'し', 'ち', 'に', 'ひ', 'み');

    $replyMessage = "";
    $context_u['lp']['ptr_lc'] = isset($context_u['lp']['ptr_lc']) ? $context_u['lp']['ptr_lc'] + 7 : 0;

    for ($i = 0; $i < 7; $i++) {
	if (!array_key_exists($context_u['lp']['ptr_lc'] + $i, $context_u['lp']['lc'])) {
	    $replyMessage .= "もうない";
	    break;
	}

	$loc_item = $context_u['lp']['lc'][$context_u['lp']['ptr_lc'] + $i];
	$replyMessage .= $keys[$i] . " : " . $loc_item['name'] . PHP_EOL;
	$replyMessage .= "      " . mb_strimwidth($loc_item['address'], 0, 23, "&", "UTF-8") . PHP_EOL;

	$dist = measureDistance($context_u['lp']['longitude'], $context_u['lp']['latitude'], $loc_item['longitude'], $loc_item['latitude']);
        if ($dist['dir_val'] != null) {
	    $replyMessage .= "     " . $dist['dir_val'] . sprintf("%5.2f", $dist['dist']) . "km";

	    $alt = getAltitude($loc_item['longitude'], $loc_item['latitude']);
	    $replyMessage .= "  Δ=" . sprintf("%+4.1f",(floatval($alt) - floatval($context_u['lp']['altitude']))) . "m" . PHP_EOL;
        }
    }

    return ($replyMessage);
}

//
//  Create Search result message builder objects
//
//	input:	search result (in a user context)
//	output:	message builder objects
//
function createResponceBuilders_inScope_2(&$context_u) {

    $replyBuilder = new MultiMessageBuilder();
    $context_u['lp']['ptr_lc'] = isset($context_u['lp']['ptr_lc']) ? $context_u['lp']['ptr_lc'] + 5 : 0;

    for ($i = 0; $i < 5; $i++) {
	if (!array_key_exists($context_u['lp']['ptr_lc'] + $i, $context_u['lp']['lc'])) {
	    $replyBuilder->add(new TextMessageBuilder("もうない"));
	    break;
	}

	$loc_item = $context_u['lp']['lc'][$context_u['lp']['ptr_lc'] + $i];
	$address = mb_strimwidth($loc_item['address'], 0, 23, "&", "UTF-8");

	$dist = measureDistance($context_u['lp']['longitude'], $context_u['lp']['latitude'], $loc_item['longitude'], $loc_item['latitude']);
        if ($dist['dir_val'] != null) {
	    $address .= PHP_EOL . $dist['dir_val'] . "に" . sprintf("%5.2f", $dist['dist']) . "km";
	    $address .= "  高低差:" . (floatval(getAltitude($loc_item['longitude'], $loc_item['latitude'])) - floatval($context_u['lp']['altitude'])) . "m";
        }
	$replyBuilder->add(new LocationMessageBuilder($loc_item['name'], $address, $loc_item['latitude'], $loc_item['longitude']));
    }

    return ($replyBuilder);
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

function Postback_callback($category, $param, &$context_s, &$context_u) {

    if ($category != "map") {
	syslog(LOG_ERR, "Illigal postback category.");
	return null;
    }

    $keys = array("い", "き", "し", "ち", "に", "ひ", "み");
    $ofs = array_search($param, $keys);

    if ($ofs == FALSE) {
	syslog(LOG_ERR, "Illegal postback parameter.");
	return null;
    }
    	
    $loc_item = $context_u['lp']['lc'][$context_u['lp']['ptr_lc'] + $ofs];
    $address = $loc_item['address'];
    return(new LocationMessageBuilder($loc_item['name'], $loc_item['address'], $loc_item['latitude'], $loc_item['longitude']));
}

?>
