---
name: stock-asset
description: Invoke before adding or modifying stock assets, stock asset administration, asset flags, ticker/exchange/currency handling, industry assignment, or stock asset repository queries. Covers `StockAsset`, `StockAssetFacade`, `StockAssetRepository`, and `src/Stock/Asset/UI/`.
---

## Stock Asset

Stock asset logic lives mainly in `src/Stock/Asset/`. It represents the stock-specific implementation of the shared `App\Asset\Asset` contract and connects price downloading, dividends, valuations, positions, industries, and watchlist behavior.

Use this skill before changing stock asset creation/editing, asset flags, ticker/exchange/currency behavior, stock asset repository queries, industry assignment, or stock asset admin UI.

### Main files

- `src/Stock/Asset/StockAsset.php` is the Doctrine entity and implements `App\Asset\Asset`.
- `src/Stock/Asset/StockAssetFacade.php` is the write boundary for creating/updating assets.
- `src/Stock/Asset/StockAssetRepository.php` contains read/query methods used by price updates, valuations, dividends, UI, and dashboards.
- `src/Stock/Asset/StockAssetExchange.php` defines supported exchanges.
- `src/Stock/Asset/Industry/` contains stock asset industry entities, repositories, and UI helpers.
- `src/Stock/Asset/UI/StockAssetFormFactory.php` builds the admin create/edit form.
- `src/Stock/Asset/UI/StockAssetGridFactory.php` renders the stock asset admin grid.
- Related downstream modules: `src/Stock/Position/`, `src/Stock/Price/`, `src/Stock/Dividend/`, `src/Stock/Valuation/`, and `src/Stock/AiAnalysis/`.

### Domain model

- `StockAsset` owns core identity and configuration:
	- `name`, `ticker`, `exchange`, `currency`, and optional `isin`.
	- `assetPriceDownloader` and `shouldDownloadPrice` for price update selection.
	- `stockAssetDividendSource`, `dividendTax`, and `brokerDividendCurrency` for dividend behavior.
	- `shouldDownloadValuation` for valuation data downloading/parsing.
	- `watchlist` and optional `industry` for UI and AI analysis selection.
- Current price is stored as `AssetPriceEmbeddable` and updated through `setCurrentPrice()` from a `StockAssetPriceRecord`.
- Positions, price records, dividends, and valuations are Doctrine collections owned by their respective child entities.

### Create/update flow

1. Admin UI builds form values in `StockAssetFormFactory`.
2. `StockAssetFacade::create()` checks ticker uniqueness through `StockAssetRepository::findByTicker()`.
3. The facade resolves optional `StockAssetIndustry` by ID and constructs `StockAsset` with current time from `DatetimeFactory`.
4. The facade persists/flushed through `EntityManagerInterface` and writes an audit log with `CurrentAppAdminGetter`.
5. `StockAssetFacade::update()` loads the existing entity, applies changes through `StockAsset::update()`, resolves industry again, flushes, and logs the update.

### Repository query rules

- Use existing repository methods when possible instead of duplicating DQL in callers.
- `findAllActiveAssets()` and `findAllActiveValuationAssets()` are used for enabled price/valuation flows.
- `findAllByAssetPriceDownloader()` supports downloader-specific price update selection and optional date/limit filtering.
- Dividend-related queries (`findByStockAssetDividendSource()`, `findDividendPayers()`, dividend counts) depend on `stockAssetDividendSource` being nullable when dividends are disabled.
- Dashboard/status counts depend on `shouldDownloadPrice`, `shouldDownloadValuation`, and `priceDownloadedAt`; do not change these semantics casually.

### Price and trend behavior

- `shouldBeUpdated()` returns the stock price update flag (`shouldDownloadPrice`) required by shared asset update code.
- `getAssetCurrentPrice()` returns an `AssetPrice` from the embedded current price and this asset.
- `getTrend()` compares the current price against the latest price record on or before a date.
	- It falls back to the previous price record if the selected historical record is the current downloaded day.
	- Missing history returns `0`, not an exception.

### Cross-module impact checklist

- If you add/remove/change a stock asset property, check:
	- Doctrine mapping and a migration (`doctrine-migrations` skill).
	- `StockAssetFacade` create/update signatures.
	- `StockAssetFormFactory` and `StockAssetGridFactory`.
	- Repository queries and dashboard counts.
	- Tests/factories that instantiate `StockAsset`.
- If you change price flags or currency behavior, inspect `src/Stock/Price/` and shared asset price infrastructure.
- If you change dividend settings, inspect `src/Stock/Dividend/` and the `stock-dividend` skill.
- If you change valuation settings or industry behavior, inspect `src/Stock/Valuation/` and the `stock-valuation-models` skill.
- If you change watchlist or stock identity fields, inspect `src/Stock/AiAnalysis/` and use `stock-ai-analysis`; add `stock-ai-analysis-gemini` only for provider-specific processing.

### UI rules

- For presenters/controls/templates, follow the typed template rules from `ui-base-presenters-templates`.
- When assigning new values to `$this->template`, add matching public typed properties to the template class.
- Do not add dynamic Latte template properties.
- Keep labels and enum option values consistent between the form and entity enum types.

### Testing rules

- Prefer unit tests for entity behavior, repository-independent rules, and facade validation.
- Use integration tests only when Doctrine queries or persistence behavior are the point of the change.
- Do not use external HTTP APIs or real RabbitMQ queues in stock asset tests.
- For code/config changes, finish with `composer cs-fix && composer build-all`.
- For this skill/documentation-only changes, consistency review is enough.
