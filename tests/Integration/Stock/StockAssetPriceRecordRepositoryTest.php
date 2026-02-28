<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetPriceRecordRepositoryTest extends IntegrationTestCase
{

	private StockAssetPriceRecordRepository $priceRecordRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->priceRecordRepository = $this->getService(StockAssetPriceRecordRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testGetById(): void
	{
		$stockAsset = $this->createStockAsset('Price Test Stock', 'PRC-TEST-1');
		$this->entityManager->persist($stockAsset);

		$priceRecord = $this->createPriceRecord($stockAsset, '2025-06-15', 150.50);
		$this->entityManager->persist($priceRecord);
		$this->entityManager->flush();

		$result = $this->priceRecordRepository->getById($priceRecord->getId());

		$this->assertSame($priceRecord->getId(), $result->getId());
		$this->assertSame(150.50, $result->getPrice());
	}

	public function testGetByIdThrowsExceptionWhenNotFound(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->priceRecordRepository->getById(999999);
	}

	public function testFindByStockAssetAndDate(): void
	{
		$stockAsset = $this->createStockAsset('Date Search Stock', 'PRC-DATE-1');
		$this->entityManager->persist($stockAsset);

		$priceRecord = $this->createPriceRecord($stockAsset, '2025-03-20', 175.00);
		$this->entityManager->persist($priceRecord);
		$this->entityManager->flush();

		$result = $this->priceRecordRepository->findByStockAssetAndDate(
			$stockAsset,
			new ImmutableDateTime('2025-03-20'),
		);

		$this->assertNotNull($result);
		$this->assertSame(175.00, $result->getPrice());
	}

	public function testFindByStockAssetAndDateReturnsNullWhenNotFound(): void
	{
		$stockAsset = $this->createStockAsset('No Price Stock', 'PRC-NOPRC-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->flush();

		$result = $this->priceRecordRepository->findByStockAssetAndDate(
			$stockAsset,
			new ImmutableDateTime('2099-01-01'),
		);

		$this->assertNull($result);
	}

	public function testFindByStockAssetSinceDate(): void
	{
		$stockAsset = $this->createStockAsset('Since Date Price Stock', 'PRC-SD-1');
		$this->entityManager->persist($stockAsset);

		$oldRecord = $this->createPriceRecord($stockAsset, '2024-01-10', 100.00);
		$newRecord1 = $this->createPriceRecord($stockAsset, '2025-02-15', 120.00);
		$newRecord2 = $this->createPriceRecord($stockAsset, '2025-03-20', 130.00);
		$this->entityManager->persist($oldRecord);
		$this->entityManager->persist($newRecord1);
		$this->entityManager->persist($newRecord2);
		$this->entityManager->flush();

		$result = $this->priceRecordRepository->findByStockAssetSinceDate(
			$stockAsset,
			new ImmutableDateTime('2025-01-01'),
		);

		$this->assertCount(2, $result);
		$this->assertSame(120.00, $result[0]->getPrice());
		$this->assertSame(130.00, $result[1]->getPrice());
	}

	public function testFindAll(): void
	{
		$stockAsset = $this->createStockAsset('FindAll Price Stock', 'PRC-FA-1');
		$this->entityManager->persist($stockAsset);

		$record1 = $this->createPriceRecord($stockAsset, '2025-01-10', 100.00);
		$record2 = $this->createPriceRecord($stockAsset, '2025-01-11', 105.00);
		$this->entityManager->persist($record1);
		$this->entityManager->persist($record2);
		$this->entityManager->flush();

		$result = $this->priceRecordRepository->findAll();

		$ids = array_map(
			static fn (StockAssetPriceRecord $r): int => $r->getId(),
			$result,
		);

		$this->assertContains($record1->getId(), $ids);
		$this->assertContains($record2->getId(), $ids);
	}

	public function testUpdatePrice(): void
	{
		$stockAsset = $this->createStockAsset('Update Price Stock', 'PRC-UPD-1');
		$this->entityManager->persist($stockAsset);

		$priceRecord = $this->createPriceRecord($stockAsset, '2025-05-01', 200.00);
		$this->entityManager->persist($priceRecord);
		$this->entityManager->flush();

		$priceRecord->updatePrice(210.00, new ImmutableDateTime());
		$this->entityManager->flush();

		$result = $this->priceRecordRepository->getById($priceRecord->getId());
		$this->assertSame(210.00, $result->getPrice());
	}

	public function testFindByIdsReturnsEmptyArrayForEmptyInput(): void
	{
		$result = $this->priceRecordRepository->findByIds([]);
		$this->assertCount(0, $result);
	}

	private function createStockAsset(string $name, string $ticker): StockAsset
	{
		return new StockAsset(
			$name,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			new ImmutableDateTime(),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
	}

	private function createPriceRecord(
		StockAsset $stockAsset,
		string $date,
		float $price,
	): StockAssetPriceRecord
	{
		return new StockAssetPriceRecord(
			new ImmutableDateTime($date),
			CurrencyEnum::USD,
			$price,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			new ImmutableDateTime(),
		);
	}

}
