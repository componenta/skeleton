<?php

declare(strict_types=1);

set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
    if ((error_reporting() & $level) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

/**
 * Resolve and test the declared Componenta source graph in an isolated workspace.
 * No dependency or generated file is written into an input checkout.
 */

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
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => ['redirect', 1]], $pipes, $cwd);
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

$work = null;
$workspaceCreated = false;
$report = ['startedAt' => gmdate(DATE_ATOM), 'php' => PHP_VERSION, 'status' => 'preparing', 'suites' => [], 'checks' => [], 'presets' => []];
try {
    if (PHP_VERSION_ID < 80513) {
        throw new RuntimeException('PHP 8.5.13 or newer is required.');
    }
    $options = getopt('', ['sources:', 'work:', 'help']);
    if (isset($options['help'])) {
        echo "Usage: php integration/run.php [--sources=/absolute/path/to/repos] [--work=/new/workspace]\n";
        exit(0);
    }
    $inventory = readJson(__DIR__ . '/sources.json');
    $local = isset($options['sources']) ? realpath($options['sources']) : null;
    if (isset($options['sources']) && $local === false) {
        throw new RuntimeException('Local source directory does not exist.');
    }
    $work = $options['work'] ?? (__DIR__ . '/.work/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)));
    if (!str_starts_with($work, '/') || file_exists($work)) {
        throw new RuntimeException('--work must be a new absolute directory.');
    }
    if (in_array('..', explode('/', $work), true)) {
        throw new RuntimeException('Workspace paths must not contain parent-directory traversal.');
    }
    $work = canonicalNewPath($work);
    if ($local !== null && str_starts_with($work, $local . '/')
        && !str_starts_with($work, $local . '/skeleton/integration/.work/')) {
        throw new RuntimeException('A local workspace must be outside the input checkouts or inside Skeleton integration/.work/.');
    }
    directory($work . '/logs');
    $workspaceCreated = true;
    echo 'Workspace: ' . $work . "\n";
    $report['workspace'] = $work;
    $overrideRepo = getenv('COMPONENTA_REPOSITORY') ?: null;
    $overrideRef = getenv('COMPONENTA_REVISION') ?: null;
    if (($overrideRepo === null) !== ($overrideRef === null)) {
        throw new RuntimeException('Set COMPONENTA_REPOSITORY and COMPONENTA_REVISION together.');
    }
    if ($overrideRepo !== null && (!isset($inventory['sources'][$overrideRepo]) || !validRef($overrideRef))) {
        throw new RuntimeException('Caller repository or revision is not in the allowed source inventory.');
    }
    if ($local !== null && $overrideRepo !== null) {
        throw new RuntimeException('Use local working trees or a remote revision override, not both.');
    }
    $manifests = [];
    $sourceLock = ['format' => 1, 'mode' => $local !== null ? 'local-copy' : 'remote', 'sources' => []];
    foreach ($inventory['sources'] as $name => $spec) {
        if (preg_match('~\Acomponenta/[a-z0-9][a-z0-9-]*\z~D', $name) !== 1
            || !validRef($spec['ref']) || preg_match('/\A\d+\.\d+\.\d+\z/D', $spec['version']) !== 1) {
            throw new RuntimeException('Invalid source specification for ' . $name);
        }
        $short = substr($name, strlen('componenta/'));
        $destination = $work . '/sources/' . $short;
        $log = $work . '/logs/source-' . $short . '.log';
        $ref = $name === $overrideRepo ? $overrideRef : $spec['ref'];
        if ($local !== null) {
            $metadata = copySource($local . '/' . $short, $destination, $log);
        } else {
            $clone = $work . '/checkouts/' . $short;
            directory($clone);
            command(['git', 'init', '--quiet'], $clone, $log);
            command(['git', 'remote', 'add', 'origin', 'https://github.com/' . $name . '.git'], $clone, $log);
            command(['git', 'fetch', '--depth=1', '--no-tags', 'origin', $ref], $clone, $log);
            command(['git', 'checkout', '--quiet', '--detach', 'FETCH_HEAD'], $clone, $log);
            $metadata = copySource($clone, $destination, $log);
        }
        $manifest = readJson($destination . '/composer.json');
        if (($manifest['name'] ?? null) !== $name) {
            throw new RuntimeException('Source package name does not match inventory: ' . $name);
        }
        $manifests[$name] = $manifest;
        $sourceLock['sources'][$name] = ['requestedRef' => $ref, 'projectedVersion' => $spec['version']] + $metadata;
        writeJson($work . '/source-lock.json', $sourceLock);
        echo $name . ' ' . substr($metadata['sha'], 0, 12) . ($metadata['dirty'] ? ' (dirty snapshot)' : '') . "\n";
    }

    $constraints = [];
    $add = static function (array $requirements) use (&$constraints): void {
        foreach ($requirements as $name => $constraint) {
            $constraints[$name][] = $constraint;
        }
    };
    $add($inventory['tools']);
    $add($manifests['componenta/skeleton']['require']);
    $versions = [];
    foreach ($inventory['sources'] as $name => $spec) {
        $versions[$name] = $spec['version'];
        // Every inventoried library is installed at its declared projected version;
        // Composer still checks all of its real require/conflict constraints.
        if ($name !== 'componenta/skeleton') {
            $add([$name => $spec['version']]);
        }
        if (isset($spec['test'])) {
            $add($manifests[$name]['require-dev'] ?? []);
        }
    }
    foreach ($inventory['extraSuites'] ?? [] as $suite) {
        $manifest = readJson($work . '/sources/' . $suite['project'] . '/' . $suite['directory'] . '/composer.json');
        $add($manifest['require'] ?? []);
        $add($manifest['require-dev'] ?? []);
    }
    $requirements = array_map(intersection(...), $constraints);
    ksort($requirements);
    writeJson($work . '/composer.json', [
        'name' => 'componenta/build-integration', 'type' => 'project', 'license' => 'MIT',
        'description' => 'Isolated integration graph generated from explicit source inventory',
        'require' => $requirements,
        'repositories' => [['type' => 'path', 'url' => 'sources/*', 'options' => ['symlink' => true, 'versions' => $versions]]],
        'minimum-stability' => $manifests['componenta/skeleton']['minimum-stability'] ?? 'stable', 'prefer-stable' => true,
        'config' => ['allow-plugins' => ['pestphp/pest-plugin' => true, 'componenta/composer-plugin' => false, '*' => false], 'sort-packages' => true],
    ]);
    $composerLog = $work . '/logs/composer.log';
    $report['composer'] = trim(command(['composer', '--version', '--no-ansi'], $work, $composerLog)['output']);
    echo "Resolving the complete runtime and tested-package development requirements...\n";
    command(['composer', 'update', '--no-interaction', '--no-progress', '--prefer-dist', '--no-scripts'], $work, $composerLog);
    command(['composer', 'check-platform-reqs', '--no-interaction'], $work, $composerLog);
    $lock = readJson($work . '/composer.lock');
    $report['installedPackages'] = count($lock['packages']) + count($lock['packages-dev'] ?? []);
    $report['composerLockSha256'] = hash_file('sha256', $work . '/composer.lock');
    $report['sourceLockSha256'] = hash_file('sha256', $work . '/source-lock.json');
    $report['status'] = 'testing';
    writeJson($work . '/report.json', $report);

    $suites = [];
    foreach ($inventory['sources'] as $name => $spec) {
        if (isset($spec['test'])) {
            $short = substr($name, strlen('componenta/'));
            $suites[] = ['name' => $short, 'project' => $short, 'directory' => ''] + $spec['test'];
        }
    }
    $suites = [...$suites, ...($inventory['extraSuites'] ?? [])];
    foreach ($suites as $suite) {
        $base = $work . '/sources/' . $suite['project'];
        $project = $base . ($suite['directory'] === '' ? '' : '/' . $suite['directory']);
        $entries = [['root' => $base, 'manifest' => $manifests['componenta/' . $suite['project']]]];
        if ($project !== $base) {
            $entries[] = ['root' => $project, 'manifest' => readJson($project . '/composer.json')];
        }
        installTestBootstrap($project, $work . '/vendor', $entries);
        $junit = $work . '/logs/' . $suite['name'] . '.xml';
        $log = $work . '/logs/' . $suite['name'] . '.log';
        $binary = $suite['runner'] === 'pest' ? $project . '/vendor/pestphp/pest/bin/pest' : $work . '/vendor/phpunit/phpunit/phpunit';
        $argv = [PHP_BINARY, $binary, ...($suite['args'] ?? []), '--colors=never', '--log-junit=' . $junit];
        echo 'Testing ' . $suite['name'] . "...\n";
        $started = microtime(true);
        $result = command($argv, $project, $log, false);
        $summary = ['exitCode' => $result['exitCode'], 'seconds' => round(microtime(true) - $started, 3)];
        try {
            $summary += junitSummary($junit);
            $summary['passed'] = $result['exitCode'] === 0 && $summary['tests'] > $summary['skipped'] && $summary['failures'] === 0 && $summary['errors'] === 0;
        } catch (Throwable $error) {
            $summary += ['passed' => false, 'error' => $error->getMessage()];
        }
        $report['suites'][$suite['name']] = $summary;
        echo $suite['name'] . ': ' . json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (!$summary['passed']) {
            echo $result['output'] . "\n";
        }
        writeJson($work . '/report.json', $report);
    }
    foreach ($inventory['checks'] ?? [] as $check) {
        $log = $work . '/logs/' . $check['name'] . '.log';
        echo 'Checking ' . $check['name'] . "...\n";
        $result = command([PHP_BINARY, $work . '/vendor/' . $check['binary'], ...$check['args']], $work . '/sources/' . $check['project'], $log, false);
        $report['checks'][$check['name']] = ['passed' => $result['exitCode'] === 0, 'exitCode' => $result['exitCode']];
        echo $result['output'] . "\n";
        writeJson($work . '/report.json', $report);
    }
    foreach ($inventory['presets'] ?? [] as $preset) {
        $log = $work . '/logs/preset-' . $preset . '.log';
        echo 'Resolving actual Installer output for ' . $preset . "...\n";
        try {
            command([PHP_BINARY, __DIR__ . '/generate-preset.php', $work, $preset], $work, $log);
            $project = $work . '/presets/' . $preset;
            command(['composer', 'update', '--no-install', '--no-plugins', '--no-scripts', '--no-progress', '--no-interaction'], $project, $log);
            $lock = readJson($project . '/composer.lock');
            $report['presets'][$preset] = [
                'passed' => true, 'packages' => count($lock['packages']), 'developmentPackages' => count($lock['packages-dev'] ?? []),
                'generatedManifestSha256' => hash_file('sha256', $project . '/composer.generated.json'),
                'composerLockSha256' => hash_file('sha256', $project . '/composer.lock'),
            ];
        } catch (Throwable $error) {
            $report['presets'][$preset] = ['passed' => false, 'error' => $error->getMessage()];
        }
        echo $preset . ': ' . json_encode($report['presets'][$preset], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        writeJson($work . '/report.json', $report);
    }
    $allPassed = array_all($report['suites'], static fn (array $suite): bool => $suite['passed'])
        && array_all($report['checks'], static fn (array $check): bool => $check['passed'])
        && array_all($report['presets'], static fn (array $preset): bool => $preset['passed']);
    $report['status'] = $allPassed ? 'passed' : 'failed';
    $report['finishedAt'] = gmdate(DATE_ATOM);
    writeJson($work . '/report.json', $report);
    echo 'Integration ' . $report['status'] . '; report: ' . $work . "/report.json\n";
    exit($report['status'] === 'passed' ? 0 : 1);
} catch (Throwable $error) {
    $report['status'] = 'failed';
    $report['error'] = $error->getMessage();
    $report['finishedAt'] = gmdate(DATE_ATOM);
    if ($workspaceCreated) {
        writeJson($work . '/report.json', $report);
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
