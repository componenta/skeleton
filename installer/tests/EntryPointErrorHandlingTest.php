<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntryPointErrorHandlingTest extends TestCase
{
    #[DataProvider('entryPoints')]
    public function testSuppressedWarningsDoNotAbortBootstrap(string $source, string $target): void
    {
        $result = $this->bootstrap($source, $target, suppressed: true);

        self::assertSame(0, $result['code'], $result['output']);
        self::assertSame('autoload-completed', $result['output']);
        self::assertSame('', $result['log']);
    }

    #[DataProvider('entryPoints')]
    public function testReportedWarningsStillAbortBootstrap(string $source, string $target): void
    {
        $result = $this->bootstrap($source, $target, suppressed: false);

        self::assertSame(1, $result['code'], $result['output']);
        self::assertStringNotContainsString('autoload-completed', $result['output']);
        self::assertStringContainsString('Optional bootstrap probe failed.', $result['log']);
    }

    public static function entryPoints(): array
    {
        return [
            'console' => ['bin/console.php', 'bin/console.php'],
            'http' => ['installer/stubs/http/public/index.php', 'public/index.php'],
            'websocket' => ['installer/stubs/websocket/bin/websocket.php', 'bin/websocket.php'],
        ];
    }

    /** @return array{code: int, output: string, log: string} */
    private function bootstrap(string $source, string $target, bool $suppressed): array
    {
        $root = sys_get_temp_dir() . '/skeleton_entry_error_' . bin2hex(random_bytes(8));
        foreach (['bin', 'public', 'vendor', 'log', 'templates/error'] as $directory) {
            mkdir($root . '/' . $directory, 0700, true);
        }
        copy(dirname(__DIR__, 2) . '/' . $source, $root . '/' . $target);
        file_put_contents($root . '/templates/error/500.phtml', 'bootstrap-failed');
        file_put_contents($root . '/vendor/autoload.php', '<?php '
            . ($suppressed ? '@' : '')
            . 'trigger_error("Optional bootstrap probe failed.", E_USER_WARNING);'
            . 'echo "autoload-completed"; exit(0);');

        try {
            $process = proc_open(
                [PHP_BINARY, $root . '/' . $target],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                sys_get_temp_dir(),
            );
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return [
                'code' => proc_close($process),
                'output' => $output,
                'log' => is_file($root . '/log/error.log') ? file_get_contents($root . '/log/error.log') : '',
            ];
        } finally {
            $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($root);
        }
    }
}
