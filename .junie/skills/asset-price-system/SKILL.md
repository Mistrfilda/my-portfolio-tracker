---
name: asset-price-system
description: Invoke before working with any asset (Stock, Portu, Crypto) price entity, price record, summary price, or price renderer. Provides the base contracts in `src/Asset/` shared by all asset types. Use when implementing a new asset type, adding a new `*PriceRecord` entity, aggregating prices across currencies, computing price diffs, or rendering prices in templates. Also trigger when the user mentions `AssetPrice`, `AssetPriceEmbeddable`, `SummaryPrice`, `PriceDiff`, or `AssetPriceRenderer`.
---

## Asset Price System — Base Contracts

Foundation for all asset types (`AssetTypeEnum`: `Stock`, `Portu`, `Crypto`). Lives in `src/Asset/`.

### Core interfaces & entities

- **`Asset`** — base entity interface implemented by `StockAsset`, `PortuAsset`, `CryptoAsset`.
- **`AssetRepository`** — base repository contract for all asset types.
- **`AssetPosition`** / **`AssetClosedPosition`** / **`AssetPortfolio`** — position-level contracts.
- **`AssetPrice`** — represents a single price value (amount + currency).
- **`AssetPriceEmbeddable`** — Doctrine `#[Embeddable]` carrying price + `CurrencyEnum`. Use inside entities instead of two separate columns.
- **`AssetPriceRecord`** (interface) — contract for price history entries; each asset type has its own entity (`StockAssetPriceRecord`, `PortuAssetPriceRecord`, `CryptoAssetPriceRecord`).
- **`AssetPriceEnum`** — price display modes.

### Services & facades

- **`AssetPriceFacade`** (interface) — one implementation per asset type.
- **`AssetPriceService`** — shared price operations.
- **`AssetPriceSourceProvider`** — resolves which downloader/source to use.
- **`AssetPriceFactory`** — constructs `AssetPrice` instances.
- **`AssetPriceRenderer`** — renders price values (HTML + formatting).
- **`AssetPriceSummaryFacade`** — aggregated price across currencies (uses typed service locator `typed(AssetPriceFacade)`).
- **`SummaryPrice`** + **`SummaryPriceService`** — accumulate amounts grouped by currency.
- **`PriceDiff`** — computes change between two prices (absolute + percentage).
- **`JsonDataFolderService`** (`src/Asset/Price/Downloader/`) — reads JSON price files from `%puppeter.folder%`.

### Rules

- Always store price as `AssetPriceEmbeddable`, never as raw decimal + string currency.
- When adding a new asset type, implement `Asset`, `AssetRepository`, `AssetPriceRecord`, `AssetPriceFacade` and register them as services; autowiring via `typed(...)` wires them into shared facades.
- Aggregate multi-currency totals through `SummaryPrice` / `SummaryPriceService`, never sum raw amounts across currencies.
- Render prices in Latte via the `summaryPriceFormat` / `assetPriceFormat` filters — see the `ui-latte-filters` skill.
- For concrete price downloaders, see the `asset-price-downloaders` skill.
