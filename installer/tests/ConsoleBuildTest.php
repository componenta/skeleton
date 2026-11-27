<?php

declare(strict_types=1);

use Componenta\App\Build\BuildStore;
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
            \Componenta\Error\App\ConfigProvider::class,
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

    public function testFirstRebuildUsesNewAttributeProviderWithoutCleaning(): void
    {
        $this->assertBuildSucceeds();
        $before = $this->store()->load();
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
                    return ['fixture.new-builder' => static fn () => new class implements \Componenta\App\Build\ApplicationBuilderInterface {
                        public function build(\Componenta\App\Build\BuildContext $build): void
                        {
                            $build->write('fixture.new', ['executed' => true]);
                        }
                    }];
                }
            }
            PHP);

        $this->assertBuildSucceeds();

        self::assertSame(['executed' => true], $this->store()->load()->read('fixture.new'));
        self::assertNotSame($before->generation, $this->store()->load()->generation);
        self::assertNull($before->read('fixture.new'));
        self::assertCount(1, $before->read('app.discovery'));
    }

    public function testRebuildSucceedsAfterRemovingPreviouslyDiscoveredClassWithoutCleaning(): void
    {
        $this->assertBuildSucceeds();
        unlink($this->root . '/src/OldDto.php');

        $this->assertBuildSucceeds();

        self::assertSame([], $this->store()->load()->read('app.discovery'));
    }

    public function testBuildDoesNotExecuteRuntimeBootMethods(): void
    {
        file_put_contents($this->root . '/src/BootProbe.php', <<<'PHP'
            <?php
            namespace RebuildFixture;
            final class BootProbe
            {
                #[\Componenta\App\Boot\Boot]
                public function boot(): void
                {
                    throw new \LogicException('Runtime boot must not run during build.');
                }
            }
            PHP);

        $this->assertBuildSucceeds();

        self::assertSame(['boot'], $this->store()->load()->read('app.boot')['RebuildFixture\\BootProbe']);
    }

    public function testListHelpAndVersionPreserveThePublishedGeneration(): void
    {
        $this->assertBuildSucceeds();
        $before = $this->store()->load()->generation;

        foreach ([['list'], ['help', 'app:build'], ['app:build', '--help'], ['-h', 'app:build'], ['app:build', '--version'], ['help', 'app:clean'], ['app:clean', '--help'], ['app:clean', '--version']] as $arguments) {
            $result = $this->console($arguments);

            self::assertSame(0, $result['code'], $result['output']);
            self::assertSame($before, $this->store()->load()->generation);
        }
    }

    public function testFailedBuildPreservesPreviousGenerationAndNeverPublishesPartialArtifacts(): void
    {
        $this->assertBuildSucceeds();
        $before = $this->store()->load();
        mkdir($this->root . '/config/autoload');
        file_put_contents($this->root . '/config/autoload/failure.global.php', <<<'PHP'
            <?php
            return [
                \Componenta\App\ConfigKey::BUILDERS => ['fixture.failure'],
                'dependencies' => ['services' => [
                    'fixture.failure' => new class implements \Componenta\App\Build\ApplicationBuilderInterface {
                        public function build(\Componenta\App\Build\BuildContext $build): void
                        {
                            $build->write('fixture.partial', ['value' => 'never published']);
                            throw new \DomainException('Fixture build failed.');
                        }
                    },
                ]],
            ];
            PHP);

        $result = $this->console(['app:build']);

        self::assertNotSame(0, $result['code']);
        self::assertStringContainsString('Fixture build failed.', $result['output']);
        self::assertSame($before->generation, $this->store()->load()->generation);
        self::assertNull($this->store()->load()->read('fixture.partial'));
    }

    public function testCleanDoesNotLoadBrokenProvidersAndKeepsPinnedGenerationReadable(): void
    {
        $this->assertBuildSucceeds();
        $before = $this->store()->load();
        file_put_contents($this->root . '/config/config.php', '<?php throw new LogicException("Broken provider.");');

        foreach (range(1, 2) as $_) {
            $result = $this->console(['app:clean']);
            self::assertSame(0, $result['code'], $result['output']);
        }

        self::assertNull($this->store()->load());
        self::assertCount(1, $before->read('app.discovery'));
    }

    public function testFailedCleanDoesNotDeleteADirectoryAtThePointerPath(): void
    {
        $pointer = $this->root . '/var/cache/build/current.json';
        mkdir($pointer, 0700, true);
        file_put_contents($pointer . '/keep.txt', 'preserved');

        $result = $this->console(['app:clean']);

        self::assertNotSame(0, $result['code']);
        self::assertSame('preserved', file_get_contents($pointer . '/keep.txt'));
        self::assertStringContainsString('current.json', preg_replace('/\s+/', '', $result['output']));
    }

    private function store(): BuildStore
    {
        return new BuildStore($this->root . '/var/cache/build');
    }

    private function assertBuildSucceeds(): void
    {
        $result = $this->console(['--no-interaction', 'app:build']);
        self::assertSame(0, $result['code'], $result['output']);
    }

    /** @param list<string> $arguments @return array{code: int, output: string} */
    private function console(array $arguments): array
    {
        $command = [PHP_BINARY];
        if (($ini = php_ini_loaded_file()) !== false) {
            array_push($command, '-c', $ini);
        }
        array_push($command, $this->root . '/bin/console.php', ...$arguments);
        // Deliberately start outside the project: bootstrap must select the project root.
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, sys_get_temp_dir());
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'output' => $output];
    }
}
