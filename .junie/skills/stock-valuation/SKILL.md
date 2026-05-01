---
name: stock-valuation-models
description: Invoke before adding or modifying stock valuation data parsing, valuation models, valuation UI/sorting, or industry peer comparison. Covers  valuation JSON download/parsing, `StockValuationData`, `StockValuationTypeEnum`, `StockValuationFacade`, and models under `src/Stock/Valuation/Model/Price/`.
---

## Stock Valuation

Stock valuation lives mainly in `src/Stock/Valuation/`. It has two connected parts:

- **Valuation data** downloaded from Finance and parsed into `StockValuationData` entities.
- **Valuation models** that calculate fair/intrinsic values from `StockValuationData` + `StockAsset`.

Use this skill for valuation parsing, `StockValuationTypeEnum`, valuation model changes, valuation UI/sorting, and industry comparison.

### Main files

- `src/Stock/Price/Downloader/Json/JsonDataSourceProviderFacade.php` creates valuation JSON download requests.
- `puppeter/KeyStatisticsScraper.js`, `puppeter/AnalystInsightsScraper.js`, `puppeter/FinancialsScraper.js`, and `puppeter/StockAssetIndustryScapper.js` scrape pages into JSON files under `%puppeter.folder%`.
- `src/Stock/Valuation/Data/StockValuationDataParseCommand.php` is the CLI entry point for parsing downloaded valuation JSON.
- `src/Stock/Valuation/Data/StockValuationDataFacade.php` coordinates parsing and persistence.
- `src/Stock/Valuation/Data/StockValuationDataKeyAnalyticsParser.php` parses  `key-statistics` HTML.
- `src/Stock/Valuation/Data/StockValuationDataAnalyticsParser.php` parses analyst insights JSON/text.
- `src/Stock/Valuation/Data/StockValuationDataNumericHelper.php` normalizes numeric strings.
- `src/Stock/Valuation/Data/StockValuationData.php` stores parsed values.
- `src/Stock/Valuation/StockValuationTypeEnum.php` defines valuation fields, groups, value types, and currency classification.
- `src/Stock/Valuation/StockValuationFacade.php` runs registered valuation models.
- `src/Stock/Valuation/UI/` contains valuation detail/table controls and sorting.

### Data download and parsing flow

1. `JsonDataSourceProviderFacade::generateStockValuationJsonFile()` prepares valuation-related downloads, including `keyStatistics.json`, `financials.json`, `analystInsights.json`, and industry data.
2. Puppeteer scrapers write JSON files into `%puppeter.folder%`.
	- `KeyStatisticsScraper.js` stores the page section as `html` and `textContent`; PHP parsing is done later from that stored HTML/text.
	- Do not assume key-statistics JSON is already structured table data.
3. `StockValuationDataParseCommand` parses downloaded files through `StockValuationDataFacade`.
	- Current valuation persistence is primarily from `processKeyStatistics()` and `processAnalystInsights()`.
	- `financials.json` may be downloaded even if it is not currently persisted by this command; verify before relying on it.
4. `StockValuationDataFacade` maps parsed values to `StockValuationTypeEnum` keys and saves `StockValuationData` records.
	- `basic_info` can be parsed from  but is not the same as persisted valuation metrics; check the facade before assuming a parsed field is stored.
5. `StockValuationFacade` loads parsed data and runs all services typed as `App\Stock\Valuation\Model\StockValuationModel`.
6. UI controls render per-stock valuation details, comparison tables, sorting, and industry peer comparison.

### Valuation field rules

- `StockValuationTypeEnum` is the source of truth for metric keys, grouping, labels, value type, and currency/non-currency behavior.
- `getTypeValueType()` controls whether a value is text, float, or percentage.
- Percentages are stored as percentage points: `5.0` means `5%`, not `0.05`.
- `isCurrencyValue()` affects UI formatting, sorting, and currency conversion. Only real money-like values belong there.
	- Currency values include market cap, enterprise value, revenue, cash/debt amounts, per-share prices, dividends per share, and analyst price targets.
	- Ratios, counts, volumes, beta, margins, yields, and debt/equity percentages are not currency values.
- Be careful with  labels that are substrings of other labels. Prefer exact/specific labels such as `Revenue (ttm)` over broad labels like `Revenue`, which can accidentally match `Enterprise Value/Revenue`.

### Numeric and currency normalization

- Use `StockValuationDataNumericHelper::parseNumericValue()` for numeric strings.
	- It should preserve missing values (`null`, empty string, `--`, `N/A`) as `null`, not `0.0`.
	- It handles suffixes such as `K`, `M`, `B`, and `T`.
	- It strips percent signs but does not divide by `100`.
- For key-statistics values that are real currency amounts, normalize with `CurrencyEnum::processFromWeb()` after numeric parsing, so special cases such as GBP/GBp stay consistent with the rest of the application.
- Use `Nette\Utils\Json` for JSON serialization/deserialization.
- Use `App\Utils\TypeValidator` when scalar validation is needed.

### Existing price models (`src/Stock/Valuation/Model/Price/`)

All extend `BasePriceModel` and return `StockValuationPriceModelResponse`:

- `BookValueValuationModel`
- `DividendDiscountValuationModel`
- `PriceEarningsValuationModel`
- `FreeCashFlowValuationModel`
- `EnterpriseValueValuationModel`
- `GrahamNumberValuationModel`
- `PegRatioValuationModel`
- `PriceToSalesValuationModel`
- `DebtAdjustedValuationModel`
- `DividendPayoutSafetyModel`
- `DividendYieldFairValueModel`
- `RoeQualityValuationModel` (quality/score model, not price)

### Valuation model flow

1. `StockValuationFacade` receives a `StockAsset` and its parsed `StockValuationData`.
2. It runs registered services typed as `StockValuationModel`.
3. Price models extend `BasePriceModel` and return `StockValuationPriceModelResponse`.
4. UI renders model output in valuation detail and comparison grids.
5. `StockIndustryComparisonFacade` aggregates peer comparison inside an industry.

### Adding a new valuation model

1. Create class under `src/Stock/Valuation/Model/Price/` extending `BasePriceModel`.
2. Implement the calculation returning `StockValuationPriceModelResponse` (fair price + metadata).
3. Register in `config/config.neon` under `services:` in the `#Stock valuation models` section. Autowiring via `typed(App\Stock\Valuation\Model\StockValuationModel)` picks it up automatically.
4. If the model needs extra input fields, extend `StockValuationData` / `ValuationDataParser` and generate a Doctrine migration (see `doctrine-migrations` skill).
5. Add a unit test for the calculation with fixed inputs (prefer unit over integration — see `testing-conventions`).

### Adding or changing a parsed  metric

1. Add or update the metric in `StockValuationTypeEnum`.
	- Set the correct group and `StockValuationTypeValueTypeEnum`.
	- Update `isCurrencyValue()` only if the value is truly monetary.
2. Update `StockValuationDataKeyAnalyticsParser` or `StockValuationDataAnalyticsParser`.
	- Prefer exact labels and include realistic table/text cases.
	- Keep missing values as `null`.
3. Update `StockValuationDataFacade` only if persistence, currency normalization, or type conversion changes.
4. If the entity/schema changes, generate a Doctrine migration.
5. Add or update unit tests under `tests/Unit/Stock/Valuation/`.
	- Parser tests belong under `tests/Unit/Stock/Valuation/Data/`.
	- Enum behavior belongs in `tests/Unit/Stock/Valuation/StockValuationTypeEnumTest.php`.
	- Use local fixtures/synthetic HTML; never call or external HTTP APIs in tests.

### UI and sorting

- Valuation detail rendering is handled by `StockValuationDetailControlFactory` and related templates under `src/Stock/Valuation/UI/`.
- Comparison tables use `StockValuationModelTableControlFactory`.
- Sorting is handled by `StockValuationSortService` and `StockValuationModelSortService`.
- Before changing `isCurrencyValue()`, check UI and sorting impact: currency values may be converted to CZK for display/sort, while ratios and percentages must not be converted.

### Rules

- Models must be **pure** — take `StockValuationData` + `StockAsset` and return a response; no DB writes, no HTTP.
- Currency: use `AssetPriceEmbeddable` / `CurrencyEnum`; do not mix currencies silently.
- Exceptions in English; throw only when input data is truly missing, otherwise return a response with a "not applicable" flag.
- Keep parsing tolerant of missing values and minor markup differences, but do not add broad substring matches that can capture the wrong metric.
- Tests must be unit tests unless integration is explicitly needed; do not use real RabbitMQ queues or external HTTP APIs.
- For code/config changes, finish with `composer cs-fix && composer build-all`. For this skill/documentation-only changes, consistency review is enough.
