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

- **`NotificationFacade`** — creates and persists a `Notification`, then publishes its `NotificationMessage` through `NotificationProducer`. Call this from domain code (new dividend detected, goal achieved, …).
- **`NotificationSenderFacade::process($notificationId)`** — invoked by `NotificationConsumer`; sends through the notification's selected channels using services collected by `typed(NotificationChannelSenderFacade)`.
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
5. Trigger it from domain code via `NotificationFacade::create(...)`. Delivery runs through the RabbitMQ notification consumer; do not call the sender directly from the web request.

### Adding a new channel

1. Create `src/Notification/<Channel>/` with a `Notification<Channel>SenderFacade` implementing `NotificationChannelSenderFacade`.
2. Register it in `config.neon`; autowiring via `typed(...)` makes it picked up by `NotificationSenderFacade`.
3. Add a value to `NotificationChannelEnum`.

### Rules

- Notification logging uses Monolog; critical errors go to Discord via `MonologDiscordHandler` (`%logger.discordWebhookUrl%`).
- Keep user-facing messages consistent with the existing notification language; exception messages and comments are English. Use `Nette\Utils\Json` for webhook payloads.
- Never send notifications from request lifecycle directly — create via `NotificationFacade` and let the consumer invoke the sender.
- Implementing or inspecting notification code is not authorization to send a real notification. For verification, mock the producer/channel; real delivery requires the user's explicit request.
