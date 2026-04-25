---
name: ui-base-presenters-templates
description: Invoke before creating or modifying a Nette Presenter or Control in this project. Provides the mandatory Template-class pattern and the base presenters (`BasePresenter`, `BaseAdminPresenter`, `BaseSysadminPresenter`, `BaseFrontPresenter`, `BaseControl`) in `src/UI/Base/`. Use when the user adds `$this->template->foo = ...`, introduces a new presenter/control, or asks about template properties.
---

## Base Presenters, Controls & Templates

Every presenter and control in this project MUST extend a project base class and MUST use a typed Template class — dynamic template properties are deprecated.

### Base classes (`src/UI/Base/`)

- **`BasePresenter`** — abstract root for all presenters.
- **`BaseAdminPresenter`** — admin module (authenticated, admin layout).
- **`BaseSysadminPresenter`** — sysadmin-only screens.
- **`BaseFrontPresenter`** — public/front module.
- **`BaseControl`** — abstract root for all controls/components.
- **`BasePresenterParameters`** — shared config (pageTitle, storageName) injected via DI (`%basePresenterParameters.*%`).

### Template classes

- `BasePresenterTemplate` — for `BasePresenter` descendants.
- `BaseAdminPresenterTemplate` — for admin presenters.
- `BaseControlTemplate` — for controls.

### Mandatory pattern

1. For every presenter/control, create a matching `<Name>Template` class extending the appropriate base template class.
2. Declare every template variable as a **public typed property** on that Template class.
3. In the presenter/control annotate the template type:
	```php
	/** @var MyPresenterTemplate $template */
	$this->template->myVar = $value;
	```
4. Never assign a variable to `$this->template` without first declaring it on the Template class — this triggers deprecation warnings and breaks strict Latte typing (`latte.strictTypes: true`).

### Other rules

- Keep actions/signals for writes/redirects and `render<Name>` for preparing read-only template data — see `nette-architecture` skill.
- Page title is set via `BasePresenterParameters`; override per action when needed.
- All comments and exception messages in English; tabs for indentation.
- For Latte filter usage (`currency`, `summaryPriceFormat`, …) see `ui-latte-filters`.
- When creating new presenter, register route in `config/routing.neon`.
