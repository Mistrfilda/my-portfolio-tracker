# Junie Guidelines for My Portfolio Tracker

## Project Overview
Investment portfolio tracking application built on PHP (Nette framework) with Doctrine ORM.

## Technologies
- **Backend:** PHP 8.5, Nette Framework, Doctrine ORM
- **Frontend:** Webpack, Tailwind CSS, TypeScript
- **Scraping:** Puppeteer (Node.js) for Yahoo Finance, PSE
- **Message Queue:** RabbitMQ
- **Database:** MySQL/MariaDB
- **Notifications:** Discord webhooks

## Project Structure
- `assets` - typescript, javascript and css assets
  	- `svg` - svg icons folder
- `src/` - main PHP application code
	- `Asset/` - base interfaces and classes for all asset types (see detailed section below)
	- `Stock/` - stocks, dividends, valuations (see detailed section below)
	- `Portu/` - Portu robo-advisor investments (see detailed section below)
	- `Crypto/` - cryptocurrencies
	- `Currency/` - currency conversions (see detailed section below)
	- `JobRequest/` - async job processing via RabbitMQ (see detailed section below)
	- `RabbitMQ/` - base RabbitMQ abstractions (see detailed section below)
	- `UI/` - shared UI components, base classes, extensions (see detailed section below)
	- `Home/` - home devices and sensors
	- `Notification/` - notification system
	- `Doctrine/` - entities and repositories
- `puppeter/` - Node.js scripts for web scraping
- `config/` - Nette configuration (.neon files)
- `docker/` - Docker configuration
- `tests/` - PHPUnit tests
- `migrations/` - database migrations

### src/Asset — Base Asset Interfaces & Classes
Foundation for all asset types in the system. Three asset types exist: `Stock`, `Portu`, `Crypto` (defined in `AssetTypeEnum`).
- **Core interfaces:** `Asset` (base entity interface), `AssetRepository`, `AssetPosition`, `AssetClosedPosition`, `AssetPortfolio`
- **Price system:** `AssetPrice`, `AssetPriceEmbeddable` (Doctrine embeddable for price+currency), `AssetPriceRecord` (interface), `AssetPriceDownloader` (interface for price downloaders), `AssetPriceFacade` (interface), `AssetPriceSourceProvider`, `AssetPriceFactory`, `AssetPriceRenderer`
- **Price helpers:** `SummaryPrice` (aggregated price across currencies), `SummaryPriceService`, `AssetPriceSummaryFacade`, `PriceDiff` (price change calculation)
- **Other:** `AssetPriceEnum` (price display modes), `JsonDataFolderService` (for JSON-based price data files), `AssetTrendFacade`/`AssetTrendCommand`

### src/Stock — Stock Module
Largest module, handles stocks, dividends, positions, valuations, and AI analysis.
- **Asset/** — `StockAsset` entity (implements `Asset`), `StockAssetRepository`, `StockAssetDetail` (DTO), `StockExchangeEnum` (NYSE, NASDAQ, XETRA, LSE, WSE, PSE, OSE), `StockAssetIndustry`, `StockAssetMetadata`, API endpoint (`StockAssetApiController`)
- **Dividend/** — `StockAssetDividend` entity, `StockAssetDividendRecord`, downloading from multiple sources, `StockAssetDividendFacade`, `DividendRadarDownloadFacade`, notifications. Sub-module `Forecast/` for dividend yield forecasting with `StockAssetDividendForecastRecord`
- **Position/** — `StockPosition` (open positions, implements `AssetPosition`), `StockClosedPosition`, `StockPositionFacade`, portfolio grouping (`StockPositionPortfolio`)
- **Price/** — Multiple downloader implementations: `StockAssetPriceJsonDownloader`, `StockAssetPricePuppeteerDownloader`, `StockAssetPricePseApiDownloader`, `StockAssetPriceTwelveDataApiDownloader`, `StockAssetPriceWebDownloader`. `StockAssetPriceRecord` entity, `StockAssetPriceFacade`
- **Valuation/** — 12+ valuation models: `GrahamValuation`, `DCFValuation`, `PERatioValuation`, `PEGRatioValuation`, `DividendDiscountValuation`, `EarningsYieldValuation`, `BookValueValuation`, `TargetPriceValuation`, `BenjaminGrahamIntrinsicValuation`, `PeterLynchValuation`, `HistoricalPERatioValuation`, `FairPriceValuation`. Valuation data parsed from JSON files (`ValuationDataParser`)
- **AiAnalysis/** — AI-powered stock analysis with prompt templates (system, output_format, portfolio, watchlist), `StockAiAnalysisPromptGenerator`, `StockAiAnalysisFacade`, dedicated presenter

### src/Portu — Portu Robo-Advisor Module
Tracks Portu robo-advisor investments. Simpler structure than Stock.
- **Asset/** — `PortuAsset` entity (implements `Asset`), `PortuAssetFacade`, `PortuAssetRepository`, UI (presenter, form, grid)
- **Position/** — `PortuPosition` entity, `PortuPositionFacade`, `PortuPositionRepository`, UI with price management
- **Price/** — `PortuAssetPriceRecord`, `PortuAssetPriceRecordRepository`

### src/Currency — Currency Conversions
Handles exchange rate downloading and currency conversions.
- **CurrencyEnum:** USD, EUR, CZK, GBP, PLN, NOK
- **CurrencyConversion** entity + repository — stores historical exchange rates
- **CurrencySourceEnum** — sources for exchange rates
- **Download/** — `CurrencyConversionDownloadFacade` (interface), implementations: `CNBCurrencyConversionDownloadFacade` (Czech National Bank), `ECBCurrencyConversionDownloadFacade` (European Central Bank), `CurrencyConversionDownloadInverseRateHelper`, CLI command `CurrencyConversionDownloadCommand`
- **GBPCurrencyHelper** — converts GBp (pence) to GBP
- **UI/** — `CurrencyOverviewPresenter`

### src/JobRequest — Async Job Processing
Deferred job execution via RabbitMQ for long-running tasks.
- **JobRequestTypeEnum:** `expense_tag_process`, `stock_asset_dividend_forecast_recalculate`, `stock_asset_dividend_forecast_recalculate_all`, `portfolio_goal_update`
- **JobRequestProcessor** — dispatches jobs to appropriate facades (ExpenseTagFacade, StockAssetDividendForecastRecordFacade, PortfolioGoalUpdateFacade)
- **JobRequestFacade** — creates job requests
- **RabbitMQ/** — `JobRequestConsumer`, `JobRequestProducer`, `JobRequestMessage`


### src/UI — Shared UI Components & Framework
All shared UI infrastructure for the Nette application.
- **Base/** — `BasePresenter` (abstract), `BaseAdminPresenter`, `BaseSysadminPresenter`, `BaseFrontPresenter`, `BaseControl` (abstract). Template classes: `BasePresenterTemplate`, `BaseAdminPresenterTemplate`, `BaseControlTemplate`. `BasePresenterParameters` for shared config.
- **Control/Datagrid/** — Custom datagrid system: `Datagrid`, `DatagridFactory`, `DoctrineDataSource` (implements `IDataSource`). Columns: `ColumnText`, `ColumnDatetime`, `ColumnBadge`, `ColumnBadgeArray` (all implement `IColumn`). Actions: `DatagridAction`. Filters: `FilterForm`, `FilterText`, `FilterValue`. Pagination: `Pagination`, `PaginationService`. Sort: `Sort`, `SortService`, `SortDirectionEnum`.
- **Control/Form/** — `AdminForm`, `AdminFormFactory`, `AdminFormRenderer`. Inputs: `DatePickerInput`, `CustomFileUpload`, `Multiplier`, `BirthdayContainerFactory`, `TimeContainerFactory`. DTO: `Birthday`.
- **Control/Chart/** — `ChartControl`, `ChartData`, `ChartDataSet`, `ChartDataProvider` (interface), `ChartType` enum
- **Control/Modal/** — `FrontModalControl`, `FrontModalControlFactory`
- **Control/Search/** — `SearchControl`, `SearchGroup`, `SearchGroupItem`
- **Filter/** — Latte template filters: `CurrencyFilter`, `CurrencyConversionFilter`, `AssetPriceFilter`, `PercentageFilter`, `DateFormatFilter`, `DatetimeFormatFilter`, `BooleanFilter`, `CompactCurrencyFilter`, `SummaryPriceFilter`, `SummaryPriceConvertFilter`, `RuleOfThreeFilter`, `SecondsFormatFilter`, etc.
- **Extension/** — Nette DI extensions: `LatteMacrosExtension`, `SvgLatteExtension` (renderSvg macro), `WebpackAssetExtension`/`WebpackLatteExtension` (encore_css/encore_js macros), `CustomPresenterFactory`/`CustomPresenterFactoryExtension`
- **Icon/** — `SvgIcon` enum — all SVG icon references
- **Menu/** — `MenuBuilder`, `MenuGroup`, `MenuItem` — admin menu construction
- **FlashMessage/** — `FlashMessage`, `FlashMessageType`
- **Tailwind/** — `TailwindColorConstant` — shared color constants

## Build & Configuration
1. **Prerequisites**: PHP 8.5+, Node.js (v24 recommended) with npm, Docker, RabbitMQ, MariaDB/MySQL.
2. **Environment**:
	- Configuration is in `config/*.neon`.
	- Local overrides go into `config/config.local.neon` or `config/config-docker.local.neon`.
3. **Setup**:
	- Run `docker-compose up -d` to start infrastructure (Database, RabbitMQ).
	- Run `composer install` to install PHP dependencies.
	- Run `npm install` to install Node.js dependencies.
	- Apply migrations: `bin/console migrations:migrate`.
	- Declare RabbitMQ queues: `bin/console-rabbit rabbitmq:declareQueuesAndExchanges`.
4. **Assets**:
	- Development: `npm run watch-dev`
	- Production: `npm run build-prod`

## Coding Standards
- **Indentation**: Use **TABS** whenever possible (except in OpenAPI spec files where spaces are allowed).
- **PHP Style**: Follow PSR-12.
- **Modern PHP**: Use Constructor property promotion where applicable.
- **Naming**: Tests must be named `*Test.php`.
- **JSON**: Always use `Nette\Utils\Json` for serialization/deserialization.
- **Validation**: Use `App\Utils\TypeValidator` for scalar type validation.
- **Nette controls and presenter** - When using nette controls and presenter, always use Template classes for parameters, base template classes are `src/UI/Base/BaseControlTemplate.php`  (for controls), `src/UI/Base/BasePresenterTemplate.php` (for presenters).
  - Always add public properties to the Template class when assigning variables to `$this->template` in Presenters or Controls. Dynamic properties are deprecated.
- **Exceptions**: 
  - always use English language in exception messages.
- **Comments**: Use only english language in comments.


## Testing
- Run: `vendor/bin/phpunit` (all), `vendor/bin/phpunit tests/Unit`, `vendor/bin/phpunit tests/Integration`.
- Prefer unit tests; never use real RabbitMQ queues in tests.
- For layout, base classes, naming and an example see the `testing-conventions` skill.

## SVG icons
- Never use `<svg>` directly; render via `{renderSvg}` macro and the `App\UI\Icon\SvgIcon` enum.
- For full rules (workflow, file naming, attributes) see the `ui-svg-icons` skill.

## Important Notes
- **Junie and reading files** - Always use junie functions for reading files, avoid using cat to read files.
- **Security**: Sensitive data (API keys, passwords) are in `docker/config-docker.local.neon` or `config/config.local.neon`. **DO NOT COMMIT THESE FILES.**
- **Development Flow**: At the end of process, always run `composer cs-fix && composer build-all` to check if everything is OK.
- **RabbitMQ**: Used for asynchronous task processing (e.g., price updates, notifications).
- **Scraping**: Puppeteer scripts in `puppeter/` for Yahoo Finance, PSE.

## Nette presenters + controls
- Register presenters in `config/routing.neon` and `MenuBuilder.php` when needed.
- For presenter/module/architecture design see the `nette-architecture` skill.
- For Latte templates see the `latte-templates` skill; for forms see `nette-forms`.

## Agent Skills
Domain-specific guidance lives in `.junie/skills/`. Always read the relevant `SKILL.md` before starting a related task.

Framework / generic:
- `nette-architecture` — presenters, modules, directory structure.
- `nette-configuration` — Nette DI, services, autowiring.
- `nette-forms` — Nette Forms (controls, validation, rendering).
- `nette-utils` — Nette Utils helpers (Arrays, Strings, Json, Finder, …).
- `latte-templates` — Latte syntax, tags, filters, n:attributes.
- `neon-format` — NEON syntax for `.neon` config files.
- `tracy-debugging` — Tracy output interpretation when fetching local URLs or debugging PHP errors.
- `rabbitmq-base` — base consumer/producer/message abstractions in `src/RabbitMQ/`.
- `doctrine-migrations` — required workflow for Doctrine entity/schema changes and migrations.

Project domain:
- `asset-price-system` — base asset contracts in `src/Asset/` (price embeddable, summary price, price diff, renderer).
- `asset-price-downloaders` — Json / TwelveData / PSE / Web / Puppeteer downloaders and their `config.neon` parameters.
- `stock-valuation-models` — 12+ valuation models under `src/Stock/Valuation/Model/Price/` and the JSON data pipeline.
- `currency-conversion` — `CurrencyEnum`, CNB/ECB downloaders, `CurrencyConversionFacade`, GBp→GBP handling.
- `job-request` — generic async jobs via `JobRequestFacade` / `JobRequestProcessor`.
- `notifications-discord` — `NotificationFacade`, Discord channel & webhook mapping.
- `puppeteer-scraping` — Node.js scrapers in `puppeter/` and their JSON handoff to PHP.
- `api-slim` — REST API layer (`src/Api/`), OpenAPI spec, `ApiKeyMiddleware`, CORS.

Project UI:
- `ui-base-presenters-templates` — base presenters/controls + mandatory Template classes.
- `ui-datagrid` — custom datagrid in `src/UI/Control/Datagrid/`.
- `ui-forms-admin` — `AdminFormFactory` and custom inputs/containers in `src/UI/Control/Form/`.
- `ui-latte-filters` — catalog of project Latte filters (currency, price, percentage, dates, …).
- `ui-svg-icons` — `renderSvg` macro + `App\UI\Icon\SvgIcon` workflow.

Project testing:
- `testing-conventions` — PHPUnit layout, base classes, naming, unit-first rule.

## Common Tasks
- Stock price updates: Finance scraper
- Dividends: automatic downloading and notifications
- Exchange rates: CNB and ECB
