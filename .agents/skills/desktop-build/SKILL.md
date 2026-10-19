---
name: desktop-build
description: Maintain desktop npm dependencies, compilation, macOS packaging, CI, and isolation from web production images.
---

# Desktop Build

Read [desktop setup](../../../doc/desktop.md) for commands and current distribution limits. The only implemented target is macOS arm64.

## Build ownership

- `clients/desktop/package.json` and its lockfile own desktop dependencies. Root npm dependencies remain exclusively for the existing web frontend.
- Renderer-only libraries belong in desktop `devDependencies` because Vite bundles them. Reserve `dependencies` for packages actually imported at runtime by main; otherwise electron-builder copies unused library source into the app.
- Preserve Node 24, npm 11.10+, and the five-day npm release-age policy in the desktop package.
- `electron.vite.config.ts` builds main, preload, and renderer into `out/`. `vite.renderer.config.ts` also supports the browser preview and injects separate development/production CSPs.
- `npm run dev` uses `electron-vite dev --watch`: renderer HMR, preload rebuild/reload, and main rebuild/restart. Reuse the existing development process on port 5173. Stop it before `npm ci`, a production build, or packaging; they replace dependencies or share its output directory.
- `electron-builder.yml` packages the compiled client into `artifacts/`. Keep its file selection restricted to client output and required client dependencies.
- The root build-desktop.sh installs locked dependencies, checks, builds, and creates the local application/ZIP. Native smoke testing is separate because it requires a graphical macOS session. The build never invokes Docker, registry publishing, or Kubernetes deployment.
- `.github/workflows/desktop-check.yaml` runs the client checks on macOS. Backend CI excludes client-only changes; OpenAPI changes also trigger desktop checks.

## Local native execution

- On this macOS machine, Electron launched from the agent command runner aborted in `HIServices::_RegisterApplication` before JavaScript. The native smoke test passed when started by PhpStorm MCP's `execute_terminal_command` with `executeInShell: false`, the known absolute `projectPath`, and `npm --prefix <absolute-project-path>/clients/desktop run test:smoke`.
- Prefer that IDE route for native runs and tests when PhpStorm MCP is available. Keep ordinary builds/static checks in the command runner. The direct-process setting avoids interactive/login shell startup files.
- `run dev` and its automatic main-process restart were also verified through this route. A terminal-tool timeout may leave development running; check the port/process before launching another instance.
- Discover npm run points with `get_run_configurations(filePath: "clients/desktop/package.json")` if using Run configurations. If the IDE cannot start a run configuration, use its direct terminal command; that was the verified route here.
- Do not repeatedly launch through a route already confirmed to cause the macOS registration crash. Report the limitation if no graphical execution route is available. Do not disable Electron sandboxing or macOS security to work around it.

## Distribution

The initial package uses an ad-hoc signature (`mac.identity: "-"`), with hardened runtime disabled, for local development. It uses no Apple certificate and is not notarized. Developer ID signing, notarization, automatic updates, and public release are later work. Do not enable certificate discovery or publishing as a side effect of a local build.

Keep a stable app identifier before introducing persistent authentication. When release signing is requested, keep certificate/notarization credentials outside the application bundle; never put them in renderer environment variables.

## Monorepo isolation

- `.dockerignore` excludes the entire client tree and the desktop entry script.
- Root `tsconfig.json` selects web assets only. Web Tailwind excludes `clients/` explicitly.
- iOS will use Swift/SwiftUI and Xcode with independent dependency management, not this npm package.
- Use [desktop-electron](../desktop-electron/SKILL.md) for native runtime changes and the root [validation matrix](../../../AGENTS.md#validation-matrix) for checks.
