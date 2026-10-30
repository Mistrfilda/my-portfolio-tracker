# Codex Guidelines for My Portfolio Tracker

Treat these as project defaults. Explicit task instructions take precedence over skill guidance; loading a skill does not authorize unrelated actions.

## Working Style

- Follow the nearest established pattern for routine, reversible choices. State assumptions or tradeoffs that materially affect the result.
- Ask only when missing information would materially change the implementation and cannot be resolved from the code or conversation. Continue independent work while waiting.
- Reuse authorization already given for the task. If a skill actually blocks progress, link to its `SKILL.md`, quote the exact instruction, and explain why it applies instead of inventing an approval requirement.
- Make the smallest change that fulfills the request. Preserve unrelated code, formatting, and others' edits; remove only the imports or code your change makes unused. Report unrelated issues without fixing them.
- For multi-step work, state a brief plan and observable completion criteria. Continue through implementation, applicable checks, and any requested runtime verification; resolve failures caused by the change before handing back the result, or report the concrete blocker.

## Repository Boundaries

- The PHP backend, REST API, and existing Nette web application remain at the repository root. Root npm commands build only the web assets in `assets/`.
- `clients/desktop/` is the macOS Electron + React + TypeScript application. It owns its package manifest, lockfile, lint/type/build configuration, and tests.
- `clients/ios/` reserves the future native Swift/SwiftUI application. Do not introduce React Native or a shared npm workspace for iOS.
- `doc/openapi.yaml` is the shared API contract. Desktop and iOS will use separate language-specific clients; share the contract rather than platform implementation code.
- Keep client source, dependencies, and artifacts out of the web Docker context and web TypeScript/Tailwind scans. Desktop builds never invoke the web deploy scripts.

## Project Invariants
- Use **tabs** whenever the file style allows it.
- In PHP, follow **PSR-12** and prefer modern features already used in the codebase, including constructor property promotion where it fits.
- Use `Nette\Utils\Json` for JSON serialization and deserialization in PHP.
- In PHP, use `App\Utils\TypeValidator` for scalar type validation.
- PHP tests must be named `*Test.php`. Desktop unit tests use `*.test.ts` and Electron smoke tests use `*.spec.ts`.
- Prefer unit tests; never use real RabbitMQ queues or external HTTP APIs in tests.
- For Nette presenters and controls, always use typed Template classes based on `src/UI/Base/BaseControlTemplate.php` and `src/UI/Base/BasePresenterTemplate.php`.
- When assigning to `$this->template`, always add a matching public typed property to the Template class. Dynamic template properties are deprecated.
- Use English in exception messages and comments.
- In Latte/PHP, use `{renderSvg}` and `App\UI\Icon\SvgIcon` for icons. In the desktop renderer use `lucide-react`. Do not handwrite raw SVG markup.
- Desktop TypeScript is strict. Keep Node/Electron APIs in main or preload; expose only explicit typed operations through the preload bridge.
- Never commit, read, print, or open `config/config.local.neon` or `docker/config-docker.local.neon`. Do not use wildcard commands that can include them; target public configuration files explicitly.

## Validation Matrix
- Integration tests rebuild their configured database tables. Do not run integration suites or migrations against the same database concurrently.
- PHP, Latte, or NEON changes: finish with `composer cs-fix && composer build-all`.
- Web TypeScript/CSS or web frontend build-configuration changes: run `npm run lint && npm run build-dev` from the repository root; also run the PHP/Latte checks when the change crosses those layers.
- Desktop source, dependencies, or build/test configuration: run `npm --prefix clients/desktop run check`, `npm --prefix clients/desktop run build`, and `npm --prefix clients/desktop run test:smoke`. For packaging changes run `./build-desktop.sh`, which installs, checks, compiles, and packages the macOS arm64 application and ZIP. The separate Electron runtime checks require a graphical macOS session; report a concrete runtime limitation if unavailable.
- Web browser-test changes under `tests/Browser/`: run `npm run test-browser` when the local app and credentials are available; otherwise run `npx playwright test --list` and report the runtime limitation.
- Agent documentation/configuration and their validators (`AGENTS.md`, `.agents/skills/`, `.codex/`, `tools/validate-agent-docs`, `tools/validate-codex-config.py`): run `composer agent-docs`; a full application build is not required when changes are confined to these files and related documentation. For validator changes, also verify relevant valid and invalid inputs.
- After the applicable checks pass, repeat or broaden them only when further edits, failures, or unresolved concerns justify it. PhpStorm inspections supplement these checks.

## Skills
- Domain-specific guidance lives in `.agents/skills/`. Select skills by their descriptions and read the relevant `SKILL.md` before changing a specialized area. If a named project skill is missing from the session's skill list, look for `.agents/skills/<name>/SKILL.md`.
- Start with [project-overview](.agents/skills/project-overview/SKILL.md) when the task is broad, cross-module, or you are not sure where the code belongs.
- For GitHub pull requests, issues, and Actions in this repository, use [github-mcp](.agents/skills/github-mcp/SKILL.md). Keep local Git operations in the CLI.
- For Docker, Kubernetes, and deployment work across this repository and the separate `/Users/filip/Sites/kubedev` checkout, use [kubernetes-infrastructure](.agents/skills/kubernetes-infrastructure/SKILL.md).
- For desktop process boundaries use [desktop-electron](.agents/skills/desktop-electron/SKILL.md); for React/shadcn UI use [desktop-react-ui](.agents/skills/desktop-react-ui/SKILL.md); for packaging, dependencies, or build isolation use [desktop-build](.agents/skills/desktop-build/SKILL.md).
- Load companion skills and references only when their capability is needed for the current task; a related-skills list is not a prerequisite checklist.
- For local browser inspection, use [mcp-local-app-access](.agents/skills/mcp-local-app-access/SKILL.md). A request to inspect the local app authorizes its documented login flow using `.env.browser-tests` only for the real local login form, without exposing credentials. After a login redirect, complete that flow and return to the requested URL; do not treat the login page alone as a blocker or a reason to switch to database inspection.
- Keep the validation matrix above authoritative. Skills may describe focused checks, but should link here for final validation instead of duplicating it.

## Project Notes
- Human-oriented setup and infrastructure details belong in `readme.md` and related docs, not in these global guidelines.
- If a domain needs more than a short rule here, prefer a dedicated skill over expanding this file.
- Keep skills concise and project-specific. Put detailed syntax or API catalogs in a skill's `references/` folder and load them only when needed.
