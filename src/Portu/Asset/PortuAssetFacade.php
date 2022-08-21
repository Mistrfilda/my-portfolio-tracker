<?php

declare(strict_types = 1);

namespace App\Portu\Asset;

use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class PortuAssetFacade
{

	public function __construct(
		private readonly PortuAssetRepository $portuAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function create(string $name, CurrencyEnum $currency,): PortuAsset
	{
		$portuAsset = new PortuAsset(
			$name,
			$currency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($portuAsset);
		$this->entityManager->flush();

		$this->logger->info(
			sprintf(
				'User %s create new portu asset %s - %s',
				$this->currentAppAdminGetter->getAppAdmin()->getName(),
				$portuAsset->getName(),
				$portuAsset->getId()->toString(),
			),
		);

		return $portuAsset;
	}

	public function update(
		UuidInterface $id,
		string $name,
		CurrencyEnum $currency,
	): PortuAsset
	{
		$portuAsset = $this->portuAssetRepository->getById($id);
		$portuAsset->update(
			$name,
			$currency,
			now: $this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();

		$this->logger->info(
			sprintf(
				'User %s updated portu asset %s - %s',
				$this->currentAppAdminGetter->getAppAdmin()->getName(),
				$portuAsset->getName(),
				$portuAsset->getId()->toString(),
			),
		);

		return $portuAsset;
	}

}
