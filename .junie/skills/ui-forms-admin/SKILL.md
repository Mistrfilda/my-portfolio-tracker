---
name: ui-forms-admin
description: Invoke before creating or modifying an admin form in this project. Provides project-specific form infrastructure (`AdminForm`, `AdminFormFactory`, `AdminFormRenderer`) and custom inputs/containers in `src/UI/Control/Form/`. Use when adding a new `*FormFactory`, custom input, date/time/birthday container, or `CustomFileUpload`. Complements the generic `nette-forms` skill.
---

## Admin Forms (project-specific)

All admin forms MUST be created via `App\UI\Control\Form\AdminFormFactory`, which returns an `AdminForm` preconfigured with `AdminFormRenderer` (Tailwind + Bootstrap‑like markup).

### Core

- **`AdminForm`** — extends Nette `Form`; used across every admin `*FormFactory`.
- **`AdminFormFactory`** — inject it into your `*FormFactory` class and call `$this->adminFormFactory->create()`.
- **`AdminFormRenderer`** — attached by the factory; do not replace.

### Custom inputs (`src/UI/Control/Form/Input/`)

- **`DatePickerInput`** — date picker bound to a JS flatpickr component.
- **`CustomFileUpload`** — styled upload with preview/reset.
- **`Multiplier`** — dynamic repeated group.

### Containers (`src/UI/Control/Form/Container/`)

- **`BirthdayContainerFactory`** — produces day/month/year sub-form mapping to `DTO/Birthday`.
- **`TimeContainerFactory`** — hour/minute container.

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

- Do not hand-roll forms with plain `new Form()` — always use `AdminFormFactory` to get consistent rendering and CSRF.
- For validation rules / groups / containers, see the generic `nette-forms` skill.
- When the form submits data that may take long (imports, heavy recalculations), dispatch the work via `JobRequest` — see `job-request` skill.
- For file uploads, save to a service that uses `Nette\Utils\FileSystem`; never call PHP `move_uploaded_file` directly.
- All error messages in English; indentation with tabs.
