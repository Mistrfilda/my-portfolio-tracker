---
name: stock-valuation-models
description: Invoke before adding or modifying a stock valuation model (price-based or quality). Provides the catalog of models under `src/Stock/Valuation/Model/Price/` and how they are wired with `StockValuationFacade` + `StockValuationData`. Use when the user asks to add a new valuation method, extend `BasePriceModel`, parse valuation JSON data, or compare valuations across industry peers.
---

## Stock Valuation Models

Stock intrinsic/fair-value models. Lives in `src/Stock/Valuation/`.

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

### Data pipeline

1. Puppeteer scrapers (`FinancialsScraper`, `KeyStatisticsScraper`, `AnalystInsightsScraper`, `StockAssetIndustryScapper`) write JSON files into `%puppeter.folder%`.
2. `StockValuationDataParseCommand` + `StockValuationDataFacade` parse those JSON files into `StockValuationData` entities.
3. `StockValuationFacade` runs registered models via `typed(StockValuationModel)` against the parsed data.
4. UI: `StockValuationDetailControlFactory` renders per-stock details; `StockValuationModelTableControlFactory` shows the comparison grid; `StockValuationSortService` / `StockValuationModelSortService` handle sorting.
5. `StockIndustryComparisonFacade` aggregates peer comparison inside an industry.

### Adding a new valuation model

1. Create class under `src/Stock/Valuation/Model/Price/` extending `BasePriceModel`.
2. Implement the calculation returning `StockValuationPriceModelResponse` (fair price + metadata).
3. Register in `config/config.neon` under `services:` in the `#Stock valuation models` section. Autowiring via `typed(App\Stock\Valuation\Model\StockValuationModel)` picks it up automatically.
4. If the model needs extra input fields, extend `StockValuationData` / `ValuationDataParser` and generate a Doctrine migration (see `doctrine-migrations` skill).
5. Add a unit test for the calculation with fixed inputs (prefer unit over integration — see `testing-conventions`).

### Rules

- Models must be **pure** — take `StockValuationData` + `StockAsset` and return a response; no DB writes, no HTTP.
- Currency: use `AssetPriceEmbeddable` / `CurrencyEnum`; do not mix currencies silently.
- Exceptions in English; throw only when input data is truly missing, otherwise return a response with a "not applicable" flag.
- JSON parsing via `Nette\Utils\Json`; validate scalars with `App\Utils\TypeValidator`.
