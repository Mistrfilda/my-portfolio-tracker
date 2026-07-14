---
name: asset-price-downloaders
description: Invoke before adding, modifying, or debugging asset price downloaders, price source generation, downloader commands, or imported price JSON. Covers the shared downloader contracts in `src/Asset/Price/`, Stock downloaders for JSON/Puppeteer, Twelve Data, PSE, and Web, plus Crypto JSON and Twelve Data flows. Also trigger for `AssetPriceDownloader`, `AssetPriceSourceProvider`, `JsonDataFolderService`, `StockAssetPriceDownloaderEnum`, `prices.json`, or price downloader scheduling and tests.
---

# Asset Price Downloaders

Keep downloader changes inside the owning asset module while preserving the shared price-record contract.

## Shared contracts

- Implement direct downloaders through `App\Asset\Price\AssetPriceDownloader`.
- Implement source-file generators through `App\Asset\Price\AssetPriceSourceProvider`.
- Use `App\Asset\Price\Downloader\JsonDataFolderService` for request, result, and parsed-result folders.
- Return concrete `AssetPriceRecord` entities and update the owning asset's current price through its established method.
- Read `asset-price-system` when changing price entities, current-price storage, summaries, or rendering.

## Concrete flows

### Stock

- `src/Stock/Price/Downloader/TwelveData/` downloads ticker prices through PSR-7/PSR-18 helpers.
- `src/Stock/Price/Downloader/Pse/` parses Prague Stock Exchange HTML and requires a configured ISIN.
- `src/Stock/Price/Downloader/Web/` handles the direct web endpoint.
- `src/Stock/Price/Downloader/Json/` generates Puppeteer request files and imports `prices.json`.
- `StockAssetPriceDownloaderEnum` selects `PSE`, `TWELVE_DATA`, or `WEB_SCRAP`; keep the enum, admin form, repository selection, and downloader behavior aligned.

### Crypto

- `src/Crypto/Price/Downloader/TwelveData/` handles direct API downloads.
- `src/Crypto/Price/Downloader/Json/` generates scraper input and imports scraper results.

### Portu

Portu prices are maintained through position/price-record flows rather than these external downloaders. Use `asset-position-system` for Portu position value changes.

## Direct-download workflow

1. Select eligible assets through the existing repository method and update threshold.
2. Build requests with `Psr7RequestFactory` and send them through `Psr18ClientFactory`.
3. Parse with `Nette\Utils\Json` or the source-specific DOM parser; validate new scalar inputs with `App\Utils\TypeValidator` where needed.
4. Find the existing record for asset + date; update it or create the concrete `*PriceRecord` entity.
5. Update the asset's current price, flush once, and preserve the existing `SystemValueFacade` monitoring timestamp.

Do not create duplicate same-day records. Preserve downloader-specific exceptions and logging instead of turning malformed responses into zero prices.

## JSON and Puppeteer workflow

1. A `*DataSourceProviderFacade` writes request JSON under `JsonDataFolderService::REQUESTS_FOLDER`.
2. A script in `puppeter/` reads the request and writes stable JSON under the results folder.
3. A PHP `*JsonDownloaderFacade` imports the result, creates or updates records, copies the processed file to `PARSED_RESULTS_FOLDER`, and deletes the active result.

Keep filenames shared through constants on the source provider. Use `Nette\Utils\FileSystem` and `Nette\Utils\Json`; do not duplicate folder strings or serialize with raw `json_encode()`.

## Currency and time rules

- Normalize source prices through the asset currency's existing web-processing path; this is required for GBp/GBP behavior.
- Use `DatetimeFactory` for today/current timestamps.
- Respect `updateStockAssetHoursThreshold`; do not add another scheduling or retry layer unless requested.
- Never hardcode API keys, cookies, or private URLs, and never inspect local secret config files.

## Commands and configuration

- Register services and public defaults in `config/config.neon`.
- Keep operational command sequences in `composer.json` aligned with the downloader pipeline.
- Stock JSON pipeline: `composer stock-assets-downloaders`.
- Crypto direct pipeline: `composer crypto-download-data`.

## Testing

- Use local JSON/HTML fixtures or synthetic PSR responses.
- Mock HTTP clients and never call Twelve Data, PSE, scraper targets, or other external services.
- Cover missing files, invalid response shapes, duplicate same-day records, currency normalization, file archival, and asset-current-price updates when those paths change.
- Use `testing-conventions`; use `doctrine-migrations` only when a mapped entity changes.

## Related skills

- `asset-price-system`
- `currency-conversion`
- `puppeteer-scraping`
- `stock-asset`
- `testing-conventions`
