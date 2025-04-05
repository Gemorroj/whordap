#!/usr/bin/env php
<?php

require_once __DIR__.'/servers-updater/_functions.php';

$template = '<?php

declare(strict_types=1);

namespace WhoRdap\Resource;

use WhoRdap\AsnServerListInterface;

class WhoisAsnServerList implements AsnServerListInterface
{
    public string $serverDefault;
    /**
     * @var array<string|int, string>
     */
    public array $servers;

    public function __construct()
    {
        $this->serverDefault = \'whois.arin.net\';
        $this->servers = [];
    }
}';

$writePath = __DIR__.'/../src/Resource/WhoisAsnServerList.php';
$startTime = \microtime(true);

$asnServers = require __DIR__.'/servers-updater/_parser_whois_asn_webpos.php';
$count = \count($asnServers);

$templatedAsnServers = [];
foreach ($asnServers as $asn => $server) {
    $templatedAsnServers[] = \sprintf("'%s' => '%s',", $asn, $server);
}

$templatedAsnServersStr = '$this->servers = ['."\n".\implode("\n", $templatedAsnServers)."\n];";
$template = \str_replace('$this->servers = [];', $templatedAsnServersStr, $template);

$write = \file_put_contents($writePath, $template);
if (false === $write) {
    throw new RuntimeException('Can\'t write to '.$writePath);
}

$endTime = \microtime(true);
$time = \round($endTime - $startTime, 2).' seconds';

echo 'WHOIS servers updated. Now: '.$count.' servers'.\PHP_EOL;
echo 'Spent time: '.$time.\PHP_EOL;
echo 'Don\'t forget run php-cs-fixer'.\PHP_EOL;
