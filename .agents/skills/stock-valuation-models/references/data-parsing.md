# Stock Valuation Data Parsing

Use this reference when changing valuation JSON imports, metrics, parsers, or normalization under `src/Stock/Valuation/Data/`.

## Download and persistence flow

1. `generateStockValuationJsonFile()` in `src/Stock/Price/Downloader/Json/JsonDataSourceProviderFacade.php` prepares requests for `keyStatistics.json`, `financials.json`, `analystInsights.json`, and industry data.
2. Scrapers in `puppeter/` write JSON under `%puppeter.folder%`. `KeyStatisticsScraper.js` stores the page section as `html` and `textContent`; its output is not a structured metric table. Related sources use `AnalystInsightsScraper.js`, `FinancialsScraper.js`, and `StockAssetIndustryScapper.js`.
3. `StockValuationDataParseCommand` invokes `StockValuationDataFacade`. Current persistence is primarily through `processKeyStatistics()` and `processAnalystInsights()`; a downloaded `financials.json` does not imply that this command persists it.
4. `StockValuationDataKeyAnalyticsParser` parses key-statistics HTML; `StockValuationDataAnalyticsParser` handles analyst insights JSON/text. The facade maps parsed values to `StockValuationTypeEnum` and stores `StockValuationData` rows. Parsed `basic_info` is distinct from persisted valuation metrics; check the facade before relying on a field being stored.

## Numeric and currency normalization

- Use `StockValuationDataNumericHelper::parseNumericValue()`. Preserve `null`, empty strings, `--`, and `N/A` as `null`; support the established `K`, `M`, `B`, and `T` suffixes.
- Percent signs are stripped without dividing by `100`; valuation percentages are stored in percentage points.
- For monetary key-statistics values, apply `CurrencyEnum::processFromWeb()` after numeric parsing. This preserves GBP/GBp normalization without converting ratios, counts, or percentages.
- Match metric labels precisely. `Revenue (ttm)` must not accidentally match `Enterprise Value/Revenue`; tolerate minor markup differences without broad substring matches that capture another metric.

## Adding or changing a metric

1. Update `StockValuationTypeEnum` with the correct group, `StockValuationTypeValueTypeEnum`, and monetary classification in `isCurrencyValue()`.
2. Update the relevant parser with realistic table/text cases and nullable missing values.
3. Update `StockValuationDataFacade` only where persistence, currency normalization, or type conversion changes. A new enum case or parser mapping alone does not change the Doctrine schema.
4. Use [doctrine-migrations](../../doctrine-migrations/SKILL.md) if mapped schema metadata changes.
5. Cover parser behavior under `tests/Unit/Stock/Valuation/Data/` and enum behavior in `tests/Unit/Stock/Valuation/StockValuationTypeEnumTest.php`. Use local fixtures or synthetic HTML/JSON, with the applicable [AGENTS.md validation](../../../../AGENTS.md#validation-matrix).
