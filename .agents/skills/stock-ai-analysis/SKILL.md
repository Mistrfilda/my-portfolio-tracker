---
name: stock-ai-analysis
description: Maintain this project's stock AI analysis and investment-plan workflows. Use when changing snapshots, prompts, response contracts, imports, orchestration, persisted results, or analysis UI.
---

## Stock AI Analysis

Stock AI analysis lives in `src/Stock/AiAnalysis/`. It turns portfolio, watchlist, market, and single-stock context into AI prompts, stores validated results, supports follow-up questions and action checklists, and exposes them through the admin UI. `InvestmentPlan/` owns a separate capital-allocation workflow linked to a completed portfolio analysis.

Treat AI output as untrusted input.

### Companion guidance

- Also use [stock-ai-analysis-gemini](../stock-ai-analysis-gemini/SKILL.md) when the change touches Gemini requests, response schemas, cached Gemini responses, prompt reduction, or Gemini processing statuses.
- Use [testing-conventions](../testing-conventions/SKILL.md) for PHPUnit changes and the applicable UI skills for analysis or investment-plan UI changes.
- Read [references/version-2.md](references/version-2.md) before changing V2 analysis-run snapshots, schemas, prompts, validation, persistence, Codex bundles/imports, or Gemini split/reduce processing.
- Read [references/investment-plan.md](references/investment-plan.md) for investment-plan work. It has its own schema, validator, calculator, and Codex import path; analysis-run V2 rules below do not replace that contract.

### Main areas

- `StockAiAnalysisFacade` creates analysis runs, queues processing, parses final responses, persists run-level data, and creates per-stock results.
- `StockAiAnalysisPromptGenerator` builds prompts, provider-facing instructions, automatic portfolio/watchlist data, and expected response schemas.
- `V2/` owns the immutable snapshot, provider-agnostic prompts, dynamic JSON Schemas, strict validation, and typed V2 response contract.
- `Codex/` exports a self-contained V2 project with one input file per company and exact schemas; `StockAiAnalysisCodexResultFormFactory` imports its final `result.json`.
- `StockAiAnalysisGeminiProcessorFacade` orchestrates provider processing, cached response reuse, status transitions, and follow-up processing.
- `StockAiAnalysisRun` stores one analysis run, its schema version, immutable input snapshot, raw response, structured run data, processing source/status, and related stock results.
- `StockAiAnalysisStockResult` stores searchable common fields plus V2 per-company structured data.
- `StockAiAnalysisFollowUpQuestion*` classes handle user follow-up questions attached to an existing run.
- `ActionChecklist/` converts AI outputs into actionable checklist items.
- `RabbitMQ/` contains async message, producer, and consumer classes for processing runs.
- `InvestmentPlan/` owns `StockAiInvestmentPlan`, its immutable capital snapshot, prompts, response contract, calculated allocation percentages, Codex bundle/import, and UI.
- `UI/` contains presenters, grids, controls, and Latte templates for listing and viewing analysis runs and results.

### Design rules

- Keep AI analysis domain logic in dedicated services/facades, not in presenters or templates.
- Prefer explicit fields over generic arrays once data crosses from raw AI response into the domain model.
- Preserve the raw AI response on the run for traceability, but do not expose or log full prompts/responses unless explicitly required.
- Do not assume the AI followed instructions. Validate shape and scalar values before assigning them to entities.
- Keep prompt text, response schema, parser, entity fields, UI rendering, and tests in sync.
- Use enums for controlled AI values. Preserve tolerant `tryFrom()` behavior where the legacy parser already uses it; reject invalid V2 controlled values through the schema.
- Keep single-stock, portfolio, watchlist, market overview, daily brief, and follow-up flows distinct unless the change explicitly merges behavior.
- Preserve the legacy parser and UI path for historical schema-version-1 runs; create new runs as V2 unless a requested migration changes that rule.

### Prompt and response rules

- Every prompt field requested from AI must have a clear parser path or be intentionally ignored with a reason.
- Every required schema field must be handled explicitly by the parser and covered by tests when behavior changes.
- Keep prompts provider-agnostic where possible. Provider-specific constraints belong near provider processing and in the Gemini-specific skill.
- Treat the V2 input snapshot as immutable after run creation. Derive required response sections, exact company counts, identifiers, timestamp, and currencies from that snapshot.
- Route analysis-run manual paste, Codex import, and Gemini output through the same V2 validator and persistence path.
- Make prompts deterministic from the same input data; avoid hidden time-dependent behavior outside `DatetimeFactory`.
- Use English for prompt-facing labels and controlled values; preserve Czech narrative output.
- Keep legacy response parsing tolerant of optional sections. Keep V2 strict about every section required by the immutable snapshot while allowing only schema-declared nullable or optional values.

### Persistence and status rules

- A `StockAiAnalysisRun` represents one analysis request and should remain the aggregate root for stored run-level data.
- Record whether a completed V2 response came from manual input, Gemini, or Codex with `StockAiAnalysisProcessingSourceEnum`.
- Status transitions must stay explicit: queued, processing, completed, and failed states should be visible in entity methods and processor flow.
- Do not enqueue duplicate processing; keep guard methods such as `canBeQueuedForGeminiProcessing()` meaningful.
- Do not accept Codex imports while Gemini is queued or processing, or after the run is complete; keep `canImportCodexResponse()` meaningful.
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

- Keep result rendering read-only; creating, importing, queueing, and asking follow-up questions belong to explicit user actions.
- Follow [ui-base-presenters-templates](../ui-base-presenters-templates/SKILL.md) and [latte-templates](../latte-templates/SKILL.md) for presenters, controls, and templates.
- Keep grids and detail pages aligned with enum labels and nullable AI fields.
- Render V2 run-level sections from `StockAiAnalysisRun::getStructuredData()` and company sections from `StockAiAnalysisStockResult::getStructuredData()`; keep the legacy rendering path for V1.
- Hide prompt and provider-processing controls after a run is complete; show the persisted results and processing source instead.
- Do not put prompt/schema construction or response parsing into Latte templates or presenters.

### Testing rules

- Prefer unit tests for prompt generation, response parsing, status transitions, follow-up processing, action checklist derivation, and cache-independent orchestration.
- Mock AI clients, RabbitMQ producers/consumers, and external data sources. Never call real AI APIs, queues, or external HTTP APIs in tests.
- Cover invalid JSON, missing optional sections, invalid enum values, missing stock assets, failed processing, and duplicate-queue guards when touching those paths.
- For V2 schema/prompt changes, cover dynamic scope, immutable metadata/identity, exact company membership, valuation invariants, and schema-to-DTO mapping.
- For V2 analysis-run Codex changes, inspect the ZIP structure and prove the bundle contains instructions, schemas, context, and one immutable input per requested company without a pre-created `result.json`.
- Follow the applicable [AGENTS.md validation](../../../AGENTS.md#validation-matrix).
