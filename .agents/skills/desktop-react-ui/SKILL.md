---
name: desktop-react-ui
description: Create or change React, shadcn/ui, Tailwind styling, navigation, and appearance in the desktop renderer. Does not apply to Nette/Latte web UI.
---

# Desktop React UI

The renderer lives in [clients/desktop/src/renderer/src](../../../clients/desktop/src/renderer/src/). It uses React, TypeScript, Tailwind 4, shadcn/ui's Radix-based new-york components, and Lucide icons.

## Locate the change

- `app.tsx` wires providers and the memory router; `components/app-layout.tsx` owns the window layout and navigation.
- `pages/` contains route-level screens. Add feature folders when a real feature needs them.
- `components/ui/` contains shadcn component source owned by this repository. Prefer its existing variants before making a new wrapper.
- `styles.css` defines shared semantic colors, radii, and light/dark themes. Use these tokens instead of copying styles between screens.
- `lib/theme.ts` and `components/theme-provider.tsx` own appearance. Local storage is for appearance only, never tokens.
- `components.json` at the desktop package root defines shadcn aliases and the component registry style. Run component additions from that package, then review generated changes.

## UI boundaries

- Use `@/` imports for renderer code and `lucide-react` for icons. Keep accessible names on icon-only actions.
- Use React Router for in-app navigation. The packaged page URL stays fixed so IPC sender validation continues to work.
- Import native operations only through the typed preload bridge. Follow [desktop-electron](../desktop-electron/SKILL.md) when adding an operation.
- Build actual states for loading, empty data, and errors when the corresponding feature arrives. Do not invent portfolio values or add inactive feature controls to the skeleton.
- Preserve macOS window dragging and interactive no-drag regions when changing the title area.
- Keep shadcn components on the chosen Radix foundation; do not mix in Base UI variants without an intentional migration.

## Inspection

Use `npm --prefix clients/desktop run dev:renderer` for a browser-only preview on `http://127.0.0.1:5173/`. Reuse an existing listener. Inspect it with standalone `mcp__playwright__browser_*` tools in their isolated Chromium, as required by root instructions.

The preview has no Electron bridge. Verify native behavior with the Electron smoke test instead of simulating a working native bridge in production code. Follow the root [validation matrix](../../../AGENTS.md#validation-matrix).
