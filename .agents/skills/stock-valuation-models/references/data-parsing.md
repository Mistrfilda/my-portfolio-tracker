# Stock Valuation Data Parsing

Use this reference when changing valuation JSON imports, metrics, parsers, or normalization under `src/Stock/Valuation/Data/`.

## Download and persistence flow

1. `generateStockValuationJsonFile()` in `src/Stock/Price/Downloader/Json/JsonDataSourceProviderFacade.php` prepares requests for `keyStatistics.json`, `financials.json`, `analystInsights.json`, and industry data.
2. Scrapers in `puppeter/` write JSON under `%puppeter.folder%`. `KeyStatisticsScraper.js` stores the page section as `html` and `textContent`; its output is not a structured metric table. `puppeter/financials.js` currently runs only key statistics; the `FinancialsScraper` import and invocation are commented out. A generated request for `financials.json` does not mean it is downloaded.
3. `StockValuationDataParseCommand` invokes `StockValuationDataFacade`. Persistence uses `processKeyStatistics()` and `processAnalystInsights()`.
4. `StockValuationDataKeyAnalyticsParser` parses key-statistics HTML; `StockValuationDataAnalyticsParser` handles analyst insights JSON/text. The facade maps parsed values to `StockValuationTypeEnum` and stores `StockValuationData` rows. Parsed `basic_info` is distinct from persisted valuation metrics; check the facade before relying on a field being stored.

## Numeric and currency normalization

- Yahoo imports use `StockValuationDataNumericHelper::parseYahooValue()` with explicit US decimal notation and `K`, `M`, `B`, `T` suffixes. In particular, `1.234` means 1.234, not 1234. The generic `parseNumericValue()` retains its legacy mixed-format behavior for other callers.
- Percent signs are stripped without dividing by `100`; valuation percentages are stored in percentage points.
- Do not divide financial statement amounts by 100 merely because the listing uses GBP. Apply GBP/GBp normalization only to quotes known to be in pence. Legacy key-statistics rows carry the configured asset currency, not independently verified reporting currency; disclose this limitation. The evaluator rejects monetary inputs stored in another currency.
- Import time is not the date of the underlying financial statement or PEG calculation. `MOST_RECENT_QUARTER` stores Yahoo's reported period when available. Previous snapshots compare changes, including missing values, and are not an annual financial series.
- Match metric labels precisely. `Revenue (ttm)` must not accidentally match `Enterprise Value/Revenue`; tolerate minor markup differences without broad substring matches that capture another metric.

## Adding or changing a metric

1. Update `StockValuationTypeEnum` with the correct group, `StockValuationTypeValueTypeEnum`, and monetary classification in `isCurrencyValue()`.
2. Update the relevant parser with realistic table/text cases and nullable missing values.
3. Update `StockValuationDataFacade` only where persistence, currency normalization, or type conversion changes. A new enum case or parser mapping alone does not change the Doctrine schema.
4. Use [doctrine-migrations](../../doctrine-migrations/SKILL.md) if mapped schema metadata changes.
5. Cover parser behavior under `tests/Unit/Stock/Valuation/Data/` and enum behavior in `tests/Unit/Stock/Valuation/StockValuationTypeEnumTest.php`. Use local fixtures or synthetic HTML/JSON, with the applicable [AGENTS.md validation](../../../../AGENTS.md#validation-matrix).
