<html lang="ja">
<head>
<meta charset="UTF-8">
<title>html contents upload page</title>
</head>
<body>

<?php
    var_dump($_POST);
    require_once("google/appengine/api/cloud_storage/CloudStorageTools.php");
    use google\appengine\api\cloud_storage\CloudStorageTools;

    if (!isset($_POST['target_url'])) {
	echo "No post infomation".PHP_EOL;
	return;
    }

    if (($html_content = file_get_contents($_POST['target_url'])) == FALSE) {
        echo "url read error\n";
        return;
    }

    $gs_file = "gs://" . CloudStorageTools::getDefaultGoogleStorageBucketName() ."/timetable/_convert_test_target.html";

    if (file_put_contents($gs_file, $html_content) == FALSE) {
	echo "file save error\n";
	return;
    }

    echo "<br><h3>Upload complete</h3><br>"
?>

</body>
</html>
