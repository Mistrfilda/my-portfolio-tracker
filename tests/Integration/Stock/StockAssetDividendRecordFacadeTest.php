<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class StockAssetDividendRecordFacadeTest extends IntegrationTestCase
{

	private StockAssetDividendRecordFacade $dividendRecordFacade;

	private StockAssetDividendRecordRepository $dividendRecordRepository;

	private EntityManagerInterface $entityManager;

	private StockAsset $stockAsset;

	protected function setUp(): void
	{
		parent::setUp();

		$this->dividendRecordFacade = $this->getService(StockAssetDividendRecordFacade::class);
		$this->dividendRecordRepository = $this->getService(StockAssetDividendRecordRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);

		$suffix = bin2hex(random_bytes(4));
		$this->stockAsset = new StockAsset(
			'Record Test Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'REC-' . $suffix,
			StockAssetExchange::NYSE,
			CurrencyEnum::CZK,
			new ImmutableDateTime(),
			isin: null,
			stockAssetDividendSource: StockAssetDividendSourceEnum::WEB,
			dividendTax: 15.0,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
		$this->entityManager->persist($this->stockAsset);
		$this->entityManager->flush();
	}

	public function testGetLastDividendsReturnsLimitedResults(): void
	{
		$countBefore = count($this->dividendRecordFacade->getLastDividends(1000));

		$this->createDividendRecords(5);

		$lastDividends = $this->dividendRecordFacade->getLastDividends($countBefore + 3);
		$this->assertCount($countBefore + 3, $lastDividends);
	}

	public function testGetLastYearDividendRecordsForDashboard(): void
	{
		$this->createDividendRecordsWithDates([
			new ImmutableDateTime('2026-01-15'),
		]);

		$records = $this->dividendRecordFacade->getLastYearDividendRecordsForDashboard();
		$this->assertGreaterThanOrEqual(1, count($records));
		$this->assertLessThanOrEqual(15, count($records));
	}

	public function testGetTotalSummaryPriceForStockAssetReturnsNullWhenNoRecords(): void
	{
		$result = $this->dividendRecordFacade->getTotalSummaryPriceForStockAsset($this->stockAsset);
		$this->assertNull($result);
	}

	public function testGetTotalSummaryPriceForStockAssetWithRecords(): void
	{
		$this->createDividendRecords(2);

		$result = $this->dividendRecordFacade->getTotalSummaryPriceForStockAsset($this->stockAsset);
		$this->assertNotNull($result);
		$this->assertGreaterThan(0.0, $result->getPrice());
	}

	public function testGetTotalSummaryPriceIncludesRecords(): void
	{
		$summaryBefore = $this->dividendRecordFacade->getTotalSummaryPrice();
		$priceBefore = $summaryBefore->getPrice();

		$this->createDividendRecords(2);

		$summaryAfter = $this->dividendRecordFacade->getTotalSummaryPrice();
		$this->assertSame(CurrencyEnum::CZK, $summaryAfter->getCurrency());
		$this->assertGreaterThan($priceBefore, $summaryAfter->getPrice());
	}

	public function testGetDividendsByYearsReturnsDescendingOrder(): void
	{
		$this->createDividendRecordsWithDates([
			new ImmutableDateTime('2025-03-15'),
			new ImmutableDateTime('2024-09-15'),
		]);

		$yearSummaries = $this->dividendRecordFacade->getDividendsByYears();
		$this->assertNotEmpty($yearSummaries);

		$years = array_keys($yearSummaries);
		for ($i = 0; $i < count($years) - 1; $i++) {
			$this->assertGreaterThanOrEqual($years[$i + 1], $years[$i]);
		}
	}

	/**
	 * @param array<ImmutableDateTime> $exDates
	 */
	private function createDividendRecordsWithDates(array $exDates): void
	{
		foreach ($exDates as $index => $exDate) {
			$dividend = new StockAssetDividend(
				$this->stockAsset,
				$exDate,
				$exDate,
				null,
				CurrencyEnum::CZK,
				0.25 * ($index + 1),
				new ImmutableDateTime(),
				StockAssetDividendTypeEnum::REGULAR,
			);
			$this->entityManager->persist($dividend);

			$record = new StockAssetDividendRecord(
				$dividend,
				10,
				0.25 * ($index + 1) * 10,
				CurrencyEnum::CZK,
				null,
				null,
				new ImmutableDateTime(),
			);
			$this->entityManager->persist($record);
		}

		$this->entityManager->flush();
	}

	private function createDividendRecords(int $count): void
	{
		$dates = [];
		for ($i = 0; $i < $count; $i++) {
			$dates[] = new ImmutableDateTime(sprintf('2025-%02d-15', $i + 1));
		}

		$this->createDividendRecordsWithDates($dates);
	}

}
