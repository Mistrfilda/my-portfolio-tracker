---
name: latte-templates
description: Invoke before creating or modifying any `.latte` file, including single-line changes. Provides this project's strict Latte typing, typed Template-class workflow, layout and snippet conventions, project filters, SVG rendering, Tailwind/Alpine coordination, and validation commands. Also trigger when the user mentions Latte syntax, tags, filters, escaping, snippets, or template inheritance.
---

# Latte Templates

Follow the project's strict typed-template workflow for every presenter and control template.

## Mandatory typing

- `latte.strictTypes` is enabled in `config/config.neon`.
- Create a matching typed Template class for every presenter or control and declare every assigned variable as a public typed property.
- Extend `BaseAdminPresenterTemplate`, `BasePresenterTemplate`, or `BaseControlTemplate` as appropriate.
- Add `@property-read <Name>Template $template` to presenters that use a specialized template class.
- In controls, call `$this->createTemplate(<Name>Template::class)` and assert the resulting type before assignment.
- Start the Latte file with `{templateType Fully\Qualified\TemplateClass}`.

Use `ui-base-presenters-templates` for the complete PHP-side pattern. Do not use dynamic template properties or `#[TemplateVariable]` as a replacement for the required Template class.

## Project layout and composition

- Presenter templates normally live in the owning module's `UI/templates/` directory.
- Control templates normally live beside the control or in its local `templates/` directory.
- Shared layouts live in `src/UI/Base/templates/`; preserve the existing block names such as `content`, `headingButtons`, `head`, and `customScripts`.
- Prefer a local `{define}` or partial for repeated markup. Do not introduce deep layout inheritance.

## Rendering rules

- Rely on Latte's context-aware escaping. Use `|noescape` only when the value is already trusted HTML and the surrounding code documents that contract.
- Keep business calculations, repository access, JSON parsing, and response transformation out of templates.
- Use `n:href` / `{link}` for Nette routes and `{control}` for components.
- Keep snippet names stable when Naja updates them; use `{snippet}` only where partial redraw is required.
- Use project filters from `ui-latte-filters` for currency, prices, percentages, dates, and durations.
- Never add raw `<svg>` markup; use `{renderSvg}` and `App\UI\Icon\SvgIcon` through `ui-svg-icons`.

## Frontend coordination

- Use `tailwind-plus-components` before composing or redesigning UI blocks.
- Use `alpine-tailwind` when adding Tailwind styling, Alpine directives, dropdowns, modals, toggles, or Naja/Alpine behavior.
- Preserve accessibility attributes and semantic labels when adapting components.

## Forms

Render project forms using the established form control/renderer pattern. Use `nette-forms` for validation and event behavior and `ui-forms-admin` for admin form construction. Do not set form defaults or mutate form state in Latte.

## Syntax references

- Read `references/tags.md` only when exact tag syntax is needed.
- Read `references/filters.md` only for built-in Latte filter behavior; project-specific filters remain in `ui-latte-filters`.

## Validation

- Run `composer latte-lint` for focused template validation.
- For PHP, Latte, or configuration changes, finish with `composer cs-fix && composer build-all`.
- For Tailwind/TypeScript changes, also run `npm run lint && npm run build-dev`.
