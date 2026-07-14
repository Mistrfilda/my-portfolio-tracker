---
name: nette-utils
description: Invoke when using or changing `Nette\Utils` helpers such as `Json`, `FileSystem`, `Finder`, `Strings`, `Arrays`, `Image`, or `Html` in this project. Provides project-specific choices around JSON, scalar validation, files, strings, and time. Do not trigger for Nette Forms, DI configuration, Doctrine, or Latte filters unless a Utils helper is directly involved.
---

# Nette Utils

Prefer the established project helper and keep utility use at the infrastructure boundary.

## Project choices

- Use `Nette\Utils\Json` for all JSON serialization and deserialization. Do not call raw `json_encode()` / `json_decode()` in application code.
- Use `App\Utils\TypeValidator` for scalar extraction and validation. Do not replace it with `Nette\Utils\Validators` in project flows.
- Use `Nette\Utils\FileSystem` for application file reads, writes, copies, directory creation, and deletion.
- Use the injected `Mistrfilda\Datetime\DatetimeFactory` and its immutable types for domain timestamps; do not introduce `Nette\Utils\DateTime` or direct current-time construction into existing domain flows.
- Use `Nette\Utils\Strings` / `Arrays` only when they make the existing code clearer than native PHP and match nearby code.

## JSON

- Decode into arrays only when the consumer expects arrays; otherwise preserve the established object shape.
- Validate decoded top-level and scalar shapes before assigning them to entities or DTOs.
- Catch `Nette\Utils\JsonException` only where the layer can add meaningful domain context or recover.

## Files

- Keep paths injected or derived from existing configuration/folder services.
- Do not read local secret configuration files.
- In import pipelines, preserve the established request/results/processed-file lifecycle.
- Tests must use temporary directories or fixtures and clean up only their own files.

## References

Read only the reference needed for the task:

- `references/arrays.md`
- `references/strings.md`
- `references/image.md`
- `references/finder.md`

Use the installed `nette/utils` API and the nearest project implementation as the source of truth when a reference and current code differ.
