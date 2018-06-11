<?php
require_once(__DIR__."/vendor/autoload.php");

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Response;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

require_once(__DIR__."/accesstoken.php");

$gs_prefix = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/";
$gs_tomo_csv = $gs_prefix . "tomodachi_profile.csv";


if (!array_key_exists('Line_id', $_POST) || (!array_key_exsists('message', $_POST))) {
    syslog(LOG_ERR, "Illegal message arrived to push message request point.");
}
else {

    include(__DIR__."/accesstoken.php");

    // create HTTPClient instance
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    //
    // BROADCASTに対応するが、その際の対象者は、tomodachi_profileに登録済みのIDになる
    //
    if ($_POST['Line_id'] == 'BROADCAST') {

    	if (($r_hndl = fopen($gs_tomo_csv, "r")) == FALSE) {
	    syslog(LOG_ERR, "tomodachi file cannot open");
	    return;
	}

	$pushMessageBuilder = new TextMessageBuilder($_POST['message']);

	while (1) {
	    if (($profile_line = fgetcsv($r_hndl)) == FALSE) break;

	    $response = $Bot->pushMessage($profile_line[0], $pushMessageBuilder);
	    if ($response->getHTTPStatus() != 200) {
		syslog(LOG_ERR, "failed to sending a push message and status code is ". $response->getHTTPStatus() . " and id = ".$profile_line[0]);
	    }
	}
	fclose($r_hndl);

    }
    else {
	$pushMessageBuilder = new TextMessageBuilder($_POST['message']);
	$response = $Bot->pushMessage($_POST['lineId'], $pushMessageBuilder);

	if ($response->getHTTPStatus() != 200) {
	    syslog(LOG_ERR, "failed to sending a push message and status code is ". $response->getHTTPStatus());
	}
    }
}


function PushMessage($Line_id, $message) {

    // create HTTPClient instance
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    $pushMessageBuilder = new TextMessageBuilder($message);
    $response = $Bot->pushMessage($lineId, $pushMessageBuilder);

    if ($response->getHTTPStatus() != 200) {
	syslog(LOG_ERR, "failed to sending a push message and status code = ". $response->getHTTPStatus());
    }
}


?>
