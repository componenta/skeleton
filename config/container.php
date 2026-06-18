<?php

declare(strict_types=1);

use Componenta\App\Config\ConfigFactory;
use Componenta\App\ContainerFactory;
use Componenta\Stdlib\PathResolverInterface;

if (!isset($paths) || !$paths instanceof PathResolverInterface) {
    throw new RuntimeException('config/container.php requires $paths to be a PathResolverInterface instance.');
}

$result = ConfigFactory::create(
    paths: $paths,
    definition: static fn () => require $paths->resolve('config/config.php'),
);

return ContainerFactory::create($paths, $result->config, $result->discovered);
