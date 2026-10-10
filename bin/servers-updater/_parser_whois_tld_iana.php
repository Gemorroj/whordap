<?php

require_once __DIR__.'/_functions.php';

$host = 'https://www.iana.org';

echo 'Connect to '.$host.'...'.\PHP_EOL;
$fp = \connect($host);

echo 'Load main page...'.\PHP_EOL;
$mainPage = \request($fp, $host.'/domains/root/db');

$matches = [];
\preg_match_all('/<span class="domain tld"><a href="(.+)">(.+)<\/a><\/span><\/td>/u', $mainPage, $matches, \PREG_SET_ORDER);
if (!$matches) {
    throw new RuntimeException('Failed to parse '.$host.'/domains/root/db');
}

echo 'Start scan domains...'.\PHP_EOL;
$servers = [];
foreach ($matches as $match) {
    $urlPage = $match[1];
    $tld = '.'.\str_replace(['/domains/root/db/', '.html'], '', $urlPage);

    echo 'Load "'.$tld.'" domain page...'.\PHP_EOL;
    $domainPage = \request($fp, $host.$urlPage);

    $matchesPageRdap = [];
    $matchesPageWhois = [];
    \preg_match('/<b>WHOIS Server:<\/b>(.+)<br>/', $domainPage, $matchesPageWhois);
    $server = isset($matchesPageWhois[1]) ? \trim($matchesPageWhois[1]) : null;
    if (!$server) {
        echo 'Warning: WHOIS/RDAP Server for "'.$tld.'" on page "'.$host.$urlPage.'" not found...'.\PHP_EOL;
        continue;
    }

    $servers[$tld] = $server;
}
echo 'End scan domains.'.\PHP_EOL;
echo \PHP_EOL;

return $servers;
