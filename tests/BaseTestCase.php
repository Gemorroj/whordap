<?php

namespace WhoRdap\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WhoRdap\NetworkClient\NetworkClient;
use WhoRdap\Transport\NativeTransport;

abstract class BaseTestCase extends TestCase
{
    protected function createLoggedClient(?int $cacheTime = null, int $timeout = 5): NetworkClient
    {
        $input = new ArgvInput();
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_DEBUG);
        $outputStyle = new SymfonyStyle($input, $output);
        $logger = new ConsoleLogger($outputStyle);

        $transport = new NativeTransport(timeout: $timeout, logger: $logger);

        if (null === $cacheTime) {
            return new NetworkClient(transport: $transport, logger: $logger);
        }

        $cache = new FilesystemAdapter('whordap', $cacheTime);

        return new NetworkClient(cache: $cache, logger: $logger, transport: $transport);
    }

    protected static function fixture(string $path): string
    {
        $file = __DIR__.'/fixtures/'.$path;
        if (!\is_file($file)) {
            throw new \RuntimeException('Fixture not found: '.$path);
        }

        $contents = \file_get_contents($file);
        if (false === $contents) {
            throw new \RuntimeException('Can not read fixture: '.$path);
        }

        return $contents;
    }
}
