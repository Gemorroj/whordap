<?php

require_once __DIR__.'/_functions.php';

$host = 'https://data.iana.org';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$jsonAsn = \request($fp, $host.'/rdap/asn.json');
$dataAsn = \json_decode($jsonAsn, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan ASN...'.\PHP_EOL;
$servers = [];
foreach ($dataAsn['services'] as $value) {
    $server = $value[1][0];
    $asnList = $value[0];
    foreach ($asnList as $asn) {
        $servers[$asn] = $server;
    }
}
echo 'End scan ASN.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
