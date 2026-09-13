<?php
declare(strict_types=1);

use Componenta\App\Console\Command\BuildCommand;
use Componenta\Config\Config;
use Componenta\Stdlib\PathResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('boots the application and exposes build help', function (): void {
    $paths = new PathResolver(dirname(__DIR__));
    $container = require $paths->resolve('config/container.php');
    expect($container)->toBeInstanceOf(ContainerInterface::class);
    expect($container->get(Config::class))->toBeInstanceOf(Config::class);

    $application = new Application();
    $application->setAutoExit(false);
    $application->addCommand($container->get(BuildCommand::class));
    $output = new BufferedOutput();

    expect($application->run(new ArrayInput(['command' => 'app:build', '--help' => true]), $output))->toBe(0);
    expect($output->fetch())->toContain('app:build');
});
