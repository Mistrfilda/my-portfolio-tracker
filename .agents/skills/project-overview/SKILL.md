---
name: project-overview
description: Locate the owning module and relevant skills when a task crosses domains or its code location is unclear.
---

## Project Overview — Navigation Map

Use the map to locate the owning code and load only the guidance needed for the task. When a known file and its surrounding implementation already establish the owner, continue there.

### High-level map

- `clients/desktop/` — macOS Electron + React client with independent npm dependencies, build, and tests.
- `clients/ios/` — future Swift/SwiftUI client; currently a documented reserved location.
- `doc/openapi.yaml` — common HTTP contract for the backend and both client implementations.
- `docker/` and root deploy scripts — application images and release orchestration; Kubernetes definitions live in the separate `../kubedev` repository.
- `src/Asset/` — shared contracts and price infrastructure used by all asset types. Start here when the task affects multiple asset modules or shared price behavior.
- `src/Stock/` — the largest investment domain: assets, positions, dividends, prices, valuations, AI analysis.
- `src/Portu/` and `src/Crypto/` — asset-specific modules outside stocks.
- `src/Currency/` — exchange rates, currency conversion, and GBp to GBP handling.
- `src/JobRequest/` + `src/RabbitMQ/` — deferred jobs and queue-based processing.
- `src/Notification/` — outbound notifications, including Discord.
- `src/UI/` — presenters, controls, datagrids, forms, filters, icons, and shared template infrastructure.
- `src/Api/` and module `Api/` folders — REST API endpoints.
- `src/OAuth2/` — OAuth grants, client lifecycle, sessionless authorization, and token persistence.
- `puppeter/` — Puppeteer scrapers that typically produce JSON consumed by PHP downloaders.
- `config/` — DI wiring, routing, and environment-specific configuration.
- `tests/` — unit and integration tests.

### Choose the likely entry point

- **Infrastructure / Docker / Kubernetes / deployment**
	- Read [kubernetes-infrastructure](../kubernetes-infrastructure/SKILL.md) for image builds, manifests, storage, networking, scheduled workloads, and rollout diagnostics across this repository and `/Users/filip/Sites/kubedev`.

- **Desktop application**
	- Start in `clients/desktop/src/`. Read [desktop-electron](../desktop-electron/SKILL.md) for main/preload/IPC, [desktop-react-ui](../desktop-react-ui/SKILL.md) for the renderer, or [desktop-build](../desktop-build/SKILL.md) for dependencies, packaging, and build isolation.
	- OAuth and API integrations also use the relevant server skill; do not duplicate protocol or data-ownership rules in client guidance.

- **Price download / price history / price rendering**
	- Start in `src/Asset/` for shared contracts.
	- Then inspect the concrete module in `src/Stock/Price/`, `src/Portu/Price/`, or `src/Crypto/`.
	- Read `asset-price-system` before changing shared price logic.
	- Read `asset-price-downloaders` for source selection, HTTP/JSON imports, or downloader commands.

- **Positions / closed positions / portfolio value**
	- Start with the contracts in `src/Asset/Position/`.
	- Continue in `src/Stock/Position/`, `src/Crypto/Position/`, or `src/Portu/Position/`.
	- Read `asset-position-system` before changing position calculations or closing flows.

- **Dividends / dividend forecasts / stock fundamentals**
	- Start in `src/Stock/Dividend/`.
	- For valuation logic, continue to `src/Stock/Valuation/` and read `stock-valuation-models`.

- **Currencies / exchange rates / LSE pence handling**
	- Start in `src/Currency/`.
	- Read `currency-conversion` before changing conversion logic or data sources.

- **Presenters / controls / templates / admin screens**
	- Start in `src/UI/` and the owning module's `UI/` subtree.
	- Use `ui-base-presenters-templates` for presenter/control or typed-template changes.
	- Load `latte-templates`, `nette-forms`, `ui-forms-admin`, or `ui-datagrid` for the corresponding UI layer.

- **REST API**
	- Start in `src/Api/` or the module-specific `Api/` folder.
	- Read `api-slim` before adding or changing endpoints.
	- For OAuth grants, scopes, client commands, or application integration, use [oauth2-server](../oauth2-server/SKILL.md).

- **Background jobs / async recalculation / queue consumers**
	- Start in `src/JobRequest/` for generic deferred work.
	- Continue to `src/RabbitMQ/` for shared queue abstractions.
	- Use `job-request` for job types and payloads; use `rabbitmq-base` when changing the queue transport or a dedicated message flow.

- **Notifications**
	- Start in `src/Notification/`.
	- Read `notifications-discord` before adding or routing a notification.

- **Schema / entity / repository changes**
	- Start in the owning module under `src/` and in `src/Doctrine/` if shared persistence code is involved.
	- Read `doctrine-migrations` before editing entities or repositories.
	- For live database inspection through PhpStorm MCP, read `phpstorm-database`; inspecting schema or data does not require generating or applying a migration.

- **Scraping / downloaded JSON inputs**
	- Start in `puppeter/` for the scraper and the related PHP downloader in `src/`.
	- Read `puppeteer-scraping` before changing the scraper pipeline.

### Practical workflow

Mirror the nearest implementation in the owning module. For shared PHP behavior, inspect the base in `src/Asset/` or `src/UI/` and the affected downstream modules. Use `testing-conventions` when writing PHPUnit tests and the [AGENTS.md validation matrix](../../../AGENTS.md#validation-matrix) for final checks.
