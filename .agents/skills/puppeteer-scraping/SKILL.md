---
name: puppeteer-scraping
description: Maintain the project's Puppeteer scrapers and their JSON handoff to PHP. Use when adding or debugging a scraper, changing output contracts, or wiring a scraper into a downloader.
---

## Puppeteer Scraping

Headless-browser scrapers written in Node.js. Output JSON files consumed by PHP `*JsonDownloader` facades.

### Layout (`puppeter/`)

- `PuppeteerScraperBase.js` — base class with browser setup, login helpers, retries. Every scraper extends it.
- Scrapers (class files, CamelCase):
	- `PricesScraper.js`, `DividendsScraper.js`, `FinancialsScraper.js`, `KeyStatisticsScraper.js`, `AnalystInsightsScraper.js`, `StockAssetIndustryScapper.js`, `CryptoScapper.js`.
- Entrypoint scripts (lowercase, invoked by CLI/cron): `prices.js`, `dividends.js`, `financials.js`, `analyst.js`, `stockAssetIndustry.js`, `crypto.js`.
- `files/` — output directory (same as `%puppeter.folder%` on the PHP side).

### Pipeline

1. Cron/CLI runs `node puppeter/<entry>.js`.
2. Entry script instantiates the scraper class; `run()` reads `puppeter/files/requests/<name>.json` and writes `puppeter/files/results/<name>.json` (with temporary result files for resume behavior).
3. PHP CLI command (e.g. `JsonDataDownloaderCommand`, `StockAssetJsonDividendDownloaderCommand`, `CryptoAssetJsonDownloaderCommand`) reads the JSON via `JsonDataFolderService` (`%puppeter.folder%`) and saves records into the DB.
4. URL sources for the JSON-based stock pipeline are centralized in `JsonWebDataService` (`stockAssetPriceUrl`, `stockAssetDividendPriceUrl`, `financialsDataUrl`, `keyStatisticsDataUrl`, `analystInsightUrl`, `stockAssetIndustryUrl`).

### Adding a new scraper

1. Add a class `puppeter/<Name>Scraper.js` extending `PuppeteerScraperBase`.
2. Add an entry script `puppeter/<name>.js` that instantiates and runs it.
3. Wire the output into the appropriate PHP importer, adding a matching `*JsonDownloader` + CLI command when the flow needs one (see [asset-price-downloaders](../asset-price-downloaders/SKILL.md) for price imports).
4. Register any new public parameters (URLs, thresholds) in `config/config.neon`.
5. Preserve the configured secret-injection path; never read or edit local secret configuration files to obtain login cookies or credentials.

### Rules

- Keep JSON field names and value types stable for PHP consumers, which use `Nette\Utils\Json` + `TypeValidator`.
- Throw in English; return non-zero exit code on failure so cron marks it failed.
- Respect the PHP-side `updateStockAssetHoursThreshold` — do not schedule scrapes more aggressively than the downloader will accept.
- Keep credentials, cookies, and private/token-bearing URLs out of git. Public source URL templates belong in the existing public configuration.
- JS files use the existing Node version (v24 recommended) — no TypeScript here; TS lives in `assets/`.
