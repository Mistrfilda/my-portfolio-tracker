---
name: ui-latte-filters
description: Format money, prices, percentages, dates, and durations or convert currencies with project Latte filters.
---

## Project Latte Filters

All filters are registered in `config/config.neon` under `latte.latteFactory -> setup -> addFilter(...)`. Corresponding classes live in `src/UI/Filter/`.

### Currency & price

- `{$amount|currency:CurrencyEnum::CZK}` — format a numeric `float|int` with the required currency (`CurrencyFilter`).
- `{$amount|compactCurrency:CurrencyEnum::CZK}` — compact numeric form with `tis`, `mil`, or `mld` suffixes; currency is optional (`CompactCurrencyFilter`).
- `{$x|assetPriceFormat}` — format `AssetPrice` / `AssetPriceEmbeddable` (`AssetPriceFilter`).
- `{$x|summaryPriceFormat}` — format one `SummaryPrice` in its own currency (`SummaryPriceFilter`).
- `{$x|summaryPriceConvert:CurrencyEnum::CZK}` — convert + format `SummaryPrice` (`SummaryPriceConvertFilter`).
- `{$amount|currencyConvert:CurrencyEnum::USD:CurrencyEnum::CZK}` — convert a numeric amount from USD to CZK; returns a `float`, so chain `|currency:CurrencyEnum::CZK` when formatting (`CurrencyConversionFilter`).
- `{$amount|nullableCurrencyConvert:CurrencyEnum::USD:CurrencyEnum::CZK}` — convert and format a numeric amount, or return `N/A` for a missing exchange rate; the input itself is not nullable (`CurrencyNullableConversionFilter`).
- `{$x|expensePriceFormat}` — cash/expense formatting (`CashPriceFilter`).

### Numbers

- `{$x|percentage}` — format percentage points with two decimals; `5` renders as `5.00 %` (`PercentageFilter`).
- `{$part|ruleOfThree:$total|percentage}` — compute `part * 100 / total`, then format it; the `ruleOfThree` filter alone returns a number. Ensure the total is nonzero; the current implementation only guards a zero first argument (`RuleOfThreeFilter::getPercentage`).

### Date & time

- `{$x|dateFormat}` — date-only (`DateFormatFilter`).
- `{$x|datetimeFormat}` — date + time (`DatetimeFormatFilter`).
- `{$seconds|secondsFormat}` — integer minutes rounded down; `FORMAT_MINUTES_UP` selects rounding up (`SecondsFormatFilter`).

### Misc

- `{$x|nullablestring}` — `null` → `----` (`NullableStringFilter`).
- `{$x|flashMessageColor}` — maps `FlashMessageType` to Tailwind color (`FlashMessageColorFilter`).

### Rules

- Never format currency/dates manually in PHP for templates — always use a filter so output stays consistent across the app.
- When converting across currencies in templates, use a `*Convert*` filter so the active rate from `CurrencyConversionFacade` is used.
- To add a new filter: implement the class under `src/UI/Filter/`, register it in `services:` of `config.neon`, then add the `addFilter(...)` line in `latte.latteFactory -> setup`.
- Check each filter's signature: formatting filters return strings, while `currencyConvert`, `ruleOfThree`, and `secondsFormat` return numbers. Conversion filters use current rates; calculate historical conversions in the owning service before rendering.
