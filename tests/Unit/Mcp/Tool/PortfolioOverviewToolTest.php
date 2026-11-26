<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Mcp\Tool\PortfolioOverviewTool;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\Api\StockPortfolioProvider;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioOverviewToolTest extends TestCase
{

	public function testReturnsOverviewWithSectorCurrencyAllocationsAndInstructions(): void
	{
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$techIndustry = new StockAssetIndustry(
			'Technology',
			'Technology',
			$now,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		);
		$assetA = new StockAsset(
			'Apple',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'AAPL',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			true,
			false,
			$techIndustry,
		);
		$assetB = new StockAsset(
			'CEZ',
			StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER,
			'CEZ',
			StockAssetExchange::PRAGUE_STOCK_EXCHANGE,
			CurrencyEnum::CZK,
			$now,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
			null,
		);

		$portfolioProvider = $this->createMock(StockPortfolioProvider::class);
		$portfolioProvider->expects(self::once())
			->method('getPortfolio')
			->with($owner)
			->willReturn([
				'generatedAt' => '2026-05-01T12:00:00+02:00',
				'currency' => 'CZK',
				'total' => [
					'investedAmount' => 80000.0,
					'currentValue' => 100000.0,
					'profit' => 20000.0,
					'profitPercent' => 25.0,
				],
				'items' => [
					[
						'stock' => [
							'id' => $assetA->getId()->toString(),
							'name' => 'Apple',
							'ticker' => 'AAPL',
							'currency' => 'USD',
							'currentPrice' => 200.0,
							'priceUpdatedAt' => '2026-05-01T11:30:00+02:00',
						],
						'pieces' => 15,
						'averagePurchasePrice' => 150.0,
						'amounts' => [
							'investedAmount' => 2250.0,
							'currentValue' => 3000.0,
							'profit' => 750.0,
							'profitPercent' => 33.33,
						],
						'currentValueCzk' => 70000.0,
						'portfolioSharePercent' => 70.0,
					],
					[
						'stock' => [
							'id' => $assetB->getId()->toString(),
							'name' => 'CEZ',
							'ticker' => 'CEZ',
							'currency' => 'CZK',
							'currentPrice' => 1000.0,
							'priceUpdatedAt' => '2026-05-01T11:00:00+02:00',
						],
						'pieces' => 30,
						'averagePurchasePrice' => 900.0,
						'amounts' => [
							'investedAmount' => 27000.0,
							'currentValue' => 30000.0,
							'profit' => 3000.0,
							'profitPercent' => 11.11,
						],
						'currentValueCzk' => 30000.0,
						'portfolioSharePercent' => 30.0,
					],
				],
			]);

		$assetRepository = $this->createMock(StockAssetRepository::class);
		$assetRepository->expects(self::once())
			->method('findWithPositionsForAppAdmin')
			->with($owner, true)
			->willReturn([$assetA, $assetB]);

		$settingsFacade = $this->createMock(StockAiAnalysisSettingsFacade::class);
		$settingsFacade->expects(self::once())
			->method('getInvestorInstructions')
			->with($owner)
			->willReturn('Long-term quality dividend growth');

		$tool = new PortfolioOverviewTool($currentAdminGetter, $portfolioProvider, $assetRepository, $settingsFacade);
		$result = $tool->execute(includeInvestorInstructions: true);

		self::assertSame('2026-05-01T12:00:00+02:00', $result['generatedAt']);
		self::assertSame(2, $result['positionsCount']);
		self::assertSame('Long-term quality dividend growth', $result['investorInstructions']);
		self::assertSame([
			['sector' => 'Technology', 'valueCzk' => 70000.0, 'positionsCount' => 1, 'weightPercent' => 70.0],
			['sector' => 'Unassigned', 'valueCzk' => 30000.0, 'positionsCount' => 1, 'weightPercent' => 30.0],
		], $result['sectorAllocation']);
		self::assertSame([
			['currency' => 'USD', 'valueCzk' => 70000.0, 'positionsCount' => 1, 'weightPercent' => 70.0],
			['currency' => 'CZK', 'valueCzk' => 30000.0, 'positionsCount' => 1, 'weightPercent' => 30.0],
		], $result['currencyAllocation']);
	}

	public function testHandlesEmptyPortfolioAndCanExcludeInstructions(): void
	{
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$portfolioProvider = self::createStub(StockPortfolioProvider::class);
		$portfolioProvider->method('getPortfolio')->willReturn([
			'generatedAt' => '2026-05-01T12:00:00+02:00',
			'currency' => 'CZK',
			'total' => [
				'investedAmount' => 0.0,
				'currentValue' => 0.0,
				'profit' => 0.0,
				'profitPercent' => 0.0,
			],
			'items' => [],
		]);

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findWithPositionsForAppAdmin')->willReturn([]);

		$settingsFacade = $this->createMock(StockAiAnalysisSettingsFacade::class);
		$settingsFacade->expects(self::never())->method('getInvestorInstructions');

		$tool = new PortfolioOverviewTool($currentAdminGetter, $portfolioProvider, $assetRepository, $settingsFacade);
		$result = $tool->execute(includeInvestorInstructions: false);

		self::assertSame(0, $result['positionsCount']);
		self::assertSame([], $result['positions']);
		self::assertSame([], $result['sectorAllocation']);
		self::assertNull($result['investorInstructions']);
	}

}
