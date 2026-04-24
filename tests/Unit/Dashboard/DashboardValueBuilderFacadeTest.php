<?php

declare(strict_types = 1);

namespace App\Test\Unit\Dashboard;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPositionFacade;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Dashboard\DashboardDividendvalueBuilderFacade;
use App\Dashboard\DashboardValueBuilderFacade;
use App\Portu\Position\PortuPositionFacade;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockPositionFacade;
use App\Test\Unit\Dashboard\Support\DashboardLinkGeneratorPresenter;
use Closure;
use LogicException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Mockery\MockInterface;
use Nette\Application\IPresenter;
use Nette\Application\IPresenterFactory;
use Nette\Application\LinkGenerator;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DashboardValueBuilderFacadeTest extends TestCase
{

	private CurrencyConversionRepository|MockInterface $currencyConversionRepository;

	private StockPositionFacade|MockInterface $stockPositionFacade;

	private SummaryPriceService|MockInterface $summaryPriceService;

	private PortuPositionFacade|MockInterface $portuPositionFacade;

	private AssetPriceSummaryFacade|MockInterface $assetPriceSummaryFacade;

	private DashboardDividendvalueBuilderFacade|MockInterface $dashboardDividendvalueBuilderFacade;

	private StockClosedPositionFacade|MockInterface $stockClosedPositionFacade;

	private CryptoPositionFacade|MockInterface $cryptoPositionFacade;

	private CryptoAssetRepository|MockInterface $cryptoAssetRepository;

	private StockAssetRepository|MockInterface $stockAssetRepository;

	private LinkGenerator $linkGenerator;

	private DatetimeFactory|MockInterface $datetimeFactory;

	private DashboardValueBuilderFacade $dashboardValueBuilderFacade;

	protected function setUp(): void
	{
		$this->currencyConversionRepository = Mockery::mock(CurrencyConversionRepository::class);
		$this->stockPositionFacade = Mockery::mock(StockPositionFacade::class);
		$this->summaryPriceService = Mockery::mock(SummaryPriceService::class);
		$this->portuPositionFacade = Mockery::mock(PortuPositionFacade::class);
		$this->assetPriceSummaryFacade = Mockery::mock(AssetPriceSummaryFacade::class);
		$this->dashboardDividendvalueBuilderFacade = Mockery::mock(DashboardDividendvalueBuilderFacade::class);
		$this->stockClosedPositionFacade = Mockery::mock(StockClosedPositionFacade::class);
		$this->cryptoPositionFacade = Mockery::mock(CryptoPositionFacade::class);
		$this->cryptoAssetRepository = Mockery::mock(CryptoAssetRepository::class);
		$this->stockAssetRepository = Mockery::mock(StockAssetRepository::class);
		$this->linkGenerator = $this->createLinkGenerator();
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);

		$this->dashboardValueBuilderFacade = new DashboardValueBuilderFacade(
			$this->currencyConversionRepository,
			$this->stockPositionFacade,
			$this->summaryPriceService,
			$this->portuPositionFacade,
			$this->assetPriceSummaryFacade,
			$this->dashboardDividendvalueBuilderFacade,
			$this->stockClosedPositionFacade,
			$this->cryptoPositionFacade,
			$this->cryptoAssetRepository,
			$this->stockAssetRepository,
			$this->linkGenerator,
			$this->datetimeFactory,
		);
	}

	public function testBuildStockValuesIncludesTopAndBottomDailyPerformanceTables(): void
	{
		$stockPositionsSummaryPrice = new SummaryPrice(CurrencyEnum::CZK, 100_000, 8);
		$czStockPositionsSummaryPrice = new SummaryPrice(CurrencyEnum::CZK, 20_000, 2);
		$usdStockPositionsSummaryPrice = new SummaryPrice(CurrencyEnum::CZK, 30_000, 2);
		$gbpStockPositionsSummaryPrice = new SummaryPrice(CurrencyEnum::CZK, 25_000, 2);
		$eurStockPositionsSummaryPrice = new SummaryPrice(CurrencyEnum::CZK, 25_000, 2);
		$totalInvestedAmount = new SummaryPrice(CurrencyEnum::CZK, 90_000, 8);
		$openDiff = new PriceDiff(10_000, 111.11, CurrencyEnum::CZK);
		$closedDiff = new PriceDiff(-5_000, 95.0, CurrencyEnum::CZK);
		$now = new ImmutableDateTime('2026-04-27 10:00:00');

		$this->stockPositionFacade->shouldReceive('getCurrentPortfolioValueSummaryPrice')->with(
			CurrencyEnum::CZK,
		)->once()->andReturn($stockPositionsSummaryPrice);
		$this->stockPositionFacade->shouldReceive('getCurrentPortfolioValueInCzechStocks')->with(
			CurrencyEnum::CZK,
		)->once()->andReturn($czStockPositionsSummaryPrice);
		$this->stockPositionFacade->shouldReceive('getCurrentPortfolioValueInUsdStocks')->with(
			CurrencyEnum::CZK,
		)->once()->andReturn($usdStockPositionsSummaryPrice);
		$this->stockPositionFacade->shouldReceive('getCurrentPortfolioValueInGbpStocks')->with(
			CurrencyEnum::CZK,
		)->once()->andReturn($gbpStockPositionsSummaryPrice);
		$this->stockPositionFacade->shouldReceive('getCurrentPortfolioValueInEurStocks')->with(
			CurrencyEnum::CZK,
		)->once()->andReturn($eurStockPositionsSummaryPrice);
		$this->stockPositionFacade->shouldReceive('getTotalInvestedAmountSummaryPrice')->with(
			CurrencyEnum::CZK,
		)->once()->andReturn($totalInvestedAmount);
		$this->summaryPriceService->shouldReceive('getSummaryPriceDiff')->with(
			$stockPositionsSummaryPrice,
			$totalInvestedAmount,
		)->once()->andReturn($openDiff);
		$this->stockClosedPositionFacade->shouldReceive('getAllStockClosedPositionsSummaryPrice')->once()->andReturn(
			$closedDiff,
		);
		$this->datetimeFactory->shouldReceive('createNow')->once()->andReturn($now);

		$assets = [
			$this->createStockAssetMock('Apple', 'AAPL', 7.25),
			$this->createStockAssetMock('Microsoft', 'MSFT', 5.00),
			$this->createStockAssetMock('ASML', 'ASML', 3.50),
			$this->createStockAssetMock('ČEZ', 'CEZ', 1.00),
			$this->createStockAssetMock('Intel', 'INTC', -1.50),
			$this->createStockAssetMock('Pfizer', 'PFE', -2.00),
			$this->createStockAssetMock('Nokia', 'NOKIA', -4.00),
			$this->createStockAssetMock('Tesla', 'TSLA', -6.50),
		];

		$this->stockAssetRepository->shouldReceive('getAllActiveAssets')->once()->andReturn($assets);

		$getStockValues = Closure::bind(
			fn () => $this->getStockValues(),
			$this->dashboardValueBuilderFacade,
			DashboardValueBuilderFacade::class,
		);
		$group = $getStockValues();

		$this->assertCount(2, $group->getTables());
		$this->assertSame('Top 4 akcie dne', $group->getTables()[0]->getLabel());
		$this->assertSame('Apple', $group->getTables()[0]->getData()[0]['stockAssetName']);
		$this->assertSame('+7.25 %', $group->getTables()[0]->getData()[0]['trend']);
		$this->assertSame('Nejhorší 4 akcie dne', $group->getTables()[1]->getLabel());
		$this->assertSame('Tesla', $group->getTables()[1]->getData()[0]['stockAssetName']);
		$this->assertSame('-6.50 %', $group->getTables()[1]->getData()[0]['trend']);
	}

	private function createLinkGenerator(): LinkGenerator
	{
		return new LinkGenerator(
			$this->createRouter(),
			new UrlScript('https://localhost/'),
			new class implements IPresenterFactory
			{

				public function getPresenterClass(string &$name): string
				{
					return DashboardLinkGeneratorPresenter::class;
				}

				public function createPresenter(string $name): IPresenter
				{
					throw new LogicException('Not needed in tests.');
				}

			},
		);
	}

	private function createRouter(): object
	{
		$router = eval(<<<'PHP'
			return new class implements \Nette\Routing\Router
			{

				public function match(\Nette\Http\IRequest $httpRequest): ?array
				{
					return null;
				}

				public function constructUrl(array $params, \Nette\Http\UrlScript $refUrl): ?string
				{
					$query = http_build_query($params);
					if ($query === '') {
						return $refUrl->getHostUrl() . $refUrl->getPath();
					}

					return $refUrl->getHostUrl() . $refUrl->getPath() . '?' . $query;
				}

			};
			PHP);
		assert(is_object($router));

		return $router;
	}

	private function createStockAssetMock(string $name, string $ticker, float $trend): StockAsset|MockInterface
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')->once()->andReturn(true);
		$stockAsset->shouldReceive('getTrend')
			->once()
			->with(Mockery::on(static fn (ImmutableDateTime $date): bool => $date->format('Y-m-d') === '2026-04-26'))
			->andReturn($trend);
		$stockAsset->shouldReceive('getName')->andReturn($name);
		$stockAsset->shouldReceive('getTicker')->andReturn($ticker);
		$stockAsset->shouldReceive('getId')->andReturn(Uuid::uuid4());

		return $stockAsset;
	}

}
