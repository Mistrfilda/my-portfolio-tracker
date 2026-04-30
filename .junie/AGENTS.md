# Junie Guidelines for My Portfolio Tracker

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
- "Add validation" → "Write tests for invalid inputs, then make them pass" – do not start with invalid tests on new features.
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
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
- Never commit `config/config.local.neon` or `docker/config-docker.local.neon`.
- When code or configuration changes, finish validation with `composer cs-fix && composer build-all`.

## Skills
- Domain-specific guidance lives in `.junie/skills/`. Read the relevant `SKILL.md` before changing a specialized area.
- Start with `.junie/skills/project-overview/SKILL.md` when the task is broad, cross-module, or you are not sure where the code belongs.
- Commonly useful skills:
	- `testing-conventions` — PHPUnit layout, base classes, naming, and mocking rules.
	- `ui-base-presenters-templates` — typed template classes for presenters and controls.
	- `latte-templates`, `nette-forms`, `ui-forms-admin` — UI and form work.
	- `doctrine-migrations` — Doctrine entities, repositories, schema changes, migrations.
	- `api-slim` — REST API endpoints and OpenAPI-related work.
	- `job-request`, `rabbitmq-base` — asynchronous jobs and RabbitMQ integration.
	- `asset-price-system`, `currency-conversion`, `stock-valuation-models` — core investment-domain logic.

## Project Notes
- Human-oriented setup and infrastructure details belong in `readme.md` and related docs, not in these global guidelines.
- If a domain needs more than a short rule here, prefer a dedicated skill over expanding this file.
