# Stock AI Investment Plans

Use this reference for changes under `src/Stock/AiAnalysis/InvestmentPlan/`. An investment plan is its own aggregate and uses `schemaVersion: 1`, independently of historical V1 and current V2 analysis runs. Do not process it through the analysis-run V2 schema or `StockAiAnalysisFacade::processResponse()`.

## Creation and snapshot

- `StockAiInvestmentPlanFacade::create()` requires a completed V2 comprehensive portfolio evaluation with persisted structured data.
- Convert the requested capital to CZK through `CurrencyConversionFacade` at creation time. Capture the current stock portfolio value, original amount/currency, CZK budget, investor profile, user context, portfolio/watchlist identities, and copied reference-analysis results in `StockAiInvestmentPlanSnapshotFactory`.
- Keep the stored `planId`, `analysisAsOf`, capital, and company identities immutable. Generate prompts and the response schema from that stored snapshot, not from live positions during import.
- Preserve percentage points, major currency units, English JSON keys/controlled values, and Czech narrative text.

## Response boundaries

- `StockAiInvestmentPlanPromptGenerator` owns investment-plan prompts. `StockAiInvestmentPlanSchemaFactory` defines its snapshot-specific result schema, including at most three allocations.
- `StockAiInvestmentPlanResponseValidator` validates the JSON object, schema, and business rules before returning `StockAiInvestmentPlanResponse`.
- `StockAiInvestmentPlanCalculator` derives percentages and projected portfolio values after validation. Do not ask AI to supply or persist authoritative `calculated` fields.
- Keep schema, prompts, validator, typed response, calculator, and investment-plan UI aligned when the contract changes. Shared analysis enums or context providers do not merge these ownership boundaries.

The validator preserves these invariants:

- Deployed capital plus cash reserve equals the immutable CZK budget, and allocations sum to deployed capital, using the validator's existing money tolerance.
- `invest_all`, `invest_part`, and `hold_cash` agree with deployed capital, cash reserve, and the allocation list.
- Allocation tickers are unique. Known companies retain their snapshot ID, name, ticker, and currency; a held ticker uses `portfolio` even if also on the watchlist. `new` requires a null ID and a ticker absent from both snapshot lists.
- Allocations cannot have insufficient data and require strong or acceptable dividend safety.
- Valuation price range, currency, and method are all present or all null. A null range requires `uncertain`; a present range satisfies `0 < low <= base <= high` and uses the allocation currency.

## Codex bundle and import

`InvestmentPlan/Codex/StockAiInvestmentPlanCodexBundleFactory` exports an unprocessed plan with instructions, `schema/result.schema.json`, `input/context.json`, `input/reference-analysis.json`, a manifest, and an empty `output/` directory. Preserve the investment-plan bundle contract; it does not use the V2 per-company bundle layout.

`InvestmentPlan/UI/StockAiInvestmentPlanCodexResultFormFactory` imports the result JSON with a 2 MiB upload limit. `StockAiInvestmentPlanFacade::processCodexResponse()` validates against the stored snapshot, then takes a pessimistic plan lock in a transaction, rechecks `canImportCodexResponse()`, enriches the validated response through the calculator, and completes the plan with source `CODEX`. Preserve the raw response and processed timestamp and reject repeated completion.

## Focused verification

Use the existing unit tests under `tests/Unit/Stock/AiAnalysis/InvestmentPlan/` for the changed boundary: snapshot metadata, prompt/schema alignment, budget and identity rejection, dividend/valuation constraints, calculator percentages, bundle contents, and completion guards. Follow the applicable [AGENTS.md validation](../../../../AGENTS.md#validation-matrix).
