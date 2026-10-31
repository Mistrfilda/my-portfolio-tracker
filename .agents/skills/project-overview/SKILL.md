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
- `src/JobRequest/` + `config/rabbitmq.neon` — deferred jobs and queue-based processing through `mistrfilda/amqp`; `src/RabbitMQ/` holds the application entity-to-message contract.
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

- **Stock assets / settings / watchlists**
	- Start in `src/Stock/Asset/` and read [stock-asset](../stock-asset/SKILL.md) for asset identity, exchange/currency settings, watchlists, and administration.

- **Price download / price history / price rendering**
	- Start in `src/Asset/` for shared contracts.
	- Then inspect the concrete module in `src/Stock/Price/`, `src/Portu/Price/`, or `src/Crypto/`.
	- Read [asset-price-system](../asset-price-system/SKILL.md) before changing shared price logic.
	- Read [asset-price-downloaders](../asset-price-downloaders/SKILL.md) for source selection, HTTP/JSON imports, or downloader commands.

- **Positions / closed positions / portfolio value**
	- Start with the contracts in `src/Asset/Position/`.
	- Continue in `src/Stock/Position/`, `src/Crypto/Position/`, or `src/Portu/Position/`.
	- Read [asset-position-system](../asset-position-system/SKILL.md) before changing position calculations or closing flows.

- **Dividends / dividend forecasts**
	- Start in `src/Stock/Dividend/` and read [stock-dividend](../stock-dividend/SKILL.md).

- **Stock fundamentals / valuations**
	- Start in `src/Stock/Valuation/` and read [stock-valuation-models](../stock-valuation-models/SKILL.md) for imported metrics, fair-value models, and peer comparisons.

- **Stock AI analysis / investment plans**
	- Start in `src/Stock/AiAnalysis/` and read [stock-ai-analysis](../stock-ai-analysis/SKILL.md) for snapshots, prompts, response contracts, imports, orchestration, and results.
	- Also use [stock-ai-analysis-gemini](../stock-ai-analysis-gemini/SKILL.md) when changing Gemini requests, schema adaptation, caches, or provider processing.

- **Currencies / exchange rates / LSE pence handling**
	- Start in `src/Currency/`.
	- Read [currency-conversion](../currency-conversion/SKILL.md) before changing conversion logic or data sources.

- **Presenters / controls / templates / admin screens**
	- Start in `src/UI/` and the owning module's `UI/` subtree.
	- Use [ui-base-presenters-templates](../ui-base-presenters-templates/SKILL.md) for presenter/control or typed-template changes.
	- Load [latte-templates](../latte-templates/SKILL.md), [nette-forms](../nette-forms/SKILL.md), [ui-forms-admin](../ui-forms-admin/SKILL.md), or [ui-datagrid](../ui-datagrid/SKILL.md) for the corresponding UI layer.

- **REST API**
	- Start in `src/Api/` or the module-specific `Api/` folder.
	- Read [api-slim](../api-slim/SKILL.md) before adding or changing endpoints.
	- For OAuth grants, scopes, client commands, or application integration, use [oauth2-server](../oauth2-server/SKILL.md).

- **Background jobs / async recalculation / queue consumers**
	- Start in `src/JobRequest/` for generic deferred work.
	- Shared queue abstractions and commands come from `mistrfilda/amqp` (`Mistrfilda\Amqp`); application wiring is in `config/rabbitmq.neon`.
	- Use [job-request](../job-request/SKILL.md) for job types and payloads; use [rabbitmq-base](../rabbitmq-base/SKILL.md) when changing the queue transport or a dedicated message flow.

- **Notifications**
	- Start in `src/Notification/`.
	- Read [notifications-discord](../notifications-discord/SKILL.md) before adding or routing a notification.

- **Schema / entity / repository changes**
	- Start in the owning module under `src/` and in `src/Doctrine/` if shared persistence code is involved.
	- Read [doctrine-migrations](../doctrine-migrations/SKILL.md) before editing entities or repositories.
	- For live database inspection through PhpStorm MCP, read [phpstorm-database](../phpstorm-database/SKILL.md); inspecting schema or data does not require generating or applying a migration.

- **Scraping / downloaded JSON inputs**
	- Start in `puppeter/` for the scraper and the related PHP downloader in `src/`.
	- Read [puppeteer-scraping](../puppeteer-scraping/SKILL.md) before changing the scraper pipeline.

### Practical workflow

Mirror the nearest implementation in the owning module. For shared PHP behavior, inspect the base in `src/Asset/` or `src/UI/` and the affected downstream modules. Use [testing-conventions](../testing-conventions/SKILL.md) when writing PHPUnit tests and the [AGENTS.md validation matrix](../../../AGENTS.md#validation-matrix) for final checks.
