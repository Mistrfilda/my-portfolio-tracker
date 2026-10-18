---
name: desktop-electron
description: Maintain the macOS Electron main process, preload bridge, window lifecycle, and native integration in clients/desktop. Use desktop-react-ui for renderer components.
---

# Desktop Electron

The desktop is a standalone macOS client. Its implementation lives in [clients/desktop](../../../clients/desktop/); development and build commands are documented in [desktop setup](../../../doc/desktop.md).

## Process ownership

- `clients/desktop/src/main/` owns windows, native APIs, the packaged asset protocol, and future HTTP/OAuth services.
- `clients/desktop/src/preload/index.ts` exposes the small typed `window.desktop` bridge. Keep the sandboxed preload bundled as one CommonJS entry with only Electron as a runtime import.
- `clients/desktop/src/shared/` contains serializable contracts and channel names, without Node or renderer dependencies.
- `clients/desktop/src/renderer/` contains React UI. Never expose raw `ipcRenderer`, arbitrary channel names, filesystem paths, or generic URL fetching to it.

## Existing boundaries

- Preserve `contextIsolation: true`, `sandbox: true`, `nodeIntegration: false`, and disabled webviews.
- Production UI loads from `app://portfolio/index.html`. `security.ts` confines protocol requests to the bundled renderer directory.
- IPC handlers validate the top-level sender, window, URL, and any new payload at runtime. TypeScript types do not validate messages.
- Development accepts only the configured loopback Vite URL. Packaged builds ignore `ELECTRON_RENDERER_URL`.
- Deny unrequested browser permissions, popups, and navigation. Add external-link handling only for a concrete feature, with URL validation.
- `--user-data-dir` supports isolated runtime tests. Do not use a user's normal profile for smoke tests.

## Future authentication

The initial skeleton has no authentication or API calls. When requested, follow [oauth2-server](../oauth2-server/SKILL.md) and its client reference. Desktop is a public PKCE client without a secret. Token storage and refresh belong in main; renderer state contains only user-facing session information.

Use [desktop-build](../desktop-build/SKILL.md) for packaging changes, development watch behavior, and the verified PhpStorm route for native execution on this machine. Follow the root [validation matrix](../../../AGENTS.md#validation-matrix) for checks. Native smoke tests launch a separate Electron instance; renderer browser inspection uses standalone Playwright MCP.
