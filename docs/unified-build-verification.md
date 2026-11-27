# Unified application build verification

The implementation was exercised on PHP 8.5.13 with Composer 2.11.2, using an isolated copy of the explicitly selected source graph. The central gate installs 195 dependencies from 52 Componenta source entries and the declared third-party requirements. Projected versions identify the intended release combinations; they are not published-release claims.

## Executed checks

- 22 suites: **1,614 test cases, 69,913 assertions, no skips, failures or errors**. Deliberate warning events are explained below.
- The existing CQRS integration suite is retained: transport, policy, retry, locking and SQLite transactions in source and prepared modes.
- CQRS App PHPStan runs at the repository's existing maximum level.
- The real Skeleton installer generates Web, API and CLI projects. Their complete runtime/dev constraints resolve to 122, 119 and 102 packages respectively.
- App's 38 benchmark cases pass a functional smoke run. Synthetic cold-bootstrap timings are not an application throughput or latency guarantee.

The full command, immutable dependency sources, projected versions and test selection are maintained in [the integration harness](../integration/README.md). Each execution writes an actual source lock, dependency locks, individual logs, JUnit output and a combined JSON report. The GitHub workflow uploads them as a review artifact. This document records local verification; the status of a remote Actions run must be checked separately.

## Behavioral regression coverage

The tests verify fresh source configuration before building, discovery of a newly added `#[AsConfig]` provider on the first rebuild, removal of a previously discovered class, and absence of runtime `#[Boot]` calls during preparation. Providers still run when composing runtime configuration; the prepared provider index does not serialize the complete Config or service objects. Prepared provider lists supplied directly to the public constructor or `withPreparedProviders()` retain their order independently of caller-owned references, while each composition creates fresh providers and reads current values.

Instance and static `#[Boot]` methods declared in traits execute through each consuming class. Source and prepared discovery skip traits as standalone boot targets, including unused traits. New boot maps omit trait entries, and runtime also ignores such entries from older prepared maps. Regression tests exercise the public configuration, build, container and discovery path and verify that building invokes no startup methods.

Publication tests cover late builder failure, unsupported optional optimization, empty artifacts, duplicate IDs, closed contexts, retained worker snapshots, repeated cleanup, release identity mismatch and required-artifact policy. A two-process regression verifies that the JSON publication pointer stays current with `opcache.validate_timestamps=0`, while an already selected generation remains pinned.

Required-artifact policy is copied independently of caller-owned PHP references, including the policy retained by an already selected generation. Bootstrap captures its required-provider validation before invoking a definition loader that can change those references. A subprocess with a restricted OPcache API verifies successful publication, cleanup and republication after completed filesystem operations. Cleanup resolves only the build directory: malformed dotenv content cannot prevent unpublishing, and existing workers retain their selected generation.

Relative store directories are bound to their original working directory without changing native `symlink/..` lookup. Absolute stores still work when the caller's working directory is unavailable. A selected reader retains its physical generation after a deployment alias switches to another release, while new loads and cleanup follow the current alias. Separate subprocess coverage verifies optional fallback and required-policy errors under `open_basedir`, without leaking filesystem warnings or replacing the caller's error handler. Filesystem roots such as `/` and `C:/` remain absolute in PathResolver.

Publication format 2 isolates internal metadata from valid artifact names, including `manifest`. Tests cover that name as both optional and required data across publication and rebuild. Write contexts cannot be cloned or serialized into a second capability that outlives the original build. Older publication formats require rebuilding; optional runtime falls back to source and required-artifact policy rejects them.

Router, CQRS and validation tests preserve their existing ordering and runtime semantics. Unsupported validation DTOs use their native provider and create fresh rules. Optional Cycle preparation reads database metadata only when enabled; a real SQLite table and sentinel row remain unchanged after schema preparation.

Cycle's standalone `db:schema` command retains a successful file publication when OPcache API access is restricted. Its subprocess regression verifies that the restriction is active, the initial write and replacement succeed, the generated payload remains valid, and the caller's error handler is restored. Existing failures of incomplete writes and file replacement still report failure. Callable typecasts also retain their original class scope after the generated configuration is loaded inside another class; Cycle App requires VarExport 2.0.1 for this behavior.

The complete Router suite is part of the central gate. It includes source/prepared generation syntax, PCRE option scope, standalone publication with a restricted OPcache API, and optional responder resolution through DI 6. PSR response, middleware and request-handler results bypass responder construction; normalization uses the application's responder and preserves its failures. Router cache schema 20 is exercised through RouterApp, including optional fallback and required-schema rejection for older maps.

RouterApp's intercepted resolver follows the same lazy responder contract. Real source and compiled routes cover PSR results, short-circuit callbacks, callback result transformation and repeated requests. A custom responder that is already lazy initializes only when normalization needs it; callback exceptions retain their identity and DI failures retain their original cause. RouteBuilder token/default inputs and array handlers detach caller-owned reference slots, including across builder clones, without deep-copying opaque values. Route attributes retain independent top-level metadata slots. Repeated named variadic arguments to `methods()` append their values in order instead of replacing an earlier method with the same argument key.

Both native and intercepted route middleware retain their selected callable if the caller later changes reference slots for its receiver or method. `CompositeSyntax` similarly retains the selected parser instances and their order independently of caller-owned array references, on both cold and cached matching and generation paths. Standalone Router artifact path checks preserve literal backslashes in resolved POSIX filenames while retaining protection against source and artifact aliases.

Console bootloader tests verify that custom hosts cannot change accepted command membership through caller-owned PHP references after construction. The standard artifact reader and bootloader factory already return independent payloads; this regression concerns maps passed directly to the public constructor.

RouterApp also supports file-only routes when application discovery is disabled. Regression coverage uses the standard ConfigFactory, DI and ApplicationBuildRunner to verify development and production source routing, publication of a required route artifact, and matching and URL generation from the prepared collection. An explicit route context returns to the file source without replacing the selected prepared collection or requiring a placeholder discovery service.

The local PCRE version is 10.46: all 12 Router matching cases requiring PCRE 10.45 execute successfully, with no version-dependent skips. Four Pest warning events come from deliberate invalid-PCRE fixtures: PHP marks them as suppressed, the tests verify the expected router exception and error-handler restoration, and reporting has not been disabled.

Compatibility-only changes to HTTP PSR adapters and the CQRS policy/transport/transaction packages retain their production PHP implementations and previous supported dependency ranges. Tests exercise the added Config 4 / DI 6 / CQRS 5 paths. Body parsing already supported Config 4 on its selected audit branch and required no new source change.

Separate PHP processes verify that HTTP, console and WebSocket entry points respect suppressed warnings while still rejecting reported warnings. These bootstrap tests do not establish complete WebSocket package compatibility.

The integration harness itself is tested with real Composer projects and Git checkouts. Inherited manifest/vendor overrides cannot redirect writes, platform-ignore variables cannot bypass declared extension requirements, and Git work-tree overrides cannot substitute another checkout's source lock or files. Authentication, proxy, mirror and cache settings remain available to child processes.

Source aliases are resolved before creating a workspace, preventing writes inside a physical input checkout reached through a symbolic link. Copying an aliased checkout preserves valid internal relative links. Regression coverage includes a linked Git worktree and verifies identical source identities and content digests through direct and aliased paths.

## Scope and remaining migrations

[Preset compatibility](preset-compatibility.md) records the unresolved Full/Auth and WebSocket migrations. Passing Web/API/CLI and the optional Cycle participant does not establish those unrelated transport/authentication integrations.

Discovery artifacts preserve the original filename keys. The normal autoload, configuration-provider and boot paths continue to work after relocating a release, but custom consumers of discovery filename keys can observe the original paths. Full path rebasing, including discovery directories outside the project, is not part of this protocol.

Preparation is an optional framework optimization. Applications can explicitly require selected artifacts as a deployment policy. `APP_ENV=production` alone does not require or trigger building.
