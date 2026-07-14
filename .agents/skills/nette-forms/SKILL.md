---
name: nette-forms
description: Invoke before creating or modifying Nette Forms, form controls, validation rules, defaults, form events, containers, mapped values, or form error handling. Provides project conventions for form factories and typed value validation. Combine with `ui-forms-admin` for all application admin forms and with `latte-templates` when changing form markup.
---

# Nette Forms

Use the project's form factories and keep business behavior outside presenters and templates.

## Project pattern

- Build application forms in a dedicated `<Feature>FormFactory` near the owning UI.
- For admin/application forms, inject `App\UI\Control\Form\AdminFormFactory` and return `AdminForm`; never instantiate `new Form()` directly.
- Add controls, validation, defaults, and the `onSuccess` handler inside the form factory.
- Keep presenter `createComponent*()` methods limited to calling the factory and handling UI-level redirect/flash behavior.
- Call a facade/service from `onSuccess`; do not implement domain calculations in the handler.

Use `ui-forms-admin` for custom project inputs, renderer behavior, and `AdminForm` helpers.

## Values and validation

- Mark truly required fields with `setRequired()`; optional fields must remain explicitly optional.
- Use Nette form rules for input-level validation (`Form::Integer`, `Form::Float`, ranges, conditions, and required values).
- After successful validation, validate scalar values with `App\Utils\TypeValidator` before passing them into domain code.
- Use the existing `ArrayHash` or mapped-class pattern from the nearest form; do not introduce a new DTO mapping style for a single form.
- Convert enum values with `from()` only for guaranteed values and `tryFrom()` when an invalid or empty value is an expected input state.

## Defaults and edit flows

- Load the edited entity in the factory or owning presenter according to the nearest existing pattern.
- Set defaults with `$form->setDefaults(...)`, never in Latte.
- Keep create/update branching explicit when signatures or validation differ.
- Disabled controls are not submitted; preserve any immutable value from the loaded entity rather than reading it from submitted data.

## Errors and events

- Attach field-specific domain validation errors to the relevant control when the user can correct them.
- Add a form-level error for recoverable form failures.
- Do not catch broad `Throwable`/`Exception` merely to hide an unexpected failure; let infrastructure errors reach Tracy/logging.
- Use `onValidate` for cross-field validation and `onSuccess` for orchestration after all rules pass.

## References

- Read `references/controls.md` for exact built-in control APIs.
- Read `references/validation.md` for conditions and validation rules.
- Read `references/rendering.md` only when custom Latte rendering is required; the default admin renderer is preferred.

## Testing and validation

- Prefer unit tests for value transformation or domain behavior extracted from a form.
- Test custom controls or complex conditional validation when behavior changes.
- Finish PHP/config changes with `composer cs-fix && composer build-all`.
