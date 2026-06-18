<?php

declare(strict_types=1);

namespace App;

use Componenta\App\Config\AsConfig;
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\PolicyMiddleware as CommandPolicyMiddleware;
use Componenta\CQRS\ConfigKey as CqrsConfigKey;
use Componenta\CQRS\Query\Middleware\PolicyMiddleware as QueryPolicyMiddleware;
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\ConfigKey as InterceptorConfigKey;

#[AsConfig]
final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            InterceptorConfigKey::HTTP_INTERCEPTORS => [
                AttributeInterceptor::class,
            ],
            CqrsConfigKey::COMMAND_MIDDLEWARES => [
                CommandPolicyMiddleware::class,
                EventMiddleware::class,
            ],
            CqrsConfigKey::QUERY_MIDDLEWARES => [
                QueryPolicyMiddleware::class,
            ],
        ];
    }
}
