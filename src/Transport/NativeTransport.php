<?php

declare(strict_types=1);

namespace WhoRdap\Transport;

use Psr\Log\LoggerInterface;
use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\TimeoutException;
use WhoRdap\TransportInterface;

readonly class NativeTransport implements TransportInterface
{
    public function __construct(private int $timeout = 5, private int $buffer = 1024, private ?LoggerInterface $logger = null)
    {
    }

    /**
     * @throws NetworkException
     * @throws InvalidResponseException
     * @throws HttpException
     */
    public function requestRdap(string $server, string $query): string
    {
        $url = \rtrim($server, '/').$query;
        $this->logger?->debug("Initialize CURL connection to HTTP server: $url");
        $fp = \curl_init($url);
        if (!$fp) {
            $this->logger?->debug("Can't init CURL connection to HTTP server: $server");
            throw new NetworkException("Can't init CURL connection to HTTP server: $server");
        }
        \curl_setopt($fp, \CURLOPT_ENCODING, '');
        \curl_setopt($fp, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($fp, \CURLOPT_FOLLOWLOCATION, true); // http -> https or redirect to actual server
        \curl_setopt($fp, \CURLOPT_TIMEOUT, $this->timeout);
        \curl_setopt($fp, \CURLOPT_BUFFERSIZE, $this->buffer);
        \curl_setopt($fp, \CURLOPT_HTTPHEADER, [
            'User-Agent: WhoRdap',
        ]);

        $this->logger?->debug('Execute request to HTTP server...');
        $raw = \curl_exec($fp);
        if (!\is_string($raw)) {
            $this->logger?->debug("Can't request to HTTP server: $server");
            throw new NetworkException(\curl_error($fp), \curl_errno($fp));
        }

        $this->logger?->debug($raw);

        $httpCode = \curl_getinfo($fp, \CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            throw HttpException::create($httpCode, $url, $raw);
        }

        if (!\json_validate($raw)) {
            $this->logger?->debug("Invalid JSON response: $server");
            throw new InvalidResponseException('Invalid RDAP response. Is not valid JSON.', $raw);
        }

        return $raw;
    }

    /**
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function requestWhois(string $server, string $query): string
    {
        $parts = \explode(':', $server, 2);
        $host = $parts[0];
        $port = (int) ($parts[1] ?? 43);

        $this->logger?->debug("Open connection to WHOIS server: $server");
        $ptr = @\fsockopen($host, $port, $errno, $errstr, $this->timeout);
        if (!$ptr) {
            $this->logger?->debug("Can't connect to WHOIS server: $server");
            throw new NetworkException($errstr, $errno);
        }

        \stream_set_timeout($ptr, $this->timeout);
        \stream_set_blocking($ptr, false);

        $this->logger?->debug("Write data \"$query\" to WHOIS server...");
        $write = \fwrite($ptr, $query."\r\n");
        if (false === $write) {
            $this->logger?->debug("Can't write data to WHOIS $server");
            $error = \error_get_last();
            throw new NetworkException($error['message']);
        }

        $raw = '';
        $_ = null;
        $startTime = \time();

        $this->logger?->debug('Read data from WHOIS server...');
        while (!\feof($ptr)) {
            $r = [$ptr];
            if (false !== @\stream_select($r, $_, $_, $this->timeout)) {
                $str = \fgets($ptr, \max(1, $this->buffer));
                if (false !== $str) {
                    $raw .= $str;
                }

                if (\time() - $startTime > $this->timeout) {
                    $this->logger?->debug('Timeout reading from WHOIS server: '.$server);
                    $this->logger?->debug('Close connection');
                    @\fclose($ptr);
                    throw new TimeoutException('Timeout reading from WHOIS server: '.$server);
                }
            } else {
                $this->logger?->debug('Failed to select stream from WHOIS server: '.$server);
                $this->logger?->debug('Close connection');
                @\fclose($ptr);
                throw new NetworkException('Failed to select stream from WHOIS server: '.$server);
            }
        }

        if ('' === $raw) {
            $this->logger?->debug('Empty data from WHOIS server: '.$server);
            $this->logger?->debug('Close connection');
            @\fclose($ptr);
            throw new NetworkException('Empty data from WHOIS server: '.$server);
        }

        $this->logger?->debug($raw);

        $this->logger?->debug('Close connection');
        \fclose($ptr);

        $raw = $this->convertToUtf8($raw);
        $raw = \mb_trim($raw);

        return $raw;
    }

    private function convertToUtf8(string $raw): string
    {
        $isUtf8 = (bool) \preg_match('//u', $raw);
        if (!$isUtf8) {
            $raw = \mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1'); // fixme: detect encoding. in fact mb_detect_encoding is not working
        }

        return $raw;
    }
}
