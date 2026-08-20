<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Watchlist;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAssetWatchlistTest extends TestCase
{

	public function testStoresAndUpdatesStaticWatchlistData(): void
	{
		$createdAt = new ImmutableDateTime('2026-08-20 09:00:00');
		$updatedAt = new ImmutableDateTime('2026-08-20 10:00:00');
		$stockAssetWatchlist = new StockAssetWatchlist(
			'Apple Inc.',
			'AAPL',
			180.0,
			CurrencyEnum::USD,
			$createdAt,
		);

		self::assertSame('Apple Inc.', $stockAssetWatchlist->getName());
		self::assertSame('AAPL', $stockAssetWatchlist->getTicker());
		self::assertSame(180.0, $stockAssetWatchlist->getRecommendedEntryPrice());
		self::assertSame(CurrencyEnum::USD, $stockAssetWatchlist->getCurrency());

		$stockAssetWatchlist->update('Microsoft Corporation', 'MSFT', null, null, $updatedAt);

		self::assertSame('Microsoft Corporation', $stockAssetWatchlist->getName());
		self::assertSame('MSFT', $stockAssetWatchlist->getTicker());
		self::assertNull($stockAssetWatchlist->getRecommendedEntryPrice());
		self::assertNull($stockAssetWatchlist->getCurrency());
		self::assertSame($updatedAt, $stockAssetWatchlist->getUpdatedAt());
	}

}
