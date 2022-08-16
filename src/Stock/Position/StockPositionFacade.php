<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
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
		private readonly CurrencyConversionFacade $currencyConversionFacade,
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
		bool $differentBrokerAmount,
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
			$differentBrokerAmount,
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
		bool $differentBrokerAmount,
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
			$differentBrokerAmount,
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

	public function getCurrentPortfolioValueSummaryPrice(
		CurrencyEnum $inCurrency,
	): SummaryPrice
	{
		return $this->getSummaryPriceForPositions(
			$inCurrency,
			$this->stockPositionRepository->findAllOpened(),
		);
	}

	public function getCurrentPortfolioValueInCzechStocks(
		CurrencyEnum $inCurrency,
	): SummaryPrice
	{
		return $this->getSummaryPriceForPositions(
			$inCurrency,
			$this->stockPositionRepository->findAllOpenedInCurrency(CurrencyEnum::CZK),
		);
	}

	public function getCurrentPortfolioValueInUsdStocks(
		CurrencyEnum $inCurrency,
	): SummaryPrice
	{
		return $this->getSummaryPriceForPositions(
			$inCurrency,
			$this->stockPositionRepository->findAllOpenedInCurrency(CurrencyEnum::USD),
		);
	}

	public function getTotalInvestedAmountSummaryPrice(CurrencyEnum $inCurrency): SummaryPrice
	{
		return $this->getSummaryPriceForTotalInvestedAmount(
			$inCurrency,
			$this->stockPositionRepository->findAllOpened(),
		);
	}

	/**
	 * @param array<StockPosition> $positions
	 */
	private function getSummaryPriceForPositions(
		CurrencyEnum $inCurrency,
		array $positions,
	): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);

		foreach ($positions as $position) {
			$currentTotalAmount = $position->getCurrentTotalAmount();
			if ($currentTotalAmount->getCurrency() !== $summaryPrice->getCurrency()) {
				$summaryPrice->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$currentTotalAmount,
						$summaryPrice->getCurrency(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

	/**
	 * @param array<StockPosition> $positions
	 */
	private function getSummaryPriceForTotalInvestedAmount(
		CurrencyEnum $inCurrency,
		array $positions,
	): SummaryPrice
	{
		$summaryPrice = new SummaryPrice($inCurrency);

		foreach ($positions as $position) {
			$currentTotalAmount = $position->getTotalInvestedAmountInBrokerCurrency();
			if ($currentTotalAmount->getCurrency() !== $summaryPrice->getCurrency()) {
				$summaryPrice->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$currentTotalAmount,
						$summaryPrice->getCurrency(),
					),
				);

				continue;
			}

			$summaryPrice->addAssetPrice($currentTotalAmount);
		}

		return $summaryPrice;
	}

}
