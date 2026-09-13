<?php
declare(strict_types=1);

use Componenta\App\Console\Command\BuildCommand;
use Componenta\Config\Config;
use Componenta\Stdlib\PathResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SmokeTest extends TestCase
{
    public function testBootsApplicationAndExposesBuildHelp(): void
    {
        $paths = new PathResolver(dirname(__DIR__));
        $container = require $paths->resolve('config/container.php');
        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(Config::class, $container->get(Config::class));

        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand($container->get(BuildCommand::class));
        $output = new BufferedOutput();

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:build', '--help' => true]), $output));
        self::assertStringContainsString('app:build', $output->fetch());
    }
}
