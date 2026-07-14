---
name: asset-position-system
description: Invoke before adding or modifying Stock, Crypto, or Portu positions, closed positions, position facades or repositories, broker-currency amounts, portfolio value aggregation, or position UI. Covers the shared `AssetPosition` and `AssetClosedPosition` contracts plus concrete flows in `src/Stock/Position/`, `src/Crypto/Position/`, and `src/Portu/Position/`. Also trigger for position closing, invested/current value calculations, or cross-asset position behavior.
---

# Asset Position System

Preserve the shared position contracts without erasing the different semantics of Stock, Crypto, and Portu positions.

## Shared contracts

- `src/Asset/Position/AssetPosition.php` defines asset, owner, piece count, invested/current amounts, per-piece price, currency, order date, and broker-currency amount.
- `src/Asset/Position/AssetClosedPosition.php` defines the linked position, closing amounts, currency, and closing date.
- `SummaryPriceService` aggregates arrays of positions. Never sum raw amounts across currencies.
- Use `asset-price-system` for `AssetPrice`, `AssetPriceEmbeddable`, `SummaryPrice`, and `PriceDiff` behavior.

## Concrete semantics

### Stock

- `StockPosition` uses an integer piece count and the stock asset's trading currency.
- Open current value uses the asset's current price; a closed position uses its closing price.
- `StockPositionFacade` implements `AssetPriceFacade` and updates portfolio-goal processing after established create/update flows.
- `StockClosedPositionFacade` includes broker-currency, CZK conversion, dividends, and closed-position summaries.

### Crypto

- `CryptoPosition` uses a float piece count.
- Open and closed current-value behavior mirrors Stock, but keep Crypto-specific entities, repositories, and side effects separate.
- `CryptoClosedPositionFacade` aggregates broker-currency and CZK results without stock-dividend behavior.

### Portu

- `PortuPosition` represents a managed portfolio, not exchange-traded pieces: `getOrderPiecesCount()` returns `1`.
- Invested and current amounts come from embedded `totalInvestedToThisDate` and `currentValue` values.
- Price history belongs to `PortuAssetPriceRecord`; do not force Stock/Crypto closing semantics onto Portu.

## Create and update workflow

1. Resolve the owning asset and current admin through the established facade dependencies.
2. Construct or update the concrete entity using `AssetPriceEmbeddable` values and `DatetimeFactory` timestamps.
3. Persist, flush, and refresh according to the nearest existing facade pattern.
4. Preserve existing logging and JobRequest side effects; do not generalize them across asset types without a requested domain change.

Use the owning module's repository. Do not fetch repositories through `EntityManager::getRepository()`.

## Closing positions

- Create the concrete closed-position entity, link it back through `closePosition()`, and persist the owning relation atomically.
- Keep purchase and closing dates distinct.
- Convert invested broker value using the order date and closing value using the closing date when calculating historical CZK profit.
- Do not treat an open position as closed based only on a nullable price; use the concrete closed-position relation.

## Currency and calculation rules

- Preserve trading-currency and broker-currency amounts separately.
- Respect `differentBrokerAmount`; do not derive broker values silently when the stored value is authoritative.
- Build totals with `SummaryPrice` / `SummaryPriceService` and differences with `PriceDiff`.
- Convert through `CurrencyConversionFacade`, using the economically correct historical date.
- Keep Stock dividend inclusion explicit; Crypto and Portu must not inherit it accidentally.

## Cross-module checklist

- For mapping or relation changes, update the concrete entity, repository/facade calls, forms, migrations, and tests.
- For calculation changes, inspect dashboard and statistic consumers in `src/Dashboard/` and `src/Statistic/`.
- For UI changes, use `ui-base-presenters-templates`, `latte-templates`, `ui-forms-admin`, and `ui-datagrid` as applicable.
- For currency or shared price behavior, use `currency-conversion` and `asset-price-system`.

## Testing

- Prefer unit tests for entity calculations, facade orchestration, closing behavior, and currency-date selection.
- Use integration tests only for repository queries and persistence mappings.
- Cover Stock integer pieces, Crypto fractional pieces, Portu's single synthetic unit, broker-currency differences, open versus closed current values, and goal-update side effects when touched.
- Never use real RabbitMQ queues or external HTTP APIs.

## Related skills

- `asset-price-system`
- `currency-conversion`
- `doctrine-migrations`
- `stock-asset`
- `testing-conventions`
