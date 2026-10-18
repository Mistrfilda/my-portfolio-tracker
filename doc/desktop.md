# Desktop and native clients

The repository is a monorepo with independent applications and dependency trees:

| Application | Location | Toolchain | Output |
| --- | --- | --- | --- |
| Web and REST API | Repository root | PHP/Nette, root npm assets | Existing Docker images |
| Desktop | `clients/desktop/` | Electron, React, TypeScript, Vite | macOS arm64 application and ZIP |
| iOS, planned | `clients/ios/` | Swift/SwiftUI and Xcode | Future iOS application |

The PHP application stays in place. There is no root npm workspace: installing or building the web does not install desktop dependencies. iOS will have its own native dependencies and build. The shared API contract is [OpenAPI](openapi.yaml), with separate client implementations for TypeScript and Swift.

## Current scope

The desktop skeleton includes a macOS window, sidebar navigation, an empty overview, appearance settings with system/light/dark modes, and an application-version bridge. It has no login, API requests, portfolio data, or automatic updates yet.

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

The smoke test checks rendering, navigation, the restricted preload bridge, Node isolation, and persistence of appearance. Its npm pre-test hook installs the Electron runtime before the timed test begins, so the first download does not consume the test timeout. It creates and removes its own temporary profile. Set `DESKTOP_EXECUTABLE` to the absolute path of a packaged app's `Contents/MacOS/My Portfolio` executable to run the same test against that package.

Native tests need a graphical macOS session. Use the PhpStorm execution route described above when working through Codex on this machine. Browser preview cannot verify native IPC or Electron's process isolation.

The initial dependency audit (2026-10-18) reports eight moderate entries from one transitive build-tool issue: [sprintf-js format-string denial of service](https://github.com/advisories/GHSA-hp3w-g68c-fv3c), via electron-builder's download/proxy logger. The advisory has no patched sprintf-js release. Keep this visible when upgrading the builder; do not apply npm's suggested downgrade or a major dependency override without testing. This chain is a development dependency and is excluded from the packaged application.

## Source boundaries

```text
clients/desktop/
├── src/
│   ├── main/                  Electron lifecycle, native operations, asset protocol
│   ├── preload/               Small, typed bridge exposed to the renderer
│   ├── shared/                Serializable bridge contracts and channel names
│   └── renderer/
│       ├── index.html
│       └── src/
│           ├── app.tsx        Providers and memory router
│           ├── components/    Application layout and theme provider
│           │   └── ui/        Owned shadcn component source
│           ├── pages/         Overview and appearance settings
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

Main owns operating-system capabilities. Preload exposes explicit methods, currently only `getAppInfo()`. The renderer is ordinary React with no Node access. It is sandboxed with context isolation enabled, and cannot choose arbitrary IPC channels.

Compiled assets load from the internal `app://portfolio/index.html` URL. File resolution is confined to the renderer output directory. IPC validates the window, top-level frame, and exact renderer URL. New windows, page navigation, and unrequested browser permissions are denied. Development permits only the fixed loopback Vite URL; packaged apps ignore it. The production CSP allows bundled resources and contains no external API origins.

React Router uses in-memory navigation so application routes do not change the document URL. Appearance is the only state stored in renderer local storage.

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

## Next task: OAuth2 and API configuration

Authentication is deliberately a separate implementation step. Follow the project's [OAuth2 client setup](../.agents/skills/oauth2-server/references/client-setup.md) and [OpenAPI contract](openapi.yaml).

The intended desktop flow is Authorization Code with S256 PKCE in the system browser, returning to a loopback callback handled by main. Desktop and iOS are public clients: they have a `client_id` and **no client secret**. Server credentials and signing credentials never belong in an installed client.

The authentication task will add validated configuration for the API base URL, authorization/token endpoints, public client ID, scopes, and callback. These are public settings, not secrets. Select the environment explicitly, require HTTPS outside documented loopback development, and associate saved sessions with that environment. Do not reuse the web's local secret configuration or place tokens in `VITE_*` variables.

Main will own token exchange, refresh, and API requests. Persistent tokens need OS-protected storage; renderer state should contain only the account/session information needed to render the UI. Logout, cancellation, expired sessions, and concurrent refresh behavior need tests as part of that task.

iOS will use Swift/SwiftUI, system web authentication, and Keychain with a separate OAuth client registration. Its actual Xcode project and Codex skill should be introduced with the first iOS implementation task.

## Codex guidance

Repository-wide boundaries and the authoritative validation matrix remain in [AGENTS.md](../AGENTS.md). Skills stay together in `.agents/skills/` and are loaded by task:

- [desktop-electron](../.agents/skills/desktop-electron/SKILL.md) — main/preload boundaries and native behavior.
- [desktop-react-ui](../.agents/skills/desktop-react-ui/SKILL.md) — React, shadcn, themes, and browser inspection.
- [desktop-build](../.agents/skills/desktop-build/SKILL.md) — dependencies, packaging, CI, and web isolation.

Existing web/PHP skills remain scoped to their application. The [project overview](../.agents/skills/project-overview/SKILL.md) routes cross-application work to the relevant guidance.
