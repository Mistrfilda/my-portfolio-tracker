---
name: desktop-electron
description: Maintain the macOS Electron main process, preload bridge, window lifecycle, and native integration in clients/desktop. Use desktop-react-ui for renderer components.
---

# Desktop Electron

The desktop is a standalone macOS client. Its implementation lives in [clients/desktop](../../../clients/desktop/); development and build commands are documented in [desktop setup](../../../doc/desktop.md).

## Process ownership

- `clients/desktop/src/main/` owns windows, native APIs, the packaged asset protocol, OAuth services in `auth/` and dashboard HTTP/validation in `dashboard/`.
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

## Authentication

- Follow [oauth2-server](../oauth2-server/SKILL.md) for server semantics. `auth/connection.json` contains public bundled settings; the current LAN server explicitly permits HTTP. Keep the HTTP opt-in explicit and reject credentials in URLs or client secrets in configuration.
- `auth/authorize.ts` owns the system-browser PKCE flow and short-lived IPv4 loopback listener. Preserve random state/verifier values, S256, callback validation, cancellation and timeout. Never expose a generic browser opener or accept authorization URLs from the renderer.
- `auth/client.ts` implements the server's form-encoded OAuth requests and validated `/me` response. Keep redirects and cookies disabled, request timeouts bounded, and raw responses/errors out of logs and IPC.
- `auth/session-service.ts` serializes lifecycle operations and rotating refreshes. Invalidate the stored old refresh token before sending a refresh request; an ambiguous result requires a new login, never automatic replay. Preserve cancellation/logout race coverage and the main-process single-instance lock.
- `auth/token-store.ts` uses asynchronous Electron `safeStorage` encryption with atomic private files, scoped by server/client/scopes. Never fall back to plaintext or move tokens into local storage/React. macOS Keychain access belongs to this application, not agent credential inspection.
- `clients/desktop/src/shared/session.ts` and preload expose only explicit account actions and token-free state notifications. Add future API operations as typed main-owned methods; never expose access tokens or arbitrary authenticated fetch to React.
- `SessionService.readAuthenticated` is main-only and serializes dashboard reads with refresh/logout. Logout invalidates queued lifecycle work as well as in-flight data. Preserve this generation check when adding resume/retry behavior.
- `clients/desktop/src/shared/dashboard.ts` defines the argument-free `dashboard.get()` bridge. Keep API paths fixed in main, validate selected fields against OpenAPI and retain section-specific failures. The renderer keeps last successful data only in memory, marks failed refreshes and discards it when the account/screen changes.
- Native smoke tests use temporary profiles, intercept browser opening and install synthetic dashboard IPC responses only inside the test process. Do not log into a real account in automated tests or add production fixture switches. See [desktop setup](../../../doc/desktop.md#oauth2-connection-and-login) for manual verification.

Use [desktop-build](../desktop-build/SKILL.md) for packaging changes, development watch behavior, and the verified PhpStorm route for native execution on this machine. Follow the root [validation matrix](../../../AGENTS.md#validation-matrix) for checks. Native smoke tests launch a separate Electron instance; renderer browser inspection uses standalone Playwright MCP.
