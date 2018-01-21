<?php

//
// Special Application location message processor
//
function locationProcessor($loc_longitude, $loc_latitude, &$context_s, &$context_u) {

    $context_u['current_apl'] = "loc_processor";

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

//    var_dump($result);
    $replyMessage = "この場所の標高は" . $result['Feature'][0]['Property']['Altitude'] . "m". PHP_EOL . "ここで何か探してるの？" . PHP_EOL;

    unset($context_u['lp']);
    $context_u['lp']['latitude']  = $loc_latitude;
    $context_u['lp']['longitude'] = $loc_longitude;

    $context_u['lp']['qc']['あ'] = "寺";
    $context_u['lp']['qc']['か'] = "神社";
    $context_u['lp']['qc']['さ'] = "駅";
    $context_u['lp']['qc']['た'] = "コンビニ";
    $context_u['lp']['qc']['な'] = "ラーメン";
    $context_u['lp']['qc']['は'] = "これ以外";

    foreach ($context_u['qc'] as $key=>$name) {
	$replyMessage .= $key . " : " . $name . PHP_EOL;
    }

    return ($replyMessage);
};

$locationSearch = function($receivedMessage, $i, $matched, &$context_s, &$context_u) {

    $context_u['current_apl'] = "loc_processor";

    if (array_key_exists($receivedMessage, $context_u['lp']['qc'])) {

	if (!array_key_exists($receivedMessage, $context_u['lp']['qc'][$receivedMessage])) {
	    return null;
	}

	$app_id = "dj00aiZpPWZITUY0Uk1TZWtqZSZzPWNvbnN1bWVyc2VjcmV0Jng9NjA-";
	$app_url = "https://map.yahooapis.jp/search/local/V1/localSearch";

	$app_param = $app_url . "?" . http_build_query(
				array ( "query" => $context_u['lp']['qc'][$receivedMessage],
					"lat" => $context_u['lp']['latitude'],
		    			"lon" => $context_u['lp']['longitude'],
					"dist" => 3,
					"sort" => "geo",
					"output" => "json"));

	echo $app_param . "<br>". PHP_EOL;

	$ch = curl_init($app_param);

	curl_setopt_array($ch, array(
	        CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => "Yahoo AppID: $app_id"));
	$result = curl_exec($ch);
	curl_close($ch);

	$result = json_decode($result, true);

	var_dump($result);
//	echo "<br>".PHP_EOL;

	unset($context_u['lp']['qc']);
	unset($context_u['lp']['lc']);
	$keys = array('あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ');

	for ($i = 0; $i < 10; $i++) {
	    if (!array_key_exists($result['Feature'][$i])) break;

	    $context_u['lp']['lc'][$keys[$i]]['name']  = $result['Feature'][$i]['Name'];

	    $coordinates = str_getcsv($result['Feature'][$i]['Geometry']['Coordinates']);
	    $context_u['lp']['lc'][$keys[$i]]['longitude'] = coordinates[0];
	    $context_u['lp']['lc'][$keys[$i]]['latitude']  = $coordinates[1];
	    $context_u['lp']['lc'][$keys[$i]]['address'] = $result['Feature'][$i]['Property']['Address'];

	}

	    

	$context_u['lp']['lc']['あ']['name']  = $result['Feature'][0]['Name'];
	$context_u['lp']['lc']['あ']['latitude']  = $result['Feature'][0]['Geometry']['Coordinates'];
	$context_u['lp']['lc']['あ']['longitude'] = 
	$context_u['lp']['lc']['あ']['address'] = $result['Feature'][0]['Property']['Address'];

    }
}

?>
