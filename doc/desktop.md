# Desktop and native clients

The repository is a monorepo with independent applications and dependency trees:

| Application | Location | Toolchain | Output |
| --- | --- | --- | --- |
| Web and REST API | Repository root | PHP/Nette, root npm assets | Existing Docker images |
| Desktop | `clients/desktop/` | Electron, React, TypeScript, Vite | macOS arm64 application and ZIP |
| iOS, planned | `clients/ios/` | Swift/SwiftUI and Xcode | Future iOS application |

The PHP application stays in place. There is no root npm workspace: installing or building the web does not install desktop dependencies. iOS will have its own native dependencies and build. The shared API contract is [OpenAPI](openapi.yaml), with separate client implementations for TypeScript and Swift.

## Current scope

The desktop includes a macOS window, sidebar navigation, appearance settings, public-client OAuth login, and a personal portfolio dashboard. It opens the server's login/consent page in the system browser, restores and refreshes the saved session, displays the user returned by `/api/v1/me`, and supports logout. Position detail/editing and automatic updates are still future work.

The initial target is Apple Silicon (arm64), matching the development machine. Intel macOS, Windows, and Linux packages are not configured. The application identifier is `com.mistrfilda.portfolio.desktop`.

## Development

Requirements: macOS with a graphical session, Node.js 24 and npm 11.10 or newer within npm 11. Desktop dependencies have their own committed lockfile, exact direct versions, and the same five-day minimum release age as the web. First installation needs internet access.

Run from the repository root:

```bash
npm --prefix clients/desktop ci
npm --prefix clients/desktop run dev
```

The development command runs `electron-vite dev --watch`, starts Vite on `http://127.0.0.1:5173/`, and opens Electron. The port is fixed; if it is occupied, reuse or stop the existing desktop development process before starting another.

| Changed source | Development behavior |
| --- | --- |
| React components and CSS | Vite updates the open window, usually preserving component state |
| Preload | Recompile preload and reload the renderer |
| Main process | Recompile main and automatically restart Electron |
| Dependencies or build configuration | Stop and restart the development command; install dependencies when needed |

This is [electron-vite's development watch mode](https://electron-vite.org/guide/hmr-and-hot-reloading). Leave it running while editing; creating an application archive is unnecessary for ordinary development. Stop it before running `npm ci`, a production build, or packaging because these operations replace dependencies or share `out/` with the development build.

### PhpStorm and agent execution

For interactive development, open `clients/desktop/package.json` in PhpStorm and run its `dev` script using the gutter Run action or the npm tool window. The equivalent command from a normal terminal is `npm --prefix clients/desktop run dev`. Keep that development process running while Codex edits the source; its watcher performs the reloads and restarts in the same graphical session.

When Codex launches native checks on this machine, use PhpStorm MCP's `execute_terminal_command` with `executeInShell: false` and the absolute repository `projectPath`. A verified command is:

```text
npm --prefix /Users/filip/Sites/my-portfolio-tracker/clients/desktop run test:smoke
```

The direct-process setting avoids starting an interactive/login shell. Use the actual checkout path on another machine. The same mechanism can invoke `run dev`; do not start a second server when port 5173 is already in use. A tool timeout can return a partial log while development continues; check the listener before treating it as a stopped process. IDE execution keeps native processes in PhpStorm's graphical session. Builds and static checks can continue to run through the agent's ordinary command runner.

On 2026-10-18, the compiled application's native smoke test passed through PhpStorm, including window rendering, preload IPC, Node isolation, and appearance persistence. The IDE-started development watcher also successfully restarted Electron after a main-source change, with the replacement native process remaining alive. Launching from the agent's own command runner had instead aborted inside macOS `HIServices::_RegisterApplication` before application code. Prefer the verified IDE execution route; do not repeat that failing launch or use macOS's Reopen dialog as a development workflow. An npm run-point launch through MCP returned a generic process-start failure in this session; direct `execute_terminal_command` succeeded.

### Browser preview

A browser-only preview is available for layout inspection:

```bash
npm --prefix clients/desktop run dev:renderer
```

It uses the same port and renderer configuration. It has no Electron bridge, so the application version is only displayed in Electron. Use standalone Playwright MCP for browser inspection; the native smoke test verifies the actual bridge and Electron window.

## One-command build

From the repository root:

```bash
./build-desktop.sh
```

The script installs the lockfile with `npm ci`, checks lint/formatting/types/unit tests, compiles all three Electron layers, and packages the result. It does not need PHP, a database, Docker, API access, or an OAuth client. Native smoke testing is a separate command because it needs a graphical macOS session.

Outputs are ignored by Git:

- `clients/desktop/out/` — compiled main, preload, and renderer.
- `clients/desktop/artifacts/mac-arm64/My Portfolio.app` — application to open locally.
- `clients/desktop/artifacts/*.zip` — archive containing the application.

This is a local build with an ad-hoc signature, without an Apple Developer ID certificate or notarization. Hardened runtime is disabled for this development package. It is not a public distribution release; macOS can restrict applications downloaded from other machines. Developer ID signing, notarization, an application icon, and an update feed will be added when distribution is needed. The build uses no Developer ID credentials and does not publish artifacts.

ZIP is the initial packaging target and does not need a mounted disk image. A DMG can be added with the distribution work; the agent environment returned `hdiutil: create failed - Device not configured` when that format was tested.

Useful individual commands:

| Command, from repository root | Purpose |
| --- | --- |
| `npm --prefix clients/desktop run check` | ESLint, Prettier, TypeScript, and unit tests |
| `npm --prefix clients/desktop run format` | Apply desktop formatting |
| `npm --prefix clients/desktop run build` | Compile without packaging |
| `npm --prefix clients/desktop run start` | Open the compiled application |
| `npm --prefix clients/desktop run test:smoke` | Test the compiled app in Electron; run build first |
| `npm --prefix clients/desktop run package:mac` | Package existing compiled output |

The smoke tests check rendering, navigation, the restricted preload bridge, Node isolation, appearance persistence, and starting/cancelling a PKCE login. Dashboard coverage includes loading, refresh failures/recovery, empty portfolios, null statistics, logout, both themes, and the minimum window size. Tests intercept the OS browser opener and install synthetic IPC responses only inside isolated test processes; they never log into the deployed server or use a real account or Keychain credential. The npm pre-test hook installs Electron before the timed tests begin. Each test creates and removes its own temporary profile. Set `DESKTOP_EXECUTABLE` to the absolute path of a packaged app's `Contents/MacOS/My Portfolio` executable to test that package.

Native tests need a graphical macOS session. Use the PhpStorm execution route described above when working through Codex on this machine. Browser preview cannot verify native IPC or Electron's process isolation.

The initial dependency audit (2026-10-18) reports eight moderate entries from one transitive build-tool issue: [sprintf-js format-string denial of service](https://github.com/advisories/GHSA-hp3w-g68c-fv3c), via electron-builder's download/proxy logger. The advisory has no patched sprintf-js release. Keep this visible when upgrading the builder; do not apply npm's suggested downgrade or a major dependency override without testing. This chain is a development dependency and is excluded from the packaged application.

## Source boundaries

```text
clients/desktop/
├── src/
│   ├── main/                  Electron lifecycle, native operations, asset protocol
│   │   ├── auth/             Connection, PKCE callback, HTTP client, session and storage
│   │   └── dashboard/        Authenticated reads and runtime API validation
│   ├── preload/               Small, typed bridge exposed to the renderer
│   ├── shared/                Serializable bridge contracts and channel names
│   └── renderer/
│       ├── index.html
│       └── src/
│           ├── app.tsx        Providers and memory router
│           ├── components/    Application layout and theme provider
│           │   └── ui/        Owned shadcn component source
│           ├── pages/         Overview and appearance settings
│           ├── features/
│           │   └── dashboard/ Portfolio panels, loading and formatting
│           ├── lib/           Theme and class-name helpers
│           └── styles.css     Tailwind and semantic theme tokens
├── tests/
│   ├── unit/                  Vitest boundary checks
│   └── smoke/                 Playwright controlling Electron
├── components.json            shadcn generator settings
├── electron.vite.config.ts    Main, preload, and renderer build
├── vite.renderer.config.ts    Shared renderer configuration and browser preview
└── electron-builder.yml       macOS packaging
```

Main owns operating-system capabilities. Preload exposes `getAppInfo()`, a `session` API (get state, sign in, cancel, retry, sign out, and subscribe to state changes), and the argument-free `dashboard.get()` operation. The renderer is ordinary React with no Node access. It cannot choose IPC channels, authentication URLs, API paths, HTTP headers or token values. Main validates every IPC sender and rejects unexpected arguments.

Compiled assets load from the internal `app://portfolio/index.html` URL. File resolution is confined to the renderer output directory. IPC validates the window, top-level frame, and exact renderer URL. New windows, page navigation, and unrequested browser permissions are denied. Development permits only the fixed loopback Vite URL; packaged apps ignore it. The production CSP allows bundled resources and contains no external API origins.

React Router uses in-memory navigation so application routes do not change the document URL. Appearance is the only state stored in renderer local storage.

## Portfolio dashboard

After login, the overview loads three fixed GET endpoints from the existing server:

- `/api/v1/dashboard/summary`: open-position value, cost and profit in CZK, stocks/crypto/Portu allocation, stock trading currencies, historical performance, annual dividend entitlements, remaining dividend forecast and daily owned-stock movers.
- `/api/v1/portfolio/dividends?limit=8`: latest personal entitlements, preserving each record's original currency and announced payment date.
- `/api/v1/exchange-rates`: twelve shared currency pairs with their effective dates and sources.

The existing `portfolio:read market:read` authorization is sufficient; no new client or scope change is needed. `src/main/dashboard/` validates numeric responses against the selected shapes in [OpenAPI](openapi.yaml) and returns only explicitly selected fields. HTTP stays in main with bounded response size, timeouts, no redirects/cookies/cache and token-free errors. `SessionService.readAuthenticated` serializes reads with rotating refresh and logout, permits at most one 401 retry and discards responses when logout starts. Overlapping dashboard loads share one in-flight request.

The React feature lives in `src/renderer/src/features/dashboard/`. Data loads on entry and through **Obnovit**. Sections fail independently. A failed manual refresh preserves that section's last successful data in memory with an explicit stale-data warning; initial failure shows an error without fabricated totals. Leaving the screen or losing the signed-in state discards the view, including after account changes. There is no disk cache of portfolio data.

Amounts and percentage points are formatted for Czech locale. Missing performance/forecasts remain unavailable, while real zero amounts remain zero. Annual dividends include future recorded entitlements and are not labelled as settled payments. The summary timestamp is its assembly time, not the freshness of every price. Historical portfolio value charts need a future API time-series contract; the current composition and dividend bars use real summary values.

Native tests use synthetic fixtures; verify the deployed account manually by starting the app, signing in if necessary, and opening **Přehled**. No test reads real credentials or production portfolios.

## React and shadcn conventions

The UI uses React, Tailwind 4, shadcn/ui's Radix-based `new-york` components, and Lucide icons. The generated Button, Card, and Badge live in this repository: they are editable source, not a remote theme service. Radix supplies interaction/accessibility primitives, Tailwind supplies styling utilities, and the local components combine them into the visual controls.

Use semantic tokens from `styles.css` for colors, borders, and radii. Application composition belongs outside `components/ui/`. Renderer imports use `@/` aliases; shared class merging lives in `@/lib/utils`. Do not mix Base UI variants into the current Radix setup.

For a new component, run the shadcn CLI from `clients/desktop/`. The initial components were generated with shadcn 4.21.4:

```bash
cd clients/desktop
npx shadcn@4.21.4 add dialog
```

Review generated source, dependency changes, imports, and accessibility before using it. Keep class merging through the existing local helper rather than adding another equivalent utility. Renderer-only packages belong in `devDependencies` because Vite bundles them; reserve `dependencies` for external main-process runtime imports. Run the desktop checks afterward.

## Build and CI isolation

- `.dockerignore` excludes the entire `clients/` tree and the desktop build script from web production images.
- The root TypeScript configuration selects only `assets/`. Web Tailwind explicitly excludes `clients/`.
- Existing web build/deploy commands keep their current meaning.
- `.github/workflows/app-check.yaml` ignores client-only changes.
- `.github/workflows/desktop-check.yaml` runs desktop checks, compilation, native smoke testing, and local packaging on macOS arm64. Changes to the OpenAPI contract also trigger it. It does not publish a release.

## OAuth2 connection and login

Public connection settings live in [connection.json](../clients/desktop/src/main/auth/connection.json) and are validated and bundled into main. The configured server is `http://my-new-portfolio-tracker.internal`, with public client ID `16ed2d5cc4b880ca0d45a3262e02c61d71a194b9` and scopes `portfolio:read market:read`. That client is already registered as “My Portfolio Desktop (internal)”.

`allowInsecureHttp: true` explicitly enables the requested LAN deployment. HTTPS remains the normal configuration requirement when that option is false. HTTP does not encrypt traffic, even within a LAN; it is an explicit choice for this installation. No global certificate validation or Electron security settings are disabled.

To target another environment, edit the public connection file, register a separate public client there, and restart development or rebuild the application. The server URL may include a deployment subdirectory. Endpoint paths are derived from it; the renderer cannot override them. No client secret, password, signing key or token belongs in this file or a `VITE_*` variable. Follow the [server registration instructions](../.agents/skills/oauth2-server/references/client-setup.md) and [OpenAPI contract](openapi.yaml).

Click **Přihlásit se** in the overview or settings. The default browser opens the server's login and consent page. Enter the existing account credentials there. Main generates independent random `state` and PKCE verifier values, opens a temporary listener on `127.0.0.1` with a free port, and exchanges the returned code using S256 PKCE. The registered callback is `http://127.0.0.1:8000/oauth/callback`; the server allows a different loopback port while requiring the same host and path. The callback checks host, path, method, state and duplicate parameters, then closes after completion, cancellation or five minutes.

After exchanging the code, main calls `GET /api/v1/me` and shares only the account ID, name, username and session status with React. This server uses OAuth2, not OpenID Connect. Browser login remains separate from the existing web application's session.

### Session storage and refresh

- The token pair is encrypted using Electron's asynchronous `safeStorage` API. On macOS its encryption key is protected by Keychain. Ciphertext lives under `app.getPath('userData')/sessions/<connection-hash>.bin` with mode `0600`, using atomic replacement and filesystem synchronization. There is no plaintext fallback.
- The connection hash includes the normalized server URL, client ID and scopes, so switching environments cannot reuse another environment's tokens. A single-instance lock prevents two processes sharing the same profile from rotating its refresh token concurrently.
- Startup restores the session and verifies the user. Main serializes session operations, refreshes shortly before expiry and revalidates after macOS resumes from sleep. A rejected access token gets at most one refresh and retry.
- Refresh tokens rotate. Before making the refresh request, main durably removes the previous saved token. If the process stops or the response is uncertain, the next attempt requires browser login instead of replaying an already consumed token and revoking its authorization family. A successfully returned replacement is saved atomically.
- An offline user lookup can be retried while keeping an unexpired session. An uncertain refresh requires a new login. The server's fixed 30-day authorization-family limit still applies.
- Logout first removes local credentials, then revokes the server authorization family. If the server is unreachable, the UI explicitly reports that local logout succeeded but remote revocation could not be confirmed. A local deletion failure is shown as an error.

macOS may request Keychain access on first use. A consistently signed distribution build is needed to avoid repeated permission prompts across application updates; the current local ad-hoc package is still intended for development. See [Electron safeStorage](https://www.electronjs.org/docs/latest/api/safe-storage).

Unit tests cover configuration isolation, PKCE/callback validation, token HTTP contracts, encrypted storage, cancellation races, refresh serialization, replay prevention and logout failures. Native smoke testing through PhpStorm passes without production credentials. The deployed server's login form was verified with this registered client and a varying callback port; a complete real-account login and Keychain persistence check still requires entering the user's credentials in the browser.

iOS will use Swift/SwiftUI, system web authentication, and Keychain with a separate OAuth client registration. Its actual Xcode project and Codex skill should be introduced with the first iOS implementation task.

## Codex guidance

Repository-wide boundaries and the authoritative validation matrix remain in [AGENTS.md](../AGENTS.md). Skills stay together in `.agents/skills/` and are loaded by task:

- [desktop-electron](../.agents/skills/desktop-electron/SKILL.md) — main/preload boundaries and native behavior.
- [desktop-react-ui](../.agents/skills/desktop-react-ui/SKILL.md) — React, shadcn, themes, and browser inspection.
- [desktop-build](../.agents/skills/desktop-build/SKILL.md) — dependencies, packaging, CI, and web isolation.

Existing web/PHP skills remain scoped to their application. The [project overview](../.agents/skills/project-overview/SKILL.md) routes cross-application work to the relevant guidance.
