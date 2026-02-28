<?php

declare(strict_types = 1);

namespace App\Test\Integration\Stock;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendFacade;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class StockAssetDividendFacadeTest extends IntegrationTestCase
{

	private StockAssetDividendFacade $stockAssetDividendFacade;

	private StockAssetDividendRepository $stockAssetDividendRepository;

	private EntityManagerInterface $entityManager;

	private StockAsset $stockAsset;

	protected function setUp(): void
	{
		parent::setUp();

		$this->stockAssetDividendFacade = $this->getService(StockAssetDividendFacade::class);
		$this->stockAssetDividendRepository = $this->getService(StockAssetDividendRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);

		$this->stockAsset = new StockAsset(
			'Dividend Test Company',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'DIV-FACADE-' . bin2hex(random_bytes(4)),
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
		$this->entityManager->persist($this->stockAsset);
		$this->entityManager->flush();
	}

	public function testCreate(): void
	{
		$exDate = new ImmutableDateTime('2025-03-15');
		$paymentDate = new ImmutableDateTime('2025-04-01');
		$declarationDate = new ImmutableDateTime('2025-02-20');

		$this->stockAssetDividendFacade->create(
			$this->stockAsset->getId(),
			$exDate,
			$paymentDate,
			$declarationDate,
			CurrencyEnum::USD,
			0.25,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$dividends = $this->stockAssetDividendRepository->findByStockAsset($this->stockAsset);
		$this->assertCount(1, $dividends);

		$dividend = $dividends[0];
		$this->assertSame($this->stockAsset->getId()->toString(), $dividend->getStockAsset()->getId()->toString());
		$this->assertSame('2025-03-15', $dividend->getExDate()->format('Y-m-d'));
		$this->assertSame('2025-04-01', $dividend->getPaymentDate()->format('Y-m-d'));
		$this->assertSame('2025-02-20', $dividend->getDeclarationDate()->format('Y-m-d'));
		$this->assertSame(CurrencyEnum::USD, $dividend->getCurrency());
		$this->assertSame(0.25, $dividend->getAmount());
		$this->assertSame(StockAssetDividendTypeEnum::REGULAR, $dividend->getDividendType());
	}

	public function testCreateWithNullDeclarationDate(): void
	{
		$exDate = new ImmutableDateTime('2025-06-15');
		$paymentDate = new ImmutableDateTime('2025-07-01');

		$this->stockAssetDividendFacade->create(
			$this->stockAsset->getId(),
			$exDate,
			$paymentDate,
			null,
			CurrencyEnum::EUR,
			1.50,
			StockAssetDividendTypeEnum::SPECIAL,
		);

		$dividends = $this->stockAssetDividendRepository->findByStockAsset($this->stockAsset);
		$this->assertCount(1, $dividends);

		$dividend = $dividends[0];
		$this->assertNull($dividend->getDeclarationDate());
		$this->assertSame(CurrencyEnum::EUR, $dividend->getCurrency());
		$this->assertSame(1.50, $dividend->getAmount());
		$this->assertSame(StockAssetDividendTypeEnum::SPECIAL, $dividend->getDividendType());
	}

	public function testUpdate(): void
	{
		$exDate = new ImmutableDateTime('2025-03-15');
		$paymentDate = new ImmutableDateTime('2025-04-01');

		$this->stockAssetDividendFacade->create(
			$this->stockAsset->getId(),
			$exDate,
			$paymentDate,
			null,
			CurrencyEnum::USD,
			0.25,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$dividends = $this->stockAssetDividendRepository->findByStockAsset($this->stockAsset);
		$this->assertCount(1, $dividends);
		$dividend = $dividends[0];

		$newExDate = new ImmutableDateTime('2025-04-15');
		$newPaymentDate = new ImmutableDateTime('2025-05-01');
		$newDeclarationDate = new ImmutableDateTime('2025-03-20');

		$this->stockAssetDividendFacade->update(
			$dividend->getId(),
			$newExDate,
			$newPaymentDate,
			$newDeclarationDate,
			CurrencyEnum::EUR,
			0.50,
			StockAssetDividendTypeEnum::SPECIAL,
		);

		$updatedDividend = $this->stockAssetDividendRepository->getById($dividend->getId());
		$this->assertSame('2025-04-15', $updatedDividend->getExDate()->format('Y-m-d'));
		$this->assertSame('2025-05-01', $updatedDividend->getPaymentDate()->format('Y-m-d'));
		$this->assertSame('2025-03-20', $updatedDividend->getDeclarationDate()->format('Y-m-d'));
		$this->assertSame(CurrencyEnum::EUR, $updatedDividend->getCurrency());
		$this->assertSame(0.50, $updatedDividend->getAmount());
		$this->assertSame(StockAssetDividendTypeEnum::SPECIAL, $updatedDividend->getDividendType());
	}

	public function testGetLastDividends(): void
	{
		$countBefore = count($this->stockAssetDividendFacade->getLastDividends(1000));

		for ($i = 1; $i <= 5; $i++) {
			$this->stockAssetDividendFacade->create(
				$this->stockAsset->getId(),
				new ImmutableDateTime(sprintf('2025-%02d-15', $i)),
				new ImmutableDateTime(sprintf('2025-%02d-01', $i + 1)),
				null,
				CurrencyEnum::USD,
				0.25 * $i,
				StockAssetDividendTypeEnum::REGULAR,
			);
		}

		$lastDividends = $this->stockAssetDividendFacade->getLastDividends($countBefore + 3);
		$this->assertCount($countBefore + 3, $lastDividends);
	}

	public function testCreateMultipleDividendsForSameAsset(): void
	{
		$this->stockAssetDividendFacade->create(
			$this->stockAsset->getId(),
			new ImmutableDateTime('2025-03-15'),
			new ImmutableDateTime('2025-04-01'),
			null,
			CurrencyEnum::USD,
			0.25,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$this->stockAssetDividendFacade->create(
			$this->stockAsset->getId(),
			new ImmutableDateTime('2025-06-15'),
			new ImmutableDateTime('2025-07-01'),
			null,
			CurrencyEnum::USD,
			0.30,
			StockAssetDividendTypeEnum::REGULAR,
		);

		$dividends = $this->stockAssetDividendRepository->findByStockAsset($this->stockAsset);
		$this->assertCount(2, $dividends);

		$amounts = array_map(
			static fn (StockAssetDividend $dividend): float => $dividend->getAmount(),
			$dividends,
		);

		$this->assertContains(0.25, $amounts);
		$this->assertContains(0.30, $amounts);
	}

}
