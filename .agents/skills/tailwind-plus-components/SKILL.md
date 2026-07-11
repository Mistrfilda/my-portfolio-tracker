---
name: tailwind-plus-components
description: Invoke before composing or redesigning frontend UI in Latte/Tailwind. Use the local `.twui` folder as the first source of Tailwind Plus HTML component examples for pages, forms, tables, lists, navigation, headings, stats, dropdowns, buttons, tabs, and similar UI blocks. Adapt examples to this Nette/Latte project instead of generating UI from memory.
---

## Purpose

The `.twui` folder contains local HTML copies of purchased Tailwind Plus components. When building or modifying frontend UI, inspect relevant files in `.twui` before writing markup so the generated UI follows Tailwind Plus patterns already available in the project.

Use this skill together with:

- `latte-templates` when editing `.latte` files.
- `alpine-tailwind` when adding Tailwind classes, Alpine interactivity, or `@tailwindplus/elements` components.
- `ui-svg-icons` when an example contains inline SVG icons.
- `ui-forms-admin` when adapting form controls rendered through Nette forms.

## Where to look

Start from `.twui` and choose the closest category before generating markup:

- `.twui/elements/` – buttons, button groups, avatars, badges, dropdowns, and small reusable UI elements.
- `.twui/forms/` – input groups, select menus, comboboxes, checkboxes, radio groups, toggles, action panels, and sign-in forms.
- `.twui/navigation/` – tabs, navbars, vertical navigation, command palettes, and navigation patterns.
- `.twui/lists/` – tables, feeds, stacked lists, and grid lists.
- `.twui/data-display/` – stats and description lists.
- `.twui/headings/` – page and section headings.
- `.twui/layout/` – dividers and layout helpers.

If the exact component is unclear, search `.twui` by a short UI term such as `dropdown`, `tabs`, `table`, `input`, `stats`, `button`, or `heading`, then open the closest HTML example.

## Adaptation workflow

1. Identify the UI goal and pick one or two closest `.twui/**/*.html` examples.
2. Copy only the relevant structure and Tailwind classes; do not paste unused demo items, placeholder content, or unrelated variants.
3. Convert plain HTML to project Latte conventions:
   - Replace static URLs with `n:href`, `{link ...}`, or existing route patterns.
   - Replace demo text and sample data with template variables already available in the presenter/control.
   - Use Latte escaping by default; avoid `|noescape` unless the surrounding code already proves it is safe.
   - Keep dynamic template properties typed in the related Template class.
4. Preserve accessibility attributes from Tailwind Plus examples (`aria-*`, `role`, `sr-only`) unless the adapted component no longer needs them.
5. Keep the final markup minimal and consistent with surrounding project code.

## Project-specific rules

- Do not use raw `<svg>` markup from `.twui` examples. Replace icons with `{renderSvg}` and `App\UI\Icon\SvgIcon`.
- `@tailwindplus/elements` is already imported in `assets/app.ts`; custom elements such as `el-dropdown` may be used when they fit the project and the existing `alpine-tailwind` guidance.
- Prefer existing shared Alpine components from `assets/ts/alpine/AppAlpine.ts` before introducing new JavaScript.
- Tailwind classes must appear in scanned project files (`src/**/*.latte`, `assets/**/*.{latte,ts,js}`) after adaptation; `.twui` itself is only a source of examples.
- Do not add broad abstractions or reusable components just because a Tailwind Plus example is reusable. Extract only when the project already has the same pattern or the current task needs reuse.
- Keep comments and identifiers in English.

## Common pitfalls

- Do not generate Tailwind UI from memory when a close `.twui` example exists.
- Do not paste entire Tailwind Plus examples with unused menu items, demo images, dummy forms, or placeholder states.
- Do not keep inline SVGs, CDN script comments, or external Tailwind Plus setup snippets from the examples.
- Do not introduce real external HTTP assets in tests or templates just because an example references demo images.
- Do not change unrelated existing UI while adapting a component.