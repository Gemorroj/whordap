<?php

require_once __DIR__.'/_functions.php';

$host = 'https://raw.githubusercontent.com';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$json = \request($fp, $host.'/weppos/whois/refs/heads/main/data/ipv6.json');
$json = \preg_replace('/\/\/ .+/', '', $json);
$data = \json_decode($json, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan IPv6...'.\PHP_EOL;
$servers = [];
foreach ($data as $ip => $value) {
    if (!isset($value['host']) || 'teredo' === $value['host'] || '6to4' === $value['host']) {
        continue;
    }

    $servers[$ip] = $value['host'];
}
echo 'End scan IPv6.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
