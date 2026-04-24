---
name: puppeteer-scraping
description: Invoke before creating or modifying a Puppeteer scraper in `puppeter/`. Provides the Node.js scraping pipeline – `PuppeteerScraperBase`, concrete scrapers, and how they integrate with PHP `*JsonDownloader` facades through JSON files in `%puppeter.folder%`. Use when the user asks to add a new scraper, debug a failing scrape, or wire scraped JSON into a PHP downloader.
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
2. Entry script instantiates the scraper class, runs it, and writes JSON into `puppeter/files/<name>.json`.
3. PHP CLI command (e.g. `JsonDataDownloaderCommand`, `StockAssetJsonDividendDownloaderCommand`, `CryptoAssetJsonDownloaderCommand`) reads the JSON via `JsonDataFolderService` (`%puppeter.folder%`) and saves records into the DB.
4. URL sources for the JSON-based stock pipeline are centralized in `JsonWebDataService` (`stockAssetPriceUrl`, `stockAssetDividendPriceUrl`, `financialsDataUrl`, `keyStatisticsDataUrl`, `analystInsightUrl`, `stockAssetIndustryUrl`).

### Adding a new scraper

1. Add a class `puppeter/<Name>Scraper.js` extending `PuppeteerScraperBase`.
2. Add an entry script `puppeter/<name>.js` that instantiates and runs it.
3. On the PHP side, add a matching `*JsonDownloader` + CLI command (see `asset-price-downloaders` skill).
4. Register parameters (URLs, thresholds) in `config/config.neon`.
5. Secrets (login cookies, credentials) live in `config/config.local.neon` / `docker/config-docker.local.neon`.

### Rules

- Output JSON must be stable (sorted keys, explicit fields) — PHP side uses `Nette\Utils\Json` + `TypeValidator`.
- Throw in English; return non-zero exit code on failure so cron marks it failed.
- Respect the PHP-side `updateStockAssetHoursThreshold` — do not schedule scrapes more aggressively than the downloader will accept.
- Keep credentials and URLs out of git.
- JS files use the existing Node version (v24 recommended) — no TypeScript here; TS lives in `assets/`.
