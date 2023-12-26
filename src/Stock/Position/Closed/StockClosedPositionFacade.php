<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Stock\Position\StockPositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class StockClosedPositionFacade
{

	public function __construct(
		private readonly StockPositionRepository $stockPositionRepository,
		private readonly StockClosedPositionRepository $stockClosedPositionRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function create(
		UuidInterface $stockPositionId,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
	): StockClosedPosition
	{
		$stockAssetPosition = $this->stockPositionRepository->getById($stockPositionId);

		$stockPosition = new StockClosedPosition(
			$stockAssetPosition,
			$pricePerPiece,
			$orderDate,
			$differentBrokerAmount,
			$totalInvestedAmountInBrokerCurrency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($stockPosition);

		$stockAssetPosition->closePosition($stockPosition);
		$this->entityManager->flush();
		$this->entityManager->refresh($stockPosition);

		$this->logger->info(
			sprintf(
				'User %s closed position %s closed position id> %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$stockPosition->getId(),
				$stockPosition->getId()->toString(),
			),
		);

		return $stockPosition;
	}

	public function update(
		UuidInterface $stockClosedPositionId,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
	): StockClosedPosition
	{
		$stockPosition = $this->stockClosedPositionRepository->getById($stockClosedPositionId);

		$stockPosition->update(
			$pricePerPiece,
			$orderDate,
			$differentBrokerAmount,
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
