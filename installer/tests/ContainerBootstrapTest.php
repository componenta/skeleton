<?php
declare(strict_types=1);

use Componenta\App\Build\ApplicationBuilderInterface;
use Componenta\App\Console\Command\BuildCommand;
use Componenta\Config\Config;
use Componenta\Config\ContainerValue;
use Componenta\Stdlib\PathResolver;
use Componenta\Stdlib\PathResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ContainerBootstrapTest extends TestCase
{
    public function testGeneratedBootstrapUsesOneContainerAndKeepsBuildLazy(): void
    {
        $root = sys_get_temp_dir() . '/skeleton_bootstrap_' . bin2hex(random_bytes(8));
        mkdir($root . '/config', 0775, true);
        mkdir($root . '/src');
        $skeleton = dirname(__DIR__, 2);
        copy($skeleton . '/config/config.php', $root . '/config/config.php');
        copy($skeleton . '/config/container.php', $root . '/config/container.php');
        copy($skeleton . '/config/console.php', $root . '/config/console.php');
        file_put_contents($root . '/config/componenta-providers.php', '<?php return ' . var_export([
            \Componenta\App\ConfigProvider::class,
            \Componenta\App\Console\ConfigProvider::class,
            SkeletonBuildProvider::class,
        ], true) . ';');
        SkeletonBuildProvider::$creations = 0;
        try {
            $paths = new PathResolver($root);
            $container = require $root . '/config/container.php';
            self::assertInstanceOf(ContainerInterface::class, $container);
            $config = $container->get(Config::class);
            self::assertSame($config, $container->get(ContainerValue::class)->config);
            $application = new Application();
            $application->setAutoExit(false);
            $application->addCommand($container->get(BuildCommand::class));
            foreach ([['command' => 'list', '--raw' => true], ['command' => 'app:build', '--help' => true]] as $arguments) {
                $output = new BufferedOutput();
                self::assertSame(0, $application->run(new ArrayInput($arguments), $output));
                self::assertStringContainsString('app:build', $output->fetch());
                self::assertSame(0, SkeletonBuildProvider::$creations);
                self::assertFileDoesNotExist($root . '/built.txt');
            }
            self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:build']), new BufferedOutput()));
            self::assertSame(1, SkeletonBuildProvider::$creations);
            self::assertSame('built', file_get_contents($root . '/built.txt'));
        } finally {
            foreach (['config/config.php', 'config/container.php', 'config/console.php', 'config/componenta-providers.php', 'built.txt'] as $file) {
                if (is_file($root . '/' . $file)) { unlink($root . '/' . $file); }
            }
            rmdir($root . '/config');
            rmdir($root . '/src');
            rmdir($root);
        }
    }
}

final class SkeletonBuildProvider extends \Componenta\Config\ConfigProvider
{
    public static int $creations = 0;

    protected function getConfig(): array
    {
        return [\Componenta\App\ConfigKey::BUILDERS => ['skeleton.test_builder']];
    }

    protected function getFactories(): array
    {
        return ['skeleton.test_builder' => static function (ContainerValue $container): ApplicationBuilderInterface {
            ++self::$creations;
            $paths = $container->get(PathResolverInterface::class, PathResolverInterface::class);
            return new class($paths) implements ApplicationBuilderInterface {
                public function __construct(private PathResolverInterface $paths) {}
                public function build(): void { file_put_contents($this->paths->resolve('built.txt'), 'built'); }
            };
        }];
    }
}
