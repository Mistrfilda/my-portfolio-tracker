<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPositionRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class CryptoClosedPositionFacade
{

	public function __construct(
		private readonly CryptoPositionRepository $cryptoPositionRepository,
		private readonly CryptoClosedPositionRepository $cryptoClosedPositionRepository,
		private readonly CryptoAssetRepository $cryptoAssetRepository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
		private readonly SummaryPriceService $summaryPriceService,
	)
	{
	}

	public function create(
		UuidInterface $cryptoPositionId,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
	): CryptoClosedPosition
	{
		$cryptoAssetPosition = $this->cryptoPositionRepository->getById($cryptoPositionId);

		$cryptoPosition = new CryptoClosedPosition(
			$cryptoAssetPosition,
			$pricePerPiece,
			$orderDate,
			$differentBrokerAmount,
			$totalInvestedAmountInBrokerCurrency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($cryptoPosition);
		$cryptoAssetPosition->closePosition($cryptoPosition);
		$this->entityManager->flush();
		$this->entityManager->refresh($cryptoPosition);

		$this->logger->info(
			sprintf(
				'User %s closed position %s closed position id> %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$cryptoPosition->getId(),
				$cryptoPosition->getId()->toString(),
			),
		);

		return $cryptoPosition;
	}

	public function update(
		UuidInterface $cryptoClosedPositionId,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
	): CryptoClosedPosition
	{
		$cryptoPosition = $this->cryptoClosedPositionRepository->getById($cryptoClosedPositionId);

		$cryptoPosition->update(
			$pricePerPiece,
			$orderDate,
			$differentBrokerAmount,
			$totalInvestedAmountInBrokerCurrency,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();
		$this->entityManager->refresh($cryptoPosition);

		$this->logger->info(
			sprintf(
				'User %s updated position %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$cryptoPosition->getId()->toString(),
			),
		);

		return $cryptoPosition;
	}

	public function getAllCryptoClosedPositionsSummaryPrice(): PriceDiff
	{
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$investedAmount = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($this->getAllCryptoClosedPositions() as $closedPosition) {
			$summaryPrice->addSummaryPrice(
				$closedPosition->getTotalAmountInBrokerCurrencyInCzk(),
			);
			$investedAmount->addSummaryPrice($closedPosition->getTotalInvestedAmountInBrokerCurrencyInCzk());
		}

		return $this->summaryPriceService->getSummaryPriceDiff(
			$summaryPrice,
			$investedAmount,
		);
	}

	/**
	 * @return array<CryptoAssetClossedPositionDTO>
	 */
	public function getAllCryptoClosedPositions(): array
	{
		$closedPositionDTOS = [];
		foreach ($this->cryptoAssetRepository->findAll() as $cryptoAsset) {
			if ($cryptoAsset->hasClosedPositions() === false) {
				continue;
			}

			$totalInvestedAmount = null;
			$totalAmount = null;
			$totalInvestedAmountInBrokerCurrency = null;
			$totalAmountInBrokerCurrency = null;
			$totalInvestedAmountInBrokerCurrencyInCzk = null;
			$totalAmountInBrokerCurrencyInCzk = null;
			$positions = [];
			foreach ($cryptoAsset->getClosedPositions() as $closedPosition) {
				$positions[] = $closedPosition;
				assert($closedPosition->getCryptoClosedPosition() !== null);
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
						$closedPosition->getCryptoClosedPosition()->getTotalCloseAmountInBrokerCurrency()->getCurrency(),
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
					$closedPosition->getCryptoClosedPosition()->getTotalCloseAmountInBrokerCurrency(),
				);

				$totalInvestedAmountInBrokerCurrencyInCzk->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$closedPosition->getTotalInvestedAmountInBrokerCurrency(),
						CurrencyEnum::CZK,
						$closedPosition->getCryptoClosedPosition()->getDate(),
					),
				);

				$totalAmountInBrokerCurrencyInCzk->addAssetPrice(
					$this->currencyConversionFacade->getConvertedAssetPrice(
						$closedPosition->getCryptoClosedPosition()->getTotalCloseAmountInBrokerCurrency(),
						CurrencyEnum::CZK,
						$closedPosition->getCryptoClosedPosition()->getDate(),
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

			$closedPositionDTOS[] = new CryptoAssetClossedPositionDTO(
				$cryptoAsset,
				$positions,
				$totalInvestedAmount,
				$totalAmount,
				$totalInvestedAmountInBrokerCurrency,
				$totalAmountInBrokerCurrency,
				$totalInvestedAmountInBrokerCurrencyInCzk,
				$totalAmountInBrokerCurrencyInCzk,
				$this->summaryPriceService->getSummaryPriceDiff(
					$totalAmount,
					$totalInvestedAmount,
				),
				$this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountInBrokerCurrency,
					$totalInvestedAmountInBrokerCurrency,
				),
				$this->summaryPriceService->getSummaryPriceDiff(
					$totalAmountInBrokerCurrencyInCzk,
					$totalInvestedAmountInBrokerCurrencyInCzk,
				),
			);
		}

		return $closedPositionDTOS;
	}

}
