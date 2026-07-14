---
name: nette-architecture
description: Invoke before designing or refactoring presenters, controls, UI factories, modules, CLI commands, or domain placement in this Nette application. Covers the project's domain-first `src/` structure, presenter lifecycle, `UI/` placement, base classes, routing mapping, and boundaries between presenters, facades, repositories, and commands. Also trigger when deciding where a new feature belongs.
---

# Nette Architecture

Use the current domain-first structure under `src/`; do not introduce a generic `app/Presentation`, `Core`, `Model`, or `Tasks` hierarchy.

## Choose the owner

- Place shared asset contracts and calculations in `src/Asset/` only when Stock, Crypto, and Portu genuinely share the behavior.
- Place domain behavior under its owning root such as `src/Stock/`, `src/Cash/`, `src/Currency/`, or `src/Notification/`.
- Keep UI code in the owning domain's `UI/` subtree.
- Keep reusable application UI infrastructure in `src/UI/`.
- Keep CLI commands near the behavior they invoke, normally in an owning `Command/` folder; commands coordinate and facades/services execute.
- Use `project-overview` when ownership is unclear or multiple modules are involved.

## Presenter lifecycle

1. `startup()` — shared setup and access checks; always call the parent implementation.
2. `action<Name>()` and `handle<Name>()` — writes, state changes, redirects, and signal handling.
3. `beforeRender()` — shared presentation setup.
4. `render<Name>()` — read-only data preparation for the template.
5. Latte template — rendering only.

Keep business rules in facades/services. Presenters coordinate request parameters, components, template data, flash messages, and redirects.

## Presenters and controls

- Extend the appropriate project base class from `src/UI/Base/`.
- Use a mandatory typed Template class for every presenter/control through `ui-base-presenters-templates`.
- Register new presenter mappings in `config/routing.neon`; the generic route itself lives in `src/Router/RouterFactory.php`.
- Keep a component or form factory close to its only consumer. Move it into shared `src/UI/` infrastructure only after there is real cross-domain reuse.
- Prefer composition through injected factories/services over deeper presenter inheritance.

## Persistence and APIs

- Repositories own Doctrine queries; callers consume named repository methods rather than constructing ad-hoc DQL.
- Facades coordinate entity changes, persistence, logging, and required side effects.
- Use `doctrine-migrations` for entities, repositories, or schema changes.
- Use `api-slim` for REST endpoints; Slim controllers are not Nette presenters.

## Validation

- Mirror the nearest implementation in the owning domain.
- Test domain logic below the presenter whenever possible.
- For code/config changes, finish with `composer cs-fix && composer build-all`.

## References

- Read `references/requires.md` only when adding Nette `#[Requires]` restrictions.
