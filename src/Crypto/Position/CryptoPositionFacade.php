<?php

declare(strict_types = 1);

namespace App\Crypto\Position;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFacade;
use App\Asset\Price\AssetPriceService;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Asset\CryptoAssetDetailDTO;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListDetailControlEnum;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class CryptoPositionFacade implements AssetPriceFacade
{

	public function __construct(
		private readonly CryptoPositionRepository $cryptoPositionRepository,
		private readonly CryptoAssetRepository $cryptoAssetRepository,
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
		UuidInterface $cryptoAssetId,
		float $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
	): CryptoPosition
	{
		$cryptoAsset = $this->cryptoAssetRepository->getById($cryptoAssetId);

		$cryptoPosition = new CryptoPosition(
			$this->currentAppAdminGetter->getAppAdmin(),
			$cryptoAsset,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmountInBrokerCurrency,
			$differentBrokerAmount,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($cryptoPosition);
		$this->entityManager->flush();
		$this->entityManager->refresh($cryptoPosition);

		$this->logger->info(
			sprintf(
				'User %s created position %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$cryptoPosition->getId()->toString(),
			),
		);

		return $cryptoPosition;
	}

	public function update(
		UuidInterface $cryptoPositionId,
		UuidInterface $cryptoAssetId,
		float $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
	): CryptoPosition
	{
		$cryptoAsset = $this->cryptoAssetRepository->getById($cryptoAssetId);
		$cryptoPosition = $this->cryptoPositionRepository->getById($cryptoPositionId);

		$cryptoPosition->update(
			$cryptoAsset,
			$orderPiecesCount,
			$pricePerPiece,
			$orderDate,
			$totalInvestedAmountInBrokerCurrency,
			$differentBrokerAmount,
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

	public function getCurrentPortfolioValueSummaryPrice(
		CurrencyEnum $inCurrency,
		AppAdmin|null $appAdmin = null,
	): SummaryPrice
	{
		return $this->summaryPriceService->getSummaryPriceForPositions(
			$inCurrency,
			$this->cryptoPositionRepository->findAllOpenedForAppAdmin(
				$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
			),
		);
	}

	public function getCurrentPortfolioValueInUsdCryptos(
		CurrencyEnum $inCurrency,
		AppAdmin|null $appAdmin = null,
	): SummaryPrice
	{
		return $this->summaryPriceService->getSummaryPriceForPositions(
			$inCurrency,
			$this->cryptoPositionRepository->findAllOpenedInCurrencyForAppAdmin(
				CurrencyEnum::USD,
				$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
			),
		);
	}

	public function getTotalInvestedAmountSummaryPrice(
		CurrencyEnum $inCurrency,
		AppAdmin|null $appAdmin = null,
	): SummaryPrice
	{
		return $this->summaryPriceService->getSummaryPriceForTotalInvestedAmountInBrokerCurrency(
			$inCurrency,
			$this->cryptoPositionRepository->findAllOpenedForAppAdmin(
				$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
			),
		);
	}

	public function getCryptoAssetDetailDTO(
		UuidInterface $cryptoAssetId,
		CryptoAssetListDetailControlEnum $cryptoAssetDetailControlEnum = CryptoAssetListDetailControlEnum::OPEN_POSITIONS,
		AppAdmin|null $appAdmin = null,
	): CryptoAssetDetailDTO
	{
		$cryptoAsset = $this->cryptoAssetRepository->getById($cryptoAssetId);

		$positions = $this->cryptoPositionRepository->findByAssetForAppAdmin(
			$cryptoAssetId,
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
			$cryptoAssetDetailControlEnum === CryptoAssetListDetailControlEnum::OPEN_POSITIONS,
		);
		if ($positions === []) {
			return new CryptoAssetDetailDTO(
				$cryptoAsset,
				[],
				new SummaryPrice($cryptoAsset->getCurrency()),
				new SummaryPrice($cryptoAsset->getCurrency()),
				new SummaryPrice($cryptoAsset->getCurrency()),
				new SummaryPrice($cryptoAsset->getCurrency()),
				new PriceDiff(0, 100, CurrencyEnum::CZK),
				new PriceDiff(0, 100, CurrencyEnum::CZK),
				new SummaryPrice(CurrencyEnum::CZK),
				new PriceDiff(0, 100, CurrencyEnum::CZK),
				0,
			);
		}

		$positionDetailDTOs = [];
		$brokerCurrency = CurrencyEnum::USD;
		$piecesCount = 0;

		foreach ($positions as $position) {

			$positionDetailDTOs[] = new CryptoAssetPositionDetailDTO(
				$position,
				$this->assetPriceService->getAssetPriceDiff(
					$position->getCurrentTotalAmount(),
					$position->getTotalInvestedAmount(),
				),
			);

			$piecesCount += $position->getOrderPiecesCount();
		}

		$totalInvestedAmount = $this->summaryPriceService->getSummaryPriceForTotalInvestedAmount(
			$cryptoAsset->getCurrency(),
			$positions,
		);

		$currentAmount = $this->summaryPriceService->getSummaryPriceForPositions(
			$cryptoAsset->getCurrency(),
			$positions,
		);

		$currentAmountInBrokerCurrency = $this->summaryPriceService->getSummaryPriceForPositions(
			$brokerCurrency,
			$positions,
		);

		$totalInvestedAmountInBrokerCurrency = $this->summaryPriceService->getSummaryPriceForTotalInvestedAmountInBrokerCurrency(
			$brokerCurrency,
			$positions,
		);

		$currentPriceDiffInBrokerCurrency = $this->summaryPriceService->getSummaryPriceDiff(
			$this->currencyConversionFacade->getConvertedSummaryPrice(
				$currentAmount,
				$brokerCurrency,
			),
			$totalInvestedAmountInBrokerCurrency,
		);

		return new CryptoAssetDetailDTO(
			$cryptoAsset,
			$positionDetailDTOs,
			$totalInvestedAmount,
			$currentAmount,
			$currentAmountInBrokerCurrency,
			$totalInvestedAmountInBrokerCurrency,
			$this->summaryPriceService->getSummaryPriceDiff($currentAmount, $totalInvestedAmount),
			$currentPriceDiffInBrokerCurrency,
			$this->currencyConversionFacade->getConvertedSummaryPrice($currentAmount, CurrencyEnum::CZK),
			$this->currencyConversionFacade->getConvertedPriceDiff(
				$currentPriceDiffInBrokerCurrency,
				CurrencyEnum::CZK,
			),
			$piecesCount,
		);
	}

	public function includeToTotalValues(): bool
	{
		return true;
	}

}
