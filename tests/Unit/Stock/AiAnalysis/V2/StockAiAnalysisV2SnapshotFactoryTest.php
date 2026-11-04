<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\V2;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisCompanyDataProvider;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SnapshotFactory;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\Stock\Position\StockPositionFacade;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisV2SnapshotFactoryTest extends TestCase
{

	#[DataProvider('providePortfolioScopes')]
	public function testAllFullCompanyScopesReceiveTheSameDataAndKeepRequestedIdentity(bool $includesPortfolio): void
	{
		$admin = $this->createStub(AppAdmin::class);
		$id = Uuid::uuid4();
		$asset = $this->createStub(StockAsset::class);
		$asset->method('getId')->willReturn($id);
		$asset->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$asset->method('getIndustry')->willReturn(null);
		$asset->method('isWatchlist')->willReturn(true);
		$company = [
			'stockAssetId' => $id->toString(),
			'stockAssetTicker' => 'TEST',
			'stockAssetName' => 'Test Company',
			'currency' => 'USD',
			'sector' => null,
			'currentPrice' => 100.0,
			'performance1Day' => 1.25,
			'performance7Days' => -3.5,
			'dilutedEps' => 5.0,
			'fundamentalMetadata' => ['dilutedEps' => ['importedAt' => '2026-10-25T12:00:00+01:00']],
			'internalValuation' => ['models' => [['availability' => 'not_applicable', 'availabilityReason' => 'Industry rule']]],
		];
		$provider = $this->createMock(StockAiAnalysisCompanyDataProvider::class);
		$provider->expects(self::exactly(3))->method('getData')->with($asset)->willReturn($company);
		$assets = $this->createStub(StockAssetRepository::class);
		$assets->method('findWithPositionsForAppAdmin')->willReturn([$asset]);
		$assets->method('findAll')->willReturn([$asset]);
		$detail = $this->createStub(StockAssetDetailDTO::class);
		$detail->method('getCurrentPriceInCzk')->willReturn(new SummaryPrice(CurrencyEnum::CZK, 2500.0));
		$detail->method('getTotalInvestedAmount')->willReturn(new SummaryPrice(CurrencyEnum::USD, 100.0));
		$detail->method('getCurrentPriceDiff')->willReturn(new PriceDiff(0.0, 100.0, CurrencyEnum::USD));
		$detail->method('getPiecesCount')->willReturn(1);
		$detail->method('getPositions')->willReturn([]);
		$positions = $this->createStub(StockPositionFacade::class);
		$positions->method('getStockAssetDetailDTO')->willReturn($detail);
		$settings = $this->createStub(StockAiAnalysisSettingsFacade::class);
		$settings->method('getInvestorInstructions')->willReturn(
			StockAiAnalysisSettingsFacade::DEFAULT_INVESTOR_INSTRUCTIONS,
		);
		$generator = new StockAiAnalysisPromptGenerator(
			$assets,
			$provider,
			$positions,
			$this->createStub(DatetimeFactory::class),
			$settings,
			$this->createStub(CurrentAppAdminGetter::class),
		);
		$watchlist = $this->createStub(StockAssetWatchlistRepository::class);
		$watchlist->method('findAllForAppAdmin')->willReturn([]);
		$factory = new StockAiAnalysisV2SnapshotFactory($generator, $watchlist, $settings);
		$snapshot = $factory->create(
			Uuid::uuid4(),
			new ImmutableDateTime('2026-11-04 12:00:00'),
			$includesPortfolio,
			true,
			false,
			null,
			'TEST',
			'User supplied name',
			$asset,
			$admin,
		);

		self::assertSame($company, $snapshot['watchlist'][0]);
		if ($includesPortfolio) {
			self::assertSame($company, array_intersect_key($snapshot['portfolio'][0], $company));
			self::assertSame(100.0, $snapshot['portfolio'][0]['portfolioPercentage']);
		} else {
			self::assertSame([], $snapshot['portfolio']);
		}

		self::assertSame([...$company, 'stockAssetName' => 'User supplied name'], $snapshot['singleStock']);
		self::assertSame(
			StockAiAnalysisSettingsFacade::DEFAULT_INVESTOR_INSTRUCTIONS,
			$snapshot['investorInstructions'],
		);
		self::assertSame([
			'stockAssetId' => $id->toString(),
			'stockAssetTicker' => 'TEST',
			'sector' => null,
			'currency' => 'USD',
			'portfolioPercentage' => 100.0,
			'performance1Day' => 1.25,
			'performance7Days' => -3.5,
			'profitLossPercent' => 0.0,
			'contextOnly' => !$includesPortfolio,
		], $snapshot['portfolioContext']['positions'][0]);
		self::assertArrayNotHasKey('internalValuation', $snapshot['portfolioContext']['positions'][0]);
	}

	/** @return array<string, array{bool}> */
	public static function providePortfolioScopes(): array
	{
		return ['requested portfolio' => [true], 'context-only portfolio' => [false]];
	}

	public function testUnknownSingleStockKeepsResearchOnlyInput(): void
	{
		$generator = $this->createMock(StockAiAnalysisPromptGenerator::class);
		$generator->expects(self::never())->method('getAutomaticStockData');
		$factory = new StockAiAnalysisV2SnapshotFactory(
			$generator,
			$this->createStub(StockAssetWatchlistRepository::class),
			$this->createStub(StockAiAnalysisSettingsFacade::class),
		);
		$snapshot = $factory->create(
			Uuid::uuid4(),
			new ImmutableDateTime('2026-11-04 12:00:00'),
			false,
			false,
			false,
			null,
			'NEW',
			'New Company',
			null,
			$this->createStub(AppAdmin::class),
		);

		self::assertSame([
			'stockAssetId' => null,
			'stockAssetTicker' => 'NEW',
			'stockAssetName' => 'New Company',
			'currency' => null,
			'sector' => null,
			'currentPrice' => null,
		], $snapshot['singleStock']);
	}

	public function testIncludesSimpleWatchlistInImmutableSnapshot(): void
	{
		$now = new ImmutableDateTime('2026-08-20 09:00:00');
		$legacyPromptGenerator = $this->createStub(StockAiAnalysisPromptGenerator::class);
		$legacyPromptGenerator->method('getAutomaticPortfolioData')->willReturn([]);
		$legacyPromptGenerator->method('getAutomaticWatchlistData')->willReturn([]);
		$stockAssetWatchlist = new StockAssetWatchlist(
			$this->createStub(AppAdmin::class),
			'Apple Inc.',
			'AAPL',
			180.0,
			CurrencyEnum::USD,
			$now,
		);
		$repository = $this->createStub(StockAssetWatchlistRepository::class);
		$repository->method('findAllForAppAdmin')->willReturn([$stockAssetWatchlist]);
		$settings = $this->createStub(StockAiAnalysisSettingsFacade::class);
		$settings->method('getInvestorInstructions')->willReturn('Keep Czech holdings.');
		$factory = new StockAiAnalysisV2SnapshotFactory($legacyPromptGenerator, $repository, $settings);

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
			$stockAssetWatchlist->getAppAdmin(),
		);

		self::assertSame('Keep Czech holdings.', $snapshot['investorInstructions']);

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
