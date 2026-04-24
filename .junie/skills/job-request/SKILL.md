---
name: job-request
description: Invoke before offloading a long-running task to async processing. Provides the `src/JobRequest/` system (`JobRequestTypeEnum`, `JobRequestFacade`, `JobRequestProcessor`) which dispatches jobs via RabbitMQ. Use when something needs to run outside the web request (recalculations, tag processing, goal updates) and before creating a brand-new dedicated queue.
---

## Job Request — Generic Async Job System

Generic deferred-job mechanism backed by RabbitMQ. Prefer it over creating a new dedicated queue for every async task.

### Components (`src/JobRequest/`)

- **`JobRequestTypeEnum`** — enumerates supported job types. Current values:
	- `expense_tag_process`
	- `stock_asset_dividend_forecast_recalculate`
	- `stock_asset_dividend_forecast_recalculate_all`
	- `portfolio_goal_update`
- **`JobRequestFacade`** — public entry point; call it from any facade/presenter to enqueue a job.
- **`JobRequestProcessor`** — consumer-side dispatcher; routes each `JobRequestTypeEnum` to the concrete facade (`ExpenseTagFacade`, `StockAssetDividendForecastRecordFacade`, `PortfolioGoalUpdateFacade`, …).
- **`RabbitMQ/`** — `JobRequestMessage`, `JobRequestProducer`, `JobRequestConsumer` built on top of `src/RabbitMQ/` base classes (see `rabbitmq-base` skill).

### How to enqueue a job

```php
$this->jobRequestFacade->create(
	JobRequestTypeEnum::EXPENSE_TAG_PROCESS,
	['expenseTagId' => $tag->getId()->toString()],
);
```

### Adding a new job type

1. Add a new case to `JobRequestTypeEnum`.
2. Implement the executing method on the appropriate domain facade (or create one if needed) — must accept the payload and be idempotent.
3. Wire the new branch in `JobRequestProcessor` (switch on enum -> call facade).
4. Make sure the target facade is registered in `config/config.neon` and autowired into `JobRequestProcessor`.
5. No new queue/exchange is needed — the existing JobRequest queue handles it.

### Rules

- Payload is serialized via `Nette\Utils\Json`; keep it small (IDs, not full entities).
- Processing must be **idempotent** — the consumer can re-run the job on retry.
- Never use real RabbitMQ in tests; test `JobRequestProcessor` by calling it directly with a synthetic `JobRequestMessage` (or test the target facade in isolation).
- Create a dedicated queue only when routing/QoS differs from generic JobRequest (e.g. price updates, notifications) — see `rabbitmq-base`.
