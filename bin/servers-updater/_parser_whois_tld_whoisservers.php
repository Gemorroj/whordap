<?php

require_once __DIR__.'/_functions.php';

$host = 'http://whoisservers.net';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load main page...'.\PHP_EOL;
$mainPage = \request($fp, $host.'/');

$matches = [];
\preg_match_all('/<h4><a href="(.+)">(.+)<\/a><\/h4>/', $mainPage, $matches, \PREG_SET_ORDER);
if (!$matches) {
    throw new RuntimeException('Failed to parse '.$host.'/');
}

echo 'Start scan domains...'.\PHP_EOL;
$servers = [];
foreach ($matches as $match) {
    $urlPage = $match[1];
    $tld = $match[2];
    if (isset($ianaServers[$tld])) {
        echo 'Skip "'.$tld.'" domain page, exists on IANA data...'.\PHP_EOL;
        continue;
    }
    if (isset($webposServers[$tld])) {
        echo 'Skip "'.$tld.'" domain page, exists on webpos data...'.\PHP_EOL;
        continue;
    }

    echo 'Load "'.$tld.'" domain page...'.\PHP_EOL;
    $domainPage = \request($fp, $host.$urlPage);

    $matchesPage = [];
    \preg_match('/<tr><td>WHOIS Server: <\/td><td>(.+)<\/td><\/tr>/', $domainPage, $matchesPage);
    if (!$matches) {
        throw new RuntimeException('Failed to parse '.$host.$urlPage);
    }

    $server = $matchesPage[1];
    $server = \htmlspecialchars_decode($server);
    if (\str_starts_with($server, 'http://') || \str_starts_with($server, 'https://')) {
        echo 'Skip "'.$tld.'" domain page. HTTP server is obsolete...'.\PHP_EOL;
        continue;
    }

    $servers[$tld] = $server;
}
echo 'End scan domains.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
