# Stock AI Analysis Version 2

Use this reference when changing the V2 contract or either provider workflow. Keep provider execution separate, but make every completed response converge on the same validator and persistence path.

## Run creation and immutable input

1. `StockAiAnalysisFacade::createRun()` creates a UUID and fixed `analysisAsOf` timestamp.
2. `StockAiAnalysisV2SnapshotFactory` captures `schemaVersion: 2`, run metadata, requested scope, conventions, portfolio/watchlist inputs, portfolio context, and optional single-stock input.
3. Store the snapshot on `StockAiAnalysisRun` with `analysisSchemaVersion = 2`. Do not rebuild it from live positions while processing the response.
4. `StockAiAnalysisV2PromptGenerator` derives system, manual, per-company, reduce, and Codex task prompts from this snapshot.

`StockAiAnalysisV2SnapshotFactory` still reuses the legacy prompt generator only as a data provider for current portfolio and watchlist facts. Do not treat that dependency as ownership of the V2 prompt contract.

## Dynamic response contract

`StockAiAnalysisV2SchemaFactory::createFullSchema()` derives required sections from `snapshot.scope`:

- Require `portfolioAnalysis` with exactly one result per portfolio company when `includesPortfolio` is true.
- Require `watchlistAnalysis` with exactly one result per watchlist company when `includesWatchlist` is true.
- Require `stockAnalysis` for a single-stock run.
- Require `marketOverview` when requested.
- Require `portfolioEvaluation` for a non-daily portfolio run.
- Require `dailyBrief` for daily portfolio or watchlist runs.

Keep English JSON keys and controlled values, Czech narrative text, major currency units, and the fixed `analysisAsOf`. Keep portfolio, watchlist, and single-stock action enums distinct because their permitted decisions differ.

Company results contain identity, summary, data quality, material events, earnings, dividend, catalysts, risks, valuation, and recommendation. Portfolio/watchlist results add `performanceComment`; single-stock results add business summary, moat, financial health, and conclusion. Run-level DTOs represent market overview, portfolio evaluation, and daily brief.

## Provider flows

### Manual

Display the V2 system instruction plus the full task prompt. Submit pasted JSON through `StockAiAnalysisFacade::processResponse()` with source `MANUAL`.

### Codex

`StockAiAnalysisCodexBundleFactory` exports a temporary ZIP for a V2 run with an input snapshot; keep the UI action available only while the run is unprocessed. Preserve this layout:

- `README.md`, `AGENTS.md`, and `manifest.json`
- `instructions/system.md` and `instructions/task.md`
- `schema/company-result.schema.json` and `schema/result.schema.json`
- `input/context.json`
- one `input/portfolio-*.json` or `input/watchlist-*.json` per company
- optional `input/stock.json` and an empty `output/` directory

Codex reads the bundled `AGENTS.md`, researches each company independently, may place partial results in `output/`, synthesizes run-level sections, validates the complete output, and creates `result.json` in the extracted project root. Do not bundle a pre-created result.

Import only `result.json`, limit it to 10 MiB, and process it with source `CODEX`. Keep the import unavailable while Gemini is queued or processing and after any provider completes the run. Delete temporary server-side ZIP files after sending them.

### Gemini

Use the split/reduce and cache details in the `stock-ai-analysis-gemini` skill. Gemini must produce the same full V2 contract and submit it through `StockAiAnalysisFacade::processResponse()` with source `GEMINI`.

## Validation and persistence

`StockAiAnalysisV2ResponseValidator` must perform all of these before persistence:

1. Decode a top-level JSON object with `Nette\Utils\Json`.
2. Validate it against the snapshot-derived JSON Schema.
3. Require immutable `schemaVersion`, `runId`, and `analysisAsOf` values.
4. Reject missing, extra, duplicate, or renamed company identities.
5. Require valuation values, currency, and method to be either all present or all null.
6. When present, require `0 < low <= base <= high` and the input asset currency.
7. Require uncertain/null valuation for insufficient data.
8. Map the validated array to typed V2 DTOs.

`StockAiAnalysisFacade::processV2Response()` validates first, then uses a transaction and pessimistic run lock. Reject a second completion after `processedAt` is set. Persist:

- the untouched raw response on the run;
- run-level structured data and `StockAiAnalysisProcessingSourceEnum` on the run;
- one `StockAiAnalysisStockResult` per company with searchable action/confidence/fair value fields and the complete company structured data;
- a calculated margin of safety only when current price and positive base fair value are available.

## Compatibility and UI

- Keep `StockAiAnalysisRun::isV2()` as the branch between V2 structured rendering and historical V1 fields.
- Do not migrate or reinterpret old V1 raw responses implicitly.
- Build action checklists from persisted structured results, not by parsing raw JSON again.
- Before completion, show the applicable manual, Gemini, and Codex controls. After completion, hide processing controls and render the quick recommendation summary plus structured run/company cards.
