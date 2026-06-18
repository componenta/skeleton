# Componenta Skeleton

Componenta Skeleton is the starter distribution of Componenta Framework for PHP 8.4+ applications. It provides a ready project with entry points, configuration, container wiring, error handling, class discovery, and HTTP, API, CLI, and WebSocket presets.

The skeleton shows how the framework assembles an application from Componenta packages: Composer discovers package providers, configuration merges them with project files, the container builds services, and `Runner` starts the selected execution scope: HTTP, CLI, or WebSocket.

## Installation

```bash
composer create-project componenta/skeleton my-app
```

During `composer create-project`, `Installer::install()` runs before dependency resolution and prepares the package set selected by the preset. In interactive mode the installer asks questions; in non-interactive mode it uses defaults. After the preset is selected, it adds the matching Composer requirements, writes entry points, and removes installer-only files.

Details: [`componenta/composer-plugin`](https://github.com/componenta/composer-plugin/blob/main/README.md) describes Composer provider discovery, and [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes the application runtime.

## Presets

| Preset | Created files and behavior |
|---|---|
| Web | HTTP application with `public/index.php`, routing, `config/routes.php`, `config/pipeline.php`, `composer serve`, and templates when a template renderer is selected. |
| Full | Web application with default selectable packages plus CQRS, policies, authentication, Cycle ORM, and an optional WebSocket server. |
| API | HTTP application with routing and a JSON welcome response, by default without template files. |
| CLI | Console application without HTTP, WebSocket, routing, or public entry point. |
| WebSocket | WebSocket application with `bin/websocket.php` and WebSocket configuration, without HTTP public entry point. |

In interactive mode, selection prompts use numbered choices: enter `0` for the first option, `1` for the second option, and so on. HTTP presets ask for a PSR-7 implementation: Nyholm, Diactoros, Guzzle, or Slim. HTTP presets also ask for a template renderer: Plates by default for Web, no renderer by default for API. The test runner is selected during installation: Pest by default, or PHPUnit. The Full preset uses the default selectable packages: Nyholm PSR-7, Plates, and Pest; it still asks whether to add the WebSocket server.

In non-interactive mode, the installer uses the Web preset with Nyholm PSR-7, templates, Pest, CQRS, and policies. Authentication, Cycle ORM, and the WebSocket add-on are disabled by default.

Details: [`componenta/http-psr`](https://github.com/componenta/http-psr/blob/main/README.md) describes HTTP factories, [`componenta/http-psr-nyholm`](https://github.com/componenta/http-psr-nyholm/blob/main/README.md) documents one PSR-7 integration, and [`componenta/templater-app`](https://github.com/componenta/templater-app/blob/main/README.md) describes template integration.

## Installer Options

After the preset is selected, the installer configures project requirements and files:

- HTTP presets create `public/index.php`, `config/routes.php`, `config/pipeline.php`, `src/Welcome.php`, and the safe error template `templates/error/500.phtml`;
- HTTP presets create `templates/welcome.phtml` and install `componenta/templater-app` when templates are selected;
- the CLI preset does not create HTTP or WebSocket infrastructure;
- the WebSocket preset creates `bin/websocket.php`, `config/websocket.php`, and the starter application `src/WebSocket/WelcomeApplication.php`;
- CQRS, policies, authentication, Cycle ORM, and the WebSocket add-on can be enabled or disabled interactively; the Full preset enables CQRS, policies, authentication, and Cycle ORM automatically;
- when authentication is enabled, CQRS and policies are forced on;
- CLI commands are executed through `php bin/console.php`.

Details: [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.md) describes command and query integration, [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.md) describes policy integration, [`componenta/auth`](https://github.com/componenta/auth/blob/main/README.md) describes authentication, and [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.md) describes Cycle ORM.

## Application Lifecycle

1. The outer entry point (`public/index.php`, `bin/console.php`, or `bin/websocket.php`) loads Composer autoload, creates a `PathResolver`, and calls `Componenta\App\run()`.
2. `Componenta\App\run()` receives a `Scope`: `Scope::HTTP`, `Scope::CLI`, or `Scope::WEBSOCKET`.
3. `config/container.php` calls `ConfigFactory::create()` and `ContainerFactory::create()`.
4. `config/config.php` returns a `ConfigDefinition`: package config providers and discovery directories.
5. The container is built from configuration, discovered classes, and services contributed by packages.
6. `Componenta\App\run()` reads `Componenta\Config\Config` from the container and wraps both objects into `Componenta\Config\ContainerValue`.
7. `Runner::run()` receives the active `Scope` and that `ContainerValue`, selects the adapter for the current scope, builds the scope target, and starts the application.
8. Bootloaders receive `BootContext`, which contains `ContainerValue`, the active scope, and the scope-specific target. They prepare the selected scope by registering commands, routes, handlers, listeners, templates, or WebSocket applications.

The order is the same for every preset. Only the execution scope and installed integration packages change.

Class discovery is part of this lifecycle. `ClassDiscoveryBootloader` restores prepared discovery data in production-like environments and runs development discovery when the application is allowed to scan source files.

Details: [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes `Scope`, `Runner`, adapters, and bootloaders; [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.md) describes HTTP scope; [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.md) describes CLI scope; [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.md) describes WebSocket scope.

## Configuration

The main configuration file is `config/config.php`. It loads package providers, attribute providers, console command configuration, and project autoload configuration:

```php
return new ConfigDefinition(
    providers: [
        new ComposerPackageConfigProvider($paths->resolve('config/componenta-providers.php')),
        new AttributeConfigProvider(),
        new FileProvider($paths->resolve('config/console.php')),
        new FileProvider($paths->resolve('config/autoload/{{,*.}global,{,*.}local}.{php,yaml,json}')),
    ],
    discovery: new DiscoveryDefinition(
        directories: ['src'],
    ),
);
```

`ComposerPackageConfigProvider` loads providers generated from installed packages. `AttributeConfigProvider` loads configuration from attributes discovered in project code. `config/console.php` registers project console commands in the same config graph. The autoload `FileProvider` loads project files from `config/autoload`.

`ConfigDefinition` also defines the class-discovery area. `DiscoveryDefinition::directories` lists directories that may be scanned; paths may be relative to the application base directory or absolute. `DiscoveryDefinition::exclude` lists directory or file patterns that must be excluded from scanning, for example generated code, temporary classes, or integrations that should not participate in attribute discovery.

```php
discovery: new DiscoveryDefinition(
    directories: ['src'],
    exclude: ['src/Generated', 'src/Legacy'],
),
```

### How Final Configuration Is Built

`ConfigFactory::create()` first loads `.env` from the application root and allows it to override existing environment values. When `.env` is absent, process environment values are used. After that, behavior depends on `APP_ENV`.

In `APP_ENV=development`, the factory:

1. loads `config/config.php` and receives a `ConfigDefinition`;
2. performs the first provider pass to obtain base configuration and compute `CacheLayout`;
3. runs class discovery from `DiscoveryDefinition`, when configured;
4. passes discovered classes to providers that implement `DiscoveryAwareConfigProviderInterface`;
5. wraps `AttributeConfigProvider` in a cached provider when an attribute-config cache file is available;
6. applies compile-delta cache prepared by a previous build, when present;
7. merges providers into the final `Componenta\Config\Config`.

Provider order matters: later providers may extend or override earlier values when the corresponding package merge rules allow it. The default skeleton loads installed package providers first, then project `#[AsConfig]` providers, then `config/console.php`, then files from `config/autoload`.

When `APP_ENV` is not `development`, `ConfigFactory` does not read `config/config.php`, instantiate providers, or scan `src`. It loads the prepared `var/cache/build/config.cache.php` created by `app:build`. Production startup is therefore deterministic: it depends on build cache, not runtime discovery.

The final `Config` object is passed into `ContainerFactory`, registered under `Componenta\Config\Config::class` and the `'config'` alias, and is available to factories as `$container->config` when a factory types its argument as `Componenta\Config\ContainerValue`. `ContainerValue` is also what application bootloaders receive through `BootContext::$container`.

`*.global.*` files are shared project configuration. `*.local.*` files are local environment configuration and are normally not committed. The installer creates `config/autoload/app.local.php` from the packaged local template and removes `app.local.php.dist` from the installed project.

Details: [`componenta/config`](https://github.com/componenta/config/blob/main/README.md) describes config providers and file loading, and [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes `ConfigFactory`, environment handling, and cache layout.

## Config Providers And `#[AsConfig]`

A config provider is a callable class that returns a configuration array. Packages use providers to register factories, aliases, autowiring, bootloaders, middleware, compiler contributors, and their own config keys.

Package providers are wired by `componenta/composer-plugin`: each package declares provider classes in `extra.componenta.config-providers`, the plugin collects them into `config/componenta-providers.php`, and `ComposerPackageConfigProvider` loads that file.

Project config can live in `src/` and be marked with `#[AsConfig]`. A minimal project provider looks like this:

```php
namespace App;

use App\Boot\WarmupBootloader;
use Componenta\App\Config\AsConfig;
use Componenta\App\ConfigKey;

#[AsConfig]
final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::BOOTLOADERS => [
                WarmupBootloader::class,
            ],
        ];
    }
}
```

`AttributeConfigProvider` finds classes marked with `#[AsConfig]` in discovery directories, creates the class without constructor arguments, invokes it, and merges the returned array into application config. The provider must return an array or iterable. Use `config/autoload/*.local.php` for machine-local settings; use `#[AsConfig]` for package or application-module configuration.

`#[AsConfig]` can target classes, functions, or methods, but the current `AttributeConfigProvider` scans discovered classes and invokes providers placed on classes. In the skeleton, the supported primary pattern is a class with `__invoke()` under `src/`.

During `composer create-project`, `Installer::install()` runs on `post-root-package-install`, before Composer resolves the selected application dependencies. It rewrites `composer.json` for the chosen preset and synchronizes the active Composer root package, so only the selected PSR-7 implementation and optional packages participate in dependency resolution. `src/ConfigProvider.php` is generated for the selected preset. HTTP presets register `InterceptorConfigKey::HTTP_INTERCEPTORS` with `AttributeInterceptor::class`. CQRS presets register `CqrsConfigKey::COMMAND_MIDDLEWARES` and `CqrsConfigKey::QUERY_MIDDLEWARES`; when policies are selected, policy middleware is included in those chains. CLI and WebSocket-only presets keep this provider minimal and do not create HTTP or CQRS configuration.

Details: [`componenta/config`](https://github.com/componenta/config/blob/main/README.md) describes the base `ConfigProvider`, and [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes `AttributeConfigProvider` and discovery.

## Package Discovery

Componenta packages declare config providers in `composer.json` under `extra.componenta.config-providers`. `componenta/composer-plugin` reads this metadata after `composer install`, `composer update`, and `composer dump-autoload`, then writes `config/componenta-providers.php`.

The generated file returns an array of provider classes and must not be edited manually. The installer does not write it directly: the file appears or changes when the Composer plugin runs. Before the plugin runs for the first time, the file may be missing; `ComposerPackageConfigProvider` then returns an empty configuration. When a package is removed from Composer, its provider disappears from the generated file on the next Composer event.

Details: [`componenta/composer-plugin`](https://github.com/componenta/composer-plugin/blob/main/README.md) describes metadata format, Composer events, and atomic provider file writes.

## Class Discovery

`DiscoveryDefinition` tells the framework which directories to scan in development mode. The skeleton scans `src`. Discovered classes are passed to packages that understand attributes:

- `componenta/app-console` discovers console commands marked with `#[AsCommand]` in development mode;
- `componenta/router-app` discovers HTTP routes;
- `componenta/cqrs-app` discovers command and query handlers;
- `componenta/policy-app` prepares policy maps;
- `componenta/interceptor-app` prepares interceptor maps.

Discovery is implemented through class listeners. A package registers a listener in its config provider, the application creates a `ClassListenerProvider`, and `ClassDiscoveryBootloader` coordinates the lifecycle:

1. When build cache is available for the current environment, the bootloader restores compiled listener state from cache.
2. In development mode, if restore is not possible, the bootloader scans configured discovery directories through `ClassIteratorInterface`.
3. `ClassListenerNotifier` passes each discovered `ClassInfo` to registered listeners.
4. After all classes are handled, every `FinalizableListenerInterface` is finalized exactly once.
5. Finalized listener state can then be used by runtime locators, routers, CQRS maps, policy maps, or interceptor maps.

Finalizable listeners separate collection from runtime use. `handle()` collects raw class metadata, while `finalize()` builds the stable data structure used by the application. A listener that supports compilation exposes `FinalizationStateInterface`; build compilers check that such listener is finalized before it is serialized. Calling `finalize()` more than once may throw `FinalizationExceptionInterface`; one-shot listeners throw `ListenerAlreadyFinalizedException`.

Compilers are registered by integration packages as build contributors. They do not scan classes themselves. A compiler receives the already-finalized listener or locator state, validates that it is safe to compile, and writes a PHP artifact that can be required during production startup. The matching restorer reads that artifact and injects the prepared state back into the runtime service. This keeps the responsibilities explicit: listeners discover, finalizers make the data complete, compilers persist it, and restorers load it.

This keeps production startup deterministic: production should not scan project source files to discover routes, commands, handlers, policies, or interceptors. It should restore data prepared by `app:build` from `var/cache/build`.

Details: [`componenta/class-finder`](https://github.com/componenta/class-finder/blob/main/README.md) describes class discovery; [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md), [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.md), [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.md), and [`componenta/interceptor-app`](https://github.com/componenta/interceptor-app/blob/main/README.md) describe their discovery maps.

## Development And Build Modes

By default `.env.dist` contains:

```dotenv
APP_ENV=development
APP_DEBUG=true
```

With `APP_ENV=development`, the application assembles configuration from providers, files, and attributes, scans configured directories, and uses development caches to make repeated starts faster.

When `APP_ENV` is not `development`, `ConfigFactory` assumes the application runs from build cache and reads `var/cache/build/config.cache.php`. In that mode, the project config definition is not rebuilt on every request. The build cache must exist before the application is started in that environment; otherwise startup fails with a configuration error.

With `APP_ENV=production`, the container tries to use prepared `var/cache/build/container.cache.php`. If an optimized `var/cache/build/container.factory.php` is generated by an external build step and the selected container cache mode allows it, `ContainerFactory` can use that factory file too. The standard `app:build` command writes `config.cache.php` and `container.cache.php`; it does not generate `container.factory.php`.

`app:build` is the production preparation command. It must run with `APP_ENV=development`, because the command builds from source configuration and development discovery metadata. Run it before switching an installation to a non-development environment:

```bash
APP_ENV=development php bin/console.php app:cache:clear --build
APP_ENV=development php bin/console.php app:build
APP_ENV=production php bin/console.php list
```

The build is intentionally run while development discovery is available. The command refuses to run from production cache. It assembles package providers, project providers, `#[AsConfig]` providers, command configuration, class-discovery listeners, compiled `#[Boot]` invocations, route maps, CQRS maps, policy maps, interceptor maps, config cache, and container cache. After that, production reads prepared files instead of repeating discovery work.

The main build artifacts live under `var/cache/build/`:

| File | Created by | Purpose |
|---|---|---|
| `config.cache.php` | `app:build` | Exported final `Config`, including discovery compiler output. |
| `container.cache.php` | `app:build` | Normalized DI dependency graph used by `ContainerFactory`. |
| `routes.cache.php` | `componenta/router-app` compiler, when routing is installed | Compiled route table restored without scanning route attributes. |
| `policies.cache.php` | `componenta/policy-app` compiler, when policies are installed | Compiled policy map. |
| `interceptors.cache.php` | `componenta/interceptor-app` compiler, when interceptors are installed | Compiled interceptor attribute map. |
| `discovery.cache.php` / `di-plans.cache.php` | discovery and DI compile contributors, when configured | Additional compile artifacts used by framework integrations. |
| `preload.php` | `app:preload` | Optional PHP preload file built from existing build artifacts. |

If a production-like environment starts without the required build cache, startup should fail with a clear configuration error instead of silently scanning source files.

`app:preload` can be run after `app:build` when the deployment uses PHP preload. The generated preload file is based on build-cache artifacts.

`APP_DEBUG` controls whether detailed error information is shown to users by runtime HTTP error handling. The generated HTTP entry point also catches failures that happen before the container starts, writes them with `error_log()`, returns status `500`, and renders `templates/error/500.phtml`. That bootstrap-safe page is used regardless of `APP_DEBUG`, because the normal error renderer may not be available yet.

Details: [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes `ConfigFactory`, `CacheLayout`, and compile support; [`componenta/error-handler-app`](https://github.com/componenta/error-handler-app/blob/main/README.md) describes HTTP error handling and safe rendering.

## Container

`config/container.php` is the application composition point. It loads configuration and returns a PSR-11 container:

```php
$result = ConfigFactory::create(
    paths: $paths,
    definition: static fn () => require $paths->resolve('config/config.php'),
);

return ContainerFactory::create($paths, $result->config, $result->discovered);
```

`ContainerFactory` adds `PathResolverInterface`, discovered classes, and services declared by providers. It also registers the final `Config` and makes it available through `ContainerValue`, the typed wrapper used by framework factories and bootloaders. The project can extend the container through `config/autoload/*.php` files or through `App\ConfigProvider` marked with `#[AsConfig]`.

Factory callables may type their first argument as either `Psr\Container\ContainerInterface` or `Componenta\Config\ContainerValue`. New application factories should prefer `ContainerValue` when they need optional lookups or configuration access:

```php
use Componenta\Config\ConfigPath;
use Componenta\Config\ContainerValue;
use Psr\Log\LoggerInterface;

static function (ContainerValue $container): App\Service\Reporter {
    return new App\Service\Reporter(
        logger: $container->get(LoggerInterface::class, LoggerInterface::class),
        enabled: $container->config->bool(new ConfigPath('reporting.enabled'), true),
    );
}
```

Details: [`componenta/di`](https://github.com/componenta/di/blob/main/README.md) describes the DI container, factories, attributes, and property resolvers; [`componenta/config`](https://github.com/componenta/config/blob/main/README.md) describes config array shape.

## Application Config

The final application configuration is represented by `Componenta\Config\Config`. `ConfigFactory::create()` creates it, and `ContainerFactory` stores the same object in the container under `Config::class` and the `'config'` alias.

```php
use Componenta\Config\Config;
use Componenta\Config\ConfigPath;

/** @var \Psr\Container\ContainerInterface $container */
$config = $container->get(Config::class);

$name = $config->string(new ConfigPath('app.name'), 'Componenta App');
$debug = $config->bool(new ConfigPath('app.debug'), false);
```

Services can receive the full config through constructor injection:

```php
namespace App\Service;

use Componenta\Config\Config;
use Componenta\Config\ConfigPath;

final readonly class FeatureFlags
{
    public function __construct(
        private Config $config,
    ) {}

    public function enabled(string $name): bool
    {
        return $this->config->bool(new ConfigPath("features.$name"), false);
    }
}
```

If a service needs one value, use the DI `#[Config]` attribute. A string key is read literally, while `ConfigPath` enables dot-notation traversal of nested arrays:

```php
namespace App\Service;

use Componenta\Config\ConfigPath;
use Componenta\DI\Attribute\Config;

final readonly class MailerOptions
{
    public function __construct(
        #[Config(new ConfigPath('mail.from'))]
        public string $from,

        #[Config(new ConfigPath('mail.retries'), default: 3)]
        public int $retries,
    ) {}
}
```

Main `Config` methods:

| Method | Purpose |
|---|---|
| `get(string\|ConfigPath $key, mixed $default = DefaultValue::None)` | Returns a raw value. Without a default, throws when the key is missing. |
| `has(string\|ConfigPath $key)` | Checks whether a key exists. |
| `string()`, `int()`, `float()`, `bool()`, `array()` | Return a value with type conversion. |
| `only(string\|ConfigPath\|array $keys)` | Returns a new `Config` containing only selected keys. |
| `except(string\|ConfigPath\|array $keys)` | Returns a new `Config` without selected keys. |
| `toArray()` | Returns the full config array. |

`get('database.host')` looks for the literal `$config['database.host']` key. Nested access requires `new ConfigPath('database.host')`, which reads `$config['database']['host']`.

`Config` also exposes the `environment` property. Use it to read variables loaded from `.env` or the process environment:

```php
$env = $config->environment;

$isProduction = $env?->match('APP_ENV', 'production') ?? false;
$timezone = $env?->string('APP_TIMEZONE', 'UTC') ?? 'UTC';
```

Config files and providers usually return arrays instead of reading `Config`. Reading the assembled `Config` belongs in services, factories, and bootloaders after all providers have been merged.

Details: [`componenta/config`](https://github.com/componenta/config/blob/main/README.md) describes `Config`, `ConfigPath`, `Environment`, file loading, and merge rules; [`componenta/di`](https://github.com/componenta/di/blob/main/README.md) describes the `#[Config]` attribute.

## Application Bootloaders

An application bootloader runs startup work before the current scope starts: HTTP, CLI, or WebSocket. Use bootloaders for work that needs the built container and the prepared application target: wiring the HTTP pipeline, registering console commands, restoring discovery maps, assigning a WebSocket application, or running application warmup.

`Runner` creates `BootContext`, `BootloaderProvider` reads class names from `ConfigKey::BOOTLOADERS`, filters them by `Scope`, resolves matching bootloaders from the container, and calls `boot()`:

```php
use Componenta\App\ConfigKey;

return [
    ConfigKey::BOOTLOADERS => [
        App\Boot\WarmupBootloader::class,
    ],
];
```

In the skeleton, this registration lives in `src/ConfigProvider.php`, which is marked with `#[AsConfig]`. To add a custom bootloader, add it to `getConfig()` and register the class as an autowired service:

```php
namespace App;

use App\Boot\WarmupBootloader;
use App\Service\WarmupService;
use Componenta\App\Config\AsConfig;
use Componenta\App\ConfigKey;

#[AsConfig]
final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::BOOTLOADERS => [
                WarmupBootloader::class,
            ],
        ];
    }

    protected function getAutowires(): array
    {
        return [
            WarmupBootloader::class,
            WarmupService::class,
        ];
    }
}
```

Application bootloaders can extend the base `Bootloader` class. In that mode `__invoke()` is called through DI, so method parameters can be resolved from the container:

```php
namespace App\Boot;

use App\Service\WarmupService;
use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\Bootloader;
use Componenta\App\Scope;
use Componenta\Config\ConfigPath;
use Componenta\Scope\Scopes;

final class WarmupBootloader extends Bootloader
{
    public Scopes $scopes {
        get => Scopes::of(Scope::HTTP);
    }

    public function supports(BootContext $context): bool
    {
        return $context->container->config->bool(new ConfigPath('warmup.enabled'), false);
    }

    public function __invoke(WarmupService $warmup): void
    {
        $warmup->run();
    }
}
```

If you need full control, implement `BootloaderInterface` directly. Inside `boot()`, `BootContext::$container`, `BootContext::$scope`, and `BootContext::target()` are available. `BootContext::$container` is a `Componenta\Config\ContainerValue`, not a raw PSR-11 container, so it provides service lookup, typed lookup helpers, optional `find()` fallback handling, and the merged application config through `$context->container->config`:

```php
namespace App\Boot;

use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\BootloaderInterface;
use Componenta\App\Boot\Target\HttpBootTargetInterface;
use Componenta\App\Scope;

final class ExtraHttpPipelineBootloader implements BootloaderInterface
{
    public function boot(BootContext $context): void
    {
        $http = $context->target(HttpBootTargetInterface::class);
        $http->pipe(\App\Http\Middleware\RequestIdMiddleware::class);
    }

    public function supports(BootContext $context): bool
    {
        return $context->scope === Scope::HTTP;
    }
}
```

For ordinary global HTTP middleware, prefer `config/pipeline.php`. A custom HTTP bootloader is useful when registration depends on the container, configuration, or an integration package. For CLI use `ConsoleBootTargetInterface`; for WebSocket use `WebSocketBootTargetInterface`.

Framework packages also use bootloaders to prepare metadata-dependent runtime services. `ClassDiscoveryBootloader` restores or builds the class-discovery listener state before route matching, command discovery, CQRS dispatch, policy checks, or interceptor lookup need that state. Application code should not scan classes from controllers, command handlers, or middleware; attribute-driven framework metadata belongs to listeners and build cache.

### Boot methods

For small startup hooks, a discovered class can expose public methods marked with `#[Boot]`. The method is called before the selected application scope starts. Use this for lightweight warmup or registration logic that belongs to an application class and needs container/config values.

```php
namespace App;

use Componenta\App\Boot\Boot;
use Componenta\DI\Attribute\Config;
use Componenta\DI\Attribute\Env;
use Componenta\DI\Attribute\EntryId;

final class Welcome
{
    #[Boot(
        priority: 20,
        params: [
            'service' => new EntryId(AppWarmup::class),
            'name' => new Config('app.name', default: 'Componenta'),
            'debug' => new Env('APP_DEBUG', default: false),
        ],
    )]
    public static function boot(AppWarmup $service, string $name, bool $debug): void
    {
        $service->prepare($name, $debug);
    }
}
```

Boot methods are ordered by descending `priority`. Their `params` array may contain plain values or DI metadata objects:

- `EntryId` resolves a service from the container;
- `Config` reads from the assembled application config;
- `Env` reads from the environment attached to the config.

In development, `BootMethodInvocation` is a class-discovery listener: it reads `#[Boot]` attributes while `ClassDiscoveryBootloader` scans configured directories and then invokes the finalized list. During `app:build`, `BootInvocationCompiler` writes the finalized list to the build config cache. In production, `CompiledBootInvocationBootloader` runs only when `APP_ENV=production` and executes the compiled list from `ConfigKey::BOOT_INVOCATIONS`; the development listener is skipped, so boot methods are not executed twice and production does not scan source files.

Details: [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes `BootContext`, `BootloaderInterface`, `BootloaderProvider`, and boot targets; [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.md), [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.md), and [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.md) show scope-specific bootloaders.

## HTTP And Routing

HTTP presets create `public/index.php`, `config/routes.php`, and `config/pipeline.php`. The public entry point starts `Scope::HTTP`. The `config/pipeline.php` file defines the global HTTP pipeline:

```php
$app->pipe(Componenta\Error\Http\Middleware\ErrorHandlerMiddleware::class, priority: 100);
$app->pipe(Componenta\Http\Middleware\BodyParsingMiddleware::class, priority: 100);
```

`componenta/router-app` adds `MatchRouteMiddleware` and `DispatchRouteMiddleware` through `RoutingBootloader` with priority `50`, so the starter error handler and body parser run before route matching. Custom middleware can use the same priority mechanism: higher numbers run earlier.

`componenta/router-app` uses `config/routes.php` as the default manual route file. The file receives `$routes` as a `Componenta\Http\Router\Routes` instance. Use it for routes that are easier to define programmatically: route groups, shared prefixes, shared middleware, shared `tokens` and `defaults`, manual `RouteRecord` instances, and nested groups.

```php
use App\Http\AdminDashboard;
use App\Http\AdminUsers;
use App\Http\Middleware\RequireAdminMiddleware;
use App\Http\Middleware\RequireAuthenticationMiddleware;

/**
 * @var \Componenta\Http\Router\Routes $routes
 */

$admin = $routes->group(
    name: 'admin',
    prefix: '/admin',
    middleware: [
        RequireAuthenticationMiddleware::class,
        RequireAdminMiddleware::class,
    ],
    tokens: ['id' => '\d+'],
);

$admin->get('dashboard', '/', AdminDashboard::class);
$admin->get('users.show', '/users/{id}', AdminUsers::class);
```

The group prefixes route names and paths. In the example, final names are `admin.dashboard` and `admin.users.show`, and paths are `/admin` and `/admin/users/{id}`. Group settings are inherited by nested groups and routes.

Routes can be added declaratively with `#[Route]` or manually in `config/routes.php`. The starter route `/` lives in `src/Welcome.php`: when templates are selected it renders `templates/welcome.phtml`, otherwise it returns JSON:

```json
{"status":"ok","message":"Componenta Framework skeleton is running."}
```

Details: [`componenta/router`](https://github.com/componenta/router/blob/main/README.md) describes the router; [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) describes route attribute discovery; [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.md) describes the HTTP adapter; [`componenta/http`](https://github.com/componenta/http/blob/main/README.md) describes base HTTP contracts and exceptions.

## HTTP Middleware

Global middleware is registered in `config/pipeline.php`. `HttpBootloader` includes this file, and `$app` implements `HttpBootTargetInterface`. Each `$app->pipe(...)` call adds middleware to the application-wide HTTP pipeline. The optional `priority` argument controls ordering; higher priority middleware runs earlier:

```php
use App\Http\Middleware\RequestIdMiddleware;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Componenta\Http\Middleware\BodyParsingMiddleware;

/**
 * @var \Componenta\App\Boot\Target\HttpBootTargetInterface $app
 */

$app->pipe(ErrorHandlerMiddleware::class, priority: 100);
$app->pipe(RequestIdMiddleware::class, priority: 100);
$app->pipe(BodyParsingMiddleware::class, priority: 100);
```

Order matters: middleware runs by priority, and definitions with the same priority keep their registration order. Error handling is usually first so it can catch exceptions from later layers. `BodyParsingMiddleware` must run before handlers that use `#[MapRequestPayload]`, because it fills the parsed body on the PSR-7 request.

The base HTTP preset installs `ErrorHandlerMiddleware` and `BodyParsingMiddleware`. Additional framework packages provide ready middleware: `CorsMiddleware`, `CsrfMiddleware`, `ThrottleMiddleware`, and `TrustedProxyMiddleware`. They can be placed in the global pipeline or on specific route groups and routes when the package is installed and its provider is loaded by the Composer plugin. `App\Http\Middleware\...` classes in the examples below are application PSR-15 middleware classes that you create in the project.

Group middleware is registered on a route group in `config/routes.php`. It applies to every route in the group and is inherited by nested groups:

```php
use App\Http\AdminDashboard;
use App\Http\AdminUsers;
use App\Http\Middleware\RequireAdminMiddleware;
use App\Http\Middleware\RequireAuthenticationMiddleware;

/**
 * @var \Componenta\Http\Router\Routes $routes
 */

$admin = $routes->group(
    name: 'admin',
    prefix: '/admin',
    middleware: [
        RequireAuthenticationMiddleware::class,
        RequireAdminMiddleware::class,
    ],
);

$admin->get('dashboard', '/', AdminDashboard::class);
$admin->get('users.show', '/users/{id}', AdminUsers::class);
```

Route-specific middleware is registered with the `middlewares` argument on `#[Route]` or manually with `RouteRecord`. Route middleware is appended after group middleware:

```php
namespace App\Http;

use App\Http\Middleware\AuditPostAccessMiddleware;
use Componenta\Http\Router\Attribute\Route;

final class PostController
{
    #[Route(
        name: 'posts.show',
        path: '/posts/{id}',
        methods: 'GET',
        middlewares: [AuditPostAccessMiddleware::class],
        tokens: ['id' => '\d+'],
        group: 'api',
    )]
    public function show(): array
    {
        return ['status' => 'ok'];
    }
}
```

```php
use App\Http\PostController;
use App\Http\Middleware\AuditPostAccessMiddleware;
use Componenta\Http\Router\RouteRecord;

/**
 * @var \Componenta\Http\Router\Routes $routes
 */

$routes->addRoute(RouteRecord::get(
    name: 'posts.show',
    path: '/posts/{id}',
    handler: [PostController::class, 'show'],
    middlewares: [AuditPostAccessMiddleware::class],
    tokens: ['id' => '\d+'],
));
```

Middleware definitions are resolved by `componenta/middleware-factory`. The common definition is a container class name. Ready `MiddlewareInterface` objects, `RequestHandlerInterface` objects, `MiddlewareGroup`, and callable middleware are also supported when resolvers can handle them. Plain strings such as `'auth'` are not a built-in named middleware registry. If the application wants aliases like that, add a custom resolver or use class names directly.

Details: [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.md) describes `config/pipeline.php`, [`componenta/middleware-factory`](https://github.com/componenta/middleware-factory/blob/main/README.md) describes resolving definitions to PSR-15 middleware, [`componenta/router`](https://github.com/componenta/router/blob/main/README.md) describes group and route middleware ordering. Middleware package READMEs document concrete implementations: [`componenta/http-body-parsing-middleware`](https://github.com/componenta/http-body-parsing-middleware/blob/main/README.md), [`componenta/http-cors-middleware`](https://github.com/componenta/http-cors-middleware/blob/main/README.md), [`componenta/http-csrf-middleware`](https://github.com/componenta/http-csrf-middleware/blob/main/README.md), [`componenta/http-throttle-middleware`](https://github.com/componenta/http-throttle-middleware/blob/main/README.md), and [`componenta/http-trusted-proxy-middleware`](https://github.com/componenta/http-trusted-proxy-middleware/blob/main/README.md).

## The `#[Route]` Attribute

`#[Route]` can be placed on an invokable class or controller method. It defines the route name, path, HTTP methods, middleware, parameter constraints, defaults, group name, and priority.

```php
namespace App\Http;

use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Http\Router\Attribute\Route;

final class PostController
{
    #[Route(
        name: 'posts.show',
        path: '/posts/{id:\\d+}',
        methods: 'GET',
        group: 'api',
        priority: 20,
    )]
    public function show(#[RequestAttribute] int $id): array
    {
        return ['id' => $id];
    }
}
```

`methods` accepts a string (`'GET'`), a pipe-separated string (`'GET|POST'`), or an array (`['GET', 'POST']`). `middlewares` accepts a string or array. `tokens` defines regex constraints for path parameters, and `defaults` defines default values.

Parameter constraints can also be written inline in the path. For example, `/posts/{id:\d+}` and `/archive/[?year:\d+=2026]` define the route token directly in the template. Explicit `tokens` override inline constraints when both are present.

`priority` controls attribute-route registration order: higher values are registered first and therefore matched first when route patterns overlap. This matters for conflicts such as `/{slug}` and `/archive`.

When `#[Route]` references a `group`, that group must be explicitly registered in `config/routes.php` before attribute routes are finalized:

```php
/**
 * @var \Componenta\Http\Router\Routes $routes
 */

use App\Http\Middleware\RequireAuthenticationMiddleware;

$routes->group('api', '/api');
$routes->group('admin', '/admin', middleware: [RequireAuthenticationMiddleware::class]);
```

If the group is not registered, the route does not receive the group's prefix, middleware, tokens, or defaults: it is added as a normal route with the group name preserved in the record. Groups referenced by route attributes should therefore be declared explicitly in `config/routes.php`.

Details: [`componenta/router`](https://github.com/componenta/router/blob/main/README.md) describes `RouteRecord`, `Routes`, and `RouteGroup`, and [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) describes `AttributeRouteLocator`.

## HTTP Request Mapping

`#[Route]` only matches the URL to a handler. Path parameters such as `{id}` are stored as PSR-7 request attributes, but they are not automatically injected into method arguments by name. Handler parameters must use request-mapping attributes from `componenta/di`.

For single values, use single-value attributes:

```php
namespace App\Http;

use Componenta\DI\Attribute\PayloadParam;
use Componenta\DI\Attribute\QueryParam;
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Http\Router\Attribute\Route;

final class PostController
{
    #[Route('posts.show', '/posts/{id}', 'GET', tokens: ['id' => '\d+'])]
    public function show(
        #[RequestAttribute] int $id,
        #[QueryParam(default: false, cast: 'bool')] bool $preview,
    ): array {
        return ['id' => $id, 'preview' => $preview];
    }

    #[Route('posts.rename', '/posts/{id}/rename', 'POST', tokens: ['id' => '\d+'])]
    public function rename(
        #[RequestAttribute] int $id,
        #[PayloadParam] string $title,
    ): array {
        return ['id' => $id, 'title' => $title];
    }
}
```

Main single-value attributes:

| Attribute | Source |
|---|---|
| `#[RequestAttribute]` | PSR-7 request attributes. Route parameters are stored here. |
| `#[QueryParam]` | Query string, for example `?page=2`. |
| `#[PayloadParam]` | Parsed request body. JSON and non-native form parsing require `BodyParsingMiddleware`; the HTTP preset includes it. |
| `#[Header]` | HTTP header. |
| `#[Cookie]` | Cookie. |
| `#[UploadedFile]` | Uploaded file from `$request->getUploadedFiles()`. |

When no name is passed, `RequestAttribute`, `QueryParam`, and `PayloadParam` use the method parameter name. Therefore `#[PayloadParam] string $title` reads the `title` field, and `#[RequestAttribute] int $id` reads the `id` request attribute. Pass an explicit name only when the HTTP field differs from the argument name: `#[PayloadParam('post_title')] string $title`. Query string and payload values often need `cast`, because raw request data arrives as strings. Route parameters are already converted by the router to `int` or `float` when the matched value looks numeric.

For DTOs or arrays, use `Map*` attributes. They extract a data array, apply `map`, `cast`, `defaults`, `sortMap`, and `exclude`, validate the DTO when a validator is available, and build the object through the container:

```php
namespace App\Http;

use Componenta\DI\Attribute\MapQueryString;
use Componenta\DI\Attribute\MapRequestPayload;
use Componenta\Http\Router\Attribute\Route;

final readonly class PostListQuery
{
    public function __construct(
        public int $page = 1,
        public ?string $tag = null,
    ) {}
}

final class MapPostListQuery extends MapQueryString
{
    protected array $cast = ['page' => 'int'];
    protected array $defaults = ['page' => 1];
}

final readonly class CreatePostCommand
{
    public function __construct(
        public string $title,
        public string $body,
    ) {}
}

final class PostController
{
    #[Route('posts.index', '/posts', 'GET')]
    public function index(#[MapPostListQuery] PostListQuery $query): array
    {
        return ['page' => $query->page, 'tag' => $query->tag];
    }

    #[Route('posts.create', '/posts', 'POST')]
    public function create(#[MapRequestPayload] CreatePostCommand $command): array
    {
        return ['title' => $command->title];
    }
}
```

Details: [`componenta/di`](https://github.com/componenta/di/blob/main/README.md) describes request mapping, `Map*` attributes, casters, and DTO validation; [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) describes how a route handler is executed through the DI interceptor.

## Interceptors

Interceptors wrap any PHP callable: a controller, handler, service method, or function. Use them for cross-cutting behavior that should not be duplicated in business code: logging, metrics, transactions, authorization, caching, parameter normalization, result serialization, response conversion, or exception handling.

The base `componenta/interceptor` package contains the runtime layer:

| Component | Purpose |
|---|---|
| `InterceptorInterface` | Contract for one interceptor. Receives `CallableContextInterface` and `ContextHandlerInterface`. |
| `InterceptingExecutor` | Callable executor with an interceptor chain. Implements `CallableExecutorInterface` and `PipelineInterface`. |
| `AttributeInterceptor` | Reads interceptor attributes from a callable and adds declared layers to the chain. |
| `ParameterResolvingInterceptor` | Resolves callable parameters through DI before later interceptors run. |
| `CallbackInterceptorFactory` | Creates closure-based interceptors: `before()`, `after()`, `catch()`, `finally()`, `around()`. |
| `#[Intercept]` | Function or method attribute that attaches an interceptor class with constructor parameters. |
| `ScopedInterface` and `Scope` | Restrict an interceptor to HTTP, CONSOLE, GRPC, QUEUE, or WEBSOCKET execution. |

HTTP presets generate `src/ConfigProvider.php` with the global HTTP interceptor pipeline:

```php
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\ConfigKey as InterceptorConfigKey;

return [
    InterceptorConfigKey::HTTP_INTERCEPTORS => [
        AttributeInterceptor::class,
    ],
];
```

The `PipelineInterface` factory always starts with `ParameterResolvingInterceptor`. It then appends interceptors listed in `InterceptorConfigKey::HTTP_INTERCEPTORS`. Controller parameters are therefore resolved through DI first, and then `AttributeInterceptor` applies attributes declared on the matched handler.

There are three common ways to attach an interceptor:

1. Register it globally in `InterceptorConfigKey::HTTP_INTERCEPTORS` when the layer must run for all HTTP handlers.
2. Use `#[Intercept(SomeInterceptor::class, ['option' => 'value'])]` when the attribute only describes which interceptor service must be created through the attribute factory.
3. Use an attribute that implements `InterceptorInterface` itself. PHP creates that attribute instance and the attribute runs its own `intercept()` method. This is useful for light stateless attributes such as `#[Paginate]`.

Example `#[Intercept]` usage:

```php
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Interceptor\Attribute\Intercept;

final class UserController
{
    #[Intercept(LogCallInterceptor::class, ['channel' => 'http'])]
    public function show(#[RequestAttribute] int $id): User
    {
        // ...
    }
}
```

Ready interceptor packages can be used as specialized attributes:

```php
use Componenta\DI\Attribute\MapQueryString;
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Http\Router\Attribute\Route;
use Componenta\Interceptor\Http\Attribute\Respond;
use Componenta\Interceptor\Http\Paginate;
use Componenta\Interceptor\Serialization\Attribute\Serialize;
use Componenta\Stdlib\PaginatorInterface;

final class PostController
{
    #[Route('posts.show', '/posts/{id}', 'GET')]
    #[Respond(200, 'application/json')]
    #[Serialize(context: ['groups' => ['post:public']])]
    public function show(#[RequestAttribute] int $id): PostView
    {
        // ...
    }

    #[Route('posts.index', '/posts', 'GET')]
    #[Respond(200, 'application/json')]
    #[Paginate]
    public function index(#[MapQueryString] PostListQuery $query): PaginatorInterface
    {
        // ...
    }
}
```

`#[Serialize]` from `componenta/serialize-interceptor` serializes the result through Symfony Serializer. `#[Respond]` and `#[Created]` from `componenta/http-respond-interceptor` convert the handler result into a PSR-7 response through `Componenta\Http\Responder`. `#[Paginate]` from `componenta/http-paginate-interceptor` is a direct interceptor attribute: when the handler returns `PaginatorInterface`, it wraps it into `ResourcePaginator` and builds `prev`/`next` links from the current PSR-7 request.

Attributes execute as layers from outside to inside: the top attribute is the outer layer, and the bottom one is closest to the callable body. An interceptor can call `$handler->handle($context)`, modify the context or result, catch an exception, or short-circuit the chain by returning without invoking the original callable.

`componenta/interceptor-app` does not execute interceptors. It compiles interceptor attributes into a map for build cache so production-like runs do not need to read attributes through reflection on every request.

Details: [`componenta/interceptor`](https://github.com/componenta/interceptor/blob/main/README.md) describes interceptor execution, [`componenta/interceptor-app`](https://github.com/componenta/interceptor-app/blob/main/README.md) describes build-cache integration, [`componenta/serialize-interceptor`](https://github.com/componenta/serialize-interceptor/blob/main/README.md), [`componenta/http-respond-interceptor`](https://github.com/componenta/http-respond-interceptor/blob/main/README.md), and [`componenta/http-paginate-interceptor`](https://github.com/componenta/http-paginate-interceptor/blob/main/README.md) describe ready attributes, and [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) describes HTTP handler integration with interceptors.

## Console Commands

The CLI preset and other presets with `componenta/app-console` use `bin/console.php`. Commands are collected in the shared `Componenta\App\Console\ConfigKey::COMMANDS` config key. Packages add their commands from config providers; the application adds project-local commands in `config/console.php`:

```php
use App\Console\ImportPostsCommand;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;

return [
    ConsoleConfigKey::COMMANDS => [
        ImportPostsCommand::class,
    ],
];
```

In development mode, commands inside discovery directories can also be marked with Symfony `#[AsCommand]`. Attribute discovery is a development convenience; production builds use the assembled `console.commands` config as the command source.

Standard Symfony Console commands are also available, for example:

```bash
php bin/console.php list
APP_ENV=development php bin/console.php app:build
php bin/console.php app:preload
php bin/console.php app:cache:clear
php bin/console.php app:cache:clear --build
php bin/console.php app:cache:clear --dev
php bin/console.php app:cache:clear --runtime
```

Run `app:build` before starting an environment where `APP_ENV` is not `development`; those environments read `var/cache/build/config.cache.php` instead of rebuilding the project config definition on every request. The command also prepares compilable discovery state contributed by installed packages, so `#[Boot]` methods, routes, CQRS handlers, policy maps, and interceptor maps can be restored without scanning `src/`.

`app:preload` generates `var/cache/build/preload.php` from existing build-cache artifacts. It does not build missing artifacts by itself; run `app:build` first. `app:cache:clear` clears build, development, and runtime caches by default; `--build`, `--dev`, and `--runtime` limit the command to one cache area.

When Cycle ORM is installed, `componenta/cycle-app` adds `db:create`, `db:generate`, `db:schema`, `db:migrate`, `db:rollback`, `db:status`, and `db:sync`. When routing is installed, `componenta/router-app` adds `router:list`.

Details: [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.md) describes the command registry, CLI bootloader, command discovery, and maintenance commands. [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.md) documents database commands. [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) documents `router:list`.

## Application Commands And Queries

When `componenta/cqrs-app` is installed, application behavior can be modeled as commands and queries. A command changes state; a query reads data. Their handlers are registered by CQRS packages and can be discovered automatically.

HTTP controllers, console commands, and other entry points should not know the execution details of a business action. They create a command or query and pass it to the matching bus.

The selected application middleware chains live in `src/ConfigProvider.php`. With CQRS enabled, the skeleton registers command middleware in this order:

```php
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\PolicyMiddleware as CommandPolicyMiddleware;
use Componenta\CQRS\ConfigKey as CqrsConfigKey;
use Componenta\CQRS\Query\Middleware\PolicyMiddleware as QueryPolicyMiddleware;

return [
    CqrsConfigKey::COMMAND_MIDDLEWARES => [
        CommandPolicyMiddleware::class,
        EventMiddleware::class,
    ],
    CqrsConfigKey::QUERY_MIDDLEWARES => [
        QueryPolicyMiddleware::class,
    ],
];
```

If policies are not selected, the policy middleware entries are omitted and query middleware defaults to an empty list. When the Cycle integration is selected, the installer also adds `TransactionMiddleware` to command middleware because the database service is available. `TransportMiddleware` is not installed by the standard presets: add it only when the application configures a CQRS transport registry and command serializer. The base `componenta/cqrs` provider intentionally starts with empty command and query chains; the application decides which runtime guarantees it needs.

Details: [`componenta/cqrs`](https://github.com/componenta/cqrs/blob/main/README.md) describes the command bus, query bus, operations, middleware, and async execution; [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.md) describes CQRS discovery and compile-cache integration.

## Policies

When `componenta/policy-app` is installed, access checks are described as policies and policy attributes on application actions. This keeps authorization outside command and query handlers.

A typical flow is: an entry point creates a command or query, CQRS middleware resolves the current actor, `componenta/policy` checks the policy for that action, and execution continues to the handler only when access is allowed.

Details: [`componenta/policy`](https://github.com/componenta/policy/blob/main/README.md) describes policies, providers, and attributes; [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.md) describes compile integration; [`componenta/cqrs`](https://github.com/componenta/cqrs/blob/main/README.md) describes policy middleware placement.

## Templates

When a template renderer is selected during installation, the HTTP preset installs `componenta/templater-app`. The project gets a `templates/` directory, the `view()` helper, and `templates/welcome.phtml`.

HTTP error templates live in `templates/error/`. The safe 500 page must be available even when detailed error output is disabled.

Details: [`componenta/templater`](https://github.com/componenta/templater/blob/main/README.md) describes renderer contracts, and [`componenta/templater-app`](https://github.com/componenta/templater-app/blob/main/README.md) describes the `view()` helper and application integration.

## WebSocket

The WebSocket preset creates the separate entry point `bin/websocket.php` and starts `Scope::WEBSOCKET`. HTTP infrastructure is not created for this preset. When WebSocket support is added as an extra feature to another preset, the installer adds the `serve:websocket` Composer script.

Details: [`componenta/websocket-server`](https://github.com/componenta/websocket-server/blob/main/README.md) describes the base socket server, and [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.md) describes WebSocket scope integration.

## Available Commands And Composer Scripts

Scripts depend on the selected preset:

| Command | Available when | Purpose |
|---|---|---|
| `composer serve` | HTTP presets | Starts PHP built-in web server on `localhost:8000` with `public/` as document root. |
| `composer serve:websocket` | WebSocket preset or WebSocket add-on | Starts `bin/websocket.php`. |
| `composer test` | Always | Runs the selected test runner: Pest or PHPUnit. |
| `composer analyse` | Always | Runs PHPStan over directories created by the selected preset. |
| `php bin/console.php list` | `componenta/app-console` is installed | Lists available console commands. |
| `APP_ENV=development php bin/console.php app:build` | `componenta/app-console` is installed | Builds config, container, and compilable discovery cache files for non-development environments. |
| `php bin/console.php app:preload` | `componenta/app-console` is installed | Generates a preload file from build-cache artifacts. |
| `php bin/console.php app:cache:clear [--build\|--dev\|--runtime]` | `componenta/app-console` is installed | Clears all application cache directories, or only the selected cache area. |
| `php bin/console.php router:list` | HTTP routing is installed | Lists registered routes. |

The CLI preset does not create `public/`, `config/routes.php`, `config/pipeline.php`, or WebSocket files. HTTP presets create `config/routes.php` and enable routing through installed packages.

Details: [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.md) describes CLI support, and [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) describes HTTP route discovery.

## Project Layout

| Path | Purpose |
|---|---|
| `.env` | Local environment file created from `.env.dist`. |
| `config/config.php` | Main declaration of providers and discovery. |
| `config/container.php` | Application container assembly. |
| `config/componenta-providers.php` | Generated provider list for installed packages. Created by `componenta/composer-plugin`. |
| `config/autoload/` | Project configuration from `*.global.*` and `*.local.*` files. |
| `src/` | Application code under the `App\` namespace. |
| `bin/` | CLI entry point. |
| `public/` | HTTP entry point. Created only for HTTP presets. |
| `templates/` | Application and error templates. Created when templates or an HTTP safe error page are needed. |
| `var/cache/dev/` | Development caches. |
| `var/cache/build/` | Build cache for environments outside `development`. |
| `var/cache/runtime/` | Runtime caches. |
| `log/` | Application logs. |
| `storage/` | Application files. |

Details: [`componenta/path-resolver`](https://github.com/componenta/path-resolver/blob/main/README.md) describes project-root path resolution, and [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) describes cache layout.

## Related Packages

- [`componenta/app`](https://github.com/componenta/app/blob/main/README.md) - lifecycle, scopes, configuration, container, caches, and bootloaders.
- [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.md) - HTTP application adapter.
- [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.md) - console runtime and commands.
- [`componenta/composer-plugin`](https://github.com/componenta/composer-plugin/blob/main/README.md) - generation of `config/componenta-providers.php`.
- [`componenta/config`](https://github.com/componenta/config/blob/main/README.md) - config providers and file loaders.
- [`componenta/di`](https://github.com/componenta/di/blob/main/README.md) - container, factories, and DI attributes.
- [`componenta/router`](https://github.com/componenta/router/blob/main/README.md) and [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.md) - routing and route discovery.
- [`componenta/cqrs`](https://github.com/componenta/cqrs/blob/main/README.md) and [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.md) - commands, queries, and their discovery.
- [`componenta/policy`](https://github.com/componenta/policy/blob/main/README.md) and [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.md) - access policies and compile integration.
- [`componenta/templater`](https://github.com/componenta/templater/blob/main/README.md) and [`componenta/templater-app`](https://github.com/componenta/templater-app/blob/main/README.md) - templates and the `view()` helper.
- [`componenta/error-handler`](https://github.com/componenta/error-handler/blob/main/README.md) and [`componenta/error-handler-app`](https://github.com/componenta/error-handler-app/blob/main/README.md) - error handling and HTTP-safe rendering.
- [`componenta/websocket-server`](https://github.com/componenta/websocket-server/blob/main/README.md) and [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.md) - WebSocket server and application integration.
