---
name: nette-configuration
description: Invoke before changing Nette DI services, parameters, autowiring, extensions, service locators, or configuration loading in this project. Covers the actual `config/config.neon`, `config/forms.neon`, `config/routing.neon`, and `config/rabbitmq.neon` layout plus service registration and `typed(...)` patterns. Combine with `neon-format` whenever editing a `.neon` file.
---

# Nette Configuration

Keep DI changes in the existing project files and mirror nearby service definitions.

## Configuration layout

`src/Bootstrap.php` loads, in order:

1. `config/config.neon` — parameters, extensions, framework configuration, and most services.
2. `config/forms.neon` — global Nette Forms messages.
3. `config/routing.neon` — presenter mapping.
4. `config/rabbitmq.neon` — RabbitMQ transport and queues.
5. Local overrides when present.

Never inspect, print, edit, or glob over local secret configuration files.

## Service registration

- Register services in the owning section of `config/config.neon` or in `config/rabbitmq.neon` for queue transport.
- Prefer a bare class service when constructor autowiring is sufficient.
- Pass named arguments when injecting scalar parameters or selecting among multiple services.
- Name a service only when another definition references it with `@serviceName` or the established configuration requires the name.
- Use `typed(Interface)` for existing multi-implementation registries such as asset price facades, download facades, notification channels, and valuation models.
- Do not add a `search` rule or a new config file for one service.

## Parameters and secrets

- Define safe defaults and public structure under `parameters:` in `config/config.neon`.
- Inject parameters into constructors; do not read configuration globally from application code.
- Never hardcode API keys, credentials, cookies, or real webhook URLs.
- Do not change local override files.

## Presenters, Latte, and extensions

- Register presenter class mappings in `config/routing.neon` when adding a presenter.
- Keep Latte strict typing and project filters under the existing `latte` and `latte.latteFactory` definitions.
- Add framework/DI extensions only when the dependency actually requires one; do not duplicate existing extension setup.

## Validation

- Use `neon-format` for syntax and tab indentation.
- Run `vendor/bin/neon-lint` on the explicit changed file for a focused check.
- Finish code/config changes with `composer cs-fix && composer build-all`.
