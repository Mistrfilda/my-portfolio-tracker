---
name: stock-valuation-models
description: Maintain valuation data parsing, fair-value models, and peer comparison. Use when changing stock valuation metrics, calculations, display, or sorting.
---

## Stock Valuation

Stock valuation lives mainly in `src/Stock/Valuation/`. It has two connected parts:

- **Valuation data** downloaded from Finance and parsed into `StockValuationData` entities.
- **Valuation models** that calculate fair/intrinsic values from `StockValuationData` + `StockAsset`.

Read [references/data-parsing.md](references/data-parsing.md) when changing imported metrics, source JSON, HTML parsing, or numeric normalization. Use [puppeteer-scraping](../puppeteer-scraping/SKILL.md) only when changing the scraper side of that flow.

### Boundaries

- `Data/StockValuationDataFacade` coordinates parsing and persistence into `StockValuationData`.
- `StockValuationTypeEnum` defines the shared metric contract consumed by parsers, models, and UI.
- `StockValuationFacade` loads parsed data and runs services typed as `App\Stock\Valuation\Model\StockValuationModel`.
- `Model/Price/` holds price models extending `BasePriceModel` and returning `StockValuationPriceModelResponse`.
- `StockIndustryComparisonFacade` aggregates industry peers; `UI/` owns detail/table rendering and sorting.

### Valuation field rules

- `StockValuationTypeEnum` is the source of truth for metric keys, grouping, labels, value type, and currency/non-currency behavior.
- `getTypeValueType()` controls whether a value is text, float, or percentage.
- Percentages are stored as percentage points: `5.0` means `5%`, not `0.05`.
- `isCurrencyValue()` affects UI formatting, sorting, and currency conversion. Only real money-like values belong there.
	- Currency values include market cap, enterprise value, revenue, cash/debt amounts, per-share prices, dividends per share, and analyst price targets.
	- Ratios, counts, volumes, beta, margins, yields, and debt/equity percentages are not currency values.
- Preserve missing metric values as `null`, not `0.0`.

### Adding a new valuation model

1. Create class under `src/Stock/Valuation/Model/Price/` extending `BasePriceModel`.
2. Implement the calculation returning `StockValuationPriceModelResponse` (fair price + metadata).
3. Register in `config/config.neon` under `services:` in the `#Stock valuation models` section. Autowiring via `typed(App\Stock\Valuation\Model\StockValuationModel)` picks it up automatically.
4. If the model needs another metric, follow [the parsed-metric workflow](references/data-parsing.md#adding-or-changing-a-metric). Existing metrics are stored as typed rows in `StockValuationData`; an enum case/parser mapping alone does not require a schema migration. Use [doctrine-migrations](../doctrine-migrations/SKILL.md) when the Doctrine mapping changes.
5. Add a unit test for the calculation with fixed inputs; follow [testing-conventions](../testing-conventions/SKILL.md).

### UI and sorting

- Valuation detail rendering is handled by `StockValuationDetailControlFactory` and related templates under `src/Stock/Valuation/UI/`.
- Comparison tables use `StockValuationModelTableControlFactory`.
- Sorting is handled by `StockValuationSortService` and `StockValuationModelSortService`.
- Before changing `isCurrencyValue()`, check UI and sorting impact: currency values may be converted to CZK for display/sort, while ratios and percentages must not be converted.

### Rules

- Models must be **pure** — take `StockValuationData` + `StockAsset` and return a response; no DB writes, no HTTP.
- Currency: use `AssetPriceEmbeddable` / `CurrencyEnum`; do not mix currencies silently.
- Preserve the distinction between missing required data and a model returning a "not applicable" response; `RoeQualityValuationModel` returns a quality score rather than a fair price.
- Follow the applicable [AGENTS.md validation](../../../AGENTS.md#validation-matrix).
