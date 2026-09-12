---
name: alpine-tailwind
description: Add or change Tailwind styling, Alpine components, or Naja integration in the project's frontend.
---

## Stack overview

- **Alpine.js 3** (`alpinejs` in `package.json`) – bootstrapped in `assets/ts/alpine/AppAlpine.ts` via `Alpine.start()`.
- **Tailwind CSS 4** (`tailwindcss`, `@tailwindcss/postcss`, `@tailwindcss/forms`, `@tailwindcss/typography`, `@tailwindcss/aspect-ratio`) – config in `tailwind.config.js`, entrypoint `assets/css/index.css`.
- **Tailwind Plus Elements** (`@tailwindplus/elements`) – loaded in `assets/app.ts`.
- **Naja** (`naja`) – AJAX layer, integrated with Alpine in `AppAlpine.ts` (re-renders charts after AJAX `complete`).
- Build: **webpack-encore**, see `webpack.config.js`. Watch: `npm run watch-dev`. Production: `npm run build-prod`.

## Where things live

- `assets/app.ts` – single entrypoint, imports `./ts/alpine/AppAlpine`, `./css/index.css`, tom-select CSS, live form validation, `@tailwindplus/elements`.
- `assets/ts/alpine/AppAlpine.ts` – **all shared `Alpine.data(...)` components live here** (or are imported here). At the bottom: `window.Alpine = Alpine; Alpine.start();`.
- `assets/ts/alpine/*.ts` – per-component files (e.g. `DragScroll.ts`) imported and registered in `AppAlpine.ts`.
- `assets/ts/<domain>/` – domain-specific TS (chart, expense, naja, select, confirm) – typically exports plain objects/functions that are wired into Alpine components in `AppAlpine.ts`.
- `assets/css/index.css` – Tailwind entrypoint + custom CSS.
- `tailwind.config.js` – legacy configuration loaded by `@config` in `assets/css/index.css`. Source detection also uses Tailwind 4's automatic scanning and CSS `@source` directives.
- `src/UI/Tailwind/TailwindColorConstant.php` – PHP-side mirror of Tailwind palette, use it when a badge/chart color needs to match Tailwind classes.

## Registering a new Alpine component

1. Keep small components inline in `AppAlpine.ts`; extract to `assets/ts/alpine/<Name>.ts` once the body grows past ~20 lines or needs its own types.
2. Register with `Alpine.data('<componentName>', () => ({ ... }))` **before** `Alpine.start()`.
3. If the component needs to be callable from outside (e.g. Latte inline handlers), expose it on `window` in the `declare global` block at the top of `assets/app.ts`.
4. Use the component in Latte:
   ```latte
   <div x-data="dropdown" class="relative">
       <button type="button" @click="toggle()" class="...">Menu</button>
       <div x-show="open" x-cloak class="absolute ...">...</div>
   </div>
   ```
5. Add `x-cloak` to prevent flash of unstyled content; its CSS rule is in `assets/css/alpine.css`, imported by `index.css`.
6. Reuse an existing watcher, or run `npm run build-dev` for a finite verification. Start `npm run watch-dev` only when a persistent watcher is needed.

### Existing shared components (don't duplicate – reuse)

`frontMenu`, `dropdown`, `flashMessage`, `select`, `datagrid`, `modal`, `loadChart`, `expenseMainTag`, `addExpenseOtherTag`, `removeOtherTag`, `currencyConvert`, `currencyQuickConversions`, `valuationTable`, `stockValuationModelData`, `dragScroll` – registered in `AppAlpine.ts`. Treat declarations in the `Window` interface as typings only; verify that a component is actually registered before reusing it.

## Tailwind conventions

- **Content paths** – for a new template location, check source detection in `tailwind.config.js` and `assets/css/index.css`; add an explicit `@source` when the location is not detected, then verify the generated CSS.
- **Colors** – custom palettes (`orange`, `sky`, `emerald`, `teal`, `cyan`, `indigo`, `rose`) are re-exported from `tailwindcss/colors` in `theme.extend.colors`. Don't use arbitrary hex values for brand colors; prefer these scales.
- **PHP ↔ CSS sync** – when a PHP enum/const needs to align with a Tailwind class (e.g. badges in `ColumnBadge`, chart datasets), use constants from `App\UI\Tailwind\TailwindColorConstant` instead of inlining hex strings.
- **Forms** – `@tailwindcss/forms` is enabled; use the project's `AdminFormRenderer` (see `ui-forms-admin` skill) which already applies matching classes.
- **Typography / prose** – `@tailwindcss/typography` available via `prose` class.

## Alpine + Naja interaction

`AppAlpine.ts` listens on `naja.addEventListener('complete', …)` and re-fetches data for any registered chart. If a new dynamic element should survive AJAX snippet updates:

- Put it inside a Nette snippet (`{snippet foo}…{/snippet}`) – Alpine automatically re-initializes `x-data` on snippet replacement (Naja triggers DOM mutation).
- For imperative re-init, call `Alpine.initTree(element)` after manual DOM manipulation.

## Common pitfalls

- **Don't** import Alpine anywhere except `AppAlpine.ts`; a second bootstrap breaks reactivity.
- **Don't** use `@tailwindplus/elements` custom tags without importing the package (already done in `app.ts`).
- **Don't** assume dynamically constructed PHP class names are detectable. Use complete class names in scanned sources or the existing `@source inline(...)` families in `assets/css/index.css`; prefer template-side classes or established palette constants.
- Prefer `@click.stop` / `@click.outside` for dropdowns instead of manual document listeners.
- Keep comments and identifiers in **English** (project rule).

## Build commands

- Dev watch: `npm run watch-dev`
- Production build: `npm run build-prod`
- Lint TS: `npm run lint` / `npm run lint-fix`

## Related skills

- `tailwind-plus-components` – local Tailwind Plus HTML examples in `.twui`; inspect before composing new UI.
- `latte-templates` – Latte syntax used together with `x-data`, `@click`, etc.
- `ui-svg-icons` – SVG icons are often toggled with Alpine (`x-show`).
- `ui-forms-admin` – form inputs already styled via Tailwind + custom renderer.
- `ui-latte-filters` – formatting values rendered inside Alpine-driven templates.
