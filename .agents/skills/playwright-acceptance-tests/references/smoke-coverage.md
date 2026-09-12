# Navigation Smoke Coverage

Use this reference when extending `tests/Browser/smoke.spec.ts`. These tests run against an existing local app and stay read-only.

## Current coverage

The smoke suite checks:

- login page renders `My portfolio tracker`, username input, `Heslo`, and `Přihlásit se`,
- user can log in and sees the `Dashboard` heading,
- authenticated sidebar menu links discovered from `nav[aria-label="Sidebar"]` render a visible `main` landmark and first `h1`,
- authenticated command-list search result links discovered from `el-command-list` render a visible `main` landmark and first `h1`,
- visited pages do not emit `console.error` or `pageerror` events.

## Expanding coverage

1. Use MCP to confirm the page is reachable by the test user and is read-only.
2. Prefer the existing dynamic sidebar/search discovery in `tests/Browser/smoke.spec.ts` when the page is already linked from the authenticated UI.
3. Add an explicit smoke test only when the page is important, read-only, and not discoverable through the current navigation helpers.
4. Keep assertions simple: navigate, assert a stable visible heading/key text, and assert no frontend errors.
5. If the page needs special setup or writes data, do not add it to the read-only smoke layer.

