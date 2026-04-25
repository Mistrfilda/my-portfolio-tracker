## Project Overview — Navigation Map

Use this skill when the task is broad, spans multiple modules, or you first need to understand where a change belongs.

### When to use

- The request mentions a feature, but not a concrete file or class.
- The task crosses backend, UI, API, async processing, or scraping.
- You need to decide which module owns a behavior before editing code.
- You are about to add a new feature and need the likely entry points.

### High-level map

- `src/Asset/` — shared contracts and price infrastructure used by all asset types. Start here when the task affects multiple asset modules or shared price behavior.
- `src/Stock/` — the largest investment domain: assets, positions, dividends, prices, valuations, AI analysis.
- `src/Portu/` and `src/Crypto/` — asset-specific modules outside stocks.
- `src/Currency/` — exchange rates, currency conversion, and GBp to GBP handling.
- `src/JobRequest/` + `src/RabbitMQ/` — deferred jobs and queue-based processing.
- `src/Notification/` — outbound notifications, including Discord.
- `src/UI/` — presenters, controls, datagrids, forms, filters, icons, and shared template infrastructure.
- `src/Api/` and module `Api/` folders — REST API endpoints.
- `puppeter/` — Puppeteer scrapers that typically produce JSON consumed by PHP downloaders.
- `config/` — DI wiring, routing, and environment-specific configuration.
- `tests/` — unit and integration tests.

### Choose the likely entry point

- **Price download / price history / price rendering**
	- Start in `src/Asset/` for shared contracts.
	- Then inspect the concrete module in `src/Stock/Price/`, `src/Portu/Price/`, or `src/Crypto/`.
	- Read `asset-price-system` before changing shared price logic.

- **Dividends / dividend forecasts / stock fundamentals**
	- Start in `src/Stock/Dividend/`.
	- For valuation logic, continue to `src/Stock/Valuation/` and read `stock-valuation-models`.

- **Currencies / exchange rates / LSE pence handling**
	- Start in `src/Currency/`.
	- Read `currency-conversion` before changing conversion logic or data sources.

- **Presenters / controls / templates / admin screens**
	- Start in `src/UI/` and the owning module's `UI/` subtree.
	- Read `ui-base-presenters-templates` first.
	- Then load `latte-templates`, `nette-forms`, `ui-forms-admin`, or `ui-datagrid` as needed.

- **REST API**
	- Start in `src/Api/` or the module-specific `Api/` folder.
	- Read `api-slim` before adding or changing endpoints.

- **Background jobs / async recalculation / queue consumers**
	- Start in `src/JobRequest/` for generic deferred work.
	- Continue to `src/RabbitMQ/` for shared queue abstractions.
	- Read `job-request` and `rabbitmq-base` before changing message flow.

- **Notifications**
	- Start in `src/Notification/`.
	- Read `notifications-discord` before adding or routing a notification.

- **Schema / entity / repository changes**
	- Start in the owning module under `src/` and in `src/Doctrine/` if shared persistence code is involved.
	- Read `doctrine-migrations` before editing entities or repositories.

- **Scraping / downloaded JSON inputs**
	- Start in `puppeter/` for the scraper and the related PHP downloader in `src/`.
	- Read `puppeteer-scraping` before changing the scraper pipeline.

### Practical workflow

1. Identify the owning module from the request.
2. Load the relevant specialized skill before editing.
3. Find the nearest existing implementation and mirror its pattern instead of inventing a new one.
4. If the task affects shared behavior, inspect both the shared base in `src/Asset/` or `src/UI/` and all touched downstream modules.
5. Verify with the relevant tests in `tests/Unit/` or `tests/Integration/`.

### Related skills

- `testing-conventions`
- `ui-base-presenters-templates`
- `doctrine-migrations`
- `asset-price-system`
- `currency-conversion`
- `job-request`
- `rabbitmq-base`
- `api-slim`
- `latte-templates`
- `nette-forms`
- `ui-datagrid`
- `ui-forms-admin`
- `notifications-discord`
- `puppeteer-scraping`
