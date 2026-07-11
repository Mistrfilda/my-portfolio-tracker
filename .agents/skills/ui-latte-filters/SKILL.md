---
name: ui-latte-filters
description: Invoke before formatting currency, prices, percentages, dates or other values in Latte templates. Provides the catalog of project-specific Latte filters registered in `config.neon` under `latte.latteFactory`. Use when the user asks how to format money, a price diff, a percentage, a date/time, a duration, or to convert between currencies in a template.
---

## Project Latte Filters

All filters are registered in `config/config.neon` under `latte.latteFactory -> setup -> addFilter(...)`. Corresponding classes live in `src/UI/Filter/`.

### Currency & price

- `{$x|currency}` — format a `Money`-like value with currency code (`CurrencyFilter`).
- `{$x|compactCurrency}` — short form (1.2k, 3.4M) (`CompactCurrencyFilter`).
- `{$x|assetPriceFormat}` — format `AssetPrice` / `AssetPriceEmbeddable` (`AssetPriceFilter`).
- `{$x|summaryPriceFormat}` — format `SummaryPrice` across currencies (`SummaryPriceFilter`).
- `{$x|summaryPriceConvert:CurrencyEnum::CZK}` — convert + format `SummaryPrice` (`SummaryPriceConvertFilter`).
- `{$x|currencyConvert:CurrencyEnum::CZK}` — convert one `AssetPrice` (`CurrencyConversionFilter`).
- `{$x|nullableCurrencyConvert:CurrencyEnum::CZK}` — nullable variant.
- `{$x|expensePriceFormat}` — cash/expense formatting (`CashPriceFilter`).

### Numbers

- `{$x|percentage}` — percentage with fixed decimals (`PercentageFilter`).
- `{$x|ruleOfThree:$total}` — compute & render `part/total` as % (`RuleOfThreeFilter::getPercentage`).

### Date & time

- `{$x|dateFormat}` — date-only (`DateFormatFilter`).
- `{$x|datetimeFormat}` — date + time (`DatetimeFormatFilter`).
- `{$x|secondsFormat}` — human duration from seconds (`SecondsFormatFilter`).

### Misc

- `{$x|nullablestring}` — `null` → empty string / placeholder (`NullableStringFilter`).
- `{$x|flashMessageColor}` — maps `FlashMessageType` to Tailwind color (`FlashMessageColorFilter`).

### Rules

- Never format currency/dates manually in PHP for templates — always use a filter so output stays consistent across the app.
- When converting across currencies in templates, use a `*Convert*` filter so the active rate from `CurrencyConversionFacade` is used.
- To add a new filter: implement the class under `src/UI/Filter/`, register it in `services:` of `config.neon`, then add the `addFilter(...)` line in `latte.latteFactory -> setup`.
- Filter classes follow the pattern: public method (usually `format(...)`) taking the value and returning a string.
