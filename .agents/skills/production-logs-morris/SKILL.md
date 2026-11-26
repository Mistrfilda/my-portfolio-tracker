---
name: production-logs-morris
description: Inspect production application logs and Tracy exception snapshots through the Morris log viewer HTTP agent API when debugging production errors.
---

# Production Logs via Morris

Use the local-network Morris log viewer (`http://192.168.1.245:30175`, MicroK8s namespace `default`) for read-only inspection of production PHP `.log` files and Tracy exception snapshots (`.html` and `.md`).

## Discovery and response format

- Self-describing API reference: `GET http://192.168.1.245:30175/llms.txt`
- Default response format: `text/markdown; charset=utf-8` (compact and token-efficient for agents).
- Optional JSON: add `?format=json` or send `Accept: application/json` (use `?format=markdown` to force Markdown).
- All `/api/agent/*` endpoints normalize container and local paths to repository-relative paths (e.g. `src/...`, `bin/...`, `vendor/...`) and redact common secrets (`apiKey`, `token`, `password`, `secret`, `cookie`, `Authorization`, DSN credentials).

## Recommended two-step workflow

1. **Fetch grouped incidents first** to get deduplicated recent `ERROR`, `CRITICAL`, `ALERT`, and `EMERGENCY` entries across `.log` and Tracy files with counts, timestamps, normalized `file:line` locations, and detail links:

```bash
curl -sS "http://192.168.1.245:30175/api/agent/incidents?days=7&limit=20"
```

Supported query parameters:
- `days` — lookback window in days (`1`–`90`, default `7`)
- `maxFiles` — maximum newest files to scan (`1`–`100`, default `20`)
- `limit` — maximum grouped incidents to return (`1`–`100`, default `20`)
- `level` — severity filter (e.g. `ERROR`, `CRITICAL`, `ALERT`, `EMERGENCY`)
- `source` — `all` (default), `log`, or `tracy`
- `q` — case-insensitive fulltext filter across titles, messages, paths, correlations, and filenames
- `format` — `markdown` (default) or `json`

2. **Fetch targeted details on demand** using the links returned by `/api/agent/incidents`:

- **Tracy exception detail** (accepts either the `.html` snapshot name or its `.md` alias; truncated to 300 lines by default so large argument dumps do not flood context; returns sanitized `.html` metadata if no `.md` dump exists):

```bash
curl -sS "http://192.168.1.245:30175/api/agent/tracy/<file>?maxLines=300"
```

Use `maxLines` (`10`–`5000`, default `300`) or `full=1` only when the truncated dump omits needed stack frames or environment context.

- **Single `.log` file entries** (newest first):

```bash
curl -sS "http://192.168.1.245:30175/api/agent/logs/<file>?level=ERROR&limit=50"
```

Supported query parameters: `level`, `q`, `from`, `to` (ISO-8601), `limit`, `offset`, `format`.

3. **Trace findings in this repository**: open the normalized `file:line` paths directly in this checkout and follow the [AGENTS.md validation matrix](../../../AGENTS.md#validation-matrix) when implementing a fix.

## Safety and boundaries

- Prefer `/api/agent/*` over the web UI endpoints (`/api/tracy/*/markdown`, `/api/logs/*`) so path normalization, truncation, and secret redaction remain active.
- Never copy residual tokens, credentials, cookies, or private configuration values into summaries, plans, tests, or repository files even if a value slips past automated redaction.
- Morris is strictly read-only. Diagnosing a production error does not authorize deploying fixes or mutating cluster state.
- If `http://192.168.1.245:30175` is unreachable (outside the local network or service unavailable), report the connectivity limitation clearly and continue with local source inspection or requested cluster diagnostics.

## Related skills

- `tracy-debugging` — local PHP/Tracy BlueScreen, `log/`, and query debugging.
- `kubernetes-infrastructure` — MicroK8s workloads, pod logs, PVCs, and rollouts.
