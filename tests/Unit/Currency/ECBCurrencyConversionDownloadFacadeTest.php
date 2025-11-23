<?php

declare(strict_types = 1);

namespace App\Test\Unit\Currency;

use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Currency\Download\CurrencyConversionDownloadInverseRateHelper;
use App\Currency\Download\ECBCurrencyConversionDownloadFacade;
use App\Http\Psr18\Psr18ClientFactory;
use App\Http\Psr7\Psr7RequestFactory;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ECBCurrencyConversionDownloadFacadeTest extends UpdatedTestCase
{

	private ECBCurrencyConversionDownloadFacade $facade;

	/** @var Psr7RequestFactory&MockInterface */
	private Psr7RequestFactory $psr7RequestFactory;

	/** @var Psr18ClientFactory&MockInterface */
	private Psr18ClientFactory $psr18ClientFactory;

	/** @var CurrencyConversionRepository&MockInterface */
	private CurrencyConversionRepository $currencyConversionRepository;

	/** @var CurrencyConversionDownloadInverseRateHelper&MockInterface */
	private CurrencyConversionDownloadInverseRateHelper $inverseRateHelper;

	/** @var DatetimeFactory&MockInterface */
	private DatetimeFactory $datetimeFactory;

	/** @var EntityManagerInterface&MockInterface */
	private EntityManagerInterface $entityManager;

	/** @var SystemValueFacade&MockInterface */
	private SystemValueFacade $systemValueFacade;

	private ImmutableDateTime $today;

	private ImmutableDateTime $now;

	protected function setUp(): void
	{
		parent::setUp();

		$this->psr7RequestFactory = Mockery::mock(Psr7RequestFactory::class);
		$this->psr18ClientFactory = Mockery::mock(Psr18ClientFactory::class);
		$this->currencyConversionRepository = Mockery::mock(CurrencyConversionRepository::class);
		$this->inverseRateHelper = Mockery::mock(CurrencyConversionDownloadInverseRateHelper::class);
		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->systemValueFacade = Mockery::mock(SystemValueFacade::class);
		$this->systemValueFacade
			->shouldReceive('updateValue')
			->once();

		$this->today = new ImmutableDateTime('2025-11-21 00:00:00');
		$this->now = new ImmutableDateTime('2025-11-21 10:30:00');

		$this->facade = new ECBCurrencyConversionDownloadFacade(
			$this->psr7RequestFactory,
			$this->psr18ClientFactory,
			$this->currencyConversionRepository,
			$this->inverseRateHelper,
			$this->datetimeFactory,
			$this->entityManager,
			$this->systemValueFacade,
		);
	}

	public function testDownloadNewRatesCreatesNewConversions(): void
	{
		$mockResponse = $this->createMockResponse(
			file_get_contents(__DIR__ . '/files/ecb-21112025.xml'),
		);

		$this->setupMockDatetimeFactory();
		$this->setupMockHttpClient($mockResponse);

		// Expect no existing rates (will create new ones)
		$this->currencyConversionRepository
			->shouldReceive('findCurrencyPairConversionForDate')
			->times(8) // 4 currencies * 2 (direct + inverse check)
			->andReturn(null);

		// Expect persist for all new conversions (direct + inverse)
		$this->entityManager
			->shouldReceive('persist')
			->times(8); // 4 currencies * 2 (direct + inverse)

		// Setup inverse rate helper for new rates
		$this->setupInverseRateHelperForNewRates();

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->systemValueFacade
			->shouldReceive('updateValue')
			->once()
			->with(SystemValueEnum::ECB_CURRENCY_DOWNLOADED_COUNT, null, 8, null);

		$result = $this->facade->downloadNewRates();

		self::assertCount(8, $result);

		// Check USD rate
		$usdRate = $this->findRateInResults($result, CurrencyEnum::EUR, CurrencyEnum::USD);
		self::assertNotNull($usdRate);
		self::assertSame(1.1520, $usdRate->getCurrentPrice());
		self::assertSame(CurrencySourceEnum::ECB, $usdRate->getSource());

		// Check GBP rate
		$gbpRate = $this->findRateInResults($result, CurrencyEnum::EUR, CurrencyEnum::GBP);
		self::assertNotNull($gbpRate);
		self::assertSame(0.88030, $gbpRate->getCurrentPrice());

		// Check PLN rate
		$plnRate = $this->findRateInResults($result, CurrencyEnum::EUR, CurrencyEnum::PLN);
		self::assertNotNull($plnRate);
		self::assertSame(4.2448, $plnRate->getCurrentPrice());

		// Check NOK rate
		$nokRate = $this->findRateInResults($result, CurrencyEnum::EUR, CurrencyEnum::NOK);
		self::assertNotNull($nokRate);
		self::assertSame(11.7940, $nokRate->getCurrentPrice());
	}

	public function testDownloadNewRatesUpdatesExistingConversions(): void
	{
		$mockResponse = $this->createMockResponse(
			file_get_contents(__DIR__ . '/files/ecb-21112025.xml'),
		);

		$this->setupMockDatetimeFactory();
		$this->setupMockHttpClient($mockResponse);

		// Create existing conversions that will be updated
		$existingUsdRate = new CurrencyConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::USD,
			1.1000,
			CurrencySourceEnum::ECB,
			$this->now,
			$this->today,
		);

		$existingUsdInverseRate = new CurrencyConversion(
			CurrencyEnum::USD,
			CurrencyEnum::EUR,
			0.9091,
			CurrencySourceEnum::ECB,
			$this->now,
			$this->today,
		);

		// Setup repository to return existing rates for USD
		$this->currencyConversionRepository
			->shouldReceive('findCurrencyPairConversionForDate')
			->with(CurrencyEnum::EUR, CurrencyEnum::USD, $this->today)
			->once()
			->andReturn($existingUsdRate);

		$this->currencyConversionRepository
			->shouldReceive('findCurrencyPairConversionForDate')
			->with(CurrencyEnum::USD, CurrencyEnum::EUR, $this->today)
			->once()
			->andReturn($existingUsdInverseRate);

		// For other currencies, return null (will create new)
		$this->currencyConversionRepository
			->shouldReceive('findCurrencyPairConversionForDate')
			->times(6)
			->andReturn(null);

		// Expect persist only for new conversions (not for updated ones)
		$this->entityManager
			->shouldReceive('persist')
			->times(6);

		$this->inverseRateHelper
			->shouldReceive('updateExistingInversedRate')
			->once()
			->with($existingUsdRate, $existingUsdInverseRate, $this->now);

		$this->setupInverseRateHelperForNewRates(1); // Skip USD as it's being updated

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->systemValueFacade
			->shouldReceive('updateValue')
			->once()
			->with(SystemValueEnum::ECB_CURRENCY_DOWNLOADED_COUNT, null, 8, null);

		$result = $this->facade->downloadNewRates();

		self::assertCount(8, $result);
		self::assertSame(1.1520, $existingUsdRate->getCurrentPrice());
	}

	public function testDownloadNewRatesHandlesMissingCurrenciesGracefully(): void
	{
		// XML without PLN and NOK currencies
		$xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube>
        <Cube time='2025-11-21'>
            <Cube currency='USD' rate='1.1520'/>
            <Cube currency='GBP' rate='0.88030'/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

		$mockResponse = $this->createMockResponse($xmlContent);

		$this->setupMockDatetimeFactory();
		$this->setupMockHttpClient($mockResponse);

		// Only USD and GBP
		$this->currencyConversionRepository
			->shouldReceive('findCurrencyPairConversionForDate')
			->times(4)
			->andReturn(null);

		$this->entityManager
			->shouldReceive('persist')
			->times(4);

		$this->setupInverseRateHelperForNewRates(2); // Only 2 currencies

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->systemValueFacade
			->shouldReceive('updateValue')
			->once()
			->with(SystemValueEnum::ECB_CURRENCY_DOWNLOADED_COUNT, null, 4, null);

		$result = $this->facade->downloadNewRates();

		self::assertCount(4, $result);
	}

	public function testGetConsoleDescription(): void
	{
		self::assertSame('ECB - EUROPEAN CENTRAL BANK', $this->facade->getConsoleDescription());
	}

	private function setupMockDatetimeFactory(): void
	{
		$this->datetimeFactory
			->shouldReceive('createToday')
			->once()
			->andReturn($this->today);

		$this->datetimeFactory
			->shouldReceive('createNow')
			->once()
			->andReturn($this->now);
	}

	private function setupMockHttpClient(ResponseInterface $response): void
	{
		$request = Mockery::mock(RequestInterface::class);

		$this->psr7RequestFactory
			->shouldReceive('createGETRequest')
			->once()
			->andReturn($request);

		$client = Mockery::mock(ClientInterface::class);
		$client
			->shouldReceive('sendRequest')
			->once()
			->with($request)
			->andReturn($response);

		$this->psr18ClientFactory
			->shouldReceive('getClient')
			->once()
			->andReturn($client);
	}

	private function createMockResponse(string $content): ResponseInterface
	{
		$stream = Mockery::mock(StreamInterface::class);
		$stream
			->shouldReceive('getContents')
			->once()
			->andReturn($content);

		$response = Mockery::mock(ResponseInterface::class);
		$response
			->shouldReceive('getBody')
			->once()
			->andReturn($stream);

		return $response;
	}

	private function setupInverseRateHelperForNewRates(int $skip = 0): void
	{
		$currencies = [
			CurrencyEnum::USD,
			CurrencyEnum::GBP,
			CurrencyEnum::PLN,
			CurrencyEnum::NOK,
		];

		$count = count($currencies) - $skip;

		$this->inverseRateHelper
			->shouldReceive('getNewInversedRate')
			->times($count)
			->andReturnUsing(static fn (CurrencyConversion $conversion) => new CurrencyConversion(
				$conversion->getToCurrency(),
				$conversion->getFromCurrency(),
				round(1 / $conversion->getCurrentPrice(), 4),
				$conversion->getSource(),
				$conversion->getCreatedAt(),
				$conversion->getForDate(),
			));
	}

	/**
	 * @param array<CurrencyConversion> $results
	 */
	private function findRateInResults(
		array $results,
		CurrencyEnum $from,
		CurrencyEnum $to,
	): CurrencyConversion|null
	{
		foreach ($results as $rate) {
			if ($rate->getFromCurrency() === $from && $rate->getToCurrency() === $to) {
				return $rate;
			}
		}

		return null;
	}

}
