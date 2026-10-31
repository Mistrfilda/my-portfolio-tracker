---
name: rabbitmq-base
description: Maintain RabbitMQ transport, messages, producers, consumers, queue configuration, and workers. Use when implementing or changing async message delivery.
---

## RabbitMQ — Base Abstractions

Foundation for all RabbitMQ consumers, producers, queue declarations, and workers in the system. The Composer package `mistrfilda/amqp` provides the transport, base classes, message contracts, and console commands under `Mistrfilda\Amqp`. The application retains only its entity-to-message contract in `src/RabbitMQ/`.

Use the package classes directly; do not copy their implementations back into the application or reintroduce `contributte/rabbitmq` or `bunny/bunny` types.

### Core classes & interfaces

- **`BaseConsumer`** (abstract) — base class for all consumers. It implements `RabbitMQConsumerHandler`, accepts a JSON payload string, maps it to the configured message class, calls `processMessage()`, and returns `RabbitMQConsumeResult::Ack` on success.
- **`BaseProducer`** (abstract) — base class for all producers. It validates the message type, serializes it through Valinor + `Nette\Utils\Json`, and publishes through `RabbitMQPublisher` to the configured queue name.
- **`RabbitMQMessage`** (interface) — contract for any RabbitMQ message.
- **`App\RabbitMQ\RabbitMQDatabaseMessage`** (interface) — application entity contract whose `getRabbitMqMessage()` method creates a package `RabbitMQMessage`; it is not a message DTO itself.
- **`RabbitMQPublisher`** (interface) — publishing contract used by producers; implemented by `PhpAmqpLibRabbitMQPublisher`.
- **`RabbitMQConsumerHandler`** (interface) — consumer contract used by the worker runner.
- **`RabbitMQConsumeResult`** (enum) — maps handler results to AMQP `ack`, `nack`, or `reject`.
- **`RabbitMQQueueConfig`** and **`RabbitMQQueueConfigCollection`** — describe queue name, consumer name, handler service, durability, and prefetch count.
- **`RabbitMQConnectionConfig`**, **`RabbitMQConnectionFactory`**, **`RabbitMQQueueDeclarator`**, and **`RabbitMQConsumerRunner`** — low-level transport services around `php-amqplib`.

### Commands and configuration

- Queue connection, queue list, concrete consumers, and producer queue names are wired in `config/rabbitmq.neon`. Shared services and command classes use `Mistrfilda\Amqp`; domain consumers and producers stay in their application modules.
- Declare configured durable queues with:
  `bin/console-rabbit rabbitmq:declare-queues`
- Run a consumer with:
  `bin/console-rabbit rabbitmq:consumer <consumerName> [secondsToLive]`
- Current consumer names are configured in `RabbitMQQueueConfig` services, for example `notificationConsumer` and `jobRequestConsumer`.
- `prefetchCount` is configured per queue and is applied by `RabbitMQConsumerRunner` through `basic_qos`.

### Module layout convention

For a new deferred task, evaluate [job-request](../job-request/SKILL.md) before adding queue infrastructure. Reuse the generic JobRequest queue when its routing/QoS fits, or the owning module's established queue for an existing flow. Add a dedicated queue only when it needs different routing/QoS or worker isolation.

Individual domain modules have their own `RabbitMQ/` subfolder with concrete implementations extending the base classes, e.g.:

- `src/JobRequest/RabbitMQ/` — `JobRequestConsumer`, `JobRequestProducer`, `JobRequestMessage`
- `src/Notification/RabbitMQ/` — `NotificationConsumer`, `NotificationProducer`, `NotificationMessage`

When a dedicated queue is needed:

1. Create a `RabbitMQ/` subfolder inside the appropriate domain module.
2. Implement `Mistrfilda\Amqp\RabbitMQMessage` on the message DTO. Use the local `RabbitMQDatabaseMessage` contract on an entity only when it needs to create its message.
3. Extend `BaseProducer` for publishing and `BaseConsumer` for handling.
4. Register the concrete consumer and producer services in `config/rabbitmq.neon`.
5. Add a `RabbitMQQueueConfig` entry with the queue name, consumer name, consumer service, and prefetch count.
6. Run `bin/console-rabbit rabbitmq:declare-queues` before using the queue.

### Rules

- Never use real RabbitMQ queues in tests — mock `RabbitMQPublisher`, call `RabbitMQConsumerHandler::consume()` directly, or test the facade/service boundary.
- Transport, base-class, and command unit tests belong to the `mistrfilda/amqp` repository. Keep domain payload, processing, and application wiring tests here.
- Always use `Nette\Utils\Json` for message payload (de)serialization.
- Keep message DTOs backward-compatible when existing queued messages may still be waiting in RabbitMQ.
