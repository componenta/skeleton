# Unified application build verification

The implementation was exercised on PHP 8.5.13 with Composer 2.11.3, using an isolated copy of the explicitly selected source graph. The central gate installs 195 dependencies from 52 Componenta source entries and the declared third-party requirements. Projected versions identify the intended release combinations; they are not published-release claims.

## Executed checks

- 20 suites: **852 tests, 3,868 assertions, no skipped tests, no failures or errors**.
- The existing CQRS integration suite is retained: transport, policy, retry, locking and SQLite transactions in source and prepared modes.
- CQRS App PHPStan runs at the repository's existing maximum level.
- The real Skeleton installer generates Web, API and CLI projects. Their complete runtime/dev constraints resolve to 122, 119 and 102 packages respectively.
- App's 38 benchmark cases pass a functional smoke run. Synthetic cold-bootstrap timings are not an application throughput or latency guarantee.

The full command, immutable dependency sources, projected versions and test selection are maintained in [the integration harness](../integration/README.md). Each execution writes an actual source lock, dependency locks, individual logs, JUnit output and a combined JSON report. The GitHub workflow uploads them as a review artifact. This document records local verification; the status of a remote Actions run must be checked separately.

## Behavioral regression coverage

The tests verify fresh source configuration before building, discovery of a newly added `#[AsConfig]` provider on the first rebuild, removal of a previously discovered class, and absence of runtime `#[Boot]` calls during preparation. Providers still run when composing runtime configuration; the prepared provider index does not serialize the complete Config or service objects.

Publication tests cover late builder failure, unsupported optional optimization, empty artifacts, duplicate IDs, closed contexts, retained worker snapshots, repeated cleanup, release identity mismatch and required-artifact policy. A two-process regression verifies that the JSON publication pointer stays current with `opcache.validate_timestamps=0`, while an already selected generation remains pinned.

Required-artifact policy is copied independently of caller-owned PHP references, including the policy retained by an already selected generation. A subprocess with a restricted OPcache API verifies successful publication, cleanup and republication after completed filesystem operations. Cleanup resolves only the build directory: malformed dotenv content cannot prevent unpublishing, and existing workers retain their selected generation.

Publication format 2 isolates internal metadata from valid artifact names, including `manifest`. Tests cover that name as both optional and required data across publication and rebuild. Write contexts cannot be cloned or serialized into a second capability that outlives the original build. Older publication formats require rebuilding; optional runtime falls back to source and required-artifact policy rejects them.

Router, CQRS and validation tests preserve their existing ordering and runtime semantics. Unsupported validation DTOs use their native provider and create fresh rules. Optional Cycle preparation reads database metadata only when enabled; a real SQLite table and sentinel row remain unchanged after schema preparation.

The Router source pin includes the latest collection-variable validation, caller-reference isolation and extended-character-class lexer fixes. Separate upstream checks pass 36 tests with 90 assertions, and source/prepared adapter probes agree. The local PCRE version is 10.44: 12 separate upstream matching cases requiring PCRE 10.45 are skipped and are not included in the 20-suite totals above. Lexer probes cover their boundaries, but do not establish matching behavior on PCRE 10.45.

Compatibility-only changes to HTTP PSR adapters and the CQRS policy/transport/transaction packages retain their production PHP implementations and previous supported dependency ranges. Tests exercise the added Config 4 / DI 6 / CQRS 5 paths. Body parsing already supported Config 4 on its selected audit branch and required no new source change.

Separate PHP processes verify that HTTP, console and WebSocket entry points respect suppressed warnings while still rejecting reported warnings. These bootstrap tests do not establish complete WebSocket package compatibility.

The integration harness itself is tested with real Composer projects and Git checkouts. Inherited manifest/vendor overrides cannot redirect writes, platform-ignore variables cannot bypass declared extension requirements, and Git work-tree overrides cannot substitute another checkout's source lock or files. Authentication, proxy, mirror and cache settings remain available to child processes.

Source aliases are resolved before creating a workspace, preventing writes inside a physical input checkout reached through a symbolic link. Copying an aliased checkout preserves valid internal relative links. Regression coverage includes a linked Git worktree and verifies identical source identities and content digests through direct and aliased paths.

## Scope and remaining migrations

[Preset compatibility](preset-compatibility.md) records the unresolved Full/Auth and WebSocket migrations. Passing Web/API/CLI and the optional Cycle participant does not establish those unrelated transport/authentication integrations.

Discovery artifacts preserve the original filename keys. The normal autoload, configuration-provider and boot paths continue to work after relocating a release, but custom consumers of discovery filename keys can observe the original paths. Full path rebasing, including discovery directories outside the project, is not part of this protocol.

Preparation is an optional framework optimization. Applications can explicitly require selected artifacts as a deployment policy. `APP_ENV=production` alone does not require or trigger building.
