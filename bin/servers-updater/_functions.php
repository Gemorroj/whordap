<?php

function connect(string $url): CurlHandle
{
    $fp = \curl_init($url);
    \curl_setopt($fp, \CURLOPT_ENCODING, '');
    \curl_setopt($fp, \CURLOPT_RETURNTRANSFER, true);
    \curl_setopt($fp, \CURLOPT_HTTPHEADER, [
        'Connection: Keep-Alive',
        'User-Agent: WhoRdap',
    ]);

    return $fp;
}

function request(CurlHandle &$fp, string $url): string
{
    \curl_setopt($fp, \CURLOPT_URL, $url);
    $result = \curl_exec($fp);
    if (false === $result) {
        \sleep(1);
        $fp = \connect($url); // try reconnect
        $result = \curl_exec($fp);
        if (false === $result) {
            throw new RuntimeException(\curl_error($fp));
        }
    }

    return $result;
}

/**
 * @param array<string, string> $servers
 *
 * @return array<string, string>
 */
function cleanupTldServers(array $servers): array
{
    foreach ($servers as $tld => $server) {
        if (\substr_count($tld, '.') > 1) {
            $lastDotPos = \strrpos($tld, '.');
            $globalTld = \substr($tld, $lastDotPos);

            if (isset($servers[$globalTld]) && $servers[$globalTld] === $server) {
                unset($servers[$tld]);
            }
        }
    }

    return $servers;
}
