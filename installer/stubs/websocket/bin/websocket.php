#!/usr/bin/env php
<?php

declare(strict_types=1);

use Componenta\App\Scope;
use Componenta\Stdlib\PathResolver;

use function Componenta\App\run;

$root = dirname(__DIR__);

error_reporting(E_ALL);
set_error_handler(static function (int $errno, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $errno, $file, $line);
});

try {
    require $root . '/vendor/autoload.php';

    run(Scope::WEBSOCKET, new PathResolver($root));
} catch (Throwable $e) {
    ini_set('error_log', $root . '/log/error.log');
    error_log((string) $e);
    fwrite(STDERR, 'Application failed to run.' . PHP_EOL);

    exit(1);
}
