<?php

declare(strict_types=1);

use Componenta\App\Config\AttributeConfigProvider;
use Componenta\App\Config\ConfigDefinition;
use Componenta\App\Config\ComposerPackageConfigProvider;
use Componenta\App\Config\DiscoveryDefinition;
use Componenta\Config\FileProvider;
use Componenta\Stdlib\PathResolverInterface;

if (!isset($paths) || !$paths instanceof PathResolverInterface) {
    throw new RuntimeException('config/config.php requires $paths to be a PathResolverInterface instance.');
}

return new ConfigDefinition(
    providers: [
        new ComposerPackageConfigProvider($paths->resolve('config/componenta-providers.php')),
        new AttributeConfigProvider(),
        new FileProvider($paths->resolve('config/console.php')),
        new FileProvider($paths->resolve('config/autoload/{{,*.}global,{,*.}local}.{php,yaml,json}')),
    ],
    discovery: new DiscoveryDefinition(
        directories: ['src'],
    ),
);
