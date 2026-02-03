<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
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
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
		private readonly SummaryPriceService $summaryPriceService,
		private readonly StockAssetDividendRecordFacade $stockAssetDividendRecordFacade,
		private readonly JobRequestFacade $jobRequestFacade,
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

		$this->jobRequestFacade->addToQueue(JobRequestTypeEnum::PORTFOLIO_GOAL_UPDATE);

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

	public function getAllStockClosedPositionsSummaryPrice(): PriceDiff
	{
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$investedAmount = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($this->getAllStockClosedPositions() as $closedPosition) {
			$summaryPrice->addSummaryPrice(
				$closedPosition->getTotalAmountInBrokerCurrencyWithDividendsInCzk() ?? $closedPosition->getTotalAmountInBrokerCurrencyInCzk(),
			);
			$investedAmount->addSummaryPrice($closedPosition->getTotalInvestedAmountInBrokerCurrencyInCzk());
		}

		return $this->summaryPriceService->getSummaryPriceDiff(
			$summaryPrice,
			$investedAmount,
		);
	}

	/**
	 * @return array<StockAssetClossedPositionDTO>
	 */
	public function getAllStockClosedPositions(): array
	{
		$closedPositionDTOS = [];
		foreach ($this->stockAssetRepository->findAll() as $stockAsset) {
			if ($stockAsset->hasClosedPositions() === false) {
				continue;
			}

			$totalInvestedAmount = null;
			$totalAmount = null;
			$totalInvestedAmountInBrokerCurrency = null;
			$totalAmountInBrokerCurrency = null;
			$totalInvestedAmountInBrokerCurrencyInCzk = null;
			$totalAmountInBrokerCurrencyInCzk = null;
			$positions = [];
			foreach ($stockAsset->getClosedPositions() as $closedPosition) {
				$positions[] = $closedPosition;
				assert($closedPosition->getStockClosedPosition() !== null);
				if (
					$totalInvestedAmount === null
					|| $totalAmount === null
					|| $totalInvestedAmountInBrokerCurrency === null
					|| $totalAmountInBrokerCurrency === null
					|| $totalInvestedAmountInBrokerCurrencyInCzk === null
					|| $totalAmountInBrokerCurrencyInCzk === null
				) {
					$totalInvestedAmount = new SummaryPrice(
						$closedPosition->getTotalInvestedAmount()->getCurrency(),
					);
					$totalAmount = new SummaryPrice(
						$closedPosition->getCurrentTotalAmount()->getCurrency(),
					);
					$totalInvestedAmountInBrokerCurrency = new SummaryPrice(
						$closedPosition->getTotalInvestedAmountInBrokerCurrency()->getCurrency(),
					);
					$totalAmountInBrokerCurrency = new SummaryPrice(
						$closedPosition->getStockClosedPosition()->getTotalCloseAmountInBrokerCurrency()->getCurrency(),
					);

					$totalInvestedAmountInBrokerCurrencyInCzk = new SummaryPrice(CurrencyEnum::CZK);
					$totalAmountInBrokerCurrencyInCzk = new SummaryPrice(CurrencyEnum::CZK);
				}

				$totalInvestedAmount->addAssetPrice(
					$closedPosition->getTotalInvestedAmount(),
				);
				$totalAmount->addAssetPrice(
					$closedPosition->getCurrentTotalAmount(),
				);
				$totalInvestedAmountInBrokerCurrency->addAssetPrice(
					$closedPosition->getTotalInvestedAmountInBrokerCurrency(),
				);
				$totalAmountInBrokerCurrency->addAssetPrice(
					$closedPosition->getStockClosedPosition()->getTotalCloseAmountInBrokerCurrency(),
				);

				$totalInvestedAmountInBrokerCurrencyInCzk->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$closedPosition->getTotalInvestedAmountInBrokerCurrency(),
						CurrencyEnum::CZK,
						$closedPosition->getStockClosedPosition()->getDate(),
					),
				);

				$totalAmountInBrokerCurrencyInCzk->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$closedPosition->getStockClosedPosition()->getTotalCloseAmountInBrokerCurrency(),
						CurrencyEnum::CZK,
						$closedPosition->getStockClosedPosition()->getDate(),
					),
				);
			}

			if (
				$totalInvestedAmount === null
				|| $totalAmount === null
				|| $totalInvestedAmountInBrokerCurrency === null
				|| $totalAmountInBrokerCurrency === null
				|| $totalInvestedAmountInBrokerCurrencyInCzk === null
				|| $totalAmountInBrokerCurrencyInCzk === null
			) {
				return [];
			}

			$dividendsSummaryPrice = $this->stockAssetDividendRecordFacade->getTotalSummaryPriceForStockAsset(
				$stockAsset,
			);

			$totalAmountWithDividends = null;
			$totalAmountInBrokerCurrencyWithDividends = null;
			$totalAmountInBrokerCurrencyWithDividendsInCzk = null;
			if ($dividendsSummaryPrice !== null) {
				$totalAmountWithDividends = new SummaryPrice($totalAmount->getCurrency());
				$totalAmountWithDividends->addSummaryPrice($totalAmount);
				$totalAmountWithDividends->addSummaryPrice(
					$this->currencyConversionFacade->getConvertedSummaryPrice(
						$dividendsSummaryPrice,
						$totalAmount->getCurrency(),
					),
				);

				$totalAmountInBrokerCurrencyWithDividends = new SummaryPrice(
					$totalAmountInBrokerCurrency->getCurrency(),
				);
				$totalAmountInBrokerCurrencyWithDividends->addSummaryPrice($totalAmountInBrokerCurrency);
				$totalAmountInBrokerCurrencyWithDividends->addSummaryPrice(
					$this->currencyConversionFacade->getConvertedSummaryPrice(
						$dividendsSummaryPrice,
						$totalAmountInBrokerCurrency->getCurrency(),
					),
				);

				$totalAmountInBrokerCurrencyWithDividendsInCzk = new SummaryPrice(
					$totalAmountInBrokerCurrencyInCzk->getCurrency(),
				);
				$totalAmountInBrokerCurrencyWithDividendsInCzk->addSummaryPrice($totalAmountInBrokerCurrencyInCzk);
				$totalAmountInBrokerCurrencyWithDividendsInCzk->addSummaryPrice(
					$this->currencyConversionFacade->getConvertedSummaryPrice(
						$dividendsSummaryPrice,
						$totalAmountInBrokerCurrencyInCzk->getCurrency(),
					),
				);
			}

			$closedPositionDTOS[] = new StockAssetClossedPositionDTO(
				$stockAsset,
				$positions,
				$totalInvestedAmount,
				$totalAmount,
				$dividendsSummaryPrice,
				$totalAmountWithDividends,
				$totalInvestedAmountInBrokerCurrency,
				$totalAmountInBrokerCurrency,
				$totalAmountInBrokerCurrencyWithDividends,
				$totalInvestedAmountInBrokerCurrencyInCzk,
				$totalAmountInBrokerCurrencyInCzk,
				$totalAmountInBrokerCurrencyWithDividendsInCzk,
				$this->summaryPriceService->getSummaryPriceDiff(
					$totalAmount,
					$totalInvestedAmount,
				),
				$totalAmountWithDividends !== null ? $this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountWithDividends,
					$totalInvestedAmount,
				) : null,
				$this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountInBrokerCurrency,
					$totalInvestedAmountInBrokerCurrency,
				),
				$totalAmountInBrokerCurrencyWithDividends !== null ? $this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountInBrokerCurrencyWithDividends,
					$totalInvestedAmountInBrokerCurrency,
				) : null,
				$this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountInBrokerCurrencyInCzk,
					$totalInvestedAmountInBrokerCurrencyInCzk,
				),
				$totalAmountInBrokerCurrencyWithDividendsInCzk !== null ? $this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountInBrokerCurrencyWithDividendsInCzk,
					$totalInvestedAmountInBrokerCurrencyInCzk,
				) : null,
			);
		}

		return $closedPositionDTOS;
	}

}
