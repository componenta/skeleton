<?php

declare(strict_types=1);

function readJson(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }

    $value = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($value)) {
        throw new RuntimeException('Expected a JSON object in ' . $path);
    }

    return $value;
}

function writeJson(string $path, array $value): void
{
    if (file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) {
        throw new RuntimeException('Cannot write ' . $path);
    }
}

function directory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create ' . $path);
    }
}

/** Resolve existing ancestor symlinks even when the final workspace is new. */
function canonicalNewPath(string $path): string
{
    $suffix = [];
    while (!file_exists($path) && !is_link($path)) {
        array_unshift($suffix, basename($path));
        $path = dirname($path);
    }
    $base = realpath($path);
    if ($base === false) {
        throw new RuntimeException('Cannot resolve path ancestor: ' . $path);
    }

    return rtrim($base, '/') . ($suffix === [] ? '' : '/' . implode('/', $suffix));
}

/** @param list<string> $command */
function command(array $command, string $cwd, string $log, bool $mustSucceed = true): array
{
    $header = json_encode($command, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    file_put_contents($log, $header, FILE_APPEND);
    $output = tmpfile();
    if ($output === false) {
        throw new RuntimeException('Cannot create a process output file.');
    }
    // Keep authentication, proxy, mirror and cache settings. Remove only inherited
    // switches that can select another work tree, write outside this project, or
    // make its declared development/platform requirements disappear from the gate.
    $environment = getenv();
    foreach ([
        'COMPOSER', 'COMPOSER_VENDOR_DIR', 'COMPOSER_BIN_DIR', 'COMPOSER_NO_DEV',
        'COMPOSER_IGNORE_PLATFORM_REQ', 'COMPOSER_IGNORE_PLATFORM_REQS',
        'GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE', 'GIT_COMMON_DIR',
        'GIT_OBJECT_DIRECTORY', 'GIT_ALTERNATE_OBJECT_DIRECTORIES', 'GIT_NAMESPACE',
    ] as $name) {
        unset($environment[$name]);
    }
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => ['redirect', 1]], $pipes, $cwd, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . $command[0]);
    }
    $exitCode = proc_close($process);
    rewind($output);
    $text = stream_get_contents($output);
    fclose($output);
    file_put_contents($log, $text . "\nExit: " . $exitCode . "\n", FILE_APPEND);
    if ($mustSucceed && $exitCode !== 0) {
        throw new RuntimeException('Command failed (' . $exitCode . '): ' . $header . $text . "\nLog: " . $log);
    }

    return ['exitCode' => $exitCode, 'output' => $text];
}

function validRef(string $ref): bool
{
    return preg_match('~\A[A-Za-z0-9][A-Za-z0-9._/+\-]*\z~D', $ref) === 1
        && !str_contains($ref, '..');
}

/** Copy the working tree, including nonignored new files and excluding dependencies. */
function copySource(string $source, string $destination, string $log): array
{
    if (!is_dir($source . '/.git') && !is_file($source . '/.git')) {
        throw new RuntimeException('Local source is not a Git checkout: ' . $source);
    }
    $sha = trim(command(['git', 'rev-parse', 'HEAD'], $source, $log)['output']);
    $status = command(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $source, $log)['output'];
    $files = explode("\0", command(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], $source, $log)['output']);
    $files = array_values(array_unique(array_filter($files, static fn (string $file): bool => $file !== '')));
    sort($files, SORT_STRING);
    $hash = hash_init('sha256');
    $count = 0;
    directory($destination);
    foreach ($files as $file) {
        // Keep tracked test fixtures named vendor; omit actual installation directories.
        if (preg_match('~(^|/)\.git(/|$)~', $file) === 1
            || str_starts_with($file, 'vendor/') || $file === 'vendor'
            || str_starts_with($file, 'integration/vendor/') || $file === 'integration/vendor'
            || str_starts_with($file, 'integration/.work/')) {
            continue;
        }
        if (str_starts_with($file, '/') || in_array('..', explode('/', $file), true)) {
            throw new RuntimeException('Unsafe source path: ' . $file);
        }
        $from = $source . '/' . $file;
        $to = $destination . '/' . $file;
        if (!file_exists($from) && !is_link($from)) {
            continue; // A locally deleted tracked file belongs to the dirty snapshot.
        }
        directory(dirname($to));
        if (is_link($from)) {
            $target = readlink($from);
            $resolved = realpath($from);
            if ($target === false || str_starts_with($target, '/')
                || ($resolved !== false && !str_starts_with($resolved, $source . '/'))) {
                throw new RuntimeException('Source symlink leaves its checkout: ' . $from);
            }
            $parts = [];
            foreach (explode('/', dirname($file) . '/' . $target) as $part) {
                if ($part === '..') {
                    if ($parts === []) {
                        throw new RuntimeException('Source symlink leaves its checkout: ' . $from);
                    }
                    array_pop($parts);
                } elseif ($part !== '' && $part !== '.') {
                    $parts[] = $part;
                }
            }
            if (!symlink($target, $to)) {
                throw new RuntimeException('Cannot copy symlink ' . $from);
            }
            hash_update($hash, $file . "\0link\0" . $target . "\0");
        } elseif (is_file($from)) {
            if (!copy($from, $to)) {
                throw new RuntimeException('Cannot copy ' . $from);
            }
            $mode = fileperms($from) & 0777;
            chmod($to, $mode);
            hash_update($hash, $file . "\0" . $mode . "\0" . hash_file('sha256', $to) . "\0");
        } else {
            throw new RuntimeException('Unsupported source entry (for example, a submodule): ' . $from);
        }
        ++$count;
    }

    return ['sha' => $sha, 'dirty' => trim($status) !== '', 'status' => array_values(array_filter(explode("\n", $status))), 'snapshotSha256' => hash_final($hash), 'files' => $count];
}

/** Intersect Composer OR expressions without discarding any declared requirement. */
function intersection(array $constraints): string
{
    $result = [''];
    foreach (array_unique($constraints) as $constraint) {
        $next = [];
        foreach ($result as $left) {
            foreach (preg_split('/\s*\|\|?\s*/', $constraint) as $right) {
                $next[] = trim($left . ' ' . trim($right));
            }
        }
        $result = array_values(array_unique($next));
        if (count($result) > 128) {
            throw new RuntimeException('Too many dependency constraint alternatives.');
        }
    }

    return implode(' || ', $result);
}

/** @param list<array> $manifests */
function installTestBootstrap(string $project, string $vendor, array $manifests): void
{
    directory($project . '/vendor/pestphp/pest/bin');
    $code = "<?php\ndeclare(strict_types=1);\n\$loader = require " . var_export($vendor . '/autoload.php', true) . ";\n";
    foreach ($manifests as $entry) {
        $root = $entry['root'];
        foreach (['autoload', 'autoload-dev'] as $key) {
            $autoload = $entry['manifest'][$key] ?? [];
            foreach (['psr-4' => 'addPsr4', 'psr-0' => 'add'] as $type => $method) {
                foreach ($autoload[$type] ?? [] as $namespace => $paths) {
                    $paths = array_map(static fn (string $path): string => $root . '/' . $path, (array) $paths);
                    $code .= '$loader->' . $method . '(' . var_export($namespace, true) . ', ' . var_export($paths, true) . ", true);\n";
                }
            }
            foreach ($autoload['classmap'] ?? [] as $path) {
                $code .= '$loader->addClassMap(\\Composer\\Autoload\\ClassMapGenerator::createMap(' . var_export($root . '/' . $path, true) . "));\n";
            }
            // Package production files are already registered by Composer. Only the
            // project (Skeleton) and development files need their own registration.
            if ($key === 'autoload-dev' || ($entry['manifest']['type'] ?? '') === 'project') {
                foreach ($autoload['files'] ?? [] as $path) {
                    $code .= 'require_once ' . var_export($root . '/' . $path, true) . ";\n";
                }
            }
        }
    }
    $code .= "return \$loader;\n";
    file_put_contents($project . '/vendor/autoload.php', $code);
    if (!copy($vendor . '/pestphp/pest/bin/pest', $project . '/vendor/pestphp/pest/bin/pest')) {
        throw new RuntimeException('Cannot copy the official Pest entry point.');
    }
}

function junitSummary(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Test runner did not produce JUnit output: ' . $path);
    }
    $document = new DOMDocument();
    if (!$document->load($path, LIBXML_NONET)) {
        throw new RuntimeException('Invalid JUnit output: ' . $path);
    }
    $cases = $document->getElementsByTagName('testcase');
    $assertions = 0;
    foreach ($cases as $case) {
        $assertions += (int) $case->getAttribute('assertions');
    }

    return [
        'tests' => $cases->length,
        'assertions' => $assertions,
        'failures' => $document->getElementsByTagName('failure')->length,
        'errors' => $document->getElementsByTagName('error')->length,
        'skipped' => $document->getElementsByTagName('skipped')->length,
    ];
}
