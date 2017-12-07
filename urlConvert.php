<html lang="ja">
<head>
<meta charset="UTF-8">
<title>convertion pagae</title>
</head>
<body>

<?php
    require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
    use google\appengine\api\cloud_storage\CloudStorageTools;

    $gs_table_file = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() ."/timetable/_convert_test_target.html";
    $content = file_get_contents($gs_table_file);

    $dom = new DOMDocument;
    @$dom->loadHTML($content);
    $xpath = new DOMXPath($dom);

    try {
    /**** 最初、このような実装にしたが、
	$scopeNode = $xpath->query("//table[@class='tblDiaDetail']")->item(0);
	if ($scopeNode == null) throw new UnexpectedValueException("cannot detect table node 'tblDiaDetail'");

	$hh_list = $xpath->query(".//tr[substring(@id, 1, 3)='hh_']", $scopeNode);

	if ($hh_list->length == 0) throw new UnexpectedValueException("cannot detect time table node 'hh_*'");

	foreach($hh_list as $h_node) {
	    $h_val = $xpath->query("./td[@class='hour']", $h_node)->item(0)->nodeValue;

	    $mm_list = $xpath->query(".//li[@class='timeNumb']", $h_node);

	    if ($mm_list->length == 0) throw new UnexpectedValueException("cannot detect time table node 'timeNumb'");

	    foreach ($mm_list as $m_node) {
	        $m_val = $xpath->query(".//dt", $m_node)->item(0)->nodeValue;
		$o_val = $xpath->query(".//dd", $m_node)->item(0)->nodeValue;

		echo $h_val." , ".$m_val." , ".$o_val."<br>".PHP_EOL;
	    }
	}
    仕様を理解した、以下のように簡単に書けることが分かった******/

	$hh_node_list = $xpath->query("//table[@class='tblDiaDetail']//tr[substring(@id,1,3)='hh_']/td[@class='hour']");
	if ($hh_node_list->length == 0) throw new UnexpectedValueException("cannot detect expected nodes");

	foreach ($hh_node_list as $h_node) {
	    echo $h_node->nodeValue." : ";
	    $mm_node_list = $xpath->query("..//li[@class='timeNumb']//dt", $h_node);
	    if ($mm_node_list->length == 0) throw new UnexpectedValueException("cannot detect expected 'timeNumb' class nodes");

	    foreach ($mm_node_list as $m_node) {
		$m_val  = $m_node->nodeValue;
		$o_vals = "";
	    //////////////////////////////////////////////////////////
	    // 始発駅マーク対策
	        $subNode_list = $xpath->query("./*", $m_node);
	        if ($subNode_list->length != 0) {
	            foreach ($subNode_list as $subNode) {
	        	$o_val = $subNode->nodeValue;
	        	//echo "$$$ ".$o_val." $$$";
	        	preg_match("/^(.*)".$o_val."(.*)/", $m_val,$result);
	        	$m_val = $result[1].$result[2];
	        	//echo "&&& ".$m_val." &&&";
	        	$o_vals .= $o_val;
	            }
	        }
	    //////////////////////////////////////////////////////////
		$o_list = $xpath->query("../dd", $m_node);
		foreach ($o_list as $o_node) $o_vals .= $o_node->nodeValue;
	        echo $m_val." ".$o_vals."  ";
	    }
	    echo "<br>".PHP_EOL;
	    	
	}

    }
    catch (UnexpectedValueException $e) {
	echo "Exception";
    	echo $e->getMessage(). "<br>".PHP_EOL;
    }

?>


</body>
</html>
