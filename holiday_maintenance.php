<html lang="ja">
<head>
<meta charset="UTF-8">

<title>holiday info maintenance</title>

<style type="text/css">
.comment {
	font-size: small;
	color: #894d88;
}
div.frame {
	width:580px;
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

<script type = "text/javascript">

function check_save_action() {
    if (_post_action == 'save') {
	if (window.confirm("編集結果を保存しますか？")) {
	    return true;
	}
	else {
	    return false;
	}
    }
    else {
	if (window.confirm("祝祭日情報を外部から取り込み、上書きしますか？")) {
	    return true;
	}
	else {
	    return false;
	}
    }
}
</script>

</head>
<body>
<div class="frame">
  <div class="head-menu">
    <div class="main-title">
      <h1>祝祭日情報の確認・編集</h1>
      <p style="position:absolute; bottom:0">by ぴょん太<span style="padding-left:40px;"><a href="index53.html">メインページへ</a></span></p>
    </div>
  </div>

  <div class="main-page">
    <form name = "holiday_edit" action="holiday_maintenance.php" method="post" onsubmit=" return check_save_action()">
    <textarea name="holiday_text" cols="48" rows="30">
    <?php
    require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
    use google\appengine\api\cloud_storage\CloudStorageTools;

    $gs_holidayinfo = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/holiday_info.csv";

    if (isset($_POST['action']) && ($_POST['action'] == '再取得')) {
    	$holiday_inf_csv = file_get_contents("http://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv");

	if ($holiday_inf_csv != FALSE) {
	    //
	    // エンコーディングをUTF-8に
	    //
	    if (($code_name = mb_detect_encoding($holiday_inf_csv, "SJIS, SJIS-win, UTF-16, UTF-8, JIS, EUC-JP")) != FALSE) {
	    	syslog(LOG_INFO, "detected encoding is ".$code_name);
		$holiday_inf_csv = mb_convert_encoding($holiday_inf_csv, "UTF-8", $code_name);
	    }
	    file_put_contents($gs_holidayinfo, $holiday_inf_csv);
	    syslog(LOG_NOTICE, $gs_holidayinfo." reloaded.");
	}
    }

    if (isset($_POST['action']) && ($_POST['action'] == '保存')) {
	file_put_contents($gs_holidayinfo, $_POST['holiday_text']);
	syslog(LOG_NOTICE, $gs_holidayinfo." saved.");
    }

    if (($r_hndl = fopen($gs_holidayinfo, "r")) == FALSE) {
	echo "祝祭日データがありません。<br>".PHP_EOL;
    }

    while (1) {
    	if (($line = fgets($r_hndl)) == FALSE) break;
    	echo htmlspecialchars($line);
    }

    fclose($r_hndl);
    ?>
    </textarea>
    <p>
    <table>
    <tr>
      <td width=90> <input type=submit name="action" value="再取得" onclick="_post_action='reload'" style="WIDTH:75px; HEIGHT:40px;"> </td>
      <td width=380><p class="comment"> 内閣府のホームページにある暦情報を取り込みます。ここにあるデータは上書きされます。（保存もします）</p></td>
    </tr>
    <tr>
      <td> <input type=submit name="action" value="保存" onclick="_post_action='save'" style="WIDTH:75px; HEIGHT:40;"> </td>
      <td><p class="comment">ここで編集した結果を保存します。ぴょん太は今後これを利用します。 </p></td>
    </td>
    </table>
    </p>
    </form>
  </div>
</div>


</body>
</html>

