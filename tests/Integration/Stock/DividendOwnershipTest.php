<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Dashboard\DashboardDividendvalueBuilderFacade;
use App\Doctrine\NoEntityFoundException;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Statistic\UI\Chart\StockDividendByCompanyChartDataProvider;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastCashflowProvider;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecord;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastStateEnum;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\Stock\Dividend\Forecast\UI\Control\StockAssetDividendForecastDetailControl;
use App\Stock\Dividend\Forecast\UI\Control\StockAssetDividendForecastItemValuesFormFactory;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordService;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Application\BadRequestException;
use PHPUnit\Framework\Attributes\TestWith;

class DividendOwnershipTest extends IntegrationTestCase
{

	private EntityManagerInterface $em;

	private CurrentAppAdminGetter $currentAdmin;

	/** @var array<AppAdmin> */
	private array $owners = [];

	/** @var array<StockPosition> */
	private array $positions = [];

	/** @var array<StockAssetDividendForecast> */
	private array $forecasts = [];

	private StockAsset $stock;

	private StockAssetDividend $event;

	/** @var array<string> */
	private array $queuedOwners = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
		$jobs = $this->createStub(JobRequestFacade::class);
		$jobs->method('addToQueue')->willReturnCallback(function (JobRequestTypeEnum $type, array $data): void {
			self::assertSame(JobRequestTypeEnum::STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE_ALL, $type);
			$this->queuedOwners[] = $data['appAdminId'];
		});
		foreach ($this->container->findByType(JobRequestFacade::class) as $name) {
			$this->container->removeService($name);
			$this->container->addService($name, $jobs);
		}

		$now = new ImmutableDateTime('2026-06-01');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($now);
		foreach ($this->container->findByType(DatetimeFactory::class) as $name) {
			$this->container->removeService($name);
			$this->container->addService($name, $clock);
		}

		$suffix = bin2hex(random_bytes(4));
		$this->stock = new StockAsset(
			'Owned dividend ' . $suffix,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'DIV-' . $suffix,
			StockAssetExchange::NYSE,
			CurrencyEnum::CZK,
			$now,
			null,
			StockAssetDividendSourceEnum::WEB,
			15.0,
			null,
			false,
			false,
			false,
			null,
		);
		$this->em->persist($this->stock);
		foreach ([10, 20, 0] as $index => $pieces) {
			$name = 'dividend-owner-' . $suffix . '-' . $index;
			$owner = new AppAdmin($name, $name, $name . '@example.test', 'unused', $now, false, false);
			$this->owners[] = $owner;
			$this->em->persist($owner);
			if ($pieces > 0) {
				$position = new StockPosition(
					$owner,
					$this->stock,
					$pieces,
					100,
					new ImmutableDateTime('2025-01-01'),
					new AssetPriceEmbeddable($pieces * 100, CurrencyEnum::CZK),
					false,
					$now,
				);
				$this->positions[] = $position;
				$this->em->persist($position);
			}

			$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now, $owner);
			$this->forecasts[] = $forecast;
			$this->em->persist($forecast);
		}

		foreach (['2025-03-15' => 2.0, '2025-09-15' => 2.0, '2026-03-15' => 3.0] as $date => $amount) {
			$event = new StockAssetDividend(
				$this->stock,
				new ImmutableDateTime($date),
				new ImmutableDateTime($date),
				null,
				CurrencyEnum::CZK,
				$amount,
				$now,
			);
			$this->em->persist($event);
			$this->event = $event;
		}

		$this->em->flush();
		$this->em->refresh($this->stock);
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testTwoOwnersRecalculateInEitherOrderWithoutSharingAmountsOrCreatingDuplicates(): void
	{
		$facade = $this->getService(StockAssetDividendRecordFacade::class);
		$forecasts = $this->getService(StockAssetDividendForecastRecordFacade::class);
		$records = $this->getService(StockAssetDividendRecordRepository::class);
		$ids = [];
		foreach ([0, 1, 1, 0] as $index) {
			$owner = $this->owners[$index];
			$facade->processForAppAdmin($owner);
			$forecasts->recalculate($this->forecasts[$index]->getId());
			$record = $records->findOneByStockDividend($this->event, $owner);
			self::assertNotNull($record);
			self::assertSame(($index + 1) * 10, $record->getTotalPiecesHeldAtExDate());
			self::assertSame(($index + 1) * 30.0, $record->getTotalAmount());
			self::assertCount(3, $records->findAll($owner));
			if (isset($ids[$index])) {
				self::assertSame($ids[$index], $record->getId()->toString());
			}

			$ids[$index] = $record->getId()->toString();
			$forecastRecord = $this->forecastRecord($index);
			self::assertSame(($index + 1) * 10, $forecastRecord->getPiecesCurrentlyHeld());
			self::assertEqualsWithDelta(($index + 1) * 25.5, $forecastRecord->getActualReceivedTotal(), 0.001);
			self::assertEqualsWithDelta(($index + 1) * 25.5, $forecastRecord->getRemainingDividendTotal(), 0.001);
			self::assertEqualsWithDelta(($index + 1) * 51.0, $forecastRecord->getTotalYearDividend(), 0.001);
			self::assertEqualsWithDelta(($index + 1) * 60.0, $forecastRecord->getTotalYearDividendBeforeTax(), 0.001);
			$months = $this->getService(StockAssetDividendForecastCashflowProvider::class)->getMonths(
				$this->forecasts[$index],
			);
			self::assertEqualsWithDelta(($index + 1) * 25.5, $months[2]->getNetAmountInCzk(), 0.001);
			self::assertEqualsWithDelta(($index + 1) * 25.5, $months[8]->getNetAmountInCzk(), 0.001);
		}

		self::assertSame(
			array_map(fn (int $i): string => $this->owners[$i]->getId()->toString(), [0, 1, 1, 0]),
			$this->queuedOwners,
		);
		$facade->processForAppAdmin($this->owners[2]);
		$forecasts->recalculate($this->forecasts[2]->getId());
		self::assertSame([], $records->findAll($this->owners[2]));
		self::assertSame([], $this->forecasts[2]->getRecords());

		$this->currentAdmin->setApiAppAdmin($this->owners[0]);
		$first = $this->forecastRecord(0);
		$second = $this->forecastRecord(1);
		$forecasts->updateCustomValuesForRecord($first->getId(), 5.0, null, null, null);
		$this->forecasts[1]->recalculatingPending();
		$this->em->flush();
		$forecasts->recalculateAll($this->owners[0]->getId());
		self::assertSame(5.0, $first->getCustomDividendUsedForCalculation());
		self::assertNull($second->getCustomDividendUsedForCalculation());
		self::assertEqualsWithDelta(102.0, $second->getTotalYearDividend(), 0.001);
		self::assertSame(StockAssetDividendForecastStateEnum::PENDING, $this->forecasts[1]->getState());
		$this->positions[0]->update(
			$this->stock,
			5,
			100,
			new ImmutableDateTime('2025-01-01'),
			new AssetPriceEmbeddable(500, CurrencyEnum::CZK),
			false,
			new ImmutableDateTime(),
		);
		$this->em->flush();
		$facade->processForAppAdmin($this->owners[0]);
		self::assertSame(15.0, $records->findOneByStockDividend($this->event, $this->owners[0])?->getTotalAmount());
		self::assertSame(60.0, $records->findOneByStockDividend($this->event, $this->owners[1])?->getTotalAmount());
	}

	public function testDashboardChartsAndStockDetailUseOnlyOwnersReceivedDividends(): void
	{
		$this->processBothOwners();
		$builder = $this->getService(DashboardDividendvalueBuilderFacade::class);
		$chart = $this->getService(StockDividendByCompanyChartDataProvider::class);
		foreach ([0, 1, 0, 2] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->owners[$index]);
			$summary = $this->getService(StockAssetDividendRecordFacade::class)->getTotalSummaryPrice();
			self::assertEqualsWithDelta($index === 2 ? 0.0 : ($index + 1) * 59.5, $summary->getPrice(), 0.001);
			$detail = $this->getService(StockAssetDividendDetailService::class)->getDetailDTOTotal($this->stock);
			self::assertEqualsWithDelta(
				$summary->getPrice(),
				$detail->getDividendRecordSummaryPrice()->getPrice(),
				0.001,
			);
			$datasets = $chart->getChartData()->jsonSerialize()['datasets'];
			self::assertCount($index === 2 ? 0 : 1, $datasets);
			if ($index !== 2) {
				self::assertSame([(int) (($index + 1) * 59.5)], $datasets[0]['data']);
			}

			// Explicit owners must work even when another account is currently signed in.
			$this->currentAdmin->setApiAppAdmin($this->owners[1]);
			$group = $builder->buildDividendValues($this->owners[$index]);
			self::assertCount($index === 2 ? 0 : 2, $group->getPositions());
			if ($index !== 2) {
				self::assertSame($index === 0 ? '26 CZK' : '51 CZK', $group->getPositions()[0]->getValue());
			}
		}
	}

	public function testClosedPositionKeepsOwnReceivedDividendAndHasNoRemainingForecast(): void
	{
		$position = $this->positions[0];
		$closed = new StockClosedPosition(
			$position,
			110,
			new ImmutableDateTime('2026-04-01'),
			false,
			new AssetPriceEmbeddable(1100, CurrencyEnum::CZK),
			new ImmutableDateTime(),
		);
		$position->closePosition($closed);
		$this->em->persist($closed);
		$this->em->flush();
		$this->getService(StockAssetDividendRecordFacade::class)->processForAppAdmin($this->owners[0]);
		$this->getService(StockAssetDividendForecastRecordFacade::class)->recalculate($this->forecasts[0]->getId());
		$record = $this->forecastRecord(0);
		self::assertSame(0, $record->getPiecesCurrentlyHeld());
		self::assertEqualsWithDelta(25.5, $record->getActualReceivedTotal(), 0.001);
		self::assertSame(0.0, $record->getRemainingDividendTotal());
		$this->currentAdmin->setApiAppAdmin($this->owners[1]);
		$closedSummaries = $this->getService(StockClosedPositionFacade::class)->getAllStockClosedPositions(
			$this->owners[0],
		);
		self::assertCount(1, $closedSummaries);
		self::assertEqualsWithDelta(
			1159.5,
			$closedSummaries[0]->getTotalAmountInBrokerCurrencyWithDividendsInCzk()?->getPrice(),
			0.001,
		);
	}

	public function testServiceFiltersMixedOwnerPositions(): void
	{
		$records = $this->getService(StockAssetDividendRecordService::class)->processDividendRecords(
			new ArrayCollection([$this->event]),
			new ArrayCollection($this->positions),
			$this->owners[0],
		);
		self::assertCount(1, $records);
		self::assertSame(10, $records->first()->getTotalPiecesHeldAtExDate());
		self::assertSame($this->owners[0], $records->first()->getAppAdmin());
	}

	public function testRepositoryScopesAndDefaultForecastFollowCurrentOwner(): void
	{
		$this->processBothOwners();
		$records = $this->getService(StockAssetDividendRecordRepository::class);
		$forecasts = $this->getService(StockAssetDividendForecastRepository::class);
		$forecastFacade = $this->getService(StockAssetDividendForecastFacade::class);
		foreach ([0, 1, 0] as $index) {
			$owner = $this->owners[$index];
			$this->currentAdmin->setApiAppAdmin($owner);
			$forecastFacade->setDefaultForYear($this->forecasts[$index]->getId());
			self::assertSame($this->forecasts[$index], $forecasts->findByDefaultForYear(2026));
			self::assertSame([$this->forecasts[$index]], $forecasts->findAll());
			self::assertSame(
				[$this->forecasts[$index]],
				$forecasts->findByIds(array_map(static fn ($f) => $f->getId(), $this->forecasts)),
			);
			foreach ([
				$records->findAll(), $records->findAllForMonthChart(), $records->findByStockAsset($this->stock),
				$records->findByStockAssetSinceDate($this->stock, new ImmutableDateTime('2025-01-01')),
				$records->findByStockAssetForYear($this->stock, 2026), $records->findByYearAndMonth(2026, 3),
				$records->findGreaterThan(new ImmutableDateTime('2025-01-01')),
				$records->findBetweenDates(new ImmutableDateTime('2025-01-01'), new ImmutableDateTime('2026-12-31')),
				$records->findCashReceivedBetweenDates(
					new ImmutableDateTime('2025-01-01'),
					new ImmutableDateTime('2026-12-31'),
				),
				$records->findLastDividendRecords(),
			] as $found) {
				self::assertNotEmpty($found);
				foreach ($found as $record) {
					self::assertSame($owner, $record->getAppAdmin());
				}
			}

			$otherRecord = $records->findOneByStockDividend($this->event, $this->owners[1 - $index]);
			self::assertNotNull($otherRecord);
			self::assertSame([], $records->findByIds([$otherRecord->getId()]));
		}

		self::assertTrue($this->forecasts[0]->isDefaultForYear());
		self::assertTrue($this->forecasts[1]->isDefaultForYear());
		$this->currentAdmin->setApiAppAdmin($this->owners[2]);
		$forecastFacade->create(2027, StockAssetDividendTrendEnum::NEUTRAL);
		self::assertCount(2, $forecasts->findAll());
		foreach ($forecasts->findAll() as $forecast) {
			self::assertSame($this->owners[2], $forecast->getAppAdmin());
		}
	}

	#[TestWith(['record'])]
	#[TestWith(['forecast'])]
	#[TestWith(['forecastRecord'])]
	#[TestWith(['updateForecast'])]
	#[TestWith(['defaultForecast'])]
	#[TestWith(['customValues'])]
	#[TestWith(['itemForm'])]
	public function testForeignIdsCannotBeReadOrChanged(string $operation): void
	{
		$this->processBothOwners();
		$this->currentAdmin->setApiAppAdmin($this->owners[0]);
		$record = $this->getService(StockAssetDividendRecordRepository::class)->findOneByStockDividend(
			$this->event,
			$this->owners[1],
		);
		assert($record instanceof StockAssetDividendRecord);
		$foreign = $this->forecastRecord(1);
		$this->expectException(NoEntityFoundException::class);
		match ($operation) {
			'record' => $this->getService(StockAssetDividendRecordRepository::class)->getById($record->getId()),
			'forecast' => $this->getService(StockAssetDividendForecastRepository::class)->getById(
				$this->forecasts[1]->getId(),
			),
			'forecastRecord' => $this->getService(StockAssetDividendForecastRecordRepository::class)->getById(
				$foreign->getId(),
			),
			'updateForecast' => $this->getService(StockAssetDividendForecastFacade::class)->update(
				$this->forecasts[1]->getId(),
				StockAssetDividendTrendEnum::NEUTRAL,
			),
			'defaultForecast' => $this->getService(StockAssetDividendForecastFacade::class)->setDefaultForYear(
				$this->forecasts[1]->getId(),
			),
			'customValues' => $this->getService(
				StockAssetDividendForecastRecordFacade::class,
			)->updateCustomValuesForRecord(
				$foreign->getId(),
				5.0,
				null,
				null,
				null,
			),
			'itemForm' => $this->getService(StockAssetDividendForecastItemValuesFormFactory::class)->create(
				$foreign->getId(),
				static function (): void {
				},
			),
		};
	}

	public function testNestedItemFormRejectsAnotherForecastOfSameOwner(): void
	{
		$this->processBothOwners();
		$this->currentAdmin->setApiAppAdmin($this->owners[0]);
		$another = new StockAssetDividendForecast(
			2027,
			StockAssetDividendTrendEnum::NEUTRAL,
			new ImmutableDateTime(),
			$this->owners[0],
		);
		$this->em->persist($another);
		$this->em->flush();
		$this->expectException(NoEntityFoundException::class);
		$this->getService(StockAssetDividendForecastItemValuesFormFactory::class)->create(
			$this->forecastRecord(0)->getId(),
			static function (): void {
			},
			$another->getId(),
		);
	}

	public function testRecalculateSignalCannotQueueAnotherForecast(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->owners[0]);
		$jobs = $this->createMock(JobRequestFacade::class);
		$jobs->expects(self::never())->method('addToQueue');
		$control = new StockAssetDividendForecastDetailControl(
			$this->forecasts[0]->getId(),
			$this->getService(StockAssetDividendForecastRepository::class),
			$this->getService(CurrencyConversionFacade::class),
			$this->getService(
				StockAssetDividendForecastCashflowProvider::class,
			),
			$jobs,
			$this->getService(StockAssetDividendForecastItemValuesFormFactory::class),
		);
		$this->expectException(BadRequestException::class);
		$control->handleRecalculate($this->forecasts[1]->getId()->toString());
	}

	#[TestWith([StockAssetDividendRecordRepository::class])]
	#[TestWith([StockAssetDividendForecastRepository::class])]
	#[TestWith([StockAssetDividendForecastRecordRepository::class])]
	public function testMissingIdentityNeverReturnsAllRecords(string $repository): void
	{
		$this->expectException(AppAdminNotLoggedInException::class);
		$this->getService($repository)->createQueryBuilder();
	}

	private function processBothOwners(): void
	{
		foreach ([0, 1] as $index) {
			$this->getService(StockAssetDividendRecordFacade::class)->processForAppAdmin($this->owners[$index]);
			$this->getService(StockAssetDividendForecastRecordFacade::class)->recalculate(
				$this->forecasts[$index]->getId(),
			);
		}
	}

	private function forecastRecord(int $index): StockAssetDividendForecastRecord
	{
		$records = $this->getService(
			StockAssetDividendForecastRecordRepository::class,
		)->findByStockAssetDividendForecast(
			$this->forecasts[$index]->getId(),
			$this->owners[$index],
		);
		self::assertCount(1, $records);
		return $records[0];
	}

}
