<html lang="ja">
<head>
<meta charset="UTF-8">
<style type="text/css">

div.frame {
	width:860px;
	margin:auto;
}

div.head-menu {
	height:100;
	background-color:#cceec0;
	padding: 0px 20px 0px 20px;
	border: 2px solid #9b9b9b;
	position: relative;
}

div.main-page {
	background-color:#eef2ee;
	padding: 5px 20px 5px 20px;
	margin: 3px 0 0 0;
	border: 2px solid #9b9b9b;
}

div.menu-side {
	float:right;
	margin-top: 30px;
}

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
	padding:8px 15px 8px 15px;
}
span.note {
	color: #894d88;
	margin-left: 6px;
	font-size: small;
}
</style>

<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") && ($_POST["msg_to_push"]) {
    require_once("pushMessage.php");
	$_send_to = $_POST["send_to"];
	$_msg_to_push = $_POST["msg_to_push"];
	if ($_send_to == "BROADCAST")
	    BroadcastMessage($_msg_to_push);
	else
	    PushMessage($_send_to, $_msg_to_push);

 ?>

<title>test site pagae</title>
</head>

<body>
<div class="frame">
<div class="head-menu">
<div class="menu-side">
<a href="https://line.me/R/ti/p/%40ttu0660o"><img height="36" border="0" alt="友だち追加" src="https://scdn.line-apps.com/n/line_add_friends/btn/ja.png"></a>
</div>
<h1>実験ページ by ぴょん太</h1>
<span style="padding-left:40px;position: absolute; bottom:0;"><a href="index53.html">メインページ</a></span>
</div>
<div class="main-page">
<hr>
<h3>ロケーションメッセージの送信テスト</h3>
このページは実験用です。触るといろいろ問題があるので適当にいじるのはやめましょう！

<!-- ////////////////////////////////////////////////// -->
<h3>テスト用：ロケーションメッセージ送信</h3>
<form name = "location_messagetest" action="messageHook.php" method="post">
北緯：<input size = 60 type='text' name='latitude' value='35.61506'><br>
東経：<input size = 60 type='text' name='longitude' value='139.37059'>
<input type='submit' name='action' value='送信'>
</form>
<hr>
<form name = "text_messagetest" action="messageHook.php" method="post">
疑似メッセージを送信：
<input size = 60 type='text' name='queryMessage'>
<input type='submit' name='action' value='送信'>
</form>

<hr>
<!-- ///////////////////////////////////////////////// -->
<h3>Push メッセージ送信</h3>
<form name = "Push_message" action="test-site.php" method="post">
宛先名：<input size=60 type='text' name='send_to'>
宛先名：<select size=60 name='send_to'>
  <option value = "BROADCAST" 'selected'>BROADCAST</option>
<?php
    require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
    use google\appengine\api\cloud_storage\CloudStorageTools;
    $gs_prefix = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/";
    $gs_tomo_csv = $gs_prefix . "tomodachi_profile.csv";

    if (($r_hndl = fopen($gs_tomo_csv, "r")) == FALSE) {
	syslog(LOG_ERR, "tomodachi file cannot open");
	return FALSE;
    }

    while (1) {
	if (($profile_line = fgetcsv($r_hndl)) == FALSE) break;
	    echo "<option value = " . $profile_line[0] . " >" . $profile_line[0] . "</option>";
    }

    fclose($r_hndl);
 ?>
  </select>
送信文：<input size=60 type='text' name='msg_to_push'>
<input type='submit' name='action' value='送信'>
</form>

</div>

</body>
</html>
