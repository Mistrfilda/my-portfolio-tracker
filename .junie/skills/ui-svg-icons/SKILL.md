---
name: ui-svg-icons
description: Invoke before adding or using any SVG icon in Latte or PHP. Provides the required `renderSvg` macro + `App\UI\Icon\SvgIcon` enum workflow. Use when the user mentions adding an icon, `SvgIcon`, `renderSvg`, `assets/svg`, or pasting raw `<svg>` markup.
---

## SVG Icons

Icons are rendered through the custom `{renderSvg}` Latte macro registered by `App\UI\Extension\Latte\SvgLatteExtension` (config: `latteMacros.svgDir: %appDir%/../assets/svg`).

### Rules

- **Never** put raw `<svg>...</svg>` markup into Latte templates, PHP, or datagrid column callbacks.
- Every icon has:
	1. An SVG file under `assets/svg/` (kebab-case filename).
	2. A constant in the `App\UI\Icon\SvgIcon` enum whose value matches the filename (without `.svg`).
- Use exactly one pattern in Latte:
	```
	{renderSvg App\UI\Icon\SvgIcon::INCOME_SIGN->value, ['class' => 'w-5 h-5 text-green-600']}
	```
- Second argument is an array of HTML attributes merged into the root `<svg>` element.

### Adding a new icon

1. Save the SVG under `assets/svg/<kebab-name>.svg`. Clean it: strip `width`/`height` (size via Tailwind `w-*`/`h-*`), set `fill="currentColor"` when you want Tailwind text color to apply.
2. Add a case to `App\UI\Icon\SvgIcon` matching the filename.
3. Reference it via `SvgIcon::NAME->value` in Latte/PHP.

### Datagrid actions

`DatagridAction` accepts a `SvgIcon` — prefer the enum over hardcoded markup. See `ui-datagrid`.
