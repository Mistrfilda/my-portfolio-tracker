# Codex Guidelines for My Portfolio Tracker

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## Working Style

### 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple reasonable interpretations exist, present them instead of choosing one silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear in a way that would materially change the implementation, stop, name what is confusing, and ask.

### 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No speculative error handling.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, naming, architecture, and project patterns, even if you'd do it differently.
- If you notice unrelated dead code or issues, mention them - don't delete them.

When your changes create orphans:
- Remove imports, variables, or functions that your changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Cover valid and invalid inputs, then make the complete test suite pass."
- "Fix the bug" → "Add a regression test that reproduces it, then make it pass."
- "Add a feature" → "Test the requested behavior alongside the implementation; do not leave intentionally failing tests."
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

- Verify the result with checks appropriate to the task. For documentation-only changes, verify consistency instead of running builds.

## Project Invariants
- Use **tabs** whenever the file style allows it.
- Follow **PSR-12** and prefer modern PHP features already used in the codebase, including constructor property promotion where it fits.
- Use `Nette\Utils\Json` for JSON serialization and deserialization.
- Use `App\Utils\TypeValidator` for scalar type validation.
- Tests must be named `*Test.php`.
- Prefer unit tests; never use real RabbitMQ queues or external HTTP APIs in tests.
- For Nette presenters and controls, always use typed Template classes based on `src/UI/Base/BaseControlTemplate.php` and `src/UI/Base/BasePresenterTemplate.php`.
- When assigning to `$this->template`, always add a matching public typed property to the Template class. Dynamic template properties are deprecated.
- Use English in exception messages and comments.
- Never use raw `<svg>` markup; use `{renderSvg}` and `App\UI\Icon\SvgIcon`.
- Never commit, read, print, or open `config/config.local.neon` or `docker/config-docker.local.neon`. Do not use wildcard commands that can include them; target public configuration files explicitly.

## Validation Matrix
- PHP, Latte, or NEON changes: finish with `composer cs-fix && composer build-all`.
- TypeScript or CSS changes: run `npm run lint && npm run build-dev`; also run the PHP/Latte checks when the change crosses those layers.
- Browser-test changes: run `npm run test-browser` when the local app and credentials are available; otherwise run `npx playwright test --list` and report the runtime limitation.
- `AGENTS.md` or `.agents/skills/`-only changes: run `composer agent-docs`; a full application build is not required.

## Skills
- Domain-specific guidance lives in `.agents/skills/`. Read the relevant `SKILL.md` before changing a specialized area.
- Start with `.agents/skills/project-overview/SKILL.md` when the task is broad, cross-module, or you are not sure where the code belongs.
- Commonly useful skills:
	- `mcp-local-app-access` — MCP inspection of the local app behind login using `.env.browser-tests`.
	- `testing-conventions` — PHPUnit layout, base classes, naming, and mocking rules.
	- `ui-base-presenters-templates` — typed template classes for presenters and controls.
	- `latte-templates`, `nette-forms`, `ui-forms-admin` — UI and form work.
	- `doctrine-migrations` — Doctrine entities, repositories, schema changes, migrations.
	- `api-slim` — REST API endpoints and OpenAPI-related work.
	- `job-request`, `rabbitmq-base` — asynchronous jobs and RabbitMQ integration.
	- `asset-price-system`, `asset-price-downloaders`, `asset-position-system`, `currency-conversion`, `stock-valuation-models` — core investment-domain logic.

## Project Notes
- Human-oriented setup and infrastructure details belong in `readme.md` and related docs, not in these global guidelines.
- If a domain needs more than a short rule here, prefer a dedicated skill over expanding this file.
- Keep skills concise and project-specific. Put detailed syntax or API catalogs in a skill's `references/` folder and load them only when needed.
