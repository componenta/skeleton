<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\Loader\ArrayLoader;
use Composer\Script\Event;

final class Installer
{
    /**
     * @var array<string, array{label: string, http: bool, websocket: bool, templates: bool, cqrs: bool, policy: bool, auth: bool, cycle: bool}>
     */
    private const array PRESETS = [
        'web' => [
            'label' => 'Web application',
            'http' => true,
            'websocket' => false,
            'templates' => true,
            'cqrs' => true,
            'policy' => true,
            'auth' => false,
            'cycle' => false,
        ],
        'full' => [
            'label' => 'Full application',
            'http' => true,
            'websocket' => false,
            'templates' => true,
            'cqrs' => true,
            'policy' => true,
            'auth' => true,
            'cycle' => true,
        ],
        'api' => [
            'label' => 'HTTP API',
            'http' => true,
            'websocket' => false,
            'templates' => false,
            'cqrs' => true,
            'policy' => true,
            'auth' => false,
            'cycle' => false,
        ],
        'cli' => [
            'label' => 'CLI application',
            'http' => false,
            'websocket' => false,
            'templates' => false,
            'cqrs' => false,
            'policy' => false,
            'auth' => false,
            'cycle' => false,
        ],
        'websocket' => [
            'label' => 'WebSocket application',
            'http' => false,
            'websocket' => true,
            'templates' => false,
            'cqrs' => false,
            'policy' => false,
            'auth' => false,
            'cycle' => false,
        ],
    ];

    /**
     * @var array<string, array{label: string, package: string}>
     */
    private const array PSR7_INTEGRATIONS = [
        'nyholm' => [
            'label' => 'Nyholm PSR-7',
            'package' => 'componenta/http-psr-nyholm',
        ],
        'diactoros' => [
            'label' => 'Laminas Diactoros',
            'package' => 'componenta/http-psr-diactoros',
        ],
        'guzzle' => [
            'label' => 'Guzzle PSR-7',
            'package' => 'componenta/http-psr-guzzle',
        ],
        'slim' => [
            'label' => 'Slim PSR-7',
            'package' => 'componenta/http-psr-slim',
        ],
    ];

    private const array BASE_PACKAGES = [
        'componenta/app',
        'componenta/app-console',
        'componenta/composer-plugin',
        'componenta/config',
        'componenta/error-handler-app',
        'componenta/path-resolver',
    ];

    private const array HTTP_PACKAGES = [
        'componenta/app-http',
        'componenta/http',
        'componenta/http-body-parsing-middleware',
        'componenta/http-psr',
        'componenta/interceptor',
        'componenta/interceptor-app',
        'componenta/router',
        'componenta/router-app',
    ];

    private const array CQRS_PACKAGES = [
        'componenta/cqrs-app',
    ];

    private const array POLICY_PACKAGES = [
        'componenta/policy',
        'componenta/policy-app',
    ];

    private const array AUTH_PACKAGES = [
        'componenta/auth',
    ];

    private const array CYCLE_PACKAGES = [
        'componenta/cycle-app',
    ];

    private const array TEMPLATE_PACKAGES = [
        'componenta/templater-app',
    ];

    private const array WEBSOCKET_PACKAGES = [
        'componenta/clock',
        'componenta/websocket-app',
    ];

    private const array LEGACY_OPTIONAL_PACKAGES = [
        'componenta/caster',
        'componenta/identity',
        'componenta/session',
        'componenta/uuid',
        'componenta/validation',
        'psr/clock',
        'psr/http-message',
        'psr/http-server-middleware',
        'psr/log',
    ];

    private const string DEFAULT_ENV = <<<'ENV'
APP_ENV=development
APP_DEBUG=true

ENV;

    private const array PACKAGE_VERSIONS = [
        'componenta/app' => '^1.0',
        'componenta/app-console' => '^1.0',
        'componenta/app-http' => '^1.0',
        'componenta/auth' => '^1.0',
        'componenta/composer-plugin' => '^1.0',
        'componenta/config' => '^1.0',
        'componenta/cqrs-app' => '^1.0',
        'componenta/clock' => '^1.0',
        'componenta/cycle-app' => '^1.0',
        'componenta/error-handler-app' => '^1.0',
        'componenta/http' => '^1.0',
        'componenta/http-body-parsing-middleware' => '^1.0',
        'componenta/http-psr' => '^1.0',
        'componenta/http-psr-nyholm' => '^1.0',
        'componenta/http-psr-diactoros' => '^1.0',
        'componenta/http-psr-guzzle' => '^1.0',
        'componenta/http-psr-slim' => '^1.0',
        'componenta/interceptor' => '^1.0',
        'componenta/interceptor-app' => '^1.0',
        'componenta/path-resolver' => '^1.0',
        'componenta/policy' => '^1.0',
        'componenta/policy-app' => '^1.0',
        'componenta/router' => '^1.0',
        'componenta/router-app' => '^1.0',
        'componenta/templater-app' => '^1.0',
        'componenta/websocket-app' => '^1.0',
        'pestphp/pest' => '^4.0',
        'phpunit/phpunit' => '^12.0',
        'psr/container' => '^2.0',
        'symfony/console' => '^7.4 || ^8.0',
    ];

    /**
     * @var array<string, string>
     */
    private const array HTTP_STUBS = [
        'http/public/index.php' => 'public/index.php',
        'http/config/pipeline.php' => 'config/pipeline.php',
        'http/config/routes.php' => 'config/routes.php',
    ];

    /**
     * @var array<string, string>
     */
    private const array WELCOME_STUBS = [
        'web/src/Welcome.php' => 'src/Welcome.php',
    ];

    /**
     * @var array<string, string>
     */
    private const array TEMPLATE_STUBS = [
        'templates/welcome.phtml' => 'templates/welcome.phtml',
    ];

    /**
     * @var array<string, string>
     */
    private const array ERROR_TEMPLATE_STUBS = [
        'templates/error/500.phtml' => 'templates/error/500.phtml',
    ];

    /**
     * @var array<string, string>
     */
    private const array WEBSOCKET_STUBS = [
        'websocket/bin/websocket.php' => 'bin/websocket.php',
        'websocket/config/websocket.php' => 'config/websocket.php',
        'websocket/src/WebSocket/WelcomeApplication.php' => 'src/WebSocket/WelcomeApplication.php',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const array TEST_STUBS = [
        'pest' => [
            'tests/phpunit.xml.dist' => 'phpunit.xml.dist',
            'tests/pest/SmokeTest.php' => 'tests/SmokeTest.php',
        ],
        'phpunit' => [
            'tests/phpunit.xml.dist' => 'phpunit.xml.dist',
            'tests/phpunit/SmokeTest.php' => 'tests/SmokeTest.php',
        ],
    ];

    private JsonFile $composerFile;

    private function __construct(
        private readonly IOInterface $io,
        private readonly Composer $composer,
        private array $definition = [],
    ) {
        $this->composerFile = new JsonFile(Factory::getComposerFile());
    }

    public static function install(Event $event): void
    {
        $installer = new self($event->getIO(), $event->getComposer());
        $installer->definition = $installer->composerFile->read();

        $preset = $installer->selectPreset();
        $full = $preset === 'full';
        $http = self::PRESETS[$preset]['http'];
        $psr7 = $http ? ($full ? 'nyholm' : $installer->selectPsr7Integration()) : null;
        $templates = $full ? true : $installer->selectTemplateEngine($preset);
        $cqrs = $full || $installer->confirmFeature('Install CQRS application integration?', self::PRESETS[$preset]['cqrs']);
        $policy = $cqrs && ($full || $installer->confirmFeature('Install policy authorization integration?', self::PRESETS[$preset]['policy']));
        $auth = $http && ($full || $installer->confirmFeature('Install authentication package?', self::PRESETS[$preset]['auth']));
        $cycle = $full || $installer->confirmFeature('Install Cycle ORM integration?', $auth || self::PRESETS[$preset]['cycle']);
        $websocket = self::PRESETS[$preset]['websocket']
            || $installer->confirmAdditionalWebSocket($preset);
        $testRunner = $full ? 'pest' : $installer->selectTestRunner();

        if ($auth) {
            $cqrs = true;
            $policy = true;
        }

        $installer->prepareProject();
        $installer->configureProjectFiles(http: $http, templates: $templates, websocket: $websocket);
        $installer->configureAppConfigProvider(http: $http, cqrs: $cqrs, policy: $policy, cycle: $cycle);
        $installer->configurePackages(
            http: $http,
            psr7: $psr7,
            templates: $templates,
            cqrs: $cqrs,
            policy: $policy,
            auth: $auth,
            cycle: $cycle,
            websocket: $websocket,
        );
        $installer->configureScripts(http: $http, websocket: $websocket);
        $installer->configureTestRunner($testRunner);
        $installer->configureTestFiles($testRunner);
        $installer->removeInstallerClassmapEntry();
        $installer->writeComposerJson();
        $installer->cleanupInstallerArtifacts();
    }

    private function selectPreset(): string
    {
        if (!$this->io->isInteractive()) {
            return 'web';
        }

        return $this->selectNumberedChoice(
            question: 'Choose application preset',
            items: self::PRESETS,
            defaultKey: 'web',
            label: static fn (string $key, array $preset): string => sprintf('%s - %s', $key, $preset['label']),
        );
    }

    private function selectPsr7Integration(): string
    {
        if (!$this->io->isInteractive()) {
            return 'nyholm';
        }

        return $this->selectNumberedChoice(
            question: 'Choose PSR-7 implementation',
            items: self::PSR7_INTEGRATIONS,
            defaultKey: 'nyholm',
            label: static fn (string $key, array $integration): string => sprintf('%s - %s', $key, $integration['label']),
        );
    }

    private function selectTemplateEngine(string $preset): bool
    {
        if (!self::PRESETS[$preset]['http']) {
            return false;
        }

        if (!$this->io->isInteractive()) {
            return self::PRESETS[$preset]['templates'];
        }

        $items = [
            'plates' => ['label' => 'Plates renderer'],
            'none' => ['label' => 'No template renderer'],
        ];

        return $this->selectNumberedChoice(
            question: 'Choose template renderer',
            items: $items,
            defaultKey: self::PRESETS[$preset]['templates'] ? 'plates' : 'none',
            label: static fn (string $key, array $item): string => sprintf('%s - %s', $key, $item['label']),
        ) === 'plates';
    }

    private function selectTestRunner(): string
    {
        if (!$this->io->isInteractive()) {
            return 'pest';
        }

        return $this->selectNumberedChoice(
            question: 'Choose test runner',
            items: [
                'pest' => ['label' => 'Pest'],
                'phpunit' => ['label' => 'PHPUnit'],
            ],
            defaultKey: 'pest',
            label: static fn (string $key, array $item): string => sprintf('%s - %s', $key, $item['label']),
        );
    }

    private function confirmFeature(string $question, bool $default): bool
    {
        return !$this->io->isInteractive()
            ? $default
            : $this->io->askConfirmation(sprintf('%s [%s] ', $question, $default ? 'Y/n' : 'y/N'), $default);
    }

    private function confirmAdditionalWebSocket(string $preset): bool
    {
        if ($preset === 'websocket') {
            return true;
        }

        return $this->confirmFeature('Install WebSocket application integration too?', false);
    }

    /**
     * @template T of array
     *
     * @param array<string, T> $items
     * @param callable(string, T): string $label
     */
    private function selectNumberedChoice(string $question, array $items, string $defaultKey, callable $label): string
    {
        $choices = [];
        $numberToKey = [];
        $defaultNumber = '0';
        $index = 0;

        foreach ($items as $key => $item) {
            $number = (string) $index;
            $choices[$number] = $label($key, $item);
            $numberToKey[$number] = $key;

            if ($key === $defaultKey) {
                $defaultNumber = $number;
            }

            ++$index;
        }

        foreach ($choices as $number => $choice) {
            $this->io->write(sprintf('  [%s] %s', $number, $choice));
        }

        $selected = $this->io->ask(
            sprintf('%s [%s]: ', $question, $defaultNumber),
            $defaultNumber,
        );
        $selectedNumber = is_int($selected) ? (string) $selected : (is_string($selected) ? $selected : null);

        if ($selectedNumber !== null && isset($numberToKey[$selectedNumber])) {
            return $numberToKey[$selectedNumber];
        }

        $choiceNumber = array_search($selected, $choices, true);

        if (is_int($choiceNumber)) {
            return $numberToKey[(string) $choiceNumber] ?? $defaultKey;
        }

        if (is_string($choiceNumber) && isset($numberToKey[$choiceNumber])) {
            return $numberToKey[$choiceNumber];
        }

        return $defaultKey;
    }

    private function prepareProject(): void
    {
        $root = dirname($this->composerFile->getPath());

        foreach (['var/cache/build', 'var/cache/dev', 'var/cache/runtime', 'log', 'storage'] as $path) {
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
                throw new RuntimeException(sprintf('Unable to create directory "%s".', $target));
            }
        }

        $localConfig = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'app.local.php';
        $distConfig = $localConfig . '.dist';

        if (!is_file($localConfig) && is_file($distConfig) && !copy($distConfig, $localConfig)) {
            throw new RuntimeException(sprintf('Unable to copy "%s" to "%s".', $distConfig, $localConfig));
        }

        $env = $root . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($env) && file_put_contents($env, self::DEFAULT_ENV) === false) {
            throw new RuntimeException(sprintf('Unable to write "%s".', $env));
        }

    }

    private function configureProjectFiles(bool $http, bool $templates, bool $websocket): void
    {
        if ($http) {
            $this->copyStubFiles(self::HTTP_STUBS);
            $this->copyStubFiles(self::WELCOME_STUBS);
            $this->copyStubFiles(self::ERROR_TEMPLATE_STUBS);
        }

        if ($http && $templates) {
            $this->copyStubFiles(self::TEMPLATE_STUBS);
        }

        if ($websocket) {
            $this->copyStubFiles(self::WEBSOCKET_STUBS);
        }
    }

    private function configureAppConfigProvider(bool $http, bool $cqrs, bool $policy, bool $cycle): void
    {
        $uses = [
            'Componenta\App\Config\AsConfig',
        ];
        $entries = [];

        if ($http) {
            $uses[] = 'Componenta\Interceptor\AttributeInterceptor';
            $uses[] = 'Componenta\Interceptor\ConfigKey as InterceptorConfigKey';
            $entries[] = $this->renderClassListEntry(
                'InterceptorConfigKey::HTTP_INTERCEPTORS',
                ['AttributeInterceptor::class'],
            );
        }

        if ($cqrs) {
            $uses[] = 'Componenta\CQRS\Command\Middleware\EventMiddleware';
            $uses[] = 'Componenta\CQRS\ConfigKey as CqrsConfigKey';

            $commandMiddlewares = [
                'EventMiddleware::class',
            ];
            $queryMiddlewares = [];

            if ($cycle) {
                $uses[] = 'Componenta\CQRS\Command\Middleware\TransactionMiddleware';
                array_unshift($commandMiddlewares, 'TransactionMiddleware::class');
            }

            if ($policy) {
                $uses[] = 'Componenta\CQRS\Command\Middleware\PolicyMiddleware as CommandPolicyMiddleware';
                $uses[] = 'Componenta\CQRS\Query\Middleware\PolicyMiddleware as QueryPolicyMiddleware';
                array_unshift($commandMiddlewares, 'CommandPolicyMiddleware::class');
                $queryMiddlewares[] = 'QueryPolicyMiddleware::class';
            }

            $entries[] = $this->renderClassListEntry('CqrsConfigKey::COMMAND_MIDDLEWARES', $commandMiddlewares);
            $entries[] = $this->renderClassListEntry('CqrsConfigKey::QUERY_MIDDLEWARES', $queryMiddlewares);
        }

        $content = "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace App;\n\n"
            . implode('', array_map(static fn (string $use): string => "use {$use};\n", $uses))
            . "\n#[AsConfig]\n"
            . "final class ConfigProvider extends \\Componenta\\Config\\ConfigProvider\n"
            . "{\n"
            . "    protected function getConfig(): array\n"
            . "    {\n"
            . ($entries === []
                ? "        return [];\n"
                : "        return [\n" . implode('', $entries) . "        ];\n")
            . "    }\n"
            . "}\n";

        $target = dirname($this->composerFile->getPath()) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ConfigProvider.php';
        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create app config directory "%s".', $dir));
        }

        if (file_put_contents($target, $content) === false) {
            throw new RuntimeException(sprintf('Unable to write "%s".', $target));
        }
    }

    /**
     * @param list<string> $classes
     */
    private function renderClassListEntry(string $key, array $classes): string
    {
        if ($classes === []) {
            return "            {$key} => [],\n";
        }

        return "            {$key} => [\n"
            . implode('', array_map(static fn (string $class): string => "                {$class},\n", $classes))
            . "            ],\n";
    }

    /**
     * @param array<string, string> $files
     */
    private function copyStubFiles(array $files): void
    {
        foreach ($files as $source => $target) {
            $this->copyStubFile($source, $target);
        }
    }

    private function copyStubFile(string $source, string $target): void
    {
        $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);
        $targetPath = dirname($this->composerFile->getPath()) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);

        if (!is_file($sourcePath)) {
            throw new RuntimeException(sprintf('Installer stub "%s" does not exist.', $sourcePath));
        }

        if (file_exists($targetPath)) {
            return;
        }

        $dir = dirname($targetPath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create stub target directory "%s".', $dir));
        }

        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException(sprintf('Unable to copy stub "%s" to "%s".', $sourcePath, $targetPath));
        }
    }

    private function configurePackages(
        bool $http,
        ?string $psr7,
        bool $templates,
        bool $cqrs,
        bool $policy,
        bool $auth,
        bool $cycle,
        bool $websocket,
    ): void {
        $this->removePresetPackages();
        $this->requirePackages(self::BASE_PACKAGES);
        $this->requirePackages(['psr/container', 'symfony/console']);

        if ($http) {
            $this->requirePackages(self::HTTP_PACKAGES);

            if ($psr7 !== null) {
                $this->requirePackages([self::PSR7_INTEGRATIONS[$psr7]['package']]);
            }
        }

        if ($templates) {
            $this->requirePackages(self::TEMPLATE_PACKAGES);
        }

        if ($cqrs) {
            $this->requirePackages(self::CQRS_PACKAGES);
        }

        if ($policy) {
            $this->requirePackages(self::POLICY_PACKAGES);
        }

        if ($auth) {
            $this->requirePackages(self::AUTH_PACKAGES);
        }

        if ($cycle) {
            $this->requirePackages(self::CYCLE_PACKAGES);
        }

        if ($websocket) {
            $this->requirePackages(self::WEBSOCKET_PACKAGES);
        }
    }

    private function configureScripts(bool $http, bool $websocket): void
    {
        if ($http) {
            $this->definition['scripts']['serve'] = '@php -S localhost:8000 -t public/';
            $this->definition['scripts']['analyse'] = '@php vendor/bin/phpstan analyse bin config public src tests';
        } else {
            unset($this->definition['scripts']['serve']);
            $this->definition['scripts']['analyse'] = '@php vendor/bin/phpstan analyse bin config src tests';
        }

        if ($websocket) {
            $this->definition['scripts']['serve:websocket'] = '@php bin/websocket.php';
        } else {
            unset($this->definition['scripts']['serve:websocket']);
        }
    }

    private function configureTestRunner(string $testRunner): void
    {
        unset($this->definition['require-dev']['pestphp/pest'], $this->definition['require-dev']['phpunit/phpunit']);

        if ($testRunner === 'phpunit') {
            $this->definition['require-dev']['phpunit/phpunit'] = self::PACKAGE_VERSIONS['phpunit/phpunit'];
            $this->definition['scripts']['test'] = '@php vendor/bin/phpunit';
            unset($this->definition['config']['allow-plugins']['pestphp/pest-plugin']);
        } else {
            $this->definition['require-dev']['pestphp/pest'] = self::PACKAGE_VERSIONS['pestphp/pest'];
            $this->definition['scripts']['test'] = '@php vendor/bin/pest';
            $this->definition['config']['allow-plugins']['pestphp/pest-plugin'] = true;
        }
    }

    private function configureTestFiles(string $testRunner): void
    {
        $this->copyStubFiles(self::TEST_STUBS[$testRunner]);
    }

    private function writeComposerJson(): void
    {
        unset(
            $this->definition['scripts']['post-root-package-install'],
            $this->definition['scripts']['post-create-project-cmd'],
            $this->definition['scripts']['post-install-cmd'],
        );

        $this->definition['require'] = $this->sortPackages($this->definition['require'] ?? []);
        $this->definition['require-dev'] = $this->sortPackages($this->definition['require-dev'] ?? []);
        $this->definition['config']['allow-plugins']['componenta/composer-plugin'] = true;

        if (($this->definition['scripts'] ?? []) === []) {
            unset($this->definition['scripts']);
        }

        if (($this->definition['require-dev'] ?? []) === []) {
            unset($this->definition['require-dev']);
        }

        if (($this->definition['config']['allow-plugins'] ?? []) === []) {
            unset($this->definition['config']['allow-plugins']);
        }

        $this->composerFile->write($this->definition);
        $this->syncComposerPackage();
        $this->io->write('<info>Componenta skeleton installed.</info>');
    }

    private function cleanupInstallerArtifacts(): void
    {
        $root = dirname($this->composerFile->getPath());

        $this->removePath($root . DIRECTORY_SEPARATOR . 'installer');
        $this->removePath(
            $root
            . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'autoload'
            . DIRECTORY_SEPARATOR . 'app.local.php.dist',
        );
        $this->removePath($root . DIRECTORY_SEPARATOR . '.env.dist');
    }

    private function syncComposerPackage(): void
    {
        $package = $this->composer->getPackage();
        $loaded = (new ArrayLoader())->load($this->definition + [
            'version' => $package->getPrettyVersion(),
        ]);

        $package->setRequires($loaded->getRequires());
        $package->setDevRequires($loaded->getDevRequires());
        $package->setAutoload($this->definition['autoload'] ?? []);
        $package->setDevAutoload($this->definition['autoload-dev'] ?? []);
        $package->setScripts($this->definition['scripts'] ?? []);
        $package->setConfig($this->definition['config'] ?? []);
        $package->setExtra($this->definition['extra'] ?? []);
    }

    private function removeInstallerClassmapEntry(): void
    {
        $classmap = $this->definition['autoload']['classmap'] ?? null;

        if (!is_array($classmap)) {
            return;
        }

        $classmap = array_values(array_filter(
            $classmap,
            static fn(mixed $path): bool => !is_string($path)
                || str_replace('\\', '/', $path) !== 'installer/Installer.php',
        ));

        if ($classmap === []) {
            unset($this->definition['autoload']['classmap']);
            return;
        }

        $this->definition['autoload']['classmap'] = $classmap;
    }

    private function removePath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            $this->unlinkPath($path);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir()
                ? $this->removeDirectory($file->getPathname())
                : $this->unlinkPath($file->getPathname());
        }

        $this->removeDirectory($path);
    }

    private function unlinkPath(string $path): void
    {
        if (@unlink($path) || !file_exists($path)) {
            return;
        }

        $this->io->writeError(sprintf('<warning>Unable to remove installer artifact "%s".</warning>', $path));
    }

    private function removeDirectory(string $path): void
    {
        if (@rmdir($path) || !is_dir($path)) {
            return;
        }

        $this->io->writeError(sprintf('<warning>Unable to remove installer directory "%s".</warning>', $path));
    }

    private function removePresetPackages(): void
    {
        $packages = array_merge(
            self::HTTP_PACKAGES,
            self::CQRS_PACKAGES,
            self::POLICY_PACKAGES,
            self::AUTH_PACKAGES,
            self::CYCLE_PACKAGES,
            self::TEMPLATE_PACKAGES,
            self::WEBSOCKET_PACKAGES,
            self::LEGACY_OPTIONAL_PACKAGES,
            array_column(self::PSR7_INTEGRATIONS, 'package'),
        );

        foreach ($packages as $package) {
            unset($this->definition['require'][$package]);
        }
    }

    /**
     * @param list<string> $packages
     */
    private function requirePackages(array $packages): void
    {
        foreach ($packages as $package) {
            $this->definition['require'][$package] = self::PACKAGE_VERSIONS[$package];
        }
    }

    /**
     * @param array<string, string> $packages
     * @return array<string, string>
     */
    private function sortPackages(array $packages): array
    {
        uksort($packages, static function (string $a, string $b): int {
            if ($a === 'php') {
                return -1;
            }

            if ($b === 'php') {
                return 1;
            }

            if (str_starts_with($a, 'ext-') && !str_starts_with($b, 'ext-')) {
                return -1;
            }

            if (!str_starts_with($a, 'ext-') && str_starts_with($b, 'ext-')) {
                return 1;
            }

            return $a <=> $b;
        });

        return $packages;
    }
}
