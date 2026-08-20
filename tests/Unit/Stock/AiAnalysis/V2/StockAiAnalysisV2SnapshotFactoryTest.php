<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SnapshotFactory;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisV2SnapshotFactoryTest extends TestCase
{

	public function testIncludesSimpleWatchlistInImmutableSnapshot(): void
	{
		$now = new ImmutableDateTime('2026-08-20 09:00:00');
		$legacyPromptGenerator = $this->createStub(StockAiAnalysisPromptGenerator::class);
		$legacyPromptGenerator->method('getAutomaticPortfolioData')->willReturn([]);
		$legacyPromptGenerator->method('getAutomaticWatchlistData')->willReturn([]);
		$stockAssetWatchlist = new StockAssetWatchlist('Apple Inc.', 'AAPL', 180.0, CurrencyEnum::USD, $now);
		$repository = $this->createStub(StockAssetWatchlistRepository::class);
		$repository->method('findAll')->willReturn([$stockAssetWatchlist]);
		$factory = new StockAiAnalysisV2SnapshotFactory($legacyPromptGenerator, $repository);

		$snapshot = $factory->create(
			Uuid::uuid4(),
			$now,
			true,
			true,
			false,
			null,
			null,
			null,
			null,
		);

		self::assertTrue($snapshot['scope']['includesSimpleWatchlist']);
		self::assertSame([[
			'stockAssetId' => $stockAssetWatchlist->getId()->toString(),
			'stockAssetTicker' => 'AAPL',
			'stockAssetName' => 'Apple Inc.',
			'currency' => 'USD',
			'currentPrice' => null,
			'recommendedEntryPrice' => 180.0,
		]], $snapshot['simpleWatchlist']);
	}

}
