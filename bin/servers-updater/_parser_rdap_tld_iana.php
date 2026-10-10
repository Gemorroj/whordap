<?php

require_once __DIR__.'/_functions.php';

$host = 'https://data.iana.org';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$jsonTld = \request($fp, $host.'/rdap/dns.json');
$jsonTld = \json_decode($jsonTld, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan TLD...'.\PHP_EOL;
$servers = [];
foreach ($jsonTld['services'] as $value) {
    $server = $value[1][0];
    $tldList = $value[0];
    foreach ($tldList as $tld) {
        $servers['.'.$tld] = $server;
    }
}
echo 'End scan TLD.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
