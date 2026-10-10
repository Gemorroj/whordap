<?php

require_once __DIR__.'/_functions.php';

$host = 'https://raw.githubusercontent.com';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$jsonAsn16 = \request($fp, $host.'/weppos/whois/refs/heads/main/data/asn16.json');
$jsonAsn16 = \preg_replace('/\/\/ .+/', '', $jsonAsn16);
$dataAsn16 = \json_decode($jsonAsn16, true, 10, \JSON_THROW_ON_ERROR);

$jsonAsn32 = \request($fp, $host.'/weppos/whois/refs/heads/main/data/asn32.json');
$jsonAsn32 = \preg_replace('/\/\/ .+/', '', $jsonAsn32);
$dataAsn32 = \json_decode($jsonAsn32, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan ASN...'.\PHP_EOL;
$servers = [];
foreach ($dataAsn16 as $range => $value) {
    if (!isset($value['host']) || '' === $value['host']) {
        continue;
    }
    $range = \str_replace(' ', '-', $range);
    $servers[$range] = $value['host'];
}
foreach ($dataAsn32 as $range => $value) {
    if (!isset($value['host']) || '' === $value['host']) {
        continue;
    }
    $range = \str_replace(' ', '-', $range);
    $servers[$range] = $value['host'];
}
echo 'End scan ASN.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
