<?php
require_once(__DIR__."/vendor/autoload.php");

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Response;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

function BroadcastMessage($message) {

    // create HTTPClient instance
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);
    $pushMessageBuilder = new TextMessageBuilder($_POST['message']);

    $gs_prefix = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/";
    $gs_tomo_csv = $gs_prefix . "tomodachi_profile.csv";

    if (($r_hndl = fopen($gs_tomo_csv, "r")) == FALSE) {
	syslog(LOG_ERR, "tomodachi file cannot open");
	return FALSE;
    }

    while (1) {
	if (($profile_line = fgetcsv($r_hndl)) == FALSE) break;
	PushMessage($profile_line[0], $message);
    }

    fclose($r_hndl);
}

function PushMessage($Line_id, $message) {

    // create HTTPClient instance
    require_once(__DIR__."/accesstoken.php");
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    $pushMessageBuilder = new TextMessageBuilder($message);
    $response = $Bot->pushMessage($Line_id, $pushMessageBuilder);

    if ($response->getHTTPStatus() != 200) {
	syslog(LOG_ERR, "failed to sending a push message and status code is ". $response->getHTTPStatus());
    }
}

?>
