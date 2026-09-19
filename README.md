# Componenta Skeleton

Starter project for PHP 8.4+ with HTTP, CLI and WebSocket presets.

## Install

```bash
composer create-project componenta/skeleton my-app
cd my-app
php bin/console.php list
```

The installer selects dependencies, creates entry points and a local `.env`, then removes its installation files. Without interactive input it selects Web with Nyholm PSR-7, Plates, Pest, CQRS and policies.

| Preset | Features |
|---|---|
| Web | HTTP, routing and optional templates |
| API | HTTP and routing, without templates by default |
| Full | HTTP, templates, CQRS, policies, authentication and Cycle ORM |
| CLI | Console commands |
| WebSocket | WebSocket application |

HTTP presets support Nyholm, Diactoros, Guzzle and Slim. Interactive setup also selects Pest or PHPUnit and optional integrations. Full uses Nyholm, Plates and Pest. WebSocket can be added to another preset.

## Configuration and lifecycle

Entry points load Composer and call `Componenta\App\run()` with a scope and `PathResolver`. The application loads `config/container.php`.

`config/config.php` returns a `ConfigDefinition` with providers, configuration files and optional discovery directories. `Componenta\App\Config\ConfigFactory` produces a composition containing runtime Config and separate dependency definitions. `Componenta\DI\ContainerFactory` receives these objects and creates the container. Factories receive `ContainerValue` and access the same Config through `$container->config`.

Composer generates `config/componenta-providers.php` from package metadata. Project providers can use `#[AsConfig]` under `src/`; project configuration files live under `config/autoload/`.

Configuration is composed in every environment. The configured class source is shared through DI. Bootloaders prepare the selected execution scope.

## Build and deployment

```bash
php bin/console.php app:build
```

This ordinary console command uses the existing container. Builders are created only when it executes; list and help keep build dependencies lazy.

When discovery is configured, the build writes `var/cache/build/classes.php` with ordered declarations and the metadata needed to reconstruct `ClassInfo`. Subsequent startups use this list without traversing directories or tokenizing source files. `AttributeConfigProvider` consumes the prepared iterator and continues registering configuration providers normally. Build leaves configuration files and `FileProvider` registrations unchanged.

App also builds `boot.php` with boot method names, and App Console builds `commands.php` with command attribute membership. Their runtime factories use these maps with prepared discovery while preserving native attribute instantiation. Missing or malformed metadata maps use the original reflection path.

The map remains active until rebuilt or deleted, independently of `APP_ENV`. All commands use ordinary bootstrap with available maps. `app:build` runs the registered builders; `app:clean` calls the optional `ApplicationBuildCleanerInterface::clean()` on the same builders. The entrypoint does not inspect command names or remove maps. List, help and version requests preserve artifacts.

Run `php bin/console.php app:clean` and then `php bin/console.php app:build` as separate processes to rebuild with fresh configuration discovery. Cleaning removes only registered builders' artifacts, preserves unrelated files, and succeeds when artifacts are already absent. The next application process uses source discovery until another build creates the maps. There is no development discovery cache or file freshness check. Builders share fresh source discovery through DI.

Packages and applications register an ordered service list under `Componenta\App\ConfigKey::BUILDERS`. Each builder implements `ApplicationBuilderInterface::build(): void` and receives dependencies through its constructor. Each builder writes its own artifact atomically. A failure stops the sequence and preserves artifacts already written by earlier builders.

Installed integrations can generate CQRS, route, interceptor and validation maps. Runtime factories select optimized implementations and supported source fallback. Custom providers and native attribute behavior are preserved. Validation uses its attribute provider for classes whose rules cannot be exported. Policy uses its normal provider and memory cache.

Use `APP_ENV=production` when deploying. Startup does not require build artifacts and runtime never invokes builders. Build during deployment to enable optimization, then restart long-running workers after updating code and artifacts.

## Development

- `composer test` checks actual bootstrap and build-command help.
- `composer serve` starts the HTTP development server.
- `bin/console.php` is the console entry point.
- HTTP presets add `public/index.php`, route and pipeline configuration.
- WebSocket presets add `bin/websocket.php` and `config/websocket.php`.

See package documentation for [App](https://github.com/Componenta/app), [DI](https://github.com/Componenta/di), [Config](https://github.com/Componenta/config), [Router App](https://github.com/Componenta/router-app), [CQRS App](https://github.com/Componenta/cqrs-app), [Interceptor](https://github.com/Componenta/interceptor), [Validation App](https://github.com/Componenta/validation-app), [Policy](https://github.com/Componenta/policy), [Cycle App](https://github.com/Componenta/cycle-app) and [WebSocket App](https://github.com/Componenta/websocket-app).
