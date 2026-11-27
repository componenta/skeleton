# Generated preset verification and remaining optional migrations

The actual Skeleton installer was executed in isolated copies of the current unified-build working tree. Web used noninteractive `NullIO`; API and CLI selected their preset and accepted all remaining defaults. The generated manifests were preserved before adding a local path repository. Composer then resolved every declared runtime and development requirement, using the selected package sources at explicit projected release versions. No platform or package constraint was ignored.

| Generated profile | Runtime packages | Development packages | Result |
| --- | ---: | ---: | --- |
| Default noninteractive Web / Nyholm | 74 | 48 | Composer SAT passed |
| API / Nyholm | 71 | 48 | Composer SAT passed |
| CLI | 54 | 48 | Composer SAT passed |

Web and API select Config 4, DI 6, CQRS App 6, CQRS Policy 4.1 and Policy 4. Policy App is removed from the generated project: its latest main provider is already an empty deprecated compatibility shim and its README asks applications to remove the dependency. No new Policy App release or code migration is necessary for this preset. See [Policy App README at 97577e3](https://github.com/componenta/policy-app/blob/97577e398a94bb59f6b330c72d8df024a34098d4/README.md) and [empty provider](https://github.com/componenta/policy-app/blob/97577e398a94bb59f6b330c72d8df024a34098d4/src/ConfigProvider.php).

## Optional profiles still outside this implementation

| Profile or option | Current installer selection | Observed incompatibility | Follow-up required |
| --- | --- | --- | --- |
| Full, or enabling authentication | `componenta/auth ^2.1.0` | Auth main / 2.1 generation requires Config 3 and DI 5. The selected new application graph requires Config 4 and DI 6. | Migrate the authentication feature to the split Auth 4 family and select the actual HTTP/session/password/JWT features that the application needs. Raising only the root Auth major would change the feature set. |
| WebSocket, including optional WebSocket support on another preset | `componenta/websocket-app ^2.0.0` | Released WebSocket App 2.0.0 requires App 5 and Config 3. The newer App-runtime branch targets App 6 but still requires Config 3; WebSocket Server's newest audit branch also requires Config 3. | Review and adapt WebSocket App and Server together, then update the installer floor and verify the generated transport entry point. A floor-only change to WebSocket App 3 does not resolve the remaining Config constraint. |

Sources for these remaining constraints:

- [Auth main manifest, f983a45](https://github.com/componenta/auth/blob/f983a4587013d74e99cc93597a6167047a8e761b/composer.json): Config `^3.0.0`, DI `^5.0.0`, broad authentication and transport dependencies.
- [Latest Auth 4 dependency-audit manifest, bd740de](https://github.com/componenta/auth/blob/bd740de4d766cd01fdac716be125d93369e3de88/composer.json): transport-neutral contracts, Clock and Identity; alias `4.0.x-dev`.
- [Released WebSocket App 2.0.0 manifest, 00f461b](https://github.com/componenta/websocket-app/blob/00f461bf08dbc2914cd759bbf57037e3dafcd9de/composer.json): App `^5.0.0`, Config `^3.0.0`.
- [WebSocket App runtime branch manifest, 1ba96ed](https://github.com/componenta/websocket-app/blob/1ba96ed6bfcf9edce7479b1dd7776653353f1a8e/composer.json): App `^6.0.0`, Config `^3.0.0`, branch alias `3.0.x-dev`.
- [WebSocket Server audit manifest, faef384](https://github.com/componenta/websocket-server/blob/faef384d46a2a8788e09c39d3ae8819c3401d7ec/composer.json): Config `^3.0.0`.

These optional authentication and WebSocket packages were inventoried without source changes. Passing Web/API/CLI dependency resolution does not establish that Full/Auth/WebSocket profiles are compatible, nor that the projected Componenta release versions have already been published.

Reproduction: run `php integration/run.php --sources=/absolute/path/to/componenta-checkouts` from this repository. The same gate runs remotely through `.github/workflows/build-integration.yml`. Its workspace contains source locks, generated Web/API/CLI manifests, resolved Composer locks, logs and a combined report. See [the integration runner](../integration/README.md) for exact inputs and coverage.
