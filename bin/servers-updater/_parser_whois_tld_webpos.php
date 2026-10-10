<?php

require_once __DIR__.'/_functions.php';

$host = 'https://raw.githubusercontent.com';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load...'.\PHP_EOL;
$json = \request($fp, $host.'/weppos/whois/refs/heads/main/data/tld.json');
$json = \preg_replace('/\/\/ .+/', '', $json);
$data = \json_decode($json, true, 10, \JSON_THROW_ON_ERROR);

echo 'Start scan domains...'.\PHP_EOL;
$servers = [];
foreach ($data as $tld => $value) {
    if ('_' === $tld) {
        continue;
    }
    $server = $value['host'] ?? null;
    if (!$server) {
        continue;
    }

    $servers['.'.$tld] = $server;
}
echo 'End scan domains.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
