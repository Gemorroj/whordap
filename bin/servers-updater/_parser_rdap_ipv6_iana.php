<?php

require_once __DIR__.'/_functions.php';

$host = 'https://data.iana.org';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$jsonIpv6 = \request($fp, $host.'/rdap/ipv6.json');
$dataIpv6 = \json_decode($jsonIpv6, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan IPv6...'.\PHP_EOL;
$servers = [];
foreach ($dataIpv6['services'] as $value) {
    $server = $value[1][0];
    $ipList = $value[0];
    foreach ($ipList as $ip) {
        $servers[$ip] = $server;
    }
}
echo 'End scan IPv6.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
