---
name: ui-base-presenters-templates
description: Invoke before creating or modifying a Nette Presenter or Control, assigning template properties, or adding a presenter/control Template class. Provides the mandatory typed-template pattern based on `src/UI/Base/`, including presenter PHPDoc, control `createTemplate()` usage, Latte `{templateType}`, lifecycle boundaries, and routing registration.
---

# Base Presenters, Controls, and Templates

Every project presenter and control must use the appropriate base class and a typed Template class. Dynamic template properties are not allowed.

## Base classes

- `BasePresenter` — common presenter behavior.
- `BaseAdminPresenter` — authenticated admin UI and admin layout.
- `BaseSysadminPresenter` — sysadmin-only UI.
- `BaseFrontPresenter` — public/front layout.
- `BaseControl` — reusable UI controls.
- `BaseAdminPresenterTemplate`, `BasePresenterTemplate`, and `BaseControlTemplate` — typed template bases.

## Presenter pattern

1. Create `<PresenterName>Template` beside the presenter and extend the appropriate base Template class.
2. Declare every presenter-specific template variable as a public typed property; document array generics with PHPDoc.
3. Add this class-level annotation to the presenter:

```php
/**
 * @property-read ExampleTemplate $template
 */
class ExamplePresenter extends BaseAdminPresenter
```

4. Assign only declared properties in `render*()`, `action*()`, or shared lifecycle methods.
5. Add `{templateType App\...\ExampleTemplate}` to every matching Latte template.

## Control pattern

```php
$template = $this->createTemplate(ExampleControlTemplate::class);
assert($template instanceof ExampleControlTemplate);
$template->items = $items;
$template->setFile(__DIR__ . '/ExampleControl.latte');
$template->render();
```

Extend `BaseControlTemplate`, declare every assigned property, and add the matching `{templateType}` directive to the Latte file.

## Boundaries

- Use actions/signals for writes and redirects; use `render*()` for read-only view preparation.
- Keep business calculations and repository queries out of Template classes and Latte.
- Register a new presenter's mapping in `config/routing.neon`.
- Use `latte-templates` for template syntax and `nette-architecture` for placement/lifecycle decisions.

## Validation

- Run `composer latte-lint` for focused template validation.
- Finish PHP/Latte/config changes with `composer cs-fix && composer build-all`.
