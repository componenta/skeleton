#!/usr/bin/env php
<?php

declare(strict_types=1);

use Componenta\App\Scope;
use Componenta\App\Console\Build\BuildConsole;
use Componenta\Stdlib\PathResolver;

use function Componenta\App\run;

$root = dirname(__DIR__);

error_reporting(E_ALL);
set_error_handler(static function (int $errno, string $message, string $file, int $line): bool {
    if ((error_reporting() & $errno) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $errno, $file, $line);
});

try {
    require $root . '/vendor/autoload.php';

    $paths = new PathResolver($root);
    $buildCode = BuildConsole::run($paths);
    if ($buildCode !== null) {
        exit($buildCode);
    }
    run(Scope::CLI, $paths);
} catch (Throwable $e) {
    ini_set('error_log', $root . '/log/error.log');
    error_log((string) $e);
    fwrite(STDERR, 'Application failed to run.' . PHP_EOL);

    exit(1);
}
