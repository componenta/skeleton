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

require_once __DIR__ . '/functions.php';

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
    if ($local !== null) {
        // A sources directory may contain aliases to checkouts elsewhere. Protect
        // each physical input root before creating even the workspace log directory.
        $sourceRoots = [$local];
        foreach (array_keys($inventory['sources']) as $name) {
            $root = realpath($local . '/' . substr($name, strlen('componenta/')));
            if ($root !== false && is_dir($root)) {
                $sourceRoots[] = $root;
            }
        }
        $skeleton = realpath($local . '/skeleton');
        $insideBuildWorkspace = $skeleton !== false && str_starts_with($work, $skeleton . '/integration/.work/');
        foreach (array_unique($sourceRoots) as $root) {
            if (str_starts_with($work, rtrim($root, '/') . '/')
                && !($insideBuildWorkspace && ($root === $local || $root === $skeleton))) {
                throw new RuntimeException('A local workspace must be outside the input checkouts or inside Skeleton integration/.work/.');
            }
        }
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
        'config' => [
            'vendor-dir' => 'vendor', 'bin-dir' => 'vendor/bin',
            'allow-plugins' => ['pestphp/pest-plugin' => true, 'componenta/composer-plugin' => false, '*' => false],
            'sort-packages' => true,
        ],
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
