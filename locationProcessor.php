<?php

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
// Special Application location message processor
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
    $context_u['lp']['qc']['わ']['query'] = "これ以外";

    $replyMessage = "この場所の標高は" . $context_u['lp']['altitude'] . "m". PHP_EOL . "ここで何か探してるの？" . PHP_EOL;

    foreach ($context_u['lp']['qc'] as $key=>$value) {
	$replyMessage .= $key . " : " . $value['query'] . PHP_EOL;
    }

    return ($replyMessage);
};

$locationSearch = function($receivedMessage, $i, $matched, &$context_s, &$context_u) {


    if (!array_key_exists('qc', $context_u['lp']) || !array_key_exists($receivedMessage, $context_u['lp']['qc']))
	return null;

    $context_u['current_apl'] = "loc_processor";

    $app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
    $app_url = "https://map.yahooapis.jp/search/local/V1/localSearch";

    $query_param = array( "lat" => $context_u['lp']['latitude'],
		    	  "lon" => $context_u['lp']['longitude'],
			  "dist" => 3,
			  "sort" => "geo",
			  "output" => "json");

    if (array_key_exists('gc', $context_u['lp']['qc'][$receivedMessage]))
	$query_param['gc'] = $context_u['lp']['qc'][$receivedMessage]['gc'];
    else
	$query_param['query'] = $context_u['lp']['qc'][$receivedMessage]['query'];

    $app_param = $app_url . "?" . http_build_query($query_param);

    echo $app_param . "<br>". PHP_EOL;

    $ch = curl_init($app_param);

    curl_setopt_array($ch, array(
	        CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => "Yahoo AppID: $app_id"));
    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result, true);

//    var_dump($result);
//    echo "<br>".PHP_EOL;

    if ($result['ResultInfo']['Count'] == 0) {
	return ("この辺にはない");
    }

    //unset($context_u['lp']['qc']);
    unset($context_u['lp']['lc']);
    $keys = array('あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ');

    for ($i = 0; $i < 10; $i++) {
	if (!array_key_exists($i, $result['Feature'])) break;

	$loc_item = array();
	$loc_item['name']    = $result['Feature'][$i]['Name'];
	$loc_item['address'] = $result['Feature'][$i]['Property']['Address'];

	$coordinates = str_getcsv($result['Feature'][$i]['Geometry']['Coordinates']);
	$loc_item['longitude'] = $coordinates[0];
	$loc_item['latitude']  = $coordinates[1];

	$context_u['lp']['lc'][$keys[$i]] = $loc_item;

	$replyMessage .= $keys[$i] . " : " . $loc_item['name'] . PHP_EOL;
	$replyMessage .= "      " . mb_strimwidth($loc_item['address'], 0, 23, "...", "UTF-8") . PHP_EOL;

	$dist = measureDistance($context_u['lp']['longitude'], $context_u['lp']['latitude'], $loc_item['longitude'], $loc_item['latitude']);

//	$replyMessage .= "      " . sprintf("dr=%5.2f dx:%4.2f dy:%4.2f", $dist['dir_num'], $dist['dx'], $dist['dy']) . PHP_EOL;
	$replyMessage .= "      " . $dist['dir_val'] . "に" . sprintf("%5.2f", $dist['dist']) . "km" . PHP_EOL;
    }

    return ($replyMessage);
};

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
