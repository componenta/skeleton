<?php

declare(strict_types=1);

/**
 * @var \Componenta\App\Boot\Target\HttpBootTargetInterface $app
 */

$app->pipe(Componenta\Error\Http\Middleware\ErrorHandlerMiddleware::class, priority: 100);
$app->pipe(Componenta\Http\Middleware\BodyParsingMiddleware::class, priority: 100);
