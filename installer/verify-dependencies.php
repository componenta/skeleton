<?php

declare(strict_types=1);

const ACTIVE_PACKAGE_GROUPS = [
    'BASE_PACKAGES',
    'HTTP_PACKAGES',
    'CQRS_PACKAGES',
    'POLICY_PACKAGES',
    'AUTH_PACKAGES',
    'CYCLE_PACKAGES',
    'CQRS_CYCLE_PACKAGES',
    'TEMPLATE_PACKAGES',
    'WEBSOCKET_PACKAGES',
];

const NON_GROUP_PACKAGE_VERSIONS = [
    'pestphp/pest',
    'phpunit/phpunit',
    'psr/container',
    'symfony/console',
];

$root = dirname(__DIR__);
$composerPath = $root . '/composer.json';
$installerPath = __DIR__ . '/Installer.php';

if (!is_file($composerPath) || !is_file($installerPath)) {
    fwrite(STDERR, "Run this verifier from the skeleton repository.\n");
    exit(1);
}

require_once $installerPath;

try {
    $composer = decodeJsonFile($composerPath);
    assertNoCustomRepositories($composer);

    $reflection = new ReflectionClass(Installer::class);
    $versions = privateArrayConstant($reflection, 'PACKAGE_VERSIONS');
    $groups = [];

    foreach (ACTIVE_PACKAGE_GROUPS as $constant) {
        $groups[$constant] = privateStringListConstant($reflection, $constant);
    }

    $integrations = privateArrayConstant($reflection, 'PSR7_INTEGRATIONS');
    $integrationPackages = [];

    foreach ($integrations as $name => $integration) {
        if (!is_string($name)
            || !is_array($integration)
            || !isset($integration['package'])
            || !is_string($integration['package'])
            || $integration['package'] === ''
        ) {
            throw new RuntimeException('PSR7_INTEGRATIONS contains an invalid entry.');
        }

        $integrationPackages[$name] = $integration['package'];
    }

    assertCompleteVersionMap($versions, $groups, $integrationPackages);
    assertRootConstraintsMatchInstaller($composer, $versions);
    resolvePresetGraphs($versions, $groups, $integrationPackages);

    fwrite(STDOUT, "Skeleton dependency floors and Packagist preset graphs are valid.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @return array<string, mixed>
 */
function decodeJsonFile(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read %s.', $path));
    }

    $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('%s must contain a JSON object.', $path));
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $composer
 */
function assertNoCustomRepositories(array $composer): void
{
    if (isset($composer['repositories']) && $composer['repositories'] !== []) {
        throw new RuntimeException(
            'composer.json must resolve released Componenta packages from Packagist; remove custom repositories.',
        );
    }
}

/**
 * @return array<string, mixed>
 */
function privateArrayConstant(ReflectionClass $reflection, string $name): array
{
    $constant = $reflection->getReflectionConstant($name);

    if ($constant === false) {
        throw new RuntimeException(sprintf('Installer::%s is missing.', $name));
    }

    $value = $constant->getValue();

    if (!is_array($value)) {
        throw new RuntimeException(sprintf('Installer::%s must be an array.', $name));
    }

    return $value;
}

/**
 * @return list<string>
 */
function privateStringListConstant(ReflectionClass $reflection, string $name): array
{
    $value = privateArrayConstant($reflection, $name);

    if (!array_is_list($value)) {
        throw new RuntimeException(sprintf('Installer::%s must be a list.', $name));
    }

    foreach ($value as $package) {
        if (!is_string($package) || $package === '') {
            throw new RuntimeException(sprintf('Installer::%s contains an invalid package.', $name));
        }
    }

    /** @var list<string> $value */
    return $value;
}

/**
 * @param array<string, mixed> $versions
 * @param array<string, list<string>> $groups
 * @param array<string, string> $integrationPackages
 */
function assertCompleteVersionMap(array $versions, array $groups, array $integrationPackages): void
{
    foreach ($versions as $package => $constraint) {
        if (!is_string($package)
            || $package === ''
            || !is_string($constraint)
            || trim($constraint) === ''
        ) {
            throw new RuntimeException('PACKAGE_VERSIONS contains an invalid package constraint.');
        }
    }

    $used = NON_GROUP_PACKAGE_VERSIONS;

    foreach ($groups as $packages) {
        array_push($used, ...$packages);
    }

    array_push($used, ...array_values($integrationPackages));
    $used = array_values(array_unique($used));
    sort($used);

    $defined = array_keys($versions);
    sort($defined);

    $missing = array_values(array_diff($used, $defined));
    $unused = array_values(array_diff($defined, $used));

    if ($missing !== []) {
        throw new RuntimeException('Missing PACKAGE_VERSIONS entries: ' . implode(', ', $missing));
    }

    if ($unused !== []) {
        throw new RuntimeException('Unused PACKAGE_VERSIONS entries: ' . implode(', ', $unused));
    }
}

/**
 * @param array<string, mixed> $composer
 * @param array<string, mixed> $versions
 */
function assertRootConstraintsMatchInstaller(array $composer, array $versions): void
{
    $rootRequirements = array_merge(
        is_array($composer['require'] ?? null) ? $composer['require'] : [],
        is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [],
    );

    $mismatches = [];

    foreach ($rootRequirements as $package => $constraint) {
        if (!is_string($package) || !array_key_exists($package, $versions)) {
            continue;
        }

        if (!is_string($constraint) || $constraint !== $versions[$package]) {
            $mismatches[] = sprintf(
                '%s (composer: %s; installer: %s)',
                $package,
                is_string($constraint) ? $constraint : get_debug_type($constraint),
                (string) $versions[$package],
            );
        }
    }

    if ($mismatches !== []) {
        throw new RuntimeException(
            'Root composer constraints differ from Installer::PACKAGE_VERSIONS: '
            . implode(', ', $mismatches),
        );
    }
}

/**
 * @param array<string, mixed> $versions
 * @param array<string, list<string>> $groups
 * @param array<string, string> $integrationPackages
 */
function resolvePresetGraphs(array $versions, array $groups, array $integrationPackages): void
{
    $base = mergePackageLists(
        $groups['BASE_PACKAGES'],
        ['psr/container', 'symfony/console'],
    );
    $httpBase = mergePackageLists($base, $groups['HTTP_PACKAGES']);
    $webFeatures = mergePackageLists(
        $groups['TEMPLATE_PACKAGES'],
        $groups['CQRS_PACKAGES'],
        $groups['POLICY_PACKAGES'],
    );
    $apiFeatures = mergePackageLists(
        $groups['CQRS_PACKAGES'],
        $groups['POLICY_PACKAGES'],
    );

    $graphs = [
        'cli' => $base,
        'api-nyholm' => mergePackageLists(
            $httpBase,
            [$integrationPackages['nyholm']],
            $apiFeatures,
        ),
        'full' => mergePackageLists(
            $httpBase,
            [$integrationPackages['nyholm']],
            $webFeatures,
            $groups['AUTH_PACKAGES'],
            $groups['CYCLE_PACKAGES'],
            $groups['CQRS_CYCLE_PACKAGES'],
        ),
        'full-websocket' => mergePackageLists(
            $httpBase,
            [$integrationPackages['nyholm']],
            $webFeatures,
            $groups['AUTH_PACKAGES'],
            $groups['CYCLE_PACKAGES'],
            $groups['CQRS_CYCLE_PACKAGES'],
            $groups['WEBSOCKET_PACKAGES'],
        ),
        'websocket' => mergePackageLists($base, $groups['WEBSOCKET_PACKAGES']),
    ];

    foreach ($integrationPackages as $name => $package) {
        $graphs['web-' . $name] = mergePackageLists(
            $httpBase,
            [$package],
            $webFeatures,
        );
    }

    $tempRoot = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'componenta-skeleton-dependency-check-'
        . getmypid();

    if (!mkdir($tempRoot, 0777, true) && !is_dir($tempRoot)) {
        throw new RuntimeException(sprintf('Unable to create %s.', $tempRoot));
    }

    try {
        foreach ($graphs as $name => $packages) {
            $workingDirectory = $tempRoot . DIRECTORY_SEPARATOR . $name;

            if (!mkdir($workingDirectory, 0777, true) && !is_dir($workingDirectory)) {
                throw new RuntimeException(sprintf('Unable to create %s.', $workingDirectory));
            }

            $requirements = ['php' => '^8.4'];

            foreach ($packages as $package) {
                $constraint = $versions[$package] ?? null;

                if (!is_string($constraint)) {
                    throw new RuntimeException(sprintf('Missing constraint for %s.', $package));
                }

                $requirements[$package] = $constraint;
            }

            ksort($requirements);
            $manifest = [
                'name' => 'componenta/skeleton-' . $name . '-verification',
                'type' => 'project',
                'require' => $requirements,
                'minimum-stability' => 'stable',
                'prefer-stable' => true,
                'config' => [
                    'allow-plugins' => [
                        'componenta/composer-plugin' => true,
                    ],
                    'sort-packages' => true,
                ],
            ];
            $json = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n";

            if (file_put_contents($workingDirectory . '/composer.json', $json) === false) {
                throw new RuntimeException(sprintf('Unable to write preset manifest %s.', $name));
            }

            runComposerUpdate($workingDirectory, $name);
        }
    } finally {
        removeDirectoryTree($tempRoot);
    }
}

/**
 * @param list<string> ...$lists
 * @return list<string>
 */
function mergePackageLists(array ...$lists): array
{
    $merged = [];

    foreach ($lists as $list) {
        foreach ($list as $package) {
            $merged[$package] = true;
        }
    }

    return array_keys($merged);
}

function runComposerUpdate(string $workingDirectory, string $graph): void
{
    $command = sprintf(
        'composer --working-dir=%s update --no-interaction --no-progress --no-scripts --no-install --prefer-dist 2>&1',
        escapeshellarg($workingDirectory),
    );
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException(sprintf('Composer could not resolve preset graph "%s".', $graph));
    }
}

function removeDirectoryTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}
