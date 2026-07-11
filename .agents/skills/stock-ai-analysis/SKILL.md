---
name: stock-ai-analysis
description: Invoke before adding or modifying general stock AI analysis behavior in `src/Stock/AiAnalysis/`, including analysis runs, prompts, response parsing, follow-up questions, stored stock results, action checklists, processing orchestration, or AI analysis UI. For Gemini-specific client, schema, cache, or API changes, also invoke `stock-ai-analysis-gemini`.
---

## Stock AI Analysis

Stock AI analysis lives in `src/Stock/AiAnalysis/`. It turns portfolio, watchlist, market, and single-stock context into AI prompts, stores analysis runs and raw responses, parses structured AI output into domain entities, supports follow-up questions, derives action checklist items, and exposes the results through the admin UI.

Treat AI output as untrusted input. Keep the implementation explicit, deterministic, and easy to verify.

### When to use

- Use this skill before changing anything under `src/Stock/AiAnalysis/` that is not purely mechanical.
- Also use `stock-ai-analysis-gemini` when the change touches Gemini requests, response schemas, cached Gemini responses, prompt reduction, or Gemini processing statuses.
- Also use `testing-conventions` before adding or modifying tests.
- Also use `ui-base-presenters-templates`, `latte-templates`, or related UI skills when changing `src/Stock/AiAnalysis/UI/`.

### Main areas

- `StockAiAnalysisFacade` creates analysis runs, queues processing, parses final responses, persists run-level data, and creates per-stock results.
- `StockAiAnalysisPromptGenerator` builds prompts, provider-facing instructions, automatic portfolio/watchlist data, and expected response schemas.
- `StockAiAnalysisGeminiProcessorFacade` orchestrates provider processing, cached response reuse, status transitions, and follow-up processing.
- `StockAiAnalysisRun` stores one analysis run, generated prompt, raw response, summaries, daily brief fields, processing status, and related stock results.
- `StockAiAnalysisStockResult` stores per-stock AI output such as news, opinion, recommendation, confidence, fair price, and commentary.
- `StockAiAnalysisFollowUpQuestion*` classes handle user follow-up questions attached to an existing run.
- `ActionChecklist/` converts AI outputs into actionable checklist items.
- `RabbitMQ/` contains async message, producer, and consumer classes for processing runs.
- `UI/` contains presenters, grids, controls, and Latte templates for listing and viewing analysis runs and results.

### Design rules

- Keep AI analysis domain logic in dedicated services/facades, not in presenters or templates.
- Prefer explicit fields over generic arrays once data crosses from raw AI response into the domain model.
- Preserve the raw AI response on the run for traceability, but do not expose or log full prompts/responses unless explicitly required.
- Do not assume the AI followed instructions. Validate shape and scalar values before assigning them to entities.
- Keep prompt text, response schema, parser, entity fields, UI rendering, and tests in sync.
- Use enums for controlled AI values. Invalid enum values should normally map to `null` unless the existing flow intentionally fails hard.
- Keep single-stock, portfolio, watchlist, market overview, daily brief, and follow-up flows distinct unless the change explicitly merges behavior.
- Avoid speculative abstractions; add only the fields and flows needed for the requested behavior.

### Prompt and response rules

- Every prompt field requested from AI must have a clear parser path or be intentionally ignored with a reason.
- Every required schema field must be handled explicitly by the parser and covered by tests when behavior changes.
- Keep prompts provider-agnostic where possible. Provider-specific constraints belong near provider processing and in the Gemini-specific skill.
- Make prompts deterministic from the same input data; avoid hidden time-dependent behavior outside `DatetimeFactory`.
- Use English for prompt-facing labels, exception messages, and comments.
- Keep response parsing tolerant of optional sections, but strict enough to reject completely invalid JSON or invalid top-level shapes.

### Persistence and status rules

- A `StockAiAnalysisRun` represents one analysis request and should remain the aggregate root for stored run-level data.
- Status transitions must stay explicit: queued, processing, completed, and failed states should be visible in entity methods and processor flow.
- Do not enqueue duplicate processing; keep guard methods such as `canBeQueuedForGeminiProcessing()` meaningful.
- When adding stored fields, update constructor/setter usage, Doctrine mapping attributes, repositories if needed, migrations, grids/details, and tests.
- Preserve created/processed timestamps via `DatetimeFactory`; do not instantiate `DateTimeImmutable` directly in this module.
- When linking AI output to `StockAsset`, handle missing or stale asset references safely.

### Follow-up question rules

- Follow-up questions belong to an existing analysis run and should not mutate the original run response unexpectedly.
- Include enough context from the parent run to answer the follow-up, but avoid sending unrelated portfolio data when not needed.
- Store the follow-up prompt/response/status separately from the main analysis run fields.
- Keep follow-up status transitions and failure handling aligned with main run processing.

### Action checklist rules

- Action checklist items should be derived from persisted AI results, not from reparsing raw response strings.
- Keep checklist priorities and labels aligned with `StockAiAnalysisActionChecklistPriorityEnum`.
- Do not hide uncertainty: low-confidence or missing AI fields should produce either no checklist item or a clearly lower-priority item, not a strong action.

### UI rules

- Keep UI code read-only with respect to AI decisions unless a user action explicitly creates, queues, or asks a follow-up question.
- For presenters and controls, use typed template classes and declare every assigned template property.
- Keep grids and detail pages aligned with enum labels and nullable AI fields.
- Do not add raw `<svg>` markup; use `{renderSvg}` and `App\UI\Icon\SvgIcon`.
- Do not put prompt/schema construction or response parsing into Latte templates or presenters.

### Testing rules

- Prefer unit tests for prompt generation, response parsing, status transitions, follow-up processing, action checklist derivation, and cache-independent orchestration.
- Mock AI clients, RabbitMQ producers/consumers, and external data sources. Never call real AI APIs, queues, or external HTTP APIs in tests.
- Cover invalid JSON, missing optional sections, invalid enum values, missing stock assets, failed processing, and duplicate-queue guards when touching those paths.
- For schema/prompt changes, include tests that prove the generated schema and parser expectations remain aligned.
- For UI changes, add the smallest relevant presenter/control/template coverage already used by the project; do not add browser tests unless the behavior requires them.
- For documentation-only skill changes, a consistency review is enough; code/config changes should finish with `composer cs-fix && composer build-all`.
