---
name: stock-dividend
description: Invoke before adding or modifying stock dividends, dividend records, dividend downloaders, dividend source settings, tax/currency handling, dividend dashboards, or dividend UI. Covers `src/Stock/Dividend/` and its connection to `StockAsset`.
---

## Stock Dividend

Stock dividend logic lives in `src/Stock/Dividend/`. It stores dividend events for `StockAsset`, supports manual create/update flows, web-downloaded dividend records, dividend source settings, tax handling, and dashboard/UI queries.

Use this skill before changing dividend entities, dividend download/parsing, dividend repository queries, dividend admin UI, tax/currency behavior, or `StockAsset` dividend settings.

### Main files

- `src/Stock/Dividend/StockAssetDividend.php` is the Doctrine entity for one dividend event.
- `src/Stock/Dividend/StockAssetDividendFacade.php` is the write boundary for manual create/update and dashboard access helpers.
- `src/Stock/Dividend/StockAssetDividendRepository.php` contains dividend queries by stock asset, date, year, ex-date, and type.
- `src/Stock/Dividend/Record/StockAssetDividendRecord.php` stores per-position dividend records linked to a dividend event.
- `src/Stock/Dividend/StockAssetDividendTypeEnum.php` distinguishes dividend types.
- `src/Stock/Dividend/StockAssetDividendSourceEnum.php` defines dividend download/source configuration stored on `StockAsset`.
- `src/Stock/Dividend/Downloader/` contains dividend downloader implementations and related contracts.
- `src/Stock/Dividend/UI/` contains admin/grid/form rendering for dividends.
- Related owner entity: `src/Stock/Asset/StockAsset.php`.

### Domain model

- `StockAssetDividend` belongs to exactly one `StockAsset`.
- Important dates:
	- `exDate` identifies the dividend event for duplicate detection.
	- `paymentDate` can be nullable on the entity because downloaded data may not always have it immediately.
	- `declarationDate` is optional.
- Amount is stored with `CurrencyEnum`; do not assume the asset trading currency is always the dividend currency.
- Tax configuration is read from the owning stock asset through `getDividendTax()`.
- `getSummaryPrice(bool $shouldDeductTax)` returns a `SummaryPrice` and optionally deducts tax.

### Manual create/update flow

1. UI collects stock asset, dates, currency, amount, and dividend type.
2. `StockAssetDividendFacade::create()` loads `StockAsset` by ID and creates a `StockAssetDividend` with current time from `DatetimeFactory`.
3. `StockAssetDividendFacade::update()` loads the existing dividend and calls `StockAssetDividend::update()`.
4. The facade persists/flushed through `EntityManagerInterface`.

### Download flow

1. Dividend-enabled assets are selected through `StockAssetRepository` using `StockAssetDividendSourceEnum`.
2. Downloader implementations in `src/Stock/Dividend/Downloader/` fetch and parse source data.
3. Existing dividend events are matched mainly through stock asset + `exDate`.
4. New or changed events are persisted as `StockAssetDividend` records.
5. Logs should be informative but must not hide parsing or HTTP errors that should fail the job.

### Repository query rules

- Use `findByStockAsset()` for all dividends of one asset ordered by the repository's existing semantics.
- Use `findByStockAssetSinceDate()` and `findByStockAssetForYear()` for date-limited portfolio/dashboard calculations.
- Use `findOneByStockAssetExDate()` for duplicate detection during imports.
- Use `findLastDividends()` / `getLastDividend()` for dashboard and recent-dividend UI.
- Keep dividend type filtering explicit; do not silently mix regular, special, or future types unless the caller asks for all types.

### Currency and tax rules

- Use `CurrencyEnum` and existing currency conversion services when a value needs to be displayed or aggregated in another currency.
- Do not hardcode dividend currency from the stock exchange; use the dividend record currency or `StockAsset::getBrokerDividendCurrency()` where the domain flow expects broker currency.
- Preserve percentage/tax semantics: `dividendTax` is nullable on `StockAsset`; nullable means no configured tax deduction.
- Be explicit whether a summary is gross or net. `getSummaryPrice(true)` deducts tax, `getSummaryPrice(false)` does not.

### Cross-module impact checklist

- If you change dividend source settings, update/check `StockAsset`, `StockAssetFacade`, `StockAssetFormFactory`, and repository queries.
- If you change dividend amount/currency/tax semantics, check dashboard summaries and currency conversion code.
- If you change downloader parsing, add local fixture/synthetic response tests; never call the real dividend source in tests.
- If entity mapping changes, generate a Doctrine migration (`doctrine-migrations` skill).
- If UI/templates change, follow `ui-base-presenters-templates`, `latte-templates`, and `ui-datagrid` as relevant.

### Testing rules

- Prefer unit tests for tax calculations, entity methods, downloader parsing, and facade branching.
- Use repository/integration tests only for Doctrine query behavior.
- Mock HTTP clients/download responses; never use external HTTP APIs in tests.
- Never use real RabbitMQ queues in dividend tests.
- For code/config changes, finish with `composer cs-fix && composer build-all`.
- For this skill/documentation-only changes, consistency review is enough.
