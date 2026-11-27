# Componenta Skeleton

Starter project for PHP 8.5.13+ with HTTP, CLI and WebSocket presets.

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

**Verified on this branch:** generated Web, API and CLI projects. Full/Auth and WebSocket still need their separate dependency/API migrations; see [preset compatibility](docs/preset-compatibility.md).

## Configuration and lifecycle

Entry points load Composer. The CLI first dispatches build operations through `BuildConsole`; ordinary commands and other scopes call `Componenta\App\run()` with a scope and `PathResolver`. The application loads `config/container.php`.

`config/config.php` returns a `ConfigDefinition` with providers, configuration files and optional discovery directories. `Componenta\App\Config\ConfigFactory` produces a composition containing runtime Config and separate dependency definitions. `Componenta\DI\ContainerFactory` receives these objects and creates the container. Factories receive `ContainerValue` and access the same Config through `$container->config`.

Composer generates `config/componenta-providers.php` from package metadata. Project providers can use `#[AsConfig]` under `src/`; project configuration files live under `config/autoload/`.

Configuration is composed in every environment. The configured class source is shared through DI. Bootloaders prepare the selected execution scope.

## Build and deployment

Preparation is optional. Source mode remains a complete runtime mode, including in production. `APP_ENV` selects application configuration; it neither creates artifacts nor proves that a build exists.

~~~bash
composer install --no-dev --optimize-autoloader
APP_BUILD_ID="release-2026-11-27" php bin/console.php app:build
~~~

Use the same `APP_BUILD_ID` in the deployed runtime. It identifies the immutable code release and structural configuration profile; use different IDs when those inputs differ. Without an ID, the deployment must keep code and build artifacts together and rebuild after every structural change. Runtime does not recursively hash source files or rebuild missing artifacts.

The CLI entry point calls `BuildConsole` immediately after autoloading, before ordinary container creation and runtime bootloaders. Use the full deployment command names `app:build` and `app:clean`. Build composes current source configuration in a fresh container, so a newly added `#[AsConfig]` provider or a removed declaration is handled on the first rebuild. No preliminary clean is needed. Provider code still executes to define the build; the complete Config object and live services are not snapshotted. Domain artifacts may include structural configuration such as handler registrations; this is part of the release/profile identity.

Each builder writes through `ApplicationBuilderInterface::build(BuildContext $build): void`. App places all outputs in a new `var/cache/build/generations/<generation>/` directory, writes its manifest, and replaces `current.json` only after every builder succeeds. A failure leaves the previous generation published. Unsupported optional optimizations are explicitly recorded and use source at runtime; actual compilation failures stop the build.

| Artifact | Prepared information | Work that remains at runtime |
|---|---|---|
| `app.discovery` | Ordered class declarations | Loading classes when needed |
| `app.config-providers` | Ordered `#[AsConfig]` provider class names | Provider construction, invocation and configuration composition |
| `app.boot` | Boot method membership | Attribute instances, DI arguments and boot calls |
| `console.commands` | Command attribute membership | Command construction and registration |
| `router.routes` | Router's portable compiled route schema | Request matching, DI and dynamic-context fallback |
| `cqrs.maps` | Handler/listener descriptors and priorities | Dispatch and runtime callbacks |
| `validation.rules` | Supported literal validation descriptions | Fresh rules/validators and unsupported DTO fallback |

Cycle schema preparation is a separate explicit opt-in in Cycle App: schema compilation can read database metadata and must not synchronize tables or run migrations. Interceptor 4 uses its native runtime attribute cache and needs no separate build map; the Skeleton no longer installs the redundant Interceptor App adapter.

A bootstrap pins one `PreparedBuild`. Rebuilding or cleaning does not switch artifacts under an existing worker. Restart workers when deploying new code. `app:clean` only unpublishes the current pointer, works without evaluating providers, and preserves generations that running processes may still use. Remove old generation directories only as part of offline release cleanup after those processes stop.

Missing or incompatible optional artifacts fall back to source. To make selected optimizations a deployment requirement, pass explicit `requiredArtifacts` to `ApplicationBuildRunner::build()` and runtime `ConfigFactory::create()`, for example `['app.discovery', 'app.config-providers', 'app.boot', 'console.commands']`. An empty builder set and empty maps are valid. Required-artifact policy is independent of `APP_ENV`.

### Migration from individual map files

Custom builders must accept `BuildContext`, replace direct file writes with `$build->write('vendor.artifact', $payload, schemaVersion: 1)`, and report a deliberately unsupported optimization with `$build->skip('vendor.artifact', $reason)`. Unexpected exceptions must propagate. Constructor injection continues to provide source dependencies. Builders must not read outputs of other participants in the same run.

Per-builder cleaners, output path/generation constructor arguments and independent `classes.php`, `boot.php`, `commands.php` readers are replaced by the shared protocol. Old files are ignored; a rebuild produces the new generation format. The optional runtime snapshot is addressed through `AppConfigKey::PREPARED_BUILD` rather than probing whether its concrete class can be autowired.

This branch targets App/Console 6, Config 4, DI 6, Router 4 / Router App 6, CQRS 5 / CQRS App 6, Validation 3.2 / Validation App 4, Error Handler App 2 and optional Cycle App 3 on PHP 8.5.13+. The installer and root requirements use release constraints. These releases must be available together before publishing this Skeleton; development verification can use explicit branch checkouts in a separate integration project.

## Development

- `composer test` checks actual bootstrap and build-command help.
- `composer serve` starts the HTTP development server.
- `bin/console.php` is the console entry point.
- HTTP presets add `public/index.php`, route and pipeline configuration.
- WebSocket presets add `bin/websocket.php` and `config/websocket.php`.

See package documentation for [App](https://github.com/Componenta/app), [DI](https://github.com/Componenta/di), [Config](https://github.com/Componenta/config), [Router App](https://github.com/Componenta/router-app), [CQRS App](https://github.com/Componenta/cqrs-app), [Interceptor](https://github.com/Componenta/interceptor), [Validation App](https://github.com/Componenta/validation-app), [Policy](https://github.com/Componenta/policy), [Cycle App](https://github.com/Componenta/cycle-app) and [WebSocket App](https://github.com/Componenta/websocket-app).
