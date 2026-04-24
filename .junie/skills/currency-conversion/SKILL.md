---
name: currency-conversion
description: Invoke before working with currencies, exchange rates, or currency conversion in this project. Provides `src/Currency/` components – `CurrencyEnum`, `CurrencyConversion`, CNB/ECB downloaders, `CurrencyConversionFacade`, and `GBPCurrencyHelper` (GBp -> GBP). Use when converting between currencies, adding a new exchange-rate source, or handling London Stock Exchange pence prices.
---

## Currency Conversions

Handles historical exchange rates and conversions between supported currencies.

### Supported currencies

`App\Currency\CurrencyEnum`: **USD, EUR, CZK, GBP, PLN, NOK**.

### Entities & sources

- **`CurrencyConversion`** (+ `CurrencyConversionRepository`) — historical exchange rate record.
- **`CurrencySourceEnum`** — which source produced the rate (CNB, ECB, …).
- **`MissingCurrencyPairException`** — thrown when no conversion exists for a requested pair/date.

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

LSE quotes in **pence (GBp)**, not pounds. Always route such values through `GBPCurrencyHelper` before storing/converting — it scales by `1/100` and returns GBP.

### Latte

Convert & format in templates via filters (see `ui-latte-filters`):
- `{$x|currencyConvert:CurrencyEnum::CZK}`
- `{$x|summaryPriceConvert:CurrencyEnum::CZK}`

### Adding a new exchange-rate source

1. Implement `CurrencyConversionDownloadFacade` under `src/Currency/Download/<Source>/`.
2. Register it in `config/config.neon` — it will be auto-picked by `typed(...)` in `CurrencyConversionDownloadCommand`.
3. Map its monitoring counter in `monitoring.monitoringUptimeMonitorMapping` if tracked.
4. Use `Nette\Utils\Json` (or the source-native format) for parsing; validate with `App\Utils\TypeValidator`.

### Rules

- Never convert by multiplying raw numbers — always go through `CurrencyConversionFacade`.
- Never assume a rate exists; catch `MissingCurrencyPairException` and fall back gracefully (or surface an error).
- Exchange rates are date-sensitive — pass the correct `DateTimeImmutable` when converting historical positions.
