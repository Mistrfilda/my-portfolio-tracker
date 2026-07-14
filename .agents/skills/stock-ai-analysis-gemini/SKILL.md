---
name: stock-ai-analysis-gemini
description: 'Invoke in addition to `stock-ai-analysis` when a change is specifically provider-facing: `src/Ai/Gemini/`, `GeminiClient`, Gemini request configuration or response extraction, Gemini processing/cache files, provider response schemas, Gemini failure mapping, or Gemini-specific processing statuses. Do not trigger for provider-agnostic stock analysis entities, UI, checklists, or follow-up behavior alone.'
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

## Cache and processing

- Cache files live below the injected Gemini folder and are owned by `StockAiAnalysisGeminiProcessorFacade`.
- Preserve established cache filenames and per-portfolio/watchlist/reduce flow unless the requested change includes migration behavior.
- Keep queued, processing, completed, and failed transitions explicit and preserve duplicate-queue guards.
- If a message payload changes, update JobRequest producer/processor handling and tests without using a real queue.

## Testing

- Mock `GeminiClient` in stock-analysis orchestration tests.
- Test `GeminiClient` with synthetic PSR responses; never call the real API.
- Cover invalid HTTP responses, invalid JSON/shape, missing text, cache reuse, reduce flow, and failed status transitions when touched.
- Use local JSON fixtures or inline arrays and never read real cached responses or local secrets.
- Finish code/config changes with `composer cs-fix && composer build-all`.
