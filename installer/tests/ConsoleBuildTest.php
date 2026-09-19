<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

final class ConsoleBuildTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/skeleton_rebuild_' . bin2hex(random_bytes(8));
        foreach (['bin', 'config', 'log', 'src', 'vendor'] as $directory) {
            mkdir($this->root . '/' . $directory, 0700, true);
        }
        $skeleton = dirname(__DIR__, 2);
        copy($skeleton . '/bin/console.php', $this->root . '/bin/console.php');
        foreach (['config.php', 'container.php', 'console.php'] as $file) {
            copy($skeleton . '/config/' . $file, $this->root . '/config/' . $file);
        }
        $autoload = dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        file_put_contents($this->root . '/vendor/autoload.php', '<?php $loader = require ' . var_export($autoload, true) . ';'
            . '$loader->addPsr4("RebuildFixture\\\\", dirname(__DIR__) . "/src", true); return $loader;');
        file_put_contents($this->root . '/config/componenta-providers.php', '<?php return ' . var_export([
            \Componenta\ClassFinder\ConfigProvider::class,
            \Componenta\App\ConfigProvider::class,
            \Componenta\App\Console\ConfigProvider::class,
            \Componenta\Error\ConfigProvider::class,
        ], true) . ';');
        file_put_contents($this->root . '/src/OldDto.php', '<?php namespace RebuildFixture; final class OldDto {}');
    }

    protected function tearDown(): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
    }

    public function testFirstRebuildRunsBuilderFromNewAttributeProvider(): void
    {
        $this->assertBuildSucceeds();
        self::assertFileExists($this->root . '/var/cache/build/classes.php');
        file_put_contents($this->root . '/var/cache/build/other-artifact.php', '<?php return "preserved";');
        $this->assertCleanSucceeds();
        file_put_contents($this->root . '/src/NewProvider.php', <<<'PHP'
            <?php
            namespace RebuildFixture;
            #[\Componenta\App\Config\AsConfig]
            final class NewProvider extends \Componenta\Config\ConfigProvider
            {
                protected function getConfig(): array
                {
                    return [\Componenta\App\ConfigKey::BUILDERS => ['fixture.new-builder']];
                }
                protected function getFactories(): array
                {
                    return ['fixture.new-builder' => static function (\Componenta\Config\ContainerValue $container) {
                        $paths = $container->get(\Componenta\Stdlib\PathResolverInterface::class);
                        return new class($paths->resolve('new-builder.txt')) implements \Componenta\App\Build\ApplicationBuilderInterface {
                            public function __construct(private string $path) {}
                            public function build(): void { file_put_contents($this->path, 'executed'); }
                        };
                    }];
                }
            }
            PHP);

        $this->assertBuildSucceeds();

        self::assertFileExists($this->root . '/new-builder.txt');
        self::assertSame('executed', file_get_contents($this->root . '/new-builder.txt'));
        self::assertSame('preserved', require $this->root . '/var/cache/build/other-artifact.php');
    }

    public function testRebuildSucceedsAfterRemovingPreviouslyDiscoveredClass(): void
    {
        $this->assertBuildSucceeds();
        $this->assertCleanSucceeds();
        unlink($this->root . '/src/OldDto.php');

        $result = $this->console(['--no-interaction', 'app:build']);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertSame([], require $this->root . '/var/cache/build/classes.php');
    }

    public function testListHelpAndVersionPreserveExistingDiscoveryMap(): void
    {
        $this->assertBuildSucceeds();
        $map = $this->root . '/var/cache/build/classes.php';
        $original = file_get_contents($map);

        foreach ([['list'], ['help', 'app:build'], ['app:build', '--help'], ['-h', 'app:build'], ['app:build', '--version'], ['help', 'app:clean'], ['app:clean', '--help'], ['app:clean', '--version']] as $arguments) {
            $result = $this->console($arguments);

            self::assertSame(0, $result['code'], $result['output']);
            self::assertFileExists($map);
            self::assertSame($original, file_get_contents($map));
        }
    }

    public function testFailedCleanupReportsAnErrorAndPreservesTheDirectory(): void
    {
        $map = $this->root . '/var/cache/build/classes.php';
        mkdir($map, 0700, true);
        file_put_contents($map . '/keep.txt', 'preserved');
        mkdir($this->root . '/config/autoload');
        file_put_contents($this->root . '/config/autoload/probe.global.php', '<?php file_put_contents(dirname(__DIR__, 2) . "/config-loaded.txt", "loaded"); return [];');

        $result = $this->console(['app:clean']);

        self::assertSame(1, $result['code'], $result['output']);
        self::assertSame('loaded', file_get_contents($this->root . '/config-loaded.txt'));
        self::assertSame('preserved', file_get_contents($map . '/keep.txt'));
        self::assertStringContainsString('classes.php', $result['output']);
    }

    public function testBuildUsesTheExistingMapWhileCleanRemovesItOnlyDuringExecution(): void
    {
        $this->assertBuildSucceeds();
        mkdir($this->root . '/config/autoload');
        file_put_contents($this->root . '/config/autoload/probe.global.php', <<<'PHP'
            <?php
            file_put_contents(dirname(__DIR__, 2) . '/map-at-startup.txt', is_file(dirname(__DIR__, 2) . '/var/cache/build/classes.php') ? 'present' : 'absent');
            return [];
            PHP);

        foreach (['app:build', 'app:b'] as $command) {
            $result = $this->console([$command]);
            self::assertSame(0, $result['code'], $result['output']);
            self::assertSame('present', file_get_contents($this->root . '/map-at-startup.txt'));
        }

        $this->assertCleanSucceeds();
        self::assertSame('present', file_get_contents($this->root . '/map-at-startup.txt'));
        foreach (['classes.php', 'boot.php', 'commands.php'] as $map) {
            self::assertFileDoesNotExist($this->root . '/var/cache/build/' . $map);
        }
        $this->assertCleanSucceeds();
        self::assertSame('absent', file_get_contents($this->root . '/map-at-startup.txt'));
        $this->assertBuildSucceeds();
        self::assertSame('absent', file_get_contents($this->root . '/map-at-startup.txt'));
    }

    private function assertCleanSucceeds(): void
    {
        $result = $this->console(['app:clean']);
        self::assertSame(0, $result['code'], $result['output']);
    }

    private function assertBuildSucceeds(): void
    {
        $result = $this->console(['app:build']);
        self::assertSame(0, $result['code'], $result['output']);
    }

    /**
     * @param list<string> $arguments
     * @return array{code: int, output: string}
     */
    private function console(array $arguments): array
    {
        $command = [PHP_BINARY];
        if (($ini = php_ini_loaded_file()) !== false) {
            array_push($command, '-c', $ini);
        }
        array_push($command, $this->root . '/bin/console.php', ...$arguments);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'output' => $output];
    }
}
