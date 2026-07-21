---
name: stock-ai-analysis-gemini
description: 'Invoke in addition to `stock-ai-analysis` when a change is specifically provider-facing: `src/Ai/Gemini/`, `GeminiClient`, Gemini request configuration or response extraction, Gemini processing/cache files, V2 per-company split/reduce calls, Gemini schema conversion, validation retry, failure mapping, or Gemini-specific statuses. Do not trigger for provider-agnostic stock analysis entities, UI, checklists, Codex bundles, or follow-up behavior alone.'
---

# Stock AI Analysis — Gemini Integration

Keep provider-specific HTTP, cache, and orchestration concerns separate from the general stock-analysis domain. Always use `stock-ai-analysis` for changes under `src/Stock/AiAnalysis/`; add this skill only when Gemini-specific behavior is involved.

## Ownership

- `src/Ai/Gemini/GeminiClient.php` is the only direct Gemini HTTP API wrapper.
- `GeminiClientException` represents HTTP, JSON, shape, and missing-text failures at the provider boundary.
- `StockAiAnalysisGeminiProcessorFacade` owns provider processing, cache reuse, reduce calls, and processing-state transitions.
- Gemini-specific JSON normalization stays near the processor; provider-agnostic result parsing stays in the stock-analysis domain.

## Client rules

- Configure API key and model through `gemini.*` DI parameters in `config/config.neon`; never hardcode them.
- Use `Nette\Utils\Json` for request/response serialization and `App\Utils\TypeValidator` for scalar validation.
- Keep HTTP request construction and response text extraction inside `GeminiClient`.
- Throw `GeminiClientException` with English context for non-success responses, invalid JSON, invalid shapes, or missing text.
- Do not log full prompts, portfolio input, raw responses, API keys, or cached response contents.

## Prompt and schema boundary

- Keep provider-independent prompt meaning in `stock-ai-analysis`.
- Put Gemini request-schema limitations and provider-specific request fields at the processor/client boundary.
- When a field changes, keep prompt, provider schema, domain parser, entity, UI, and tests aligned; do not make the domain parser depend on incidental Gemini envelope fields.

## V2 split and reduce flow

- Read the V2 reference in `stock-ai-analysis` before changing this flow.
- For portfolio and watchlist runs, make one request per immutable company snapshot with `generateCompanyPrompt()` and the matching one-company schema.
- Validate company identity and valuation against the expected snapshot item before accepting or caching a partial response.
- Build run-level `marketOverview`, `portfolioEvaluation`, or `dailyBrief` in a reduce request only when the snapshot-derived reduce schema requires them.
- Merge metadata, partial company analyses, and reduce output into the full V2 response, then pass it to `StockAiAnalysisFacade::processResponse()` with source `GEMINI`.
- For runs without portfolio or watchlist items, use the full prompt and full schema in `manual.json`; this includes single-stock-only runs.
- Convert standard JSON Schema through `StockAiAnalysisV2SchemaFactory::toGeminiResponseSchema()` only at the Gemini boundary. Keep the canonical schema provider-independent.

## Cache and processing

- Cache files live below the injected Gemini folder and are owned by `StockAiAnalysisGeminiProcessorFacade`.
- Preserve `manual.json`, numbered `portfolio-*.json` and `watchlist-*.json`, and `reduce.json` naming unless the requested change includes cache migration behavior.
- Validate cached V2 responses before reuse. Regenerate invalid cache entries instead of trusting their presence.
- Allow one corrected retry after an invalid V2 provider response, include concise validation errors in the retry prompt, and never cache an invalid response.
- Keep queued, processing, completed, and failed transitions explicit and preserve duplicate-queue guards.
- If a message payload changes, update JobRequest producer/processor handling and tests without using a real queue.

## Testing

- Mock `GeminiClient` in stock-analysis orchestration tests.
- Test `GeminiClient` with synthetic PSR responses; never call the real API.
- Cover invalid HTTP responses, invalid JSON/shape, missing text, cache reuse, reduce flow, and failed status transitions when touched.
- Use local JSON fixtures or inline arrays and never read real cached responses or local secrets.
- Finish code/config changes with `composer cs-fix && composer build-all`.
