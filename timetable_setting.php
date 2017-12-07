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
<title>index pagae</title>

<script type = "text/javascript">

    function selectline(_station_name, _route_name) {
	document.station_list.elements.station_name.value = _station_name;
	document.station_list.elements.route_name.value   = _route_name;

	document.station_list.elements.action[0].disabled = "";
	document.station_list.elements.action[1].disabled = "";
    }

    function check_delete() {
    	if (_post_action == "delete") {
	    if (window.confirm("エントリーを削除しますか？")) {
	    	return true;
	    }
	    else {
		return false;
	    }
	}
    	else if ((_post_action == "save" ) || (_post_action == "create")) {
	    if (window.confirm("エントリーを登録しますか？")) {
	    	return true;
	    }
	    else {
		return false;
	    }
	}
    }
    
    function valid_chk() {
	if ((document.edit_area.elements.station_name.value == "") || (document.edit_area.elements.route_name.value == "") ) {
	    document.edit_area.elements.action[0].disabled = true;
	}
	else {
	    document.edit_area.elements.action[0].disabled = "";
	}
    }

</script>

<?php
/////////////////////////////////////////////////////////////////////////////
////									/////
    require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
    use google\appengine\api\cloud_storage\CloudStorageTools;

    //
    //  html -> csv CONVERTER
    //
    function convert_html_to_csv($html_content) {

	$csv_content = "";
	$dom = new DOMDocument;
	@$dom->loadHTML($html_content);
	$xpath = new DOMXPath($dom);

	try {
	    $hh_node_list = $xpath->query("//table[@class='tblDiaDetail']//tr[substring(@id,1,3)='hh_']/td[@class='hour']");
	    if ($hh_node_list->length == 0) throw new UnexpectedValueException("cannot detect expected nodes");

	    foreach ($hh_node_list as $h_node) {
		$mm_node_list = $xpath->query("..//li[@class='timeNumb']//dt", $h_node);
		if ($mm_node_list->length == 0) throw new UnexpectedValueException("cannot detect expected 'timeNumb' class nodes");
		$h_val = $h_node->nodeValue;

		foreach ($mm_node_list as $m_node) {
		    $m_val  = $m_node->nodeValue;
		    $o_vals = "";
		    ////////////////////////////////////////////////////
		    // apply span node (class='mark')
		    $subNode_list = $xpath->query("./*", $m_node);
		    for ($i = 0; $i < $subNode_list->length; $i++) {
		    	$o_val = $subNode_list->item($i)->nodeValue;
		    	preg_match("/^(.*)".$o_val."(.*)$/", $m_val, $result);
		    	$m_val = $result[1].$result[2];
		    	$o_vals .= $o_val;
		    }
		    ////////////////////////////////////////////////////
		    $o_list = $xpath->query("../dd", $m_node);
		    foreach ($o_list as $o_node) $o_vals .= $o_node->nodeValue;
		    $csv_content .= $h_val.",".$m_val.",".($o_vals!="" ? '"'.$o_vals.'"':"").PHP_EOL;
		}
	    }
	}
	catch (UnexpectedValueException $e) {
	    echo "Exception";
    	    echo $e->getMessage(). "<br>".PHP_EOL;
	}

	return $csv_content;
    }

    //
    //  $packedContext = array( 'user_context'=> array( 'userId' => array('station'=>?, 'time'=>?, 'route'=>?), ... ),
    //				'day_of_the_week' => ?,
    //				'expiration' => ?,
    //				'expiration_holiday' => ? )
    //
    // $gs_context = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_context.pac";
    // $packedContext = unserialize(file_get_contents($gs_context));

    //
    // $packedStation = array(_name => array('nickname'=> ?, 
    //					     'primary_route'=> ?,
    //					     'routes'=> array(_name=>array(_url_holiday, _url_weekday,,,,_url_sataday)
    //
    $gs_station = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() . "/_station.pac";
    $packedStation = unserialize(file_get_contents($gs_station));

    //
    // Take in POST data 
    //
    if (isset($_POST['action'])) {
	if ($_POST['action'] == '新規作成' || $_POST['action'] == "保存") {
	    //
	    //  POSTデータを取り込む。
	    $station_name = htmlspecialchars(trim($_POST['station_name']));
	    $route_name   = htmlspecialchars(trim($_POST['route_name']));
	    if (isset($packedStation[$station_name])) $stationInfo = $packedStation[$station_name];
	    $stationInfo['nickname'] = htmlspecialchars(trim($_POST['nickname']));
	    $stationInfo['routes'][$route_name][0] = trim($_POST['url_holiday']);
	    $stationInfo['routes'][$route_name][1] = trim($_POST['url_weekday']);
	    $stationInfo['routes'][$route_name][6] = trim($_POST['url_saturday']);

	    if ((isset($_POST['primary'])) || !isset($stationInfo['primary_route']))
		$stationInfo['primary_route'] = $route_name;

	    $packedStation[$station_name] = $stationInfo;
	    file_put_contents($gs_station, serialize($packedStation));

	    //
	    //  時刻表情報をcloudstorageに読み込む
	    //
	    $gs_route_prefix = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName()."/timetable";

    	    if ($stationInfo['routes'][$route_name][0] != "") {
		$html_content = file_get_contents($stationInfo['routes'][$route_name][0]);
		$csv_content  = convert_html_to_csv($html_content);

	        $gs_route_name = $gs_route_prefix . "/_".$station_name."-".$route_name."-0.csv";
		file_put_contents($gs_route_name, $csv_content);
	    }

	    if ($stationInfo['routes'][$route_name][1] != "") {
    		$html_content = file_get_contents($stationInfo['routes'][$route_name][1]);
		$csv_content  = convert_html_to_csv($html_content);

	        $gs_route_name = $gs_route_prefix . "/_".$station_name."-".$route_name."-1.csv";
		file_put_contents($gs_route_name, $csv_content);
	    }

	    if ($stationInfo['routes'][$route_name][6] != "") {
    		$html_content = file_get_contents($stationInfo['routes'][$route_name][6]);
		$csv_content  = convert_html_to_csv($html_content);

	        $gs_route_name = $gs_route_prefix . "/_".$station_name."-".$route_name."-6.csv";
		file_put_contents($gs_route_name, $csv_content);
	    }
    	}
    	else if ($_POST['action'] == '削除') {
	    //
	    //  指定された station_name & route_nameのデータを消去する
	    $station_name = trim($_POST['station_name']);
	    $route_name   = trim($_POST['route_name']);
	    $stationInfo  = $packedStation[$station_name];

	    unset($stationInfo['routes'][$route_name]);
	    if (count($stationInfo['routes']) == 0) {
		//
		// if route count become zero by deleting, station info must be deleted.
		unset($packedStation[$station_name]);
	    }
	    else {
		if ($stationInfo['primary_route'] == $route_name) {
		    //
		    // if primary root is deleted, reset new primary root
		    reset($stationInfo['routes']);
		    $stationInfo['primary_route'] = key($stationInfo['routes']);
		}
		$packedStation[$station_name] = $stationInfo;
	    }
	    file_put_contents($gs_station, serialize($packedStation));
    	}
    	else if ($_POST['action'] == '編集') {
	    //
	    //  Do nothing.
    	}
    }
////									/////
/////////////////////////////////////////////////////////////////////////////
?>


</head>
<body>
<div class="frame">
<div class="head-menu">
<div class="menu-side">
<a href="https://line.me/R/ti/p/%40ttu0660o"><img height="36" border="0" alt="友だち追加" src="https://scdn.line-apps.com/n/line_add_friends/btn/ja.png"></a>
</div>
<h1>時刻表メンテナンス by ぴょん太</h1>
<span style="padding-left:40px;position: absolute; bottom:0;"><a href="index53.html">メインページ</a></span>
</div>
<div class="main-page">
<hr>
<h3>登録済みの時刻表</h3>

<form name = "station_list" action="timetable_setting.php" method="post" onsubmit="return check_delete()">
<input type='hidden' name='station_name'>
<input type='hidden' name='route_name'>
<table>
<thead>
<tr>
  <th width=50 style="text-align:left">選択</th>
  <th width=130 style="text-align:left">駅名</th>
  <th width=130 style="text-align:left">正式名</th>
  <th width=100>路線</th>
  <th width=80>優先</th>
  <th width=120>登録済URL</th>
</tr>
</thead>
<?php
/////////////////////////////////////////////////////////////////////////////
////									/////
if (is_array($packedStation)) {
    $_id=1;
    foreach ($packedStation as $station_name => $stationInfo) {
	foreach ($stationInfo['routes'] as $route_name => $routeInfo) {
	    echo "<tr>".PHP_EOL;
	    echo "<td><input type='radio' name='select' id='st_".$_id."' onclick=\"selectline('".$station_name."','".$route_name."')\"></td>".PHP_EOL;			// RADIO
	    echo "<td><label for='st_".$_id."'>".$station_name."</label></td>".PHP_EOL;				// 駅名
	    echo "<td><label for='st_".$_id."'>".$stationInfo['nickname']."</label></td>".PHP_EOL;		// 駅呼称
	    echo "<td style='text-align:center;'><label for='st_".$_id."'>".$route_name."</label></td>".PHP_EOL;	// 路線名
	    echo "<td style='text-align:center;'><label for='st_".$_id."'>";
	    if ($route_name == $stationInfo['primary_route']) echo 'X';	// 優先
	    echo "</label></td>".PHP_EOL;
	    echo "<td><label for='st_".$_id."'>";
	    if (isset($routeInfo[1]) && $routeInfo[1] != "")  echo "<a href=\"".$routeInfo[1]."\">平日</a> ";
	    if (isset($routeInfo[0]) && $routeInfo[0] != "")  echo "<a href=\"".$routeInfo[0]."\">休日</a> ";
	    if (isset($routeInfo[6]) && $routeInfo[6] != "")  echo "<a href=\"".$routeInfo[6]."\">土曜</a> ";
	    echo "</label></td>".PHP_EOL;
	    echo "</tr>".PHP_EOL;
	    $_id++;
	}
    }
}
////									/////
/////////////////////////////////////////////////////////////////////////////
?>
</table>
<p style="text-align:center;">
<?php
/////////////////////////////////////////////////////////////////////////////
////									/////
    if (count($packedStation)) {
	echo "<td><input type='submit' name='action' value='編集' style='width:75px;' onclick=\"_post_action='edit'\" disabled>".PHP_EOL;
	echo "<td><input type='submit' name='action' value='削除' style='width:75px;' onclick=\"_post_action='delete'\" disabled>".PHP_EOL;
    }
////									/////
/////////////////////////////////////////////////////////////////////////////
?>
</p></form>
<hr>

<h3>時刻表編集<span class='note'>・・・新しく時刻表を登録するときはこちらから</span></h3>

<form name = "edit_area" action="timetable_setting.php" method="post" onsubmit="return check_delete()">
<table>
<?php
/////////////////////////////////////////////////////////////////////////////
////									/////
    if (isset($_POST['action']) && ($_POST['action'] == '編集')) {
	$station_name = trim($_POST['station_name']);
	$route_name   = trim($_POST['route_name']);
	$stationInfo = $packedStation[$station_name];
	$nickname    = $stationInfo['nickname'];
	$primary_root = $stationInfo['primary_route'];
	$url_holiday = $stationInfo['routes'][$route_name][0];
	$url_weekday = $stationInfo['routes'][$route_name][1];
	$url_saturday= $stationInfo['routes'][$route_name][6];
	$checked     = $route_name == $primary_root ? "checked='checked'" : "";
	echo "<tr><td width=70>駅名</td><td width=630><input type='text' name='station_name' value='".$station_name."' onKeyup=\"valid_chk()\" ><span class='note'>LINEから時刻表を呼び出す時に分かりやすい名前をつける</span></td></tr>".PHP_EOL;
	echo "<tr><td>正式名</td><td><input type='text' name='nickname' value='".$nickname."'><span class='note'>これは今は使っていないが念のため設定する</span></td></tr>".PHP_EOL;
	echo "<tr><td>路線</td><td><input type='text' name='route_name' value='".$route_name." 'onKeyup=\"valid_chk()\"><span class='note'>上り/下り、○○方面、など。これも利用時に分かりやす名前であればよい。</span></td></tr>".PHP_EOL;
	echo "<tr><td>優先</td><td><label><input type='checkbox' name='primary' ".$checked."><span class='note'>利用時は路線名を省略可 省略時に採用する優先路線を各駅に一つ指定</span></label></td></tr>".PHP_EOL;
	echo "<tr><td>URL（平日）</td><td><span class='note'>YAホ～!の時刻表ページを参考にさせていただくのでここにURLをペースト</span><BR><input type='url' name='url_weekday' value='".$url_weekday."' style=\"width:600px;\"></td></tr>".PHP_EOL;
	echo "<tr><td>URL（休日）</td><td><input type='url' name='url_holiday' value='".$url_holiday."' style=\"width:600px;\"></td></tr>".PHP_EOL;
	echo "<tr><td>URL（土曜）</td><td><input type='url' name='url_saturday' value='".$url_saturday."' style=\"width:600px;\"></td></tr>".PHP_EOL;
	echo "</table>".PHP_EOL;
	echo "<input type='submit' name='action' value='保存' onclick=\"_post_action='save'\">".PHP_EOL;
    }
    else {
	echo "<tr><td width=70>駅名</td><td width=630><input type='text' name='station_name' onKeyup=\"valid_chk()\"><span class='note'>LINEから時刻表を呼び出す時に分かりやすい名前をつける</span></td></tr>".PHP_EOL;
	echo "<tr><td>正式名</td><td><input type='text' name='nickname'><span class='note'>これは今は使っていないが念のため設定する</span></td></tr>".PHP_EOL;
	echo "<tr><td>路線</td><td><input type='text' name='route_name' onKeyup=\"valid_chk()\"><span class='note'>上り/下り、○○方面、など。これも利用時に分かりやす名前であればよい。</span></td></tr>".PHP_EOL;
	echo "<tr><td>優先</td><td><label><input type='checkbox' name='primary' value='primary'><span class='note'>利用時は路線名を省略可 省略時に採用する優先路線を各駅に一つ指定</span></label></td></tr>".PHP_EOL;
	echo "<tr><td>URL（平日）</td><td><span class='note'>YAホ～!の時刻表ページを参考にさせていただくのでここにURLをペースト</span><BR><input type='url' name='url_weekday' style=\"width:600px;\"></td></tr>".PHP_EOL;
	echo "<tr><td>URL（休日）</td><td><input type='url' name='url_holiday' style=\"width:600px;\"></td></tr>".PHP_EOL;
	echo "<tr><td>URL（土曜）</td><td><input type='url' name='url_saturday' style=\"width:600px;\"></td></tr>".PHP_EOL;
	echo "</table>".PHP_EOL;
	echo "<p style='text-align:center'>";
	echo "<input type='submit' name='action' value='新規作成' onclick=\"_post_action='create'\" disabled>".PHP_EOL;
    }
////									/////
/////////////////////////////////////////////////////////////////////////////
?>
<input type="submit" name="action" value="キャンセル"></p>
</form>

</div>
<div class="main-page">

<!-- ////////////////////////////////////////////////// -->
<hr>ここから下は触らないで！<br>
<h3>テスト用：メッセージ送受信内容の確認</h3>
<form name = "test_request" action="messageHook.php" method="post">
送信メッセージ
<input size = 60 type='text' name='queryMessage'>
<input type='submit' name='action' value='送信'>
</form>

<hr>
<h3>テスト用：HTML変換機能の確認</h3>
<a href="urlConvert.php">data convert</a>
<form name = "convert_test" action="upload_url.php" method="post">
対象ページ(URL)
<input size=80 type='url' name='target_url'>
<input type='submit' name='upload' value='upload'>
<input type='checkbox' name='mode'>
</form>

<hr>ここから下は気にしないで！<br>

<?php
echo "POST data<br>\n";
var_dump($_POST);
echo "<br>\npackedStation<br>\n"; var_dump($packedStation);
?>

</div>
</div>

</body>
</html>
