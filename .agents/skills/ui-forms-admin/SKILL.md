---
name: ui-forms-admin
description: Build or change admin forms using AdminFormFactory, the project renderer, and custom inputs or containers.
---

## Admin Forms (project-specific)

All admin forms MUST be created via `App\UI\Control\Form\AdminFormFactory`, which returns an `AdminForm` preconfigured with the project's Tailwind `AdminFormRenderer`.

### Core

- **`AdminForm`** — extends Nette `Form`; used across every admin `*FormFactory`.
- **`AdminFormFactory`** — inject it into your `*FormFactory` class and call `$this->adminFormFactory->create()`.
- **`AdminFormRenderer`** — attached by the factory; do not replace.

### Custom inputs (`src/UI/Control/Form/Input/`)

- **`DatePickerInput`** — rendered as a native `input[type="date"]`; converts between `Y-m-d` and `ImmutableDateTime`.
- **`CustomFileUpload`** — styled upload with preview/reset.
- **`Multiplier`** — dynamic repeated group.

### Containers and their factories

- **`BirthdayContainerFactory`** — under `src/UI/Control/Form/Input/`; produces a day/month/year sub-form mapping to `DTO/Birthday`.
- **`TimeContainerFactory`** — also under `Input/`; produces an hour/minute container. Shared `AdminFormContainer` lives under `src/UI/Control/Form/Container/`.

### Pattern for a new form

1. Create `XxxFormFactory` in the module's `UI/` folder.
2. Constructor-inject `AdminFormFactory` and any services/facades needed.
3. Provide `public function create(callable $onSuccess, ...): AdminForm`:
	- `$form = $this->adminFormFactory->create();`
	- Add controls (`addText`, `addSelect`, `DatePickerInput`, `CustomFileUpload`, …).
	- Call `$form->onSuccess[] = fn($form, $values) => $onSuccess(...)`.
	- Return `$form`.
4. Register the factory under `services:` in `config/config.neon`.

### Rules

- Do not hand-roll forms with plain `new Form()` — use `AdminFormFactory` for consistent rendering and optional mapped values. The factory does not call `addProtection()`; do not assume it adds a CSRF token.
- For validation rules / groups / containers, see the generic `nette-forms` skill.
- When the form submits data that may take long (imports, heavy recalculations), dispatch the work via `JobRequest` — see `job-request` skill.
- For file uploads, save to a service that uses `Nette\Utils\FileSystem`; never call PHP `move_uploaded_file` directly.
- Exception messages and comments are English; keep form labels and user-facing validation messages consistent with the existing Czech UI. Indentation uses tabs.
