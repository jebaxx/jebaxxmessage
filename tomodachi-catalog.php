<html lang="ja">
<head>
<meta charset="UTF-8">

<title>Tomodachi catalog paage</title>

<style type="text/css">
table {
	border-collapse:collapse;
	border-spacing:0;
	padding:0;
	margin:10;
}
table tr {
	border:1px solid #9b9b9b;
	padding:10px;
//	padding:10px 15px 10px 15px;
}
table th , table td {
	font-size: small;
	padding:8px 15px 8px 15px;
}
.comment {
	font-size: small;
	color: #894d88;
}
div.frame {
//	width:580px;
	margin:auto;
}
div.head-menu {
	height:130;
	background-color:#cceec0;
	border: 2px solid #9b9b9b;
	padding: 0px 20px 0px 20px;
	position: relative;
}
div.main-title {
	margin-left: 30px;
}
div.main-page {
	background-color:#eef2ee;
	margin-top: 3px;
	padding-top: 25;
	padding-left: 20px;
	border: 2px solid #9b9b9b;
}
</style>

<?php

require_once(__DIR__."/vendor/autoload.php");
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot;
use \LINE\LINEBot\Response;

require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
use google\appengine\api\cloud_storage\CloudStorageTools;

$gs_prefix = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/";
$gs_context = $gs_prefix . "context_*.pac";
$gs_tomo_csv = $gs_prefix . "tomodachi_profile.csv";

function get_tomodachi_profile() {

    global $gs_context, $gs_tomo_csv;

    include(__DIR__."/accesstoken.php");

    // create HTTPClient instance
    $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(ACCESS_TOKEN);
    $Bot = new \LINE\LINEBot($httpClient, ['channelSecret' => SECRET_TOKEN]);

    $profile_content = "";

    foreach (glob($gs_context) as $filename) {
	if (preg_match("/context_(.*)\.pac/", $filename ,$result) == FALSE) {
	    syslog(LOG_WARNING, "file name mismatch: ".$filename);
	    continue;
	}

	if ($result[1] == "s") continue;

	$response = $Bot->getProfile($result[1]);
	syslog(LOG_INFO, "RAW profile: ".print_r($response, true));
	if ($response->isSucceeded()) {
	    $profile = $response->getJSONDecodedBody();

	    $profile_content .= '"'.$result[1]                . '", ';
	    $profile_content .= '"'.$profile['displayName']   . '", ';
	    $profile_content .= '"'.$profile['pictureUrl']    . '", ';
	    if (isset($profile['statusMessage'])) {
		$profile_content .= '"'.$profile['statusMessage'] . '"'  .PHP_EOL;
	    }
	    else {
	    	$profile_content .= PHP_EOL;
	    }
	}
    }

    file_put_contents($gs_tomo_csv, $profile_content);
}

if ((isset($_POST['action'])) && ($_POST['action'] == "再取得")) get_tomodachi_profile();

?>

</head>
<body>
<div class="frame">
  <div class="head-menu">
    <div class="main-title">
      <h1>友だちカタログ</h1>
      <p style="position:absolute; bottom:0">by ぴょん太<span style="padding-left:40px;"><a href="index53.html">メインページへ</a></span></p>
    </div>
  </div>

  <div class="main-page">
  <table>
  <tr>
    <th width=270>ID</th>
    <th width=120 style="text-align:left">displayName</th>
    <th width=130>picture</th>
    <th width=200 style="text-align:left">statusMessage</th>
    <th width=120 style="text-align:left">Modified time</th>
  </tr>
  <?php
    if (($r_hndl = fopen($gs_tomo_csv, "r")) == FALSE) {
    	get_tomodachi_profile();

	if (($r_hndl = fopen($gs_tomo_csv, "r")) == FALSE)
	    syslog(LOG_ERR, "tomodachi file cannot open");
    }

    while (1) {
	if (($profile_line = fgetcsv($r_hndl)) == FALSE) break;

	echo "  <tr>".PHP_EOL;
	echo "    <td>".$profile_line[0]."</td>".PHP_EOL;
	echo "    <td>".$profile_line[1]."</td>".PHP_EOL;
	echo "    <td><img src=".$profile_line[2]." width=120></td>".PHP_EOL;
	echo "    <td>".$profile_line[3]."</td>".PHP_EOL;

	$file_stat = stat($gs_prefix . "context_" . $profile_line[0] . ".pac");
	$mtime = new DateTime();
	$mtime->setTimestamp($file_stat['mtime']);
	echo "    <td>".$mtime->format('Y/m/d H:i')."</td>".PHP_EOL;
	echo "  </tr>".PHP_EOL;
    }

    fclose($r_hndl);

  ?>
  </table>

  <form action="tomodachi-catalog.php" method="post">
    <input type="submit" name="action" value="再取得" style="margin-left:10px;">
  </form>

</body>
</html>

