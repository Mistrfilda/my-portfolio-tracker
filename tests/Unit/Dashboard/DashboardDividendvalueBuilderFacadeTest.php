<?php

declare(strict_types = 1);

namespace App\Test\Unit\Dashboard;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Dashboard\DashboardDividendvalueBuilderFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecord;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Test\Unit\Dashboard\Support\DashboardLinkGeneratorPresenter;
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

class DashboardDividendvalueBuilderFacadeTest extends TestCase
{

	private StockAssetDividendFacade|MockInterface $stockAssetDividendFacade;

	private LinkGenerator $linkGenerator;

	private DatetimeFactory|MockInterface $datetimeFactory;

	private StockAssetDividendRecordFacade|MockInterface $stockAssetDividendRecordFacade;

	private StockAssetDividendForecastRepository|MockInterface $stockAssetDividendForecastRepository;

	private CurrencyConversionFacade|MockInterface $currencyConversionFacade;

	private DashboardDividendvalueBuilderFacade $dashboardDividendvalueBuilderFacade;

	protected function setUp(): void
	{
		$this->stockAssetDividendFacade = Mockery::mock(StockAssetDividendFacade::class);
		$this->linkGenerator = $this->createLinkGenerator();
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->stockAssetDividendRecordFacade = Mockery::mock(StockAssetDividendRecordFacade::class);
		$this->stockAssetDividendForecastRepository = Mockery::mock(StockAssetDividendForecastRepository::class);
		$this->currencyConversionFacade = Mockery::mock(CurrencyConversionFacade::class);

		$this->dashboardDividendvalueBuilderFacade = new DashboardDividendvalueBuilderFacade(
			$this->stockAssetDividendFacade,
			$this->linkGenerator,
			$this->datetimeFactory,
			$this->stockAssetDividendRecordFacade,
			$this->stockAssetDividendForecastRepository,
			$this->currencyConversionFacade,
		);
	}

	public function testBuildDividendValuesIncludesRemainingValueFromDefaultForecast(): void
	{
		$now = new ImmutableDateTime('2026-04-24 10:00:00');
		$forecast = Mockery::mock(StockAssetDividendForecast::class);
		$usdRecord = Mockery::mock(StockAssetDividendForecastRecord::class);
		$czkRecord = Mockery::mock(StockAssetDividendForecastRecord::class);

		$this->datetimeFactory->shouldReceive('createNow')->once()->andReturn($now);
		$this->stockAssetDividendFacade->shouldReceive('getLastYearDividendRecordsForDashboard')->once()->andReturn([]);
		$this->stockAssetDividendRecordFacade->shouldReceive('getLastDividends')->with(8)->once()->andReturn([]);
		$this->stockAssetDividendRecordFacade->shouldReceive('getDividendsByYears')->once()->andReturn([]);
		$this->stockAssetDividendForecastRepository->shouldReceive('findByDefaultForYear')->with(
			2026,
		)->once()->andReturn($forecast);
		$forecast->shouldReceive('getRecords')->once()->andReturn([$usdRecord, $czkRecord]);

		$usdRecord->shouldReceive('getRemainingDividendTotal')->once()->andReturn(10.0);
		$usdRecord->shouldReceive('getRemainingDividendTotalBeforeTax')->once()->andReturn(12.0);
		$usdRecord->shouldReceive('getCurrency')->times(2)->andReturn(CurrencyEnum::USD);

		$czkRecord->shouldReceive('getRemainingDividendTotal')->once()->andReturn(50.0);
		$czkRecord->shouldReceive('getRemainingDividendTotalBeforeTax')->once()->andReturn(60.0);
		$czkRecord->shouldReceive('getCurrency')->times(2)->andReturn(CurrencyEnum::CZK);

		$this->currencyConversionFacade->shouldReceive('getConvertedSummaryPrice')
			->once()
			->with(
				Mockery::on(
					static fn (SummaryPrice $summaryPrice): bool => $summaryPrice->getCurrency() === CurrencyEnum::USD
						&& $summaryPrice->getPrice() === 10.0,
				),
				CurrencyEnum::CZK,
			)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 30.0));
		$this->currencyConversionFacade->shouldReceive('getConvertedSummaryPrice')
			->once()
			->with(
				Mockery::on(
					static fn (SummaryPrice $summaryPrice): bool => $summaryPrice->getCurrency() === CurrencyEnum::USD
						&& $summaryPrice->getPrice() === 12.0,
				),
				CurrencyEnum::CZK,
			)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 40.0));

		$group = $this->dashboardDividendvalueBuilderFacade->buildDividendValues();

		$this->assertCount(1, $group->getPositions());
		$this->assertSame('Očekávaná dividenda do konce roku', $group->getPositions()[0]->getLabel());
		$this->assertSame('80 CZK', $group->getPositions()[0]->getValue());
		$this->assertSame(
			'Defaultní forecast 2026, před zdaněním 100 CZK',
			$group->getPositions()[0]->getDescription(),
		);
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

}
