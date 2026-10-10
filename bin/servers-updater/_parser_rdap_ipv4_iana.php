<?php

require_once __DIR__.'/_functions.php';

$host = 'https://data.iana.org';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$jsonIpv4 = \request($fp, $host.'/rdap/ipv4.json');
$dataIpv4 = \json_decode($jsonIpv4, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan IPv4...'.\PHP_EOL;
$servers = [];
foreach ($dataIpv4['services'] as $value) {
    $server = $value[1][0];
    $ipList = $value[0];
    foreach ($ipList as $ip) {
        $servers[$ip] = $server;
    }
}
echo 'End scan IPv4.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
