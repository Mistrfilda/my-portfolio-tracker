# Codex Guidelines for My Portfolio Tracker

Treat these as project defaults. Explicit task instructions take precedence over skill guidance; loading a skill does not authorize unrelated actions.

## Working Style

- Follow the nearest established pattern for routine, reversible choices. State assumptions or tradeoffs that materially affect the result.
- Ask only when missing information would materially change the implementation and cannot be resolved from the code or conversation. Continue independent work while waiting.
- Reuse authorization already given for the task. If a skill actually blocks progress, identify the file and exact instruction instead of inventing an approval requirement.
- Make the smallest change that fulfills the request. Preserve unrelated code, formatting, and others' edits; remove only the imports or code your change makes unused. Report unrelated issues without fixing them.
- For multi-step work, state a brief plan and observable completion criteria. Continue through implementation, applicable checks, and any requested runtime verification; resolve failures caused by the change before handing back the result, or report the concrete blocker.

## Project Invariants
- Use **tabs** whenever the file style allows it.
- Follow **PSR-12** and prefer modern PHP features already used in the codebase, including constructor property promotion where it fits.
- Use `Nette\Utils\Json` for JSON serialization and deserialization in PHP.
- Use `App\Utils\TypeValidator` for scalar type validation.
- Tests must be named `*Test.php`.
- Prefer unit tests; never use real RabbitMQ queues or external HTTP APIs in tests.
- For Nette presenters and controls, always use typed Template classes based on `src/UI/Base/BaseControlTemplate.php` and `src/UI/Base/BasePresenterTemplate.php`.
- When assigning to `$this->template`, always add a matching public typed property to the Template class. Dynamic template properties are deprecated.
- Use English in exception messages and comments.
- Never use raw `<svg>` markup; use `{renderSvg}` and `App\UI\Icon\SvgIcon`.
- Never commit, read, print, or open `config/config.local.neon` or `docker/config-docker.local.neon`. Do not use wildcard commands that can include them; target public configuration files explicitly.

## Validation Matrix
- Integration tests rebuild their configured database tables. Do not run integration suites or migrations against the same database concurrently.
- PHP, Latte, or NEON changes: finish with `composer cs-fix && composer build-all`.
- TypeScript or CSS changes: run `npm run lint && npm run build-dev`; also run the PHP/Latte checks when the change crosses those layers.
- Browser-test changes: run `npm run test-browser` when the local app and credentials are available; otherwise run `npx playwright test --list` and report the runtime limitation.
- Agent documentation/configuration and their validators (`AGENTS.md`, `.agents/skills/`, `.codex/`, `tools/validate-agent-docs`, `tools/validate-codex-config.py`): run `composer agent-docs`; a full application build is not required when changes are confined to these files and related documentation. For validator changes, also verify relevant valid and invalid inputs.
- After the applicable checks pass, repeat or broaden them only when further edits, failures, or unresolved concerns justify it. PhpStorm inspections supplement these checks.

## Skills
- Domain-specific guidance lives in `.agents/skills/`. Read the relevant `SKILL.md` before changing a specialized area.
- Start with `.agents/skills/project-overview/SKILL.md` when the task is broad, cross-module, or you are not sure where the code belongs.
- Load companion skills and references only when their capability is needed for the current task; a related-skills list is not a prerequisite checklist.
- For local browser inspection, use [mcp-local-app-access](.agents/skills/mcp-local-app-access/SKILL.md). A request to inspect the local app authorizes its documented login flow using `.env.browser-tests` only for the real local login form, without exposing credentials. After a login redirect, complete that flow and return to the requested URL; do not treat the login page alone as a blocker or a reason to switch to database inspection.
- Keep the validation matrix above authoritative. Skills may describe focused checks, but should link here for final validation instead of duplicating it.

## Project Notes
- Human-oriented setup and infrastructure details belong in `readme.md` and related docs, not in these global guidelines.
- If a domain needs more than a short rule here, prefer a dedicated skill over expanding this file.
- Keep skills concise and project-specific. Put detailed syntax or API catalogs in a skill's `references/` folder and load them only when needed.
