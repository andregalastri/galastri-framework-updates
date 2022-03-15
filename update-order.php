<?php
$orderFile = "order.php";
$order = include($orderFile);

if(array_search($argv[1], $order) === false) {
    array_push($order, $argv[1]);

    $fp = fopen($orderFile, 'w+');
    fwrite($fp, "<?php\n\n");
    fwrite($fp, "return ".var_export($order, true).";");
    fclose($fp);
}
