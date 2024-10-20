<?php

declare(strict_types = 1);

namespace App\Stock\Dividend;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendFacade
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetRepository $stockAssetRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function create(
		UuidInterface $stockAssetId,
		ImmutableDateTime $exDate,
		ImmutableDateTime $paymentDate,
		ImmutableDateTime|null $declarationDate,
		CurrencyEnum $currency,
		float $amount,
	): void
	{
		$stockAssetDividend = new StockAssetDividend(
			$this->stockAssetRepository->getById($stockAssetId),
			$exDate,
			$paymentDate,
			$declarationDate,
			$currency,
			$amount,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($stockAssetDividend);
		$this->entityManager->flush();
	}

	public function update(
		UuidInterface $id,
		ImmutableDateTime $exDate,
		ImmutableDateTime $paymentDate,
		ImmutableDateTime|null $declarationDate,
		CurrencyEnum $currency,
		float $amount,
	): void
	{
		$stockAssetDividend = $this->stockAssetDividendRepository->getById($id);
		$stockAssetDividend->update(
			$exDate,
			$paymentDate,
			$declarationDate,
			$currency,
			$amount,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();
	}

	/**
	 * @return array<StockAssetDividend>
	 */
	public function getLastYearDividendRecordsForDashboard(): array
	{
		return $this->stockAssetDividendRepository->findGreaterThan(
			$this->datetimeFactory->createNow()->deductYearsFromDatetime(1),
			15,
		);
	}

	/**
	 * @return array<StockAssetDividend>
	 */
	public function getLastDividends(int $limit): array
	{
		return $this->stockAssetDividendRepository->findLastDividends($limit);
	}

}
