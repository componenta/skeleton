<?php

declare(strict_types=1);

use App\WebSocket\WelcomeApplication;
use Componenta\App\WebSocket\Boot\Target\WebSocketBootTargetInterface;

/**
 * @var WebSocketBootTargetInterface $app
 */

$app->application = new WelcomeApplication();
