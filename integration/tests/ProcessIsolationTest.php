<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/functions.php';

final class ProcessIsolationTest extends TestCase
{
    private string $root;
    private array $environment = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/componenta-integration-process-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700);
        mkdir($this->root . '/empty-hooks', 0700);
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->root);
    }

    public function testComposerCannotReplaceTheSelectedManifestOrWriteIntoAnotherVendor(): void
    {
        $project = $this->composerProject('selected');
        $unrelated = $this->composerProject('unrelated');
        $this->setEnvironment('COMPOSER', $unrelated . '/composer.json');
        $this->setEnvironment('COMPOSER_VENDOR_DIR', $unrelated . '/vendor');
        $this->setEnvironment('COMPOSER_BIN_DIR', $unrelated . '/bin');

        $result = command([
            'composer', 'update', '--no-interaction', '--no-plugins', '--no-scripts', '--no-audit', '--no-progress',
        ], $project, $this->root . '/composer.log', false);

        self::assertSame(0, $result['exitCode'], $result['output']);
        self::assertFileExists($project . '/composer.lock');
        self::assertFileExists($project . '/vendor/autoload.php');
        self::assertFileDoesNotExist($unrelated . '/composer.lock');
        self::assertDirectoryDoesNotExist($unrelated . '/vendor');
        self::assertDirectoryDoesNotExist($unrelated . '/bin');
    }

    public function testComposerCannotInheritAnIgnoredPlatformRequirement(): void
    {
        $project = $this->composerProject('platform', ['ext-componenta-integration-missing' => '*']);
        $this->setEnvironment('COMPOSER_IGNORE_PLATFORM_REQS', '1');
        $this->setEnvironment('COMPOSER_IGNORE_PLATFORM_REQ', 'ext-componenta-integration-missing');

        $result = command([
            'composer', 'update', '--no-install', '--no-interaction', '--no-plugins', '--no-scripts', '--no-audit', '--no-progress',
        ], $project, $this->root . '/platform.log', false);

        self::assertNotSame(0, $result['exitCode'], $result['output']);
        self::assertStringContainsString('ext-componenta-integration-missing', $result['output']);
        self::assertFileDoesNotExist($project . '/composer.lock');
    }

    public function testSourceCopyUsesTheRequestedCheckoutDespiteInheritedGitWorkTree(): void
    {
        $source = $this->gitProject('selected', 'SelectedOnly.php');
        $unrelated = $this->gitProject('unrelated', 'UnrelatedOnly.php');
        $expectedSha = trim(command(['git', 'rev-parse', 'HEAD'], $source, $this->root . '/git.log')['output']);
        $this->setEnvironment('GIT_DIR', $unrelated . '/.git');
        $this->setEnvironment('GIT_WORK_TREE', $unrelated);
        $this->setEnvironment('GIT_INDEX_FILE', $unrelated . '/.git/index');

        $metadata = copySource($source, $this->root . '/snapshot', $this->root . '/copy.log');

        self::assertSame($expectedSha, $metadata['sha']);
        self::assertFileExists($this->root . '/snapshot/SelectedOnly.php');
        self::assertFileDoesNotExist($this->root . '/snapshot/UnrelatedOnly.php');
        self::assertSame(2, $metadata['files']);
    }

    private function composerProject(string $name, array $require = []): string
    {
        $path = $this->root . '/' . $name;
        mkdir($path);
        file_put_contents($path . '/composer.json', json_encode([
            'name' => 'componenta/integration-' . $name,
            'require' => $require === [] ? new stdClass() : $require,
            'repositories' => [['packagist.org' => false]],
            'config' => ['vendor-dir' => 'vendor', 'bin-dir' => 'vendor/bin'],
        ], JSON_THROW_ON_ERROR));

        return $path;
    }

    private function gitProject(string $name, string $fixture): string
    {
        $path = $this->composerProject($name);
        file_put_contents($path . '/' . $fixture, '<?php // ' . $name);
        $log = $this->root . '/git.log';
        command(['git', 'init', '--quiet'], $path, $log);
        command(['git', 'add', '.'], $path, $log);
        command([
            'git', '-c', 'user.name=Integration Test', '-c', 'user.email=integration@example.test',
            '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=' . $this->root . '/empty-hooks',
            'commit', '--quiet', '-m', 'Test fixture',
        ], $path, $log);

        return $path;
    }

    private function setEnvironment(string $name, string $value): void
    {
        $this->environment[$name] = getenv($name);
        putenv($name . '=' . $value);
    }
}
