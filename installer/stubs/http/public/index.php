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

if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

try {
    require $root . '/vendor/autoload.php';

    run(Scope::HTTP, new PathResolver($root));
} catch (Throwable $e) {
    ini_set('error_log', $root . '/log/error.log');
    error_log((string) $e);

    if (!headers_sent()) {
        http_response_code(500);
    }

    $statusCode = 500;
    require $root . '/templates/error/500.phtml';

    exit(1);
}
