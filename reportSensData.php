<?php

$reportSensData =  function($receivedMessage, $i, $matched, &$context_s, &$context_u) {

    if ($i != 0) return null;

    //
    // Read cloudStorage data whitch is owned by other project 'jebaxxMonitor'.
    // In default settings, this access is deied, so additionl access right is needed.
    // It can be added in jebaxxMOnitor's storage buouser.
    //
    $gs_file = "gs://jebaxxmonitor.appspot.com/postTime";
    $packedData = unserialize(file_get_contents($gs_file));

    if (!is_array($packedData)) return(null);

    $t = new DateTime();
    $sens_rec = $t->setTimeStamp($packedData[0])->format('Y/m/d H:i') . PHP_EOL;

    $props = $packedData[2];

    if (isset($props['T-ADT7410-02'])) {
	$sens_rec .= "気温：". sprintf("%4.1f", $props['T-ADT7410-02']);
    }
    else if (isset($props['T-ADT7410-01'])) {
	$sens_rec .= "気温：". sprintf("%4.1f", $props['T-ADT7410-01']) . '(予)';
    }

    if (isset($props['P-BMP180-01'])) {
	$sens_rec .= PHP_EOL. "気圧：". $props['P-BMP180-01'];
    }

    $sens_rec .= PHP_EOL . "https://jebaxxmonitor.appspot.com/primary_sensors.php";

    return($sens_rec);
};

?>
