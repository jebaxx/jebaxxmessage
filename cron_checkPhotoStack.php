<?php

require_once(__DIR__."/vendor/autoload.php");
require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Response;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\Constant\HTTPHeader;

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

$gs_file = "gs://jebaxxconnector.appspot.com/photo_queue/";

$result = glob($gs_file . "*.005");

foreach $result as $filename {

    if (preg_match("@([^/_]+)_[0-9]+\.005@", $filename, $matched) == FALSE) {
	syslog(LOG_WARNING, "unexpected file name found");
	continue;
    }

    PushMessage($matched[1], "送信に失敗したファイルが見つかったよ...". $filename);
}


function PushMessage($Line_id, $message) {

    // create HTTPClient instance
    $httpClient = new CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    $pushMessageBuilder = new TextMessageBuilder($message);
    $response = $Bot->pushMessage($lineId, $pushMessageBuilder);

    if ($response->getHTTPStatus() != 200) {
	syslog(LOG_ERR, "Failed to sending a push message and status code is ". $response->getHTTPStatus());
    }
}

?>
