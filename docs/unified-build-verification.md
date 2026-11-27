# Unified application build verification

The implementation was exercised on PHP 8.5.13 with Composer 2.11.3, using an isolated copy of the explicitly selected source graph. The central gate installs 195 dependencies from 52 Componenta source entries and the declared third-party requirements. Projected versions identify the intended release combinations; they are not published-release claims.

## Executed checks

- 20 suites: **847 tests, 3,844 assertions, no skipped tests, no failures or errors**.
- The existing CQRS integration suite is retained: transport, policy, retry, locking and SQLite transactions in source and prepared modes.
- CQRS App PHPStan runs at the repository's existing maximum level.
- The real Skeleton installer generates Web, API and CLI projects. Their complete runtime/dev constraints resolve to 122, 119 and 102 packages respectively.
- App's 38 benchmark cases pass a functional smoke run. Synthetic cold-bootstrap timings are not an application throughput or latency guarantee.

The full command, immutable dependency sources, projected versions and test selection are maintained in [the integration harness](../integration/README.md). Each execution writes an actual source lock, dependency locks, individual logs, JUnit output and a combined JSON report. The GitHub workflow uploads them as a review artifact. This document records local verification; the status of a remote Actions run must be checked separately.

## Behavioral regression coverage

The tests verify fresh source configuration before building, discovery of a newly added `#[AsConfig]` provider on the first rebuild, removal of a previously discovered class, and absence of runtime `#[Boot]` calls during preparation. Providers still run when composing runtime configuration; the prepared provider index does not serialize the complete Config or service objects.

Publication tests cover late builder failure, unsupported optional optimization, empty artifacts, duplicate IDs, closed contexts, retained worker snapshots, repeated cleanup, release identity mismatch and required-artifact policy. A two-process regression verifies that the JSON publication pointer stays current with `opcache.validate_timestamps=0`, while an already selected generation remains pinned.

Publication format 2 isolates internal metadata from valid artifact names, including `manifest`. Tests cover that name as both optional and required data across publication and rebuild. Write contexts cannot be cloned or serialized into a second capability that outlives the original build. Older publication formats require rebuilding; optional runtime falls back to source and required-artifact policy rejects them.

Router, CQRS and validation tests preserve their existing ordering and runtime semantics. Unsupported validation DTOs use their native provider and create fresh rules. Optional Cycle preparation reads database metadata only when enabled; a real SQLite table and sentinel row remain unchanged after schema preparation.

Compatibility-only changes to HTTP PSR adapters and the CQRS policy/transport/transaction packages retain their production PHP implementations and previous supported dependency ranges. Tests exercise the added Config 4 / DI 6 / CQRS 5 paths. Body parsing already supported Config 4 on its selected audit branch and required no new source change.

Separate PHP processes verify that HTTP, console and WebSocket entry points respect suppressed warnings while still rejecting reported warnings. These bootstrap tests do not establish complete WebSocket package compatibility.

The integration harness itself is tested with real Composer projects and Git checkouts. Inherited manifest/vendor overrides cannot redirect writes, platform-ignore variables cannot bypass declared extension requirements, and Git work-tree overrides cannot substitute another checkout's source lock or files. Authentication, proxy, mirror and cache settings remain available to child processes.

## Scope and remaining migrations

[Preset compatibility](preset-compatibility.md) records the unresolved Full/Auth and WebSocket migrations. Passing Web/API/CLI and the optional Cycle participant does not establish those unrelated transport/authentication integrations.

Discovery artifacts preserve the original filename keys. The normal autoload, configuration-provider and boot paths continue to work after relocating a release, but custom consumers of discovery filename keys can observe the original paths. Full path rebasing, including discovery directories outside the project, is not part of this protocol.

Preparation is an optional framework optimization. Applications can explicitly require selected artifacts as a deployment policy. `APP_ENV=production` alone does not require or trigger building.
