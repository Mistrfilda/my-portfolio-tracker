<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Stock\Asset\StockAssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class StockPositionFacade
{

	public function __construct(
		private readonly StockPositionRepository $stockPositionRepository,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function create(
		UuidInterface $stockAssetId,
		int $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
	): StockPosition
	{
		$stockAsset = $this->stockAssetRepository->getById($stockAssetId);

		$stockPosition = new StockPosition(
			$this->currentAppAdminGetter->getAppAdmin(),
			$stockAsset,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmountInBrokerCurrency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($stockPosition);
		$this->entityManager->flush();
		$this->entityManager->refresh($stockPosition);

		$this->logger->info(
			sprintf(
				'User %s created position %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$stockPosition->getId()->toString(),
			),
		);

		return $stockPosition;
	}

	public function update(
		UuidInterface $stockPositionId,
		UuidInterface $stockAssetId,
		int $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
	): StockPosition
	{
		$stockAsset = $this->stockAssetRepository->getById($stockAssetId);
		$stockPosition = $this->stockPositionRepository->getById($stockPositionId);

		$stockPosition->update(
			$stockAsset,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmountInBrokerCurrency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();
		$this->entityManager->refresh($stockPosition);

		$this->logger->info(
			sprintf(
				'User %s updated position %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$stockPosition->getId()->toString(),
			),
		);

		return $stockPosition;
	}

}
