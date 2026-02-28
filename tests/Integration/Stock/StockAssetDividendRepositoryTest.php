<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class StockAssetDividendRepositoryTest extends IntegrationTestCase
{

	private StockAssetDividendRepository $stockAssetDividendRepository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->stockAssetDividendRepository = $this->getService(StockAssetDividendRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testGetById(): void
	{
		$stockAsset = $this->createStockAsset('Dividend Stock', 'DIV-TEST-1');
		$this->entityManager->persist($stockAsset);

		$dividend = $this->createDividend($stockAsset, '2025-06-15', 0.82);
		$this->entityManager->persist($dividend);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->getById($dividend->getId());

		$this->assertSame($dividend->getId()->toString(), $result->getId()->toString());
		$this->assertSame(0.82, $result->getAmount());
	}

	public function testGetByIdThrowsExceptionWhenNotFound(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->stockAssetDividendRepository->getById(Uuid::uuid4());
	}

	public function testFindByStockAsset(): void
	{
		$stockAsset1 = $this->createStockAsset('Stock A', 'DIV-SA-1');
		$stockAsset2 = $this->createStockAsset('Stock B', 'DIV-SB-1');
		$this->entityManager->persist($stockAsset1);
		$this->entityManager->persist($stockAsset2);

		$div1 = $this->createDividend($stockAsset1, '2025-03-15', 0.50);
		$div2 = $this->createDividend($stockAsset1, '2025-06-15', 0.55);
		$div3 = $this->createDividend($stockAsset2, '2025-03-15', 1.00);
		$this->entityManager->persist($div1);
		$this->entityManager->persist($div2);
		$this->entityManager->persist($div3);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->findByStockAsset($stockAsset1);

		$this->assertCount(2, $result);

		$amounts = array_map(
			static fn (StockAssetDividend $d): float => $d->getAmount(),
			$result,
		);

		$this->assertContains(0.50, $amounts);
		$this->assertContains(0.55, $amounts);
	}

	public function testFindOneByStockAssetExDate(): void
	{
		$stockAsset = $this->createStockAsset('ExDate Stock', 'DIV-EX-1');
		$this->entityManager->persist($stockAsset);

		$exDate = new ImmutableDateTime('2025-09-15');
		$dividend = $this->createDividend($stockAsset, '2025-09-15', 0.75);
		$this->entityManager->persist($dividend);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->findOneByStockAssetExDate($stockAsset, $exDate);

		$this->assertNotNull($result);
		$this->assertSame(0.75, $result->getAmount());
	}

	public function testFindOneByStockAssetExDateReturnsNullWhenNotFound(): void
	{
		$stockAsset = $this->createStockAsset('ExDate Missing', 'DIV-EXM-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->findOneByStockAssetExDate(
			$stockAsset,
			new ImmutableDateTime('2099-01-01'),
		);

		$this->assertNull($result);
	}

	public function testFindByStockAssetSinceDate(): void
	{
		$stockAsset = $this->createStockAsset('Since Date Stock', 'DIV-SD-1');
		$this->entityManager->persist($stockAsset);

		$oldDividend = $this->createDividend($stockAsset, '2024-01-15', 0.30);
		$newDividend = $this->createDividend($stockAsset, '2025-06-15', 0.40);
		$this->entityManager->persist($oldDividend);
		$this->entityManager->persist($newDividend);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->findByStockAssetSinceDate(
			$stockAsset,
			new ImmutableDateTime('2025-01-01'),
		);

		$this->assertCount(1, $result);
		$this->assertSame(0.40, $result[0]->getAmount());
	}

	public function testFindGreaterThan(): void
	{
		$stockAsset = $this->createStockAsset('Greater Than Stock', 'DIV-GT-1');
		$this->entityManager->persist($stockAsset);

		$pastDividend = $this->createDividend($stockAsset, '2020-01-01', 0.10);
		$futureDividend = $this->createDividend($stockAsset, '2099-06-01', 0.20);
		$this->entityManager->persist($pastDividend);
		$this->entityManager->persist($futureDividend);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->findGreaterThan(
			new ImmutableDateTime('2099-01-01'),
			10,
		);

		$ids = array_map(
			static fn (StockAssetDividend $d): string => $d->getId()->toString(),
			$result,
		);

		$this->assertContains($futureDividend->getId()->toString(), $ids);
		$this->assertNotContains($pastDividend->getId()->toString(), $ids);
	}

	public function testGetLastDividend(): void
	{
		$stockAsset = $this->createStockAsset('Last Dividend Stock', 'DIV-LAST-1');
		$this->entityManager->persist($stockAsset);

		$older = $this->createDividend($stockAsset, '2025-01-15', 0.50);
		$newer = $this->createDividend($stockAsset, '2025-06-15', 0.60);
		$this->entityManager->persist($older);
		$this->entityManager->persist($newer);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->getLastDividend($stockAsset);

		$this->assertNotNull($result);
		$this->assertSame(0.60, $result->getAmount());
	}

	public function testGetLastDividendReturnsNullWhenNoDividends(): void
	{
		$stockAsset = $this->createStockAsset('No Div Stock', 'DIV-NONE-1');
		$this->entityManager->persist($stockAsset);
		$this->entityManager->flush();

		$result = $this->stockAssetDividendRepository->getLastDividend($stockAsset);

		$this->assertNull($result);
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
			stockAssetDividendSource: StockAssetDividendSourceEnum::WEB,
			dividendTax: 15.0,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
	}

	private function createDividend(
		StockAsset $stockAsset,
		string $exDate,
		float $amount,
	): StockAssetDividend
	{
		return new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime($exDate),
			null,
			null,
			CurrencyEnum::USD,
			$amount,
			new ImmutableDateTime(),
			StockAssetDividendTypeEnum::REGULAR,
		);
	}

}
