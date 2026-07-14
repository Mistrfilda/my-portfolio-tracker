---
name: neon-format
description: Invoke before creating or modifying `.neon` files. Provides project NEON syntax, tab indentation, block and inline notation, DI entity syntax, safe handling of local overrides, and `vendor/bin/neon-lint` validation. Combine with `nette-configuration` for services, parameters, autowiring, or extensions, and with the relevant specialized skill for `phpstan.neon` or RabbitMQ configuration.
---

# NEON Format

Preserve the surrounding file style and use tabs for block indentation.

## Syntax

- Write mappings as `key: value` with a space after the colon.
- Use `- value` for block sequences.
- Use `[a, b]` or `{key: value}` only for short, simple inline values.
- Quote values that contain NEON punctuation, look like booleans/dates/numbers, or need leading/trailing whitespace.
- Use `null`, `true`, and `false` consistently with the surrounding project configuration.
- Keep DI entities and named arguments in their established form, for example `Service(argument: %parameter%)` and `typed(Interface)`.

Do not mechanically convert tabs to spaces. YAML files such as `doc/openapi.yaml` and GitHub workflows are not NEON and use spaces.

## Project files

- `config/config.neon` — main framework, parameters, and services.
- `config/forms.neon` — form messages.
- `config/routing.neon` — presenter mappings.
- `config/rabbitmq.neon` — queue transport.
- `phpstan.neon` — static-analysis configuration.

Never inspect, print, edit, or include local secret configuration files in wildcard commands.

## PHP API

Use `Nette\Neon\Neon::decode()`, `decodeFile()`, and `encode()` when application code must process NEON. Preserve `Nette\Neon\Exception` unless the current layer can provide useful context.

## Validation

- Run `vendor/bin/neon-lint <explicit-changed-file>` for a focused syntax check.
- Finish code/config changes with `composer cs-fix && composer build-all`.
