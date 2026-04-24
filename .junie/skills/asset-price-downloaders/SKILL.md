---
name: asset-price-downloaders
description: Invoke before adding or modifying an asset price/dividend/valuation downloader. Provides the decision tree between Json, Puppeteer, Web, PSE API and TwelveData API downloaders plus the config.neon conventions. Use when the user asks to add a new price source, extend a `*DownloaderFacade` / `*DownloaderCommand`, wire new parameters in `config.neon`, or integrate a new market data provider.
---

## Asset Price Downloaders

Concrete price/dividend/valuation data sources used by each asset module. The interface contract is `App\Asset\Price\AssetPriceDownloader` — see the `asset-price-system` skill.

### Available downloader types

Pick the first that fits:

1. **`*JsonDownloader`** — reads a JSON file produced by a Puppeteer scraper in `%puppeter.folder%`. Preferred when data comes from headless-browser scraping. Flow: Puppeteer script → JSON file → `JsonDataFolderService` → `*JsonDownloader`. Examples: `StockAssetPriceJsonDownloader`, `CryptoAssetJsonDownloaderFacade`, `StockAssetJsonDividendDownloader`.
2. **`*TwelveDataApiDownloader`** — official Twelve Data REST API. Needs `%twelveData.apiKey%`; respects `%twelveData.updateStockAssetHoursThreshold%`. Use for supported tickers when API quota allows.
3. **`*PseApiDownloader`** — Prague Stock Exchange API for PSE-listed tickers. Uses `%pse.*%` parameters (requests array with `url`, `pricePositionTag`, `tableTdsCount`).
4. **`*WebDownloader`** — authenticated HTML scraping (uses `%webStockPriceDownloader.*%`, including `cookie` and `requestHost`). Use only when nothing else works.
5. **`*PuppeteerDownloader`** — legacy direct Puppeteer invocation. Prefer the JSON flow (#1) for new code.

### Config conventions (`config/config.neon`)

- `twelveData.apiKey`, `twelveData.updateStockAssetHoursThreshold`
- `pse.updateStockAssetHoursThreshold`, `pse.verifySsl`, `pse.requests`
- `stockDividend.webStockAssetDividendDownloaderUrl`
- `webStockPriceDownloader.{url,requestHost,verifySsl,cookie,updateStockAssetHoursThreshold}`
- `stockValuation.{financialsDataUrl,keyStatisticsDataUrl,analystInsightUrl,stockAssetIndustryUrl}`
- `crypto.tableUrl`
- `puppeter.folder` — root folder for Puppeteer-generated JSON files.

Secrets (API keys, cookies, webhook URLs) live in `config/config.local.neon` or `docker/config-docker.local.neon`, never in committed files.

### Adding a new downloader

1. Implement the appropriate `*DownloaderFacade` under `src/<Module>/Price/Downloader/<Source>/` (or `src/<Module>/Dividend/Downloader/<Source>/`).
2. Create a matching `*DownloaderCommand` CLI command (Contributte Console).
3. Register both in `config/config.neon` under `services:`; pass parameters via `%...%` placeholders.
4. If JSON-based, add the Puppeteer script under `puppeter/` — see the `puppeteer-scraping` skill.
5. If multiple implementations share a facade interface, inject via `typed(App\...\DownloaderFacade)` and dispatch through `AssetPriceSourceProvider` / enum (`StockAssetPriceDownloaderEnum`).

### Rules

- Respect `updateStockAssetHoursThreshold` — never re-fetch more often than the threshold allows.
- Always use `Nette\Utils\Json` for (de)serialization.
- Use `App\Utils\TypeValidator` for scalar validation of downloaded payloads.
- Throw exceptions in English; log via Monolog (errors are forwarded to Discord by `MonologDiscordHandler`).
- Never hardcode URLs/keys — always read from `%parameters%`.
