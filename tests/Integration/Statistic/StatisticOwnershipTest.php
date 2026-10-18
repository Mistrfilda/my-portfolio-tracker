<?php

declare(strict_types = 1);

namespace App\Test\Integration\Statistic;

use App\Admin\AppAdmin;
use App\Admin\AppAdminNotLoggedInException;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Dashboard\DashboardValueGroupEnum;
use App\Doctrine\NoEntityFoundException;
use App\FinancialIndependence\FinancialIndependenceDefaultsProvider;
use App\JobRequest\JobRequestFacade;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Statistic\Performance\PortfolioPerformanceEventProvider;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticFacade;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticPresetEnum;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticRepository;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticFacade;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortfolioStatisticRepository;
use App\Statistic\PortolioStatisticType;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use App\Statistic\Total\PortfolioTotalStatisticControlFacade;
use App\Statistic\UI\Chart\PortfolioTotalValueChartProvider;
use App\Statistic\UI\Chart\PortfolioTotalValueLastMonthChartProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Test\Integration\Asset\PositionOwnershipFixture;
use App\Test\Integration\IntegrationTestCase;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Extension\Webpack\WebpackAssetsFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenterFactory;
use Nette\Application\Request;
use Nette\Bootstrap\Configurator;
use Nette\Caching\Storage;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Http\IRequest;
use Nette\Http\Request as HttpRequest;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\TestWith;
use Ramsey\Uuid\Uuid;

class StatisticOwnershipTest extends IntegrationTestCase
{

	private EntityManagerInterface $em;

	private CurrentAppAdminGetter $currentAdmin;

	private PositionOwnershipFixture $fixture;

	private AppAdmin $emptyAdmin;

	private ImmutableDateTime $start;

	private ImmutableDateTime $end;

	private ImmutableDateTime $now;

	/** @var array<int, array<PortfolioStatisticRecord>> */
	private array $records = [];

	/** @var array<string> */
	private array $queuedReports = [];

	protected function configureContainer(Configurator $configurator): void
	{
		$configurator->addConfig(['session' => ['autoStart' => 'never']]);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$this->fixture = new PositionOwnershipFixture($this->em);
		$this->start = new ImmutableDateTime('2026-01-01');
		$this->end = new ImmutableDateTime('2026-01-31 20:00:00');
		$this->now = new ImmutableDateTime('2026-02-01 12:00:00');
		$name = 'empty-statistics-' . Uuid::uuid4()->toString();
		$this->emptyAdmin = new AppAdmin($name, $name, $name . '@example.test', 'unused', $this->now, false, false);
		$this->em->persist($this->emptyAdmin);
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($this->now);
		$clock->method('createToday')->willReturn($this->now->setTime(0, 0));
		$this->replaceService(DatetimeFactory::class, $clock);
		$conversion = $this->createStub(CurrencyConversionRepository::class);
		$pair = fn (CurrencyEnum $from, CurrencyEnum $to): CurrencyConversion => new CurrencyConversion(
			$from,
			$to,
			1,
			CurrencySourceEnum::CNB,
			$this->now,
			$this->now,
		);
		$conversion->method('getCurrentCurrencyPairConversion')->willReturnCallback($pair);
		$conversion->method('findCurrencyPairConversionForClosestDate')->willReturnCallback($pair);
		$this->replaceService(CurrencyConversionRepository::class, $conversion);
		$this->replaceService(Storage::class, new MemoryStorage());
		$queue = $this->createStub(JobRequestFacade::class);
		$queue->method('addPortfolioPeriodStatisticProcessToQueue')->willReturnCallback(function (string $id): void {
			$this->queuedReports[] = $id;
		});
		$this->replaceService(JobRequestFacade::class, $queue);
		$this->replaceService(WebpackAssetsFactory::class, $this->createStub(WebpackAssetsFactory::class));
		$session = $this->createStub(Session::class);
		$session->method('getSection')->willReturn($this->createStub(SessionSection::class));
		$this->replaceService(Session::class, $session);
		$dividend = new StockAssetDividend(
			$this->fixture->stock,
			new ImmutableDateTime('2026-01-15'),
			new ImmutableDateTime('2026-01-20'),
			null,
			CurrencyEnum::CZK,
			2,
			$this->now,
		);
		$this->em->persist($dividend);
		foreach ($this->fixture->admins as $index => $owner) {
			$factor = $index + 1;
			$this->records[$index] = [
				$this->snapshot($owner, $this->start, 1000 * $factor, 1000 * $factor),
				$this->snapshot($owner, $this->end, 1000 * $factor, (1200 + 100 * $factor) * $factor),
			];
			$this->em->persist(new StockAssetDividendRecord(
				$dividend,
				10 * $factor,
				20 * $factor,
				CurrencyEnum::CZK,
				null,
				null,
				$this->now,
				$owner,
			));
			$this->em->persist(new PortuAssetPriceRecord(
				$this->end,
				CurrencyEnum::CZK,
				new AssetPriceEmbeddable(1500 * $factor, CurrencyEnum::CZK),
				new AssetPriceEmbeddable(1100 * $factor, CurrencyEnum::CZK),
				$this->fixture->portuPositions[$index],
				$this->now,
			));
		}

		foreach ([$this->start, $this->end] as $date) {
			$stockPrice = new StockAssetPriceRecord(
				$date,
				CurrencyEnum::USD,
				110,
				$this->fixture->stock,
				StockAssetPriceDownloaderEnum::TWELVE_DATA,
				$this->now,
			);
			$cryptoPrice = new CryptoAssetPriceRecord(
				$date,
				CurrencyEnum::USD,
				110,
				$this->fixture->crypto,
				$this->now,
			);
			$this->em->persist($stockPrice);
			$this->em->persist($cryptoPrice);
			$this->fixture->stock->setCurrentPrice($stockPrice, $this->now);
			$this->fixture->crypto->setCurrentPrice($cryptoPrice, $this->now);
		}

		$this->em->flush();
		foreach ([$this->fixture->stock, $this->fixture->crypto, $this->fixture->portu, $this->fixture->otherPortu] as $asset) {
			$this->em->refresh($asset);
		}

		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testQueriesAndChartsSeparateMatchingDatesAndRejectForeignIds(): void
	{
		$records = $this->getService(PortfolioStatisticRecordRepository::class);
		$values = $this->getService(PortfolioStatisticRepository::class);
		foreach ([0, 1, 0] as $index) {
			$owner = $this->fixture->admins[$index];
			$this->currentAdmin->setApiAppAdmin($owner);
			$expected = $this->records[$index];
			self::assertSame($expected, $records->findAll());
			self::assertSame($expected[0], $records->findFirst());
			self::assertSame($expected[1], $records->findLast());
			self::assertSame($expected[0], $records->findFirstBetweenDates($this->start, $this->end));
			self::assertSame($expected[1], $records->findLastBetweenDates($this->start, $this->end));
			self::assertSame($expected, $records->findBetweenDates($this->start, $this->end));
			self::assertSame($expected, $records->findMinMaxDateByMonth(2026));
			self::assertSame([$expected[0]], $records->findByIds([
				$this->records[0][0]->getId(), $this->records[1][0]->getId(),
			]));
			self::assertSame($expected[0], $records->getById($expected[0]->getId()));
			$this->assertNotFound(fn () => $records->getById($this->records[1 - $index][0]->getId()));
			$endValue = (1200 + 100 * ($index + 1)) * ($index + 1);
			self::assertSame(
				[1000.0 * ($index + 1), (float) $endValue],
				array_column(
					$records->findDailyPerformanceValuesBetweenDates($this->start, $this->end),
					'portfolioValue',
				),
			);
			self::assertSame(
				[(string) (1000 * ($index + 1)), (string) $endValue],
				array_column($records->findDailyChartValuesBetweenDates($this->start, $this->end), 'portfolioValue'),
			);
			self::assertCount(4, $values->createQueryBuilder()->getQuery()->getResult());
			self::assertCount(2, $values->getPortfolioTotalValueForType(PortolioStatisticType::TOTAL_VALUE_IN_CZK, 31));
			self::assertCount(2, $values->getPortfolioTotalValueForTypeForGreaterDate(
				PortolioStatisticType::TOTAL_VALUE_IN_CZK,
				$this->start,
			));
			foreach ([PortfolioTotalValueChartProvider::class, PortfolioTotalValueLastMonthChartProvider::class] as $class) {
				$chart = clone $this->getService($class);
				$chart->addType(PortolioStatisticType::TOTAL_VALUE_IN_CZK);
				$data = $chart->getChartData()->jsonSerialize()['datasets'][0]['data'];
				self::assertSame(
					$class === PortfolioTotalValueChartProvider::class
						? [1000 * ($index + 1)]
						: [1000 * ($index + 1), $endValue],
					$data,
				);
			}
		}

		$this->currentAdmin->beginApiRequest();
		self::assertSame($this->records[1], $records->findAll($this->fixture->admins[1]));
	}

	public function testRebuildAndCacheInvalidationNeverChangeOtherOwnersHistory(): void
	{
		$rebuild = $this->getService(PortfolioPerformanceRebuildFacade::class);
		$months = $this->getService(PortfolioPerformanceMonthRepository::class);
		$total = $this->getService(PortfolioStatisticTotalValueProvider::class);
		$events = $this->getService(PortfolioPerformanceEventProvider::class);
		foreach ([0, 1, 1, 0] as $index) {
			$owner = $this->fixture->admins[$index];
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1 - $index]);
			self::assertSame(1, $rebuild->rebuild($owner));
			$month = $months->findAllOrdered($owner)[0];
			self::assertSame($owner, $month->getAppAdmin());
			self::assertSame(105.0 * ($index + 1), $month->getRealizedProfit());
			self::assertSame(20.0 * ($index + 1), $month->getNetDividends());
			self::assertSame($month, $months->findLastEndingAtOrBefore($this->end, $owner));
			self::assertSame(
				105.0 * ($index + 1),
				$events->getIncomeBetween($this->start, $this->end, $owner)->realizedProfit,
			);
		}

		$otherOwner = $this->fixture->admins[1];
		$otherMonth = $months->findAllOrdered($otherOwner)[0];
		$otherCachedValue = $total->getAllTimeValue($otherOwner);
		self::assertNotNull($otherCachedValue);
		self::assertSame(2800.0, $otherCachedValue->getValueAtEnd());
		foreach ([0, 1, 0] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			self::assertSame(
				(float) ((1200 + 100 * ($index + 1)) * ($index + 1)),
				$total->getAllTimeValue()?->getValueAtEnd(),
			);
		}

		$this->snapshot($this->fixture->admins[0], $this->now, 1000, 1600);
		$this->em->flush();
		self::assertSame(2, $rebuild->rebuild($this->fixture->admins[0]));
		self::assertSame(1600.0, $total->getAllTimeValue($this->fixture->admins[0])?->getValueAtEnd());
		self::assertSame($otherCachedValue, $total->getAllTimeValue($otherOwner));
		self::assertSame([$otherMonth], $months->findAllOrdered($otherOwner));
		self::assertSame(2, count($this->getService(PortfolioStatisticRecordRepository::class)->findAll($otherOwner)));

		$db = $this->getService(Connection::class);
		$duplicate = $db->fetchAssociative(
			'SELECT * FROM portfolio_performance_month WHERE id = ?',
			[$otherMonth->getId()],
		);
		self::assertIsArray($duplicate);
		unset($duplicate['id']);
		$this->expectException(UniqueConstraintViolationException::class);
		$db->insert('portfolio_performance_month', $duplicate);
	}

	public function testReportsUseStoredOwnerForSnapshotsAssetsDividendsAndJobs(): void
	{
		$facade = $this->getService(PortfolioPeriodStatisticFacade::class);
		$repository = $this->getService(PortfolioPeriodStatisticRepository::class);
		$reports = [];
		foreach ($this->fixture->admins as $owner) {
			$this->currentAdmin->setApiAppAdmin($owner);
			$reports[] = $facade->create($this->start, $this->end);
		}

		self::assertCount(2, $this->queuedReports);
		foreach ([1, 0] as $index) {
			$this->currentAdmin->beginApiRequest();
			if ($index === 1) {
				$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
			}

			$report = $reports[$index];
			$facade->process($report->getId()->toString());
			self::assertTrue($report->isCompleted());
			self::assertSame($this->fixture->admins[$index], $report->getAppAdmin());
			self::assertSame(1000.0 * ($index + 1), $report->getSummary()?->investedAtStart);
			self::assertSame(105.0 * ($index + 1), $report->getSummary()?->closedPositionsProfit);
			self::assertSame(20.0 * ($index + 1), $report->getDividendSection()?->netTotalCzk);
			$assets = $report->getAssetSection()?->assets;
			self::assertNotNull($assets);
			self::assertCount(3, $assets);
			$byType = [];
			foreach ($assets as $asset) {
				$byType[$asset->assetType] = $asset;
			}

			self::assertSame(1000.0 * ($index + 1), $byType['stock']->purchasesCzk);
			self::assertSame(100.0 * ($index + 1), $byType['stock']->capitalResultCzk);
			self::assertSame(50.0 * ($index + 1), $byType['crypto']->purchasesCzk);
			self::assertSame(
				$this->fixture->portuPositions[$index]->getAsset()->getId()->toString(),
				$byType['portu']->assetId,
			);
			self::assertSame(
				(float) ((1200 + 100 * ($index + 1)) * ($index + 1)),
				$report->getChartSection()?->portfolioValues[1]->value,
			);
			$original = $report->getSummary();
			$facade->process($report->getId()->toString());
			self::assertEquals($original, $report->getSummary());
		}

		foreach ([0, 1, 0] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			self::assertSame([$reports[$index]], $repository->createQueryBuilder()->getQuery()->getResult());
			self::assertSame($reports[$index], $facade->get($reports[$index]->getId()->toString()));
			$foreignId = $reports[1 - $index]->getId()->toString();
			foreach ([static fn () => $facade->get($foreignId), static fn () => $facade->retry(
				$foreignId,
			), static fn () => $facade->regenerate($foreignId)] as $attempt) {
				$this->assertNotFound($attempt);
			}
		}

		$newReport = $facade->regenerate($reports[0]->getId()->toString());
		self::assertSame($reports[0]->getAppAdmin(), $newReport->getAppAdmin());
		$newReport->markFailed('Synthetic retry', $this->now);
		$facade->retry($newReport->getId()->toString());
		self::assertSame($reports[0]->getAppAdmin(), $newReport->getAppAdmin());
		self::assertCount(4, $this->queuedReports);
	}

	public function testYearlySummariesAndContributionDefaultsFollowTheCurrentOwner(): void
	{
		$rebuild = $this->getService(PortfolioPerformanceRebuildFacade::class);
		$yearly = $this->getService(PortfolioTotalStatisticControlFacade::class);
		$defaults = $this->getService(FinancialIndependenceDefaultsProvider::class);
		foreach ($this->fixture->admins as $index => $owner) {
			$this->snapshot($owner, $this->end->modify('+1 hour'), 2000 * ($index + 1), 2300 * ($index + 1));
		}

		$this->em->flush();
		foreach ([0, 1, 0] as $index) {
			$owner = $this->fixture->admins[$index];
			$rebuild->rebuild($owner);
			$this->currentAdmin->setApiAppAdmin($owner);
			$groups = $yearly->getData();
			self::assertCount(1, $groups);
			self::assertCount(1, $groups[0]->getValues());
			self::assertSame(2300.0 * ($index + 1), $groups[0]->getYearValue()->getValueAtEnd());
			self::assertSame(105.0 * ($index + 1), $groups[0]->getYearValue()->getClosedPositionsProfitInPeriod());
			self::assertSame(20.0 * ($index + 1), $groups[0]->getYearValue()->getDividendsInPeriod());
			$input = $defaults->provide();
			self::assertSame(1, $input->contributionRecordCount);
			self::assertEqualsWithDelta(
				875.0 * ($index + 1) / 12,
				$input->calculationInput->monthlyContributionCzk,
				0.00001,
			);
			self::assertEquals($this->start, $input->historicalReturnPeriodStart);
			self::assertEquals($this->end->modify('+1 hour'), $input->historicalReturnPeriodEnd);
		}
	}

	public function testRebuildRefreshesChangedOwnerIncomeWithinTheSameProcess(): void
	{
		$events = $this->getService(PortfolioPerformanceEventProvider::class);
		$rebuild = $this->getService(PortfolioPerformanceRebuildFacade::class);
		foreach ($this->fixture->admins as $index => $owner) {
			self::assertSame(
				20.0 * ($index + 1),
				$events->getIncomeBetween($this->start, $this->end, $owner)->netDividends,
			);
		}

		$record = $this->getService(StockAssetDividendRecordRepository::class)->findAll($this->fixture->admins[0])[0];
		$record->update(10, 70, CurrencyEnum::CZK, null, null, $this->now);
		$this->em->flush();
		$rebuild->rebuild($this->fixture->admins[0]);
		self::assertSame(
			70.0,
			$events->getIncomeBetween($this->start, $this->end, $this->fixture->admins[0])->netDividends,
		);
		self::assertSame(
			40.0,
			$events->getIncomeBetween($this->start, $this->end, $this->fixture->admins[1])->netDividends,
		);
		self::assertSame(
			70.0,
			$this->getService(PortfolioPerformanceMonthRepository::class)->findAllOrdered(
				$this->fixture->admins[0],
			)[0]->getNetDividends(),
		);
	}

	public function testAllTimeReportPresetStartsAtTheOwnersFirstSnapshot(): void
	{
		$earlier = new ImmutableDateTime('2025-12-01');
		$this->snapshot($this->fixture->admins[0], $earlier, 1000, 1000);
		$this->em->flush();
		$facade = $this->getService(PortfolioPeriodStatisticFacade::class);
		foreach ([0, 1, 0] as $index) {
			$this->currentAdmin->setApiAppAdmin($this->fixture->admins[$index]);
			$report = $facade->createForPreset(PortfolioPeriodStatisticPresetEnum::ALL);
			self::assertEquals($index === 0 ? $earlier : $this->start, $report->getRequestedStartAt());
			self::assertSame($this->fixture->admins[$index], $report->getAppAdmin());
		}

		$this->currentAdmin->setApiAppAdmin($this->emptyAdmin);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('No portfolio statistic snapshot is available.');
		$facade->createForPreset(PortfolioPeriodStatisticPresetEnum::ALL);
	}

	public function testSnapshotAndPerformanceAppendUseExplicitOwnerWithAnotherUserLoggedIn(): void
	{
		$facade = $this->getService(PortfolioStatisticFacade::class);
		$rebuild = $this->getService(PortfolioPerformanceRebuildFacade::class);
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$record = $facade->saveCurrentDashboardValues($this->fixture->admins[0], false);
		self::assertSame($this->fixture->admins[0], $record->getAppAdmin());
		self::assertNotEmpty($record->getPortfolioStatistics()->toArray());
		self::assertSame(
			'1 200 CZK',
			$record->getPortfolioStatisticByType(PortolioStatisticType::TOTAL_VALUE_IN_CZK)?->getValue(),
		);
		$initialCount = $record->getPortfolioStatistics()->count();
		$rebuild->rebuild($record->getAppAdmin());
		$this->currentAdmin->beginApiRequest();
		$facade->appendPortfolioPerformanceValues($record);
		self::assertGreaterThan($initialCount, $record->getPortfolioStatistics()->count());
		foreach ($record->getPortfolioStatistics() as $value) {
			self::assertSame($record, $value->getPortfolioStatisticRecord());
		}
	}

	public function testEmptyAccountAndZeroSnapshotsHaveNoForeignPerformance(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->emptyAdmin);
		$total = $this->getService(PortfolioStatisticTotalValueProvider::class);
		$performance = $this->getService(PortfolioPerformanceProvider::class);
		self::assertNull($total->getAllTimeValue());
		self::assertNull($performance->getAllTimeSummary());
		self::assertSame([], $this->getService(PortfolioTotalStatisticControlFacade::class)->getData());
		self::assertSame(0.0, $performance->getIncomeBetween($this->start, $this->end)->realizedProfit);
		self::assertSame(0.0, $performance->getIncomeBetween($this->start, $this->end)->netDividends);
		self::assertNull(
			$this->getService(
				FinancialIndependenceDefaultsProvider::class,
			)->provide()->historicalAnnualizedTotalReturnPercentage,
		);
		$this->snapshot($this->emptyAdmin, $this->start, 0, 0);
		$this->snapshot($this->emptyAdmin, $this->end, 0, 0);
		$this->em->flush();
		self::assertSame(0, $this->getService(PortfolioPerformanceRebuildFacade::class)->rebuild($this->emptyAdmin));
		self::assertNull($total->getAllTimeValue());
		self::assertNull($performance->getSummaryBetween($this->start, $this->end));
	}

	public function testMissingIdentityCannotReadHistoryEvenAfterCacheWasPopulated(): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$total = $this->getService(PortfolioStatisticTotalValueProvider::class);
		$total->getAllTimeValue();
		$this->currentAdmin->beginApiRequest();
		foreach ([
			static fn () => $total->getAllTimeValue(),
			fn () => $this->getService(PortfolioStatisticRecordRepository::class)->findAll(),
			fn () => $this->getService(PortfolioStatisticRepository::class)->createQueryBuilder(),
			fn () => $this->getService(PortfolioPerformanceMonthRepository::class)->findAllOrdered(),
			fn () => $this->getService(PortfolioPeriodStatisticRepository::class)->createQueryBuilder(),
			fn () => $this->getService(PortfolioPerformanceEventProvider::class)->getIncomeBetween(
				$this->start,
				$this->end,
			),
		] as $attempt) {
			try {
				$attempt();
				self::fail('Missing identity must not expose personal statistics.');
			} catch (AppAdminNotLoggedInException) {
				self::assertTrue(true);
			}
		}
	}

	#[TestWith(['PortfolioStatisticRecord'])]
	#[TestWith(['PortfolioPeriodStatistic'])]
	public function testForeignWebDetailReturnsNotFound(string $name): void
	{
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[0]);
		$report = $this->getService(PortfolioPeriodStatisticFacade::class)->create($this->start, $this->end);
		$id = $name === 'PortfolioStatisticRecord' ? $this->records[0][0]->getId() : $report->getId()->toString();
		$this->currentAdmin->setApiAppAdmin($this->fixture->admins[1]);
		$this->replaceService(IRequest::class, new HttpRequest(new UrlScript('http://localhost/')));
		$presenter = $this->getService(IPresenterFactory::class)->createPresenter('Admin:' . $name);
		assert($presenter instanceof BaseAdminPresenter);
		$presenter->autoCanonicalize = false;
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);
		$presenter->run(new Request('Admin:' . $name, 'GET', ['action' => 'detail', 'id' => $id]));
	}

	private function snapshot(
		AppAdmin $owner,
		ImmutableDateTime $date,
		float $invested,
		float $value,
	): PortfolioStatisticRecord
	{
		$record = new PortfolioStatisticRecord($owner, $date);
		$this->em->persist($record);
		foreach ([
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK->value => $invested,
			PortolioStatisticType::TOTAL_VALUE_IN_CZK->value => $value,
		] as $type => $amount) {
			$this->em->persist(new PortfolioStatistic(
				$record,
				$date,
				DashboardValueGroupEnum::TOTAL_VALUES,
				$type,
				(string) $amount,
				'blue',
				null,
				null,
				PortolioStatisticType::from($type),
				PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
				null,
			));
		}

		return $record;
	}

	private function assertNotFound(callable $attempt): void
	{
		try {
			$attempt();
		} catch (NoEntityFoundException) {
			self::assertTrue(true);
			return;
		}

		self::fail('A foreign statistic must not be accessible.');
	}

	/** @param class-string $type */
	private function replaceService(string $type, object $service): void
	{
		foreach ($this->container->findByType($type) as $name) {
			$this->container->removeService($name);
			$this->container->addService($name, $service);
		}
	}

}
