---
name: notifications-discord
description: Invoke before sending a notification or adding a new notification type. Provides `src/Notification/` system – `NotificationFacade`, `NotificationSenderFacade`, `NotificationTypeEnum`, Discord channel (`DiscordChannelService`, `DiscordMessageService`) and webhook mapping in `config.neon`. Use when adding a new notification type, routing it to a specific Discord webhook, or integrating a non-Discord channel.
---

## Notification System

Centralized notification dispatch with pluggable channels. Currently only Discord is implemented.

### Components (`src/Notification/`)

- **`Notification`** entity + **`NotificationRepository`** — persisted notification (for history / retry).
- **`NotificationTypeEnum`** — all supported types (dividend announcements, trend alerts, goal updates, etc.).
- **`NotificationStateEnum`** — lifecycle state.
- **`NotificationChannelEnum`** — which channel delivers it (Discord, …).
- **`NotificationParameterEnum`** / **`NotificationParameters`** — structured payload.

### Facades

- **`NotificationFacade`** — create & persist a `Notification`. Call this from domain code (new dividend detected, goal achieved, …).
- **`NotificationSenderFacade`** — sends queued notifications; autowired with `typed(NotificationChannelSenderFacade)` so it iterates every available channel.
- **`NotificationChannelSenderFacade`** (interface) — contract for a channel sender.

### Discord channel (`src/Notification/Discord/`)

- **`NotificationDiscordSenderFacade`** — implements `NotificationChannelSenderFacade`.
- **`DiscordMessageService`** — builds the Discord webhook payload (embeds, colors).
- **`DiscordChannelService`** — maps `NotificationTypeEnum` → webhook URL via constructor arg `discordWebhooksMapping: %notifications.discord.webhooks%`.

### Webhook configuration (`config.neon`)

```
notifications:
	discord:
		webhooks:
			new_dividend: null
			trend_alert_default: null
			trend_alert_1_days: null
			trend_alert_7_days: null
			trend_alert_30_days: null
			goals_update: null
			default: null
```

Real URLs live in `config/config.local.neon` — do NOT commit them.

### Adding a new notification type

1. Add case to `NotificationTypeEnum`.
2. Extend `NotificationParameters` / `NotificationParameterEnum` if the payload is new.
3. Register a webhook slot under `notifications.discord.webhooks` (nullable default so it works without a real webhook).
4. Map it in `DiscordChannelService` / `DiscordMessageService` so the right embed + webhook is used.
5. Trigger it from domain code via `NotificationFacade::create(...)` — do NOT call the sender directly from the web request; `NotificationSenderFacade` flushes them (typically via CLI/cron).

### Adding a new channel

1. Create `src/Notification/<Channel>/` with a `Notification<Channel>SenderFacade` implementing `NotificationChannelSenderFacade`.
2. Register it in `config.neon`; autowiring via `typed(...)` makes it picked up by `NotificationSenderFacade`.
3. Add a value to `NotificationChannelEnum`.

### Rules

- Notification logging uses Monolog; critical errors go to Discord via `MonologDiscordHandler` (`%logger.discordWebhookUrl%`).
- Message strings in English; use `Nette\Utils\Json` for webhook payload.
- Never send notifications from request lifecycle directly — always create via `NotificationFacade` and let the sender flush them.
