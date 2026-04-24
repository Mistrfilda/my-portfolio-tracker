---
name: rabbitmq-base
description: Invoke before creating or modifying RabbitMQ consumers, producers, or messages in this project. Provides base classes and contracts from `src/RabbitMQ/` used across all modules (Stock, JobRequest, etc.). Use when adding a new async message flow, extending `BaseConsumer`/`BaseProducer`, implementing `RabbitMQMessage`/`RabbitMQDatabaseMessage`, or wiring a new queue/exchange. Also trigger when the user mentions RabbitMQ, AMQP, queues, consumers, or producers in this codebase.
---

## RabbitMQ — Base Abstractions

Foundation for all RabbitMQ consumers and producers in the system. Lives in `src/RabbitMQ/`.

### Core classes & interfaces

- **`BaseConsumer`** (abstract) — base class for all consumers. Extend it when implementing a new consumer.
- **`BaseProducer`** (abstract) — base class for all producers. Extend it when publishing messages.
- **`RabbitMQMessage`** (interface) — contract for any RabbitMQ message.
- **`RabbitMQDatabaseMessage`** (interface) — message that references a database entity (carries entity id/type).

### Module layout convention

Individual domain modules have their own `RabbitMQ/` subfolder with concrete implementations extending the base classes, e.g.:

- `src/JobRequest/RabbitMQ/` — `JobRequestConsumer`, `JobRequestProducer`, `JobRequestMessage`
- `src/Stock/.../RabbitMQ/` — stock-specific consumers/producers/messages

When adding a new async flow:

1. Create a `RabbitMQ/` subfolder inside the appropriate domain module.
2. Implement `RabbitMQMessage` (or `RabbitMQDatabaseMessage` when it refers to an entity).
3. Extend `BaseProducer` for publishing and `BaseConsumer` for handling.
4. Register the new queue/exchange in config and run:
   `bin/console-rabbit rabbitmq:declareQueuesAndExchanges`

### Rules

- Never use real RabbitMQ queues in tests — mock the producer/consumer or call the facade directly.
- For deferred long-running tasks, prefer the generic `JobRequest` system (`src/JobRequest/`) over creating a new dedicated queue.
- Always use `Nette\Utils\Json` for message payload (de)serialization.
- Exception messages and comments must be in English.
