<?php

declare(strict_types = 1);

namespace App\Test\Integration\Statistic;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Currency\CurrencyConversion;
use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\Currency\CurrencySourceEnum;
use App\Dashboard\DashboardValueGroupEnum;
use App\JobRequest\JobRequestFacade;
use App\Statistic\Performance\Command\PortfolioPerformanceRebuildCommand;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\Performance\PortfolioPerformanceUnableToCalculateException;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticFacade;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortfolioStatisticSaveCommand;
use App\Statistic\PortolioStatisticType;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Test\Integration\Asset\PositionOwnershipFixture;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Monolog\Logger;
use Nette\Caching\Storage;
use Nette\Caching\Storages\MemoryStorage;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

class StatisticBatchOwnershipTest extends IntegrationTestCase
{

	private EntityManagerInterface $em;

	private CurrentAppAdminGetter $currentAdmin;

	private PositionOwnershipFixture $fixture;

	private AppAdmin $emptyAdmin;

	private ImmutableDateTime $now;

	/** @var array<AppAdmin> */
	private array $ownerOrder;

	/** @var array<string, Throwable> */
	private array $failures = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->em = $this->getService(EntityManagerInterface::class);
		$this->fixture = new PositionOwnershipFixture($this->em);
		$this->now = new ImmutableDateTime('2026-02-01 12:00:00');
		$start = new ImmutableDateTime('2026-01-01');
		$end = new ImmutableDateTime('2026-01-31 20:00:00');
		$name = 'batch-empty-' . Uuid::uuid4()->toString();
		$this->emptyAdmin = new AppAdmin($name, $name, $name . '@example.test', 'unused', $this->now, false, false);
		$this->em->persist($this->emptyAdmin);
		$this->ownerOrder = [...$this->fixture->admins, $this->emptyAdmin];
		$owners = $this->getMockBuilder(AppAdminRepository::class)
			->setConstructorArgs([AppAdmin::class, $this->em])
			->onlyMethods(['findAll'])->getMock();
		// Keep real owner reloads after EntityManager::clear(), limit the batch to this test's accounts.
		$owners->expects(self::atLeastOnce())->method('findAll')->willReturnCallback(fn (): array => $this->ownerOrder);
		$this->replaceService(AppAdminRepository::class, $owners);
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
		$this->replaceService(JobRequestFacade::class, $this->createStub(JobRequestFacade::class));
		$logger = $this->createStub(Logger::class);
		$logger->method('error')->willReturnCallback(function (string $message, array $context = []): void {
			if (isset($context['appAdminId'], $context['exception'])) {
				$this->failures[$context['appAdminId']] = $context['exception'];
			}
		});
		$this->replaceService(LoggerInterface::class, $logger);
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
			$this->snapshot($owner, $start, 1000 * $factor, 1000 * $factor);
			$this->snapshot($owner, $end, 1000 * $factor, (1200 + 100 * $factor) * $factor);
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
			$this->fixture->portuPositions[$index]->updateCurrentValue(
				new AssetPriceEmbeddable(1200 * $factor, CurrencyEnum::CZK),
				new AssetPriceEmbeddable(1100 * $factor, CurrencyEnum::CZK),
			);
		}

		$stockPrice = new StockAssetPriceRecord(
			$end,
			CurrencyEnum::USD,
			110,
			$this->fixture->stock,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$this->now,
		);
		$cryptoPrice = new CryptoAssetPriceRecord($end, CurrencyEnum::USD, 110, $this->fixture->crypto, $this->now);
		$this->em->persist($stockPrice);
		$this->em->persist($cryptoPrice);
		$this->fixture->stock->setCurrentPrice($stockPrice, $this->now);
		$this->fixture->crypto->setCurrentPrice($cryptoPrice, $this->now);
		$this->em->flush();
		$this->em->clear();
		$this->currentAdmin = $this->getService(CurrentAppAdminGetter::class);
		$this->currentAdmin->beginApiRequest();
	}

	protected function tearDown(): void
	{
		$this->currentAdmin->endApiRequest();
		parent::tearDown();
	}

	public function testCommandsProcessAllAccountsInEitherOrderWithoutIdentityAndAppendFreshPerformance(): void
	{
		$this->seedPerformanceAndCache();
		$save = new CommandTester($this->getService(PortfolioStatisticSaveCommand::class));
		$records = $this->getService(PortfolioStatisticRecordRepository::class);
		$months = $this->getService(PortfolioPerformanceMonthRepository::class);
		$total = $this->getService(PortfolioStatisticTotalValueProvider::class);
		$previous = [];
		foreach ([1, 2] as $run) {
			self::assertSame(Command::SUCCESS, $save->execute([]), $save->getDisplay());
			foreach ($this->fixture->admins as $index => $owner) {
				self::assertCount(2 + $run, $records->findAll($owner));
				$record = $records->findLast($owner);
				self::assertNotNull($record);
				self::assertTrue($owner->getId()->equals($record->getAppAdmin()->getId()));
				$values = [];
				foreach ($record->getPortfolioStatistics() as $value) {
					$values[$value->getLabel()] = $value->getValue();
				}

				self::assertSame('22.50 %', $values['Výkonnost portfolia (TWR)']);
				self::assertSame(
					$index === 0 ? '1 200 CZK' : '2 400 CZK',
					$record->getPortfolioStatisticByType(PortolioStatisticType::TOTAL_VALUE_IN_CZK)?->getValue(),
				);
				$performance = $months->findAllOrdered($owner);
				self::assertCount(2, $performance);
				self::assertSame(105.0 * ($index + 1), $performance[0]->getRealizedProfit());
				self::assertSame(20.0 * ($index + 1), $performance[0]->getNetDividends());
				self::assertSame(1200.0 * ($index + 1), $total->getAllTimeValue($owner)?->getValueAtEnd());
				$state = [$values, $this->monthRows($owner, false)];
				if ($run === 2) {
					self::assertSame($previous[$index], $state);
				}

				$previous[$index] = $state;
			}

			self::assertCount($run, $records->findAll($this->emptyAdmin));
			self::assertSame(
				'0 CZK',
				$records->findLast($this->emptyAdmin)?->getPortfolioStatisticByType(
					PortolioStatisticType::TOTAL_VALUE_IN_CZK,
				)?->getValue(),
			);
			self::assertSame([], $months->findAllOrdered($this->emptyAdmin));
			self::assertNull($total->getAllTimeValue($this->emptyAdmin));
			$this->ownerOrder = array_reverse($this->ownerOrder);
		}

		$rebuild = new CommandTester($this->getService(PortfolioPerformanceRebuildCommand::class));
		self::assertSame(Command::SUCCESS, $rebuild->execute([]));
		foreach ($this->fixture->admins as $index => $owner) {
			self::assertCount(4, $records->findAll($owner));
			self::assertSame($previous[$index][1], $this->monthRows($owner, false));
		}

		self::assertCount(2, $records->findAll($this->emptyAdmin));
		self::assertSame([], $this->failures);
		self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
	}

	public function testInvalidHistoryRollsBackItsSnapshotAndContinuesWithOtherAccounts(): void
	{
		$this->seedPerformanceAndCache();
		$owner = $this->fixture->admins[0];
		$record = $this->getService(PortfolioStatisticRecordRepository::class)->findFirst($owner);
		self::assertNotNull($record);
		$this->em->getConnection()->update('portfolio_statistic', ['value' => '0'], [
			'portfolio_statistic_record_id' => $record->getId(),
			'type' => PortolioStatisticType::TOTAL_VALUE_IN_CZK->value,
		]);
		$this->em->clear();
		$before = $this->monthRows($owner);
		$save = new CommandTester($this->getService(PortfolioStatisticSaveCommand::class));
		self::assertSame(Command::FAILURE, $save->execute([]));
		self::assertInstanceOf(
			PortfolioPerformanceUnableToCalculateException::class,
			$this->failures[(string) $owner->getId()],
		);
		self::assertSame($before, $this->monthRows($owner));
		$this->assertFailedAccountIsUnchangedAndOtherAccountsFinished();
	}

	#[TestWith([false])]
	#[TestWith([true])]
	public function testFailureAfterAppendRestoresPreviousMonthsSnapshotsAndCachedValues(bool $reverseOrder): void
	{
		$this->seedPerformanceAndCache();
		if ($reverseOrder) {
			$this->ownerOrder = array_reverse($this->ownerOrder);
		}

		$owner = $this->fixture->admins[0];
		$before = $this->monthRows($owner);
		$statistics = $this->getService(PortfolioStatisticFacade::class);
		$failingStatistics = $this->createStub(PortfolioStatisticFacade::class);
		$failingStatistics->method('saveCurrentDashboardValues')->willReturnCallback(
			$statistics->saveCurrentDashboardValues(...),
		);
		$failingStatistics->method('appendPortfolioPerformanceValues')->willReturnCallback(
			static function (PortfolioStatisticRecord $record) use ($statistics, $owner): void {
				$statistics->appendPortfolioPerformanceValues($record);
				if ($record->getAppAdmin()->getId()->equals($owner->getId())) {
					throw new RuntimeException('Synthetic failure after saving performance');
				}
			},
		);
		$this->replaceService(PortfolioStatisticFacade::class, $failingStatistics);
		$save = new CommandTester($this->getService(PortfolioStatisticSaveCommand::class));
		self::assertSame(Command::FAILURE, $save->execute([]));
		self::assertSame($before, $this->monthRows($owner));
		self::assertSame(
			'Synthetic failure after saving performance',
			$this->failures[(string) $owner->getId()]->getMessage(),
		);
		$this->assertFailedAccountIsUnchangedAndOtherAccountsFinished();
	}

	private function assertFailedAccountIsUnchangedAndOtherAccountsFinished(): void
	{
		$records = $this->getService(PortfolioStatisticRecordRepository::class);
		self::assertCount(2, $records->findAll($this->fixture->admins[0]));
		self::assertSame(4, array_sum(array_map(
			static fn (PortfolioStatisticRecord $record): int => $record->getPortfolioStatistics()->count(),
			$records->findAll($this->fixture->admins[0]),
		)));
		self::assertCount(3, $records->findAll($this->fixture->admins[1]));
		self::assertCount(1, $records->findAll($this->emptyAdmin));
		$total = $this->getService(PortfolioStatisticTotalValueProvider::class);
		self::assertSame(1300.0, $total->getAllTimeValue($this->fixture->admins[0])?->getValueAtEnd());
		self::assertSame(2400.0, $total->getAllTimeValue($this->fixture->admins[1])?->getValueAtEnd());
		self::assertCount(1, $this->failures);
		self::assertTrue($this->em->isOpen());
		self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
	}

	private function seedPerformanceAndCache(): void
	{
		foreach ($this->fixture->admins as $index => $owner) {
			$managedOwner = $this->getService(AppAdminRepository::class)->findById($owner->getId());
			self::assertSame(1, $this->getService(PortfolioPerformanceRebuildFacade::class)->rebuild($managedOwner));
			self::assertSame(
				$index === 0 ? 1300.0 : 2800.0,
				$this->getService(PortfolioStatisticTotalValueProvider::class)->getAllTimeValue(
					$owner,
				)?->getValueAtEnd(),
			);
		}
	}

	/** @return array<array<string, mixed>> */
	private function monthRows(AppAdmin $owner, bool $includeId = true): array
	{
		$rows = $this->em->getConnection()->fetchAllAssociative(
			'SELECT * FROM portfolio_performance_month WHERE app_admin_id = ? ORDER BY period_month',
			[$owner->getId()->toString()],
		);
		if (!$includeId) {
			foreach ($rows as &$row) {
				unset($row['id']);
			}
		}

		return $rows;
	}

	private function snapshot(AppAdmin $owner, ImmutableDateTime $date, float $invested, float $value): void
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
				PortolioStatisticType::from(
					$type,
				),
				PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
				null,
			));
		}
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
