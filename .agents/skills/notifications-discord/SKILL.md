---
name: notifications-discord
description: Maintain application notifications and Discord delivery. Use for notification types, payloads, channel routing, webhook mapping, or explicitly requested notification delivery.
---

## Notification System

Centralized notification dispatch with pluggable channels. Currently only Discord is implemented.

### Components (`src/Notification/`)

- **`Notification`** entity + **`NotificationRepository`** — persisted notification (for history / retry). Immutable `recipient` references `AppAdmin`; null means shared market content. Never use a missing personal owner as shared delivery. The repository's global ID accessor is used by the worker, not a personal web history.
- **`NotificationTypeEnum`** — all supported types (dividend announcements, trend alerts, goal updates, etc.).
- **`NotificationStateEnum`** — lifecycle state.
- **`NotificationChannelEnum`** — which channel delivers it (Discord, …).
- **`NotificationParameterEnum`** / **`NotificationParameters`** — structured payload.

### Facades

- **`NotificationFacade`** — `create()` for shared market content, `createForRecipient(AppAdmin, ...)` for personal data. Persists the recipient and content before publishing the existing ID-only `NotificationMessage` through `NotificationProducer`. Goal notifications require a recipient.
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
		legacyRecipientUsername: filip
		userWebhooks: []
		webhooks:
			new_dividend: null
			trend_alert_default: null
			trend_alert_1_days: null
			trend_alert_7_days: null
			trend_alert_30_days: null
			goals_update: null
			default: null
```

Webhook URLs come from the existing secret-injection configuration. Keep public defaults nullable; never read local secret configuration or commit webhook URLs.

- `webhooks` handles shared messages and personal messages for `legacyRecipientUsername` only. Other accounts require `userWebhooks.<username>` with the same channel keys. A user map replaces the shared map completely; an empty map disables personal messages, and missing keys never fall back to another account.
- `goals_updates` takes precedence over the legacy `goals_update` alias, including explicit null. A missing trend-period key may fall back to that account's `trend_alert_default`; explicit null disables it.
- Stock/Crypto trend prices are shared; Portu values are personal and grouped by portfolio owner. Until goals gain ownership, their notifications explicitly use `LegacyPortfolioOwnerProvider`; follow [the isolation plan](../../../doc/user-data-isolation-plan.md).
- Retry loads the stored recipient, never the current web user. `SENT` is skipped; disabled channels retain the existing completed-state behavior. This does not guarantee exactly-once delivery across a crash after HTTP delivery.

### Adding a new notification type

1. Add case to `NotificationTypeEnum`.
2. Extend `NotificationParameters` / `NotificationParameterEnum` if the payload is new.
3. Register a webhook slot under `notifications.discord.webhooks` (nullable default so it works without a real webhook).
4. Map it in `DiscordChannelService` / `DiscordMessageService` so the right embed + webhook is used.
5. Trigger it via `NotificationFacade::create(...)` for shared content or `createForRecipient(...)` for personal content. Delivery runs through the RabbitMQ notification consumer; do not call the sender directly from the web request.

### Adding a new channel

1. Create `src/Notification/<Channel>/` with a `Notification<Channel>SenderFacade` implementing `NotificationChannelSenderFacade`.
2. Register it in `config.neon`; autowiring via `typed(...)` makes it picked up by `NotificationSenderFacade`.
3. Add a value to `NotificationChannelEnum`.

### Rules

- Notification logging uses Monolog; critical errors go to Discord via `MonologDiscordHandler` (`%logger.discordWebhookUrl%`).
- Keep user-facing messages consistent with the existing notification language; exception messages and comments are English. Use `Nette\Utils\Json` for webhook payloads.
- Never send notifications from request lifecycle directly — create via `NotificationFacade` and let the consumer invoke the sender.
- Implementing or inspecting notification code is not authorization to send a real notification. For verification, mock the producer/channel; real delivery requires the user's explicit request.
