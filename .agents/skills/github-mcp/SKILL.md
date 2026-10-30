---
name: github-mcp
description: Inspect and manage this project's GitHub pull requests, issues, and Actions through GitHub MCP. 
---

## GitHub MCP

### Target and access

- This project's repository is [Mistrfilda/my-portfolio-tracker](https://github.com/Mistrfilda/my-portfolio-tracker). Use a different repository only when the task targets it; do not assume sibling projects have the same owner or hosting service.
- Prefer the configured `mcp__github_personal__*` tools for requested GitHub work. Discover `mcp__github_personal__get_me` if the tools are not initially visible, and use the exposed schemas as the authority for arguments.
- Call `get_me` on first GitHub access in a session to confirm the account; reuse that result for subsequent calls. Scope repository operations to the intended owner and repository.
- Keep working-tree inspection, diffs, commits, and other local Git operations in the CLI. 
- Follow the task's existing authorization. A connection check or CI inspection does not authorize comments, issue/PR changes, workflow reruns, or merges.

### PRs, issues, and CI

- Use list tools for small inventories and search tools for targeted queries. Start with 5–10 results and request only relevant fields; fetch descriptions, diffs, or comments when needed for the task.
- For CI, use `actions_list` with `list_workflow_runs`, selecting the requested branch or commit. Keep the run ID distinct from the displayed run number.
- For a failed run, use `actions_list` with `list_workflow_jobs`, then `get_job_logs` for the failed job with a bounded `tail_lines`. Expand the log excerpt only when the failure needs more context.
- Compare CI findings with [.github/workflows/app-check.yaml](../../../.github/workflows/app-check.yaml) or [.github/workflows/desktop-check.yaml](../../../.github/workflows/desktop-check.yaml) and the owning source files. Apply the [validation matrix](../../../AGENTS.md#validation-matrix) when making fixes.
- Report the relevant PR, issue, or workflow-run link and its observed state. Keep failures found during a connection check separate from the connection result; do not start unrelated repairs.

### Connection checks

- Verify access with `get_me` and a bounded repository or Actions read. A discovered tool list alone does not prove authentication or permissions, and successful reads do not prove write access.
- If MCP is unavailable or access is denied, report the failing operation and continue any independent local work. Do not retrieve tokens from shell startup files or credential stores as a fallback.
- If the bearer-token variable is present in the launching terminal but missing at MCP startup, a shared Codex daemon may retain an older environment. Diagnose against current [Codex MCP documentation](https://developers.openai.com/codex/mcp); do not restart a shared daemon without accounting for other running tasks.
