---
name: stock-ai-analysis-gemini
description: Invoke before adding or modifying stock AI analysis, Gemini prompts, Gemini response schemas, cached Gemini processing, AI analysis RabbitMQ jobs, AI analysis results, or AI analysis UI. Covers `src/Stock/AiAnalysis/` and `src/Ai/Gemini/`.
---

## Stock AI Analysis and Gemini

Stock AI analysis lives mainly in `src/Stock/AiAnalysis/` and uses the shared Gemini client in `src/Ai/Gemini/`. It generates prompts from portfolio/watchlist/market data, optionally processes them asynchronously through RabbitMQ, calls Gemini, caches intermediate Gemini responses, parses structured JSON, and persists run-level plus per-stock results.

Use this skill before changing AI analysis prompts, response schemas, Gemini API handling, cached response processing, AI analysis entities, RabbitMQ jobs, per-stock AI result parsing, or AI analysis UI.

### Main files

- `src/Stock/AiAnalysis/StockAiAnalysisFacade.php` creates runs, queues Gemini processing, and parses final responses.
- `src/Stock/AiAnalysis/StockAiAnalysisPromptGenerator.php` builds prompts, system instructions, response schemas, and automatic portfolio/watchlist data.
- `src/Stock/AiAnalysis/StockAiAnalysisGeminiProcessorFacade.php` executes Gemini processing, handles cached response files, and updates Gemini processing status.
- `src/Stock/AiAnalysis/StockAiAnalysisRun.php` stores one analysis run, generated prompt, raw response, summaries, Gemini status, and related stock results.
- `src/Stock/AiAnalysis/StockAiAnalysisStockResult.php` stores per-stock analysis results.
- `src/Stock/AiAnalysis/*Enum.php` files define structured output values such as sentiment, confidence, result type, action suggestion, portfolio prompt type, and processing status.
- `src/Stock/AiAnalysis/ActionChecklist/` derives action checklist items from AI results.
- `src/Stock/AiAnalysis/RabbitMQ/` contains async Gemini process message, producer, and consumer.
- `src/Stock/AiAnalysis/UI/` contains presenters, grids, controls, and typed templates.
- `src/Ai/Gemini/GeminiClient.php` wraps the Gemini `generateContent` HTTP API.
- `src/Ai/Gemini/GeminiClientException.php` is the domain exception for Gemini client failures.

### Run and processing flow

1. `StockAiAnalysisFacade::createRun()` generates a prompt through `StockAiAnalysisPromptGenerator::generate()` and persists a `StockAiAnalysisRun`.
2. `StockAiAnalysisFacade::enqueueGeminiProcessing()` marks the run as queued and publishes `StockAiAnalysisGeminiProcessMessage`.
3. `StockAiAnalysisGeminiProcessConsumer` receives the message and calls `StockAiAnalysisGeminiProcessorFacade::process()`.
4. The processor marks the run as processing, builds Gemini requests, loads cached JSON responses when present, or calls `GeminiClient::generateContent()`.
5. The processor passes the combined JSON response to `StockAiAnalysisFacade::processResponse()`.
6. The facade decodes and validates JSON, updates run-level summaries, persists per-stock results, and flushes.
7. The processor marks the run as completed or failed; failures are logged and rethrown.

### Prompt and schema rules

- Keep `StockAiAnalysisPromptGenerator::generate()` and `generateResponseSchema()` aligned.
	- If the prompt asks for a new field, the schema and parser must understand it.
	- If the schema requires a field, the parser must handle it explicitly.
- `generateSystemInstruction()` should stay provider-agnostic and not contain temporary debugging instructions.
- Portfolio and watchlist flows can be split into per-stock Gemini calls and then reduced; preserve this shape unless you intentionally change processing cost/latency behavior.
- Daily brief behavior is derived from `StockAiAnalysisPortfolioPromptTypeEnum`; check `StockAiAnalysisRun::isDailyBrief()` before changing related fields.

### Gemini client rules

- `GeminiClient::generateContent()` is the only direct Gemini HTTP API wrapper.
- Configure Gemini through DI parameters (`gemini.apiKey`, `gemini.model`) and `config/config.neon`; do not hardcode model names or API keys.
- Use `Nette\Utils\Json` for request/response serialization.
- Throw `GeminiClientException` for HTTP, invalid JSON, invalid shape, or missing text failures in the client layer.
- Do not log full prompts or raw Gemini responses unless the change explicitly requires it and sensitive data exposure is considered.

### Response parsing rules

- `StockAiAnalysisFacade::processResponse()` expects a JSON object and throws English exceptions for invalid JSON/shape.
- Validate scalar values through `App\Utils\TypeValidator` before assigning them to entities.
- Use enum `tryFrom()` for AI-provided enum values; invalid values should become `null` unless the current flow explicitly requires hard failure.
- Preserve raw response on `StockAiAnalysisRun` for traceability.
- When adding a per-stock result field, update:
	- response schema generation,
	- prompt text,
	- response parsing,
	- `StockAiAnalysisStockResult`,
	- UI/grid/control rendering,
	- tests.

### Cache and async rules

- Cached Gemini response files are owned by `StockAiAnalysisGeminiProcessorFacade` and stored below the injected temp directory.
- Cache filenames are part of the processing flow (`manual.json`, per-portfolio files, per-watchlist files, reduce files); change them only with matching tests.
- `StockAiAnalysisRun::canBeQueuedForGeminiProcessing()` protects against duplicate processing; keep status transitions explicit.
- RabbitMQ tests must use mocks or in-memory abstractions; never use real queues.
- If changing message shape, update producer, consumer, message tests, and any queue configuration.

### UI rules

- AI analysis UI lives under `src/Stock/AiAnalysis/UI/`.
- Follow `ui-base-presenters-templates` for typed presenter/control templates.
- Do not assign dynamic Latte template properties.
- Keep enum labels and UI filters aligned with enum cases.
- For grids, follow existing `StockAiAnalysisGridFactory` and control patterns instead of adding ad-hoc rendering in presenters.

### Testing rules

- Prefer unit tests for prompt generation, response schema generation, response parsing, Gemini processing cache behavior, and Gemini client HTTP handling.
- Mock `GeminiClient` in stock AI analysis tests; do not call the real Gemini API.
- Use local JSON fixtures or inline arrays for AI responses.
- Cover invalid JSON, missing optional sections, invalid enum values, cached response reuse, and failure status transitions when touching those areas.
- For code/config changes, finish with `composer cs-fix && composer build-all`.
- For this skill/documentation-only changes, consistency review is enough.
