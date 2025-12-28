<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry;

use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;

class StockAssetIndustryFacade
{

	public function __construct(
		private StockAssetIndustryRepository $stockAssetIndustryRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	public function create(
		string $name,
		string $mappingName,
		float|null $currentPERatio,
		float|null $marketCap,
	): void
	{
		$stockAssetIndustry = new StockAssetIndustry(
			$name,
			$mappingName,
			$this->datetimeFactory->createNow(),
			$currentPERatio,
			$marketCap,
		);

		$this->entityManager->persist($stockAssetIndustry);
		$this->entityManager->flush();
	}

	public function update(
		UuidInterface $id,
		string $name,
		string $mappingName,
		float|null $currentPERatio,
		float|null $marketCap,
	): void
	{
		$stockAssetIndustry = $this->stockAssetIndustryRepository->getById($id);
		$stockAssetIndustry->update(
			$name,
			$mappingName,
			$this->datetimeFactory->createNow(),
			$currentPERatio,
			$marketCap,
		);

		$this->entityManager->flush();
	}

}
