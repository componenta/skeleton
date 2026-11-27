# Application build integration

Run the same source graph and tests locally or in GitHub Actions:

```sh
php integration/run.php
php integration/run.php --sources=/absolute/path/to/componenta-checkouts
```

PHP 8.5.13 or newer, Composer 2 and Git must be on `PATH`. SQLite, DOM, mbstring and the other declared package extensions must be installed. The runner does not ignore platform requirements.

`sources.json` declares every Componenta library used by this gate, its source revision and its projected package version. Unchanged libraries use immutable commits. Packages being changed together use `refactor/unified-application-build-2026-11-27`; these are development sources, and the projected version is not a claim that a release has been published. Every run records the actual resolved commits in `source-lock.json` and the resolved dependency graph in `composer.lock`.

The remote mode fetches public GitHub sources. A package workflow can override exactly one inventory entry with `COMPONENTA_REPOSITORY=componenta/package` and `COMPONENTA_REVISION=<commit>`. Both values are required together. They are passed directly as process arguments, never evaluated by a shell. The reusable workflow takes these values as `repository` and `revision` inputs.

Local mode copies tracked and nonignored untracked files from each named checkout. Locally deleted files stay deleted in the copy. Each snapshot records its commit, dirty file list, content digest and copied file count. A dirty snapshot cannot be reproduced from its Git commit alone. Input checkouts and their installed dependencies are never changed. Source symlinks that leave a checkout are rejected.

Each execution creates a new `integration/.work/<run>/` directory. An explicit `--work=/new/absolute/directory` is also supported. The directory must not already exist. The workspace contains copied sources, the generated Composer project, dependency lock, source lock, JSON report and individual command/JUnit logs. Keep the workspace if a local dirty snapshot needs to be inspected or rerun manually.

The generated Composer project intersects Skeleton's runtime requirements, every tested package's development requirements, the CQRS integration manifest and the declared test tools. All inventoried libraries are installed from their copied paths at their explicit projected versions. Their own runtime requirements and conflicts remain active. There are no `replace` declarations or ignored platform requirements. Composer scripts are disabled; the Componenta Composer plugin is not activated for this test workspace. Its provider-generation behavior is separate from application build testing.

Tests run in separate PHP processes. The official Pest executable is copied unchanged into each test project so Pest resolves that project's tests and hooks. A small project autoloader uses the shared Composer installation and adds that project's test namespaces. PHPUnit suites use their existing configuration, or an explicit bootstrap and test directory where the package has no configuration. The runner executes all declared suites, requires nonempty JUnit results and exits unsuccessfully if any suite fails.

The gate includes Skeleton's installer tests, application build adapters, all four PSR-7 adapters and the existing CQRS integration suite covering source/prepared behavior with transport, policy, retry, locking and transactions. It also runs the three packages whose compatibility bounds were changed for that CQRS suite and retains CQRSApp's PHPStan analysis gate with its existing configuration. Cycle's tests cover its optional schema preparation. The source inventory includes their runtime dependencies; it does not imply that every dependency's own test suite or every optional Componenta ecosystem is covered.

The runner separately generates the default Web, API and CLI projects through the actual public `Installer::install` method in disposable copies. Web uses noninteractive defaults; API and CLI choose their preset and keep the remaining defaults. Each unmodified output is saved as `presets/<name>/composer.generated.json`. Only the path repository inventory is then added, and Composer resolves the generated runtime and development requirements with `--no-install --no-scripts --no-plugins`. These gates check dependency satisfiability; they do not replace application runtime tests. Full/Auth and WebSocket presets are outside this gate and still require their separate integration work.

After the participating branches are published, other packages can call the central workflow:

```yaml
jobs:
  application-build:
    uses: componenta/skeleton/.github/workflows/build-integration.yml@refactor/unified-application-build-2026-11-27
    with:
      repository: ${{ github.repository }}
      revision: ${{ github.sha }}
    permissions:
      contents: read
```

The workflow uploads source/dependency locks, logs and reports even after a failed run. A local passing run does not establish that a remote GitHub Actions run has passed.
