---
name: ui-datagrid
description: Invoke before creating or modifying a Datagrid (the project's custom grid, NOT ublaboo). Provides the API of `src/UI/Control/Datagrid/` – Datagrid, DatagridFactory, DoctrineDataSource, columns, filters, actions, pagination, sort. Use when adding a `*GridFactory`, adding columns, filters, row actions, pagination or sort to an admin list view.
---

## UI Datagrid — Custom Project Datagrid

Project's own datagrid under `src/UI/Control/Datagrid/`. Do **not** use ublaboo/datagrid.

### Core

- **`Datagrid`** — the control itself (extends `BaseControl`).
- **`DatagridFactory`** — factory registered as a service; inject it into your `*GridFactory`.
- **`DatagridTemplate`** + `datagrid.latte` — default template.

### Data source

- **`Datasource/DoctrineDataSource`** — implements `IDataSource` over a Doctrine `QueryBuilder`. Pass a prepared QB from the repository.
- Never fetch the full collection manually — the data source handles pagination/sort SQL.

### Columns (`Column/`)

All implement `IColumn`:

- `ColumnText` — plain/escaped text or callback.
- `ColumnDatetime` — uses `DatetimeFormatFilter` / `DateFormatFilter`.
- `ColumnBadge` — single colored badge (uses `TailwindColorConstant`).
- `ColumnBadgeArray` — list of badges.

### Filters (`Filter/`)

- `FilterForm` — top-level filter form attached to the grid.
- `FilterText` / `FilterValue` — input types.
- Filters are applied to the underlying `QueryBuilder` by `DoctrineDataSource`.

### Actions (`Action/`)

- `DatagridAction` — per-row action (link + icon via `SvgIcon`). Use `renderSvg` in the action's icon slot — see `ui-svg-icons`.

### Pagination & Sort

- `Pagination/Pagination` + `PaginationService`.
- `Sort/Sort` + `SortService` + `SortDirectionEnum`.

### Conventions

- Each grid lives in a `*GridFactory` class in the module's `UI/` folder (e.g. `StockAssetGridFactory`) and is registered in `config.neon` under `services:`.
- Factory method signature: `public function create(...): Datagrid` — returns a configured `Datagrid`.
- Pass business data only via the repository's QueryBuilder; format/derive in column callbacks.
- For template variables of the hosting presenter/control, always use a Template class — see `ui-base-presenters-templates`.
