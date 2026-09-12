---
name: job-request
description: Use the generic async job system for deferred domain tasks. Apply when changing job dispatch or payloads, or deciding whether a task needs a dedicated queue.
---

## Job Request — Generic Async Job System

Generic deferred-job mechanism backed by RabbitMQ. Prefer it over creating a new dedicated queue for every async task.

### Components (`src/JobRequest/`)

- **`JobRequestTypeEnum`** — source of truth for supported job types.
- **`JobRequestFacade`** — public entry point; call it from any facade/presenter to enqueue a job.
- **`JobRequestProcessor`** — consumer-side dispatcher; routes each `JobRequestTypeEnum` to the concrete facade (`ExpenseTagFacade`, `StockAssetDividendForecastRecordFacade`, `PortfolioGoalUpdateFacade`, …).
- **`RabbitMQ/`** — `JobRequestMessage`, `JobRequestProducer`, `JobRequestConsumer` built on top of `src/RabbitMQ/` base classes (see [rabbitmq-base](../rabbitmq-base/SKILL.md)).

The Gemini enum case, convenience method, and processor branch remain for compatibility. New stock-analysis run and follow-up messages use the dedicated `StockAiAnalysisGeminiProcessProducer` in `src/Stock/AiAnalysis/RabbitMQ/`; see [stock-ai-analysis-gemini](../stock-ai-analysis-gemini/SKILL.md) when changing that flow.

### How to enqueue a job

```php
$this->jobRequestFacade->addToQueue(
	JobRequestTypeEnum::EXPENSE_TAG_PROCESS,
);
```

### Adding a new job type

1. Add a new case to `JobRequestTypeEnum`.
2. Implement the executing method on the appropriate domain facade (or create one if needed). Pass only the payload fields it needs and keep processing idempotent.
3. Wire the new branch in `JobRequestProcessor` (switch on enum -> call facade).
4. Add a named convenience method on `JobRequestFacade` when callers would otherwise duplicate payload keys, as with `addPortfolioPeriodStatisticProcessToQueue()`.
5. Make sure the target facade is registered in `config/config.neon` and autowired into `JobRequestProcessor`.
6. No new queue/exchange is needed — the existing JobRequest queue handles it.

### Rules

- Payload is serialized via `Nette\Utils\Json`; keep it small (IDs, not full entities).
- Processing must be **idempotent** — the consumer can re-run the job on retry.
- Test dispatch by calling `JobRequestProcessor::process(JobRequestTypeEnum $type, array $additionalData)` with a synthetic enum and payload and mocked target facades. The consumer extracts these arguments from `JobRequestMessage`; the processor does not accept a message object.
- Create a dedicated queue only when routing, QoS, or worker isolation differs from generic JobRequest (e.g. price updates, notifications) — see [rabbitmq-base](../rabbitmq-base/SKILL.md).
- Follow [testing-conventions](../testing-conventions/SKILL.md) for PHPUnit tests and the applicable [AGENTS.md validation](../../../AGENTS.md#validation-matrix).
