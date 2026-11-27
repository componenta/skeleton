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

    public function testWorkspaceCannotBeCreatedInsideACheckoutReachedThroughASourceAlias(): void
    {
        $source = $this->gitProject('actual-checkout', 'Original.php');
        $aliases = $this->root . '/aliases';
        mkdir($aliases);
        symlink($source, $aliases . '/app');
        $original = file_get_contents($source . '/Original.php');
        $work = $source . '/unexpected-workspace';

        $result = command([
            PHP_BINARY, dirname(__DIR__) . '/run.php', '--sources=' . $aliases, '--work=' . $work,
        ], $this->root, $this->root . '/workspace.log', false);

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('outside the input checkouts', $result['output']);
        self::assertDirectoryDoesNotExist($work, $result['output']);
        self::assertSame($original, file_get_contents($source . '/Original.php'));
    }

    public function testSourceAliasPreservesInternalRelativeLinksAndSnapshotIdentity(): void
    {
        $repository = $this->gitProject('repository', 'Original.php');
        $source = $this->root . '/linked-worktree';
        command([
            'git', '-c', 'core.hooksPath=' . $this->root . '/empty-hooks',
            'worktree', 'add', '--quiet', '--detach', $source, 'HEAD',
        ], $repository, $this->root . '/worktree.log');
        self::assertFileExists($source . '/.git');
        symlink('Original.php', $source . '/InternalLink.php');
        $alias = $this->root . '/source-alias';
        symlink($source, $alias);

        $direct = copySource($source, $this->root . '/direct-copy', $this->root . '/direct.log');
        $aliased = copySource($alias, $this->root . '/aliased-copy', $this->root . '/aliased.log');

        self::assertSame($direct['sha'], $aliased['sha']);
        self::assertSame($direct['snapshotSha256'], $aliased['snapshotSha256']);
        self::assertTrue(is_link($this->root . '/aliased-copy/InternalLink.php'));
        self::assertSame(file_get_contents($source . '/Original.php'), file_get_contents($this->root . '/aliased-copy/InternalLink.php'));
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
