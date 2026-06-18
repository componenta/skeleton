<?php

declare(strict_types=1);

namespace App\WebSocket;

use Componenta\WebSocket\Application\Error\WebSocketErrorContextInterface;
use Componenta\WebSocket\Application\WebSocketApplicationInterface;
use Componenta\WebSocket\Connection\ConnectionInterface;
use Componenta\WebSocket\Protocol\CloseInfo;
use Componenta\WebSocket\Protocol\Message;

final class WelcomeApplication implements WebSocketApplicationInterface
{
    public function connected(ConnectionInterface $connection): void
    {
        $connection->sendText('Componenta WebSocket server is running.');
    }

    public function received(ConnectionInterface $connection, Message $message): void
    {
        if ($message->isText()) {
            $connection->sendText($message->payload);
        }
    }

    public function disconnected(ConnectionInterface $connection, CloseInfo $close): void {}

    public function failed(WebSocketErrorContextInterface $context): void {}
}
