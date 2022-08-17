<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAssetDetailDTO;
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
		private readonly StockPositionSummaryPriceService $stockPositionSummaryPriceService,
		private readonly AssetPriceService $assetPriceService,
		private readonly SummaryPriceService $summaryPriceService,
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
		return $this->stockPositionSummaryPriceService->getSummaryPriceForPositions(
			$inCurrency,
			$this->stockPositionRepository->findAllOpened(),
		);
	}

	public function getCurrentPortfolioValueInCzechStocks(
		CurrencyEnum $inCurrency,
	): SummaryPrice
	{
		return $this->stockPositionSummaryPriceService->getSummaryPriceForPositions(
			$inCurrency,
			$this->stockPositionRepository->findAllOpenedInCurrency(CurrencyEnum::CZK),
		);
	}

	public function getCurrentPortfolioValueInUsdStocks(
		CurrencyEnum $inCurrency,
	): SummaryPrice
	{
		return $this->stockPositionSummaryPriceService->getSummaryPriceForPositions(
			$inCurrency,
			$this->stockPositionRepository->findAllOpenedInCurrency(CurrencyEnum::USD),
		);
	}

	public function getTotalInvestedAmountSummaryPrice(CurrencyEnum $inCurrency): SummaryPrice
	{
		return $this->stockPositionSummaryPriceService->getSummaryPriceForTotalInvestedAmount(
			$inCurrency,
			$this->stockPositionRepository->findAllOpened(),
		);
	}

	public function getStockAssetDetailDTO(UuidInterface $stockAssetId): StockAssetDetailDTO
	{
		$stockAsset = $this->stockAssetRepository->getById($stockAssetId);

		if ($stockAsset->hasPositions() === false) {
			return new StockAssetDetailDTO(
				$stockAsset,
				[],
				new SummaryPrice($stockAsset->getCurrency()),
				new SummaryPrice($stockAsset->getCurrency()),
				new PriceDiff(0, 0, CurrencyEnum::CZK),
				new SummaryPrice(CurrencyEnum::CZK),
			);
		}

		$positionDetailDTOs = [];
		foreach ($stockAsset->getPositions() as $position) {
			$positionDetailDTOs[] = new StockAssetPositionDetailDTO(
				$position,
				$this->assetPriceService->getAssetPriceDiff(
					$position->getCurrentTotalAmount(),
					$position->getTotalInvestedAmount(),
				),
			);
		}

		$totalInvestedAmount = $this->stockPositionSummaryPriceService->getSummaryPriceForTotalInvestedAmount(
			$stockAsset->getCurrency(),
			$stockAsset->getPositions(),
		);

		$currentAmount = $this->stockPositionSummaryPriceService->getSummaryPriceForPositions(
			$stockAsset->getCurrency(),
			$stockAsset->getPositions(),
		);

		return new StockAssetDetailDTO(
			$stockAsset,
			$positionDetailDTOs,
			$totalInvestedAmount,
			$currentAmount,
			$this->summaryPriceService->getSummaryPriceDiff($currentAmount, $totalInvestedAmount),
			$this->currencyConversionFacade->getConvertedSummaryPrice($currentAmount, CurrencyEnum::CZK),
		);
	}

}