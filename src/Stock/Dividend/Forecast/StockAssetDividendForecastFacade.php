<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class StockAssetDividendForecastFacade
{

	public function __construct(
		private StockAssetDividendForecastRepository $stockAssetDividendForecastRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	public function create(int $forYear, StockAssetDividendTrendEnum $trend): void
	{
		$stockAssetDividendForecast = new StockAssetDividendForecast(
			$forYear,
			$trend,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($stockAssetDividendForecast);
		$this->entityManager->flush();
	}

	public function update(UuidInterface $id, StockAssetDividendTrendEnum $trend): void
	{
		$stockAssetDividendForecast = $this->stockAssetDividendForecastRepository->getById($id);
		$stockAssetDividendForecast->update($trend, $this->datetimeFactory->createNow());
		$this->entityManager->flush();
	}

	public function setDefaultForYear(UuidInterface $id): void
	{
		$stockAssetDividendForecast = $this->stockAssetDividendForecastRepository->getById($id);

		$existing = $this->stockAssetDividendForecastRepository->findByDefaultForYear(
			$stockAssetDividendForecast->getForYear(),
		);

		if ($stockAssetDividendForecast->getId() === $existing?->getId()) {
			return;
		}

		if ($existing !== null) {
			$existing->removeDefaultForYear();
		}

		$stockAssetDividendForecast->defaultForYear();
		$this->entityManager->flush();
	}

}
