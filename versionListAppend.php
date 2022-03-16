<?php
$versionListFile = "versionList.php";
$versionList = include($versionListFile);

if(array_search($argv[1], $versionList) === false) {
    array_push($versionList, $argv[1]);

    $fp = fopen($versionListFile, 'w+');
    fwrite($fp, "<?php\n\n");
    fwrite($fp, "return ".var_export($versionList, true).";");
    fclose($fp);
}
