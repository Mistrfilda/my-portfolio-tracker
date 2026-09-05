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
- **`AssetPriceSourceProvider`** — interface for generating JSON price-source request files; downloader selection belongs to the concrete asset flow.
- **`AssetPriceFactory`** — constructs `AssetPrice` instances.
- **`AssetPriceRenderer`** — renders price values (HTML + formatting).
- **`AssetPriceSummaryFacade`** — aggregated price across currencies (uses typed service locator `typed(AssetPriceFacade)`).
- **`SummaryPrice`** holds a total in one currency and rejects additions in another. **`SummaryPriceService`** converts position amounts to the requested currency before accumulating them.
- **`PriceDiff`** — computes change between two prices (absolute + percentage).
- **`JsonDataFolderService`** (`src/Asset/Price/Downloader/`) — reads JSON price files from `%puppeter.folder%`.

### Rules

- Use `AssetPriceEmbeddable` for embedded amount-and-currency values where the shared asset contract expects it. Preserve established standalone price-record mappings.
- When adding a new asset type, implement `Asset`, `AssetRepository`, `AssetPriceRecord`, and `AssetPriceFacade`. Map entities through Doctrine and register the repository/facade services; `typed(...)` collects facade implementations, not entity instances.
- Aggregate multi-currency totals through `SummaryPrice` / `SummaryPriceService`, never sum raw amounts across currencies.
- Render prices in Latte via the `summaryPriceFormat` / `assetPriceFormat` filters — see the `ui-latte-filters` skill.
- For concrete price downloaders, see the `asset-price-downloaders` skill.
