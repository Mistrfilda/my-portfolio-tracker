<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Position\Api;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\OAuth2\Http\OAuthResponses;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\Api\StockHoldingSerializer;
use App\Stock\Position\Api\StockPortfolioController;
use App\Stock\Position\Api\StockPortfolioProvider;
use App\Stock\Position\StockAssetPositionDetailDTO;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionFacade;
use Doctrine\ORM\NoResultException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class StockPortfolioProviderTest extends TestCase
{

	public function testUsesTheOwnerForHoldingsAndHistoricalBrokerCosts(): void
	{
		$owner = $this->createStub(AppAdmin::class);
		$now = new ImmutableDateTime('2026-10-31T12:00:00+01:00');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($now);
		$detail = $this->detail();
		$assets = $this->createMock(StockAssetRepository::class);
		$assets->expects(self::once())->method('findWithPositionsForAppAdmin')->with($owner, true)
			->willReturn([$detail->getStockAsset()]);
		$positions = $this->createMock(StockPositionFacade::class);
		$positions->expects(self::once())->method('getStockAssetDetailDTO')
			->with($detail->getStockAsset()->getId(), StockAssetListDetailControlEnum::OPEN_POSITIONS, $owner)
			->willReturn($detail);
		$positions->expects(self::once())->method('getTotalInvestedAmountSummaryPrice')->with(CurrencyEnum::CZK, $owner)
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 4900.25));
		$dividends = $this->createMock(StockAssetDividendDetailService::class);
		$dividends->expects(self::once())->method('getMarketDetailDTOFromDate')
			->with($detail->getStockAsset(), $now->deductDaysFromDatetime(365))->willReturn($this->dividends());
		$provider = new StockPortfolioProvider(
			$assets,
			$positions,
			$dividends,
			new StockHoldingSerializer(),
			$this->prices(),
			$clock,
		);
		$data = $provider->getPortfolio($owner);
		self::assertSame('2026-10-31T12:00:00+01:00', $data['generatedAt']);
		self::assertSame(4900.25, $data['total']['investedAmount']);
		self::assertSame(6000.0, $data['total']['currentValue']);
		self::assertSame(1099.75, $data['total']['profit']);
		self::assertSame(100.0, $data['items'][0]['portfolioSharePercent']);
		self::assertSame('2025-10-31', $data['items'][0]['dividends']['fromDate']);
	}

	public function testSerializerKeepsTradingAndBrokerAmountsSeparate(): void
	{
		$detail = $this->detail();
		$data = new StockHoldingSerializer()->serialize(
			$detail,
			$this->dividends(),
			new ImmutableDateTime('2025-10-31'),
			12000,
		);
		self::assertSame('USD', $data['stock']['currency']);
		self::assertSame(120.0, $data['stock']['currentPrice']);
		self::assertSame('EUR', $data['brokerCurrency']);
		self::assertSame(
			['investedAmount' => 200.0, 'currentValue' => 240.0, 'profit' => 40.0, 'profitPercent' => 20.0],
			$data['amounts'],
		);
		self::assertSame(180.0, $data['brokerAmounts']['investedAmount']);
		self::assertSame(20.0, $data['brokerAmounts']['profit']);
		self::assertSame(50.0, $data['portfolioSharePercent']);
		self::assertSame(2.5, $data['dividends']['yieldOnPricePercent']);
		self::assertSame(3.0, $data['dividends']['yieldOnCostPercent']);
		self::assertSame(3.0, $data['dividends']['grossPerShare']);
		self::assertSame('2025-06-10', $data['positions'][0]['orderDate']);
		self::assertSame(2, $data['positions'][0]['pieces']);
		self::assertSame(['amount' => 180.0, 'currency' => 'EUR'], $data['positions'][0]['brokerCost']);
		self::assertSame($data['amounts'], $data['positions'][0]['amounts']);
		self::assertArrayNotHasKey('appAdmin', $data['positions'][0]);
	}

	public function testZeroDenominatorsHaveUnavailableYieldsAndZeroShare(): void
	{
		$data = new StockHoldingSerializer()->serialize(
			$this->detail(0, 0),
			$this->dividends(),
			new ImmutableDateTime(),
			0,
		);
		self::assertNull($data['dividends']['yieldOnPricePercent']);
		self::assertNull($data['dividends']['yieldOnCostPercent']);
		self::assertSame(0.0, $data['portfolioSharePercent']);
	}

	public function testMissingRatesReturnAnUnavailableResponseInsteadOfPartialValues(): void
	{
		foreach ([new NoResultException(), new MissingCurrencyPairException()] as $exception) {
			$provider = $this->createStub(StockPortfolioProvider::class);
			$provider->method('getPortfolio')->willThrowException($exception);
			$controller = new StockPortfolioController(
				$provider,
				$this->createStub(CurrentAppAdminGetter::class),
				new OAuthResponses(),
			);
			$response = $controller->list(
				new ServerRequestFactory()->createServerRequest('GET', '/api/v1/portfolio/stocks'),
				new Response(),
			);
			self::assertSame(503, $response->getStatusCode());
			self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
			self::assertSame('data_unavailable', Json::decode((string) $response->getBody(), true)['error']);
		}
	}

	private function prices(): SummaryPriceService
	{
		return new SummaryPriceService($this->createStub(CurrencyConversionFacade::class));
	}

	private function dividends(): StockAssetDividendDetailDTO
	{
		$dividends = $this->createStub(StockAssetDividendDetailDTO::class);
		$dividends->method('getDividendsSummaryPriceWithTax')->willReturn(new SummaryPrice(CurrencyEnum::USD, 3));
		return $dividends;
	}

	private function detail(float $currentPrice = 120, float $purchasePrice = 100): StockAssetDetailDTO
	{
		$now = new ImmutableDateTime('2026-10-31T12:00:00+01:00');
		$stock = $this->createStub(StockAsset::class);
		$stock->method('getId')->willReturn(Uuid::uuid4());
		$stock->method('getName')->willReturn('Example Technology');
		$stock->method('getTicker')->willReturn('EXM');
		$stock->method('getExchange')->willReturn(StockAssetExchange::NASDAQ);
		$stock->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$stock->method('getAssetCurrentPrice')->willReturn(new AssetPrice($stock, $currentPrice, CurrencyEnum::USD));
		$stock->method('getPriceDownloadedAt')->willReturn($now);
		$position = new StockPosition(
			$this->createStub(AppAdmin::class),
			$stock,
			2,
			$purchasePrice,
			new ImmutableDateTime('2025-06-10'),
			new AssetPriceEmbeddable(180, CurrencyEnum::EUR),
			true,
			$now,
		);
		$invested = new SummaryPrice(CurrencyEnum::USD, $purchasePrice * 2);
		$current = new SummaryPrice(CurrencyEnum::USD, $currentPrice * 2);
		$brokerInvested = new SummaryPrice(CurrencyEnum::EUR, 180);
		$brokerCurrent = new SummaryPrice(CurrencyEnum::EUR, $currentPrice * 2 / 1.2);
		$czkCurrent = new SummaryPrice(CurrencyEnum::CZK, $currentPrice * 2 * 25);
		$diff = $this->prices()->getSummaryPriceDiff($current, $invested);
		return new StockAssetDetailDTO(
			$stock,
			[new StockAssetPositionDetailDTO($position, $diff)],
			$invested,
			$current,
			$brokerCurrent,
			$brokerInvested,
			$diff,
			$this->prices()->getSummaryPriceDiff($brokerCurrent, $brokerInvested),
			$czkCurrent,
			$this->prices()->getSummaryPriceDiff($czkCurrent, new SummaryPrice(CurrencyEnum::CZK, 4900.25)),
			2,
		);
	}

}
