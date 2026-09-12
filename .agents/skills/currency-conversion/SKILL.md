---
name: currency-conversion
description: Maintain historical currency conversion and exchange-rate downloads. Use when changing amount conversions, rate sources, or GBP/GBp price normalization.
---

## Currency Conversions

Handles historical exchange rates and conversions between supported currencies.

### Supported currencies

`App\Currency\CurrencyEnum`: **USD, EUR, CZK, GBP, PLN, NOK**.

### Entities & sources

- **`CurrencyConversion`** (+ `CurrencyConversionRepository`) — historical exchange rate record.
- **`CurrencySourceEnum`** — which source produced the rate (CNB, ECB, …).
- **`MissingCurrencyPairException`** — `convertSimpleValue()` wraps a missing repository result in this exception; the object-conversion methods currently propagate Doctrine `NoResultException`.

### Facade

- **`CurrencyConversionFacade`** — the single entry point for converting amounts. Use it from facades, filters, and services. Do NOT query `CurrencyConversionRepository` directly from UI code.

### Downloaders (`src/Currency/Download/`)

- Interface **`CurrencyConversionDownloadFacade`**; implementations:
	- `CNBCurrencyConversionDownloadFacade` — Czech National Bank.
	- `ECBCurrencyConversionDownloadFacade` — European Central Bank.
- **`CurrencyConversionDownloadInverseRateHelper`** — derives the inverse pair when the source publishes only one direction.
- CLI: `CurrencyConversionDownloadCommand` (uses `typed(CurrencyConversionDownloadFacade)` to run all implementations).
- Monitoring counters: `cnb_currency_downloaded_count`, `ecb_currency_downloaded_count` (see `notifications-discord` / monitoring config).

### GBP / GBp handling

For source values quoted in **pence (GBp)**, use the established `CurrencyEnum::processFromWeb()` path, which calls `GBPCurrencyHelper::formatGBpToGBP()`. The helper divides the numeric value by `100`; it does not return a currency enum. Do not apply it again to amounts already stored in pounds.

### Latte

Convert & format in templates via filters (see `ui-latte-filters`):
- Numeric amount: `{$amount|currencyConvert:CurrencyEnum::USD:CurrencyEnum::CZK|currency:CurrencyEnum::CZK}`.
- Summary: `{$summary|summaryPriceConvert:CurrencyEnum::CZK}` (converts and formats).

### Adding a new exchange-rate source

1. Implement `CurrencyConversionDownloadFacade` under `src/Currency/Download/<Source>/`.
2. Register it in `config/config.neon` — it will be auto-picked by `typed(...)` in `CurrencyConversionDownloadCommand`.
3. Map its monitoring counter in `monitoring.monitoringUptimeMonitorMapping` if tracked.
4. Use `Nette\Utils\Json` (or the source-native format) for parsing; validate with `App\Utils\TypeValidator`.

### Rules

- Convert application amounts through `CurrencyConversionFacade` so rate selection and historical-date behavior stay consistent.
- Preserve the caller's missing-rate behavior; catch only where a meaningful fallback or domain error belongs. Do not silently substitute a zero or current rate for missing historical data.
- Exchange rates are date-sensitive — pass the correct `Mistrfilda\Datetime\Types\ImmutableDateTime` when converting historical positions.
