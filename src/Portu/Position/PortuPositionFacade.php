<?php

declare(strict_types = 1);

namespace App\Portu\Position;

use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFacade;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\Portu\Asset\PortuAssetRepository;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

class PortuPositionFacade implements AssetPriceFacade
{

	public function __construct(
		private readonly PortuAssetRepository $portuAssetRepository,
		private readonly PortuPositionRepository $portuPositionRepository,
		private readonly PortuAssetPriceRecordRepository $portuAssetPriceRecordRepository,
		private readonly SummaryPriceService $summaryPriceService,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly JobRequestFacade $jobRequestFacade,
	)
	{
	}

	public function create(
		UuidInterface $portuAssetId,
		ImmutableDateTime $startDate,
		float $startInvestmentPrice,
		float $monthlyIncreasePrice,
		float $currentValuePrice,
		float $totalInvestedToThisDatePrice,
	): PortuPosition
	{
		$portuAsset = $this->portuAssetRepository->getById($portuAssetId);

		$portuPosition = new PortuPosition(
			$portuAsset,
			$this->currentAppAdminGetter->getAppAdmin(),
			$startDate,
			new AssetPriceEmbeddable(
				$startInvestmentPrice,
				$portuAsset->getCurrency(),
			),
			new AssetPriceEmbeddable(
				$monthlyIncreasePrice,
				$portuAsset->getCurrency(),
			),
			new AssetPriceEmbeddable(
				$currentValuePrice,
				$portuAsset->getCurrency(),
			),
			new AssetPriceEmbeddable(
				$totalInvestedToThisDatePrice,
				$portuAsset->getCurrency(),
			),
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($portuPosition);
		$this->entityManager->flush();
		$this->entityManager->refresh($portuPosition);

		$this->logger->info(
			sprintf(
				'User %s created portu position %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$portuPosition->getId()->toString(),
			),
		);

		return $portuPosition;
	}

	public function update(
		UuidInterface $portuPositionId,
		UuidInterface $portuAssetId,
		ImmutableDateTime $startDate,
		float $startInvestmentPrice,
		float $monthlyIncreasePrice,
		float $currentValuePrice,
		float $totalInvestedToThisDatePrice,
	): PortuPosition
	{
		$portuPosition = $this->portuPositionRepository->getById($portuPositionId);
		$portuAsset = $this->portuAssetRepository->getById($portuAssetId);

		$portuPosition->update(
			$portuAsset,
			$this->currentAppAdminGetter->getAppAdmin(),
			$startDate,
			new AssetPriceEmbeddable(
				$startInvestmentPrice,
				$portuAsset->getCurrency(),
			),
			new AssetPriceEmbeddable(
				$monthlyIncreasePrice,
				$portuAsset->getCurrency(),
			),
			new AssetPriceEmbeddable(
				$currentValuePrice,
				$portuAsset->getCurrency(),
			),
			new AssetPriceEmbeddable(
				$totalInvestedToThisDatePrice,
				$portuAsset->getCurrency(),
			),
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->flush();
		$this->entityManager->refresh($portuPosition);

		$this->logger->info(
			sprintf(
				'User %s updated portu position %s',
				$this->currentAppAdminGetter->getAppAdmin()->getId()->toString(),
				$portuPosition->getId()->toString(),
			),
		);

		return $portuPosition;
	}

	public function updatePriceForDate(
		UuidInterface $portuPositionId,
		ImmutableDateTime $date,
		float $currentValuePrice,
		float $totalInvestedToThisDatePrice,
		bool $shouldUpdateWholePosition,
	): PortuAssetPriceRecord
	{
		$portuPosition = $this->portuPositionRepository->getById($portuPositionId);

		$portuAssetPriceRecord = $this->portuAssetPriceRecordRepository->findByPositionAndDate(
			$date,
			$portuPosition,
		);

		if ($portuAssetPriceRecord === null) {
			$portuAssetPriceRecord = new PortuAssetPriceRecord(
				$date,
				$portuPosition->getCurrency(),
				new AssetPriceEmbeddable(
					$currentValuePrice,
					$portuPosition->getCurrency(),
				),
				new AssetPriceEmbeddable(
					$totalInvestedToThisDatePrice,
					$portuPosition->getCurrency(),
				),
				$portuPosition,
				$this->datetimeFactory->createNow(),
			);

			$this->entityManager->persist($portuAssetPriceRecord);
		} else {
			$portuAssetPriceRecord->update(
				new AssetPriceEmbeddable(
					$currentValuePrice,
					$portuPosition->getCurrency(),
				),
				new AssetPriceEmbeddable(
					$totalInvestedToThisDatePrice,
					$portuPosition->getCurrency(),
				),
				$this->datetimeFactory->createNow(),
			);
		}

		if ($shouldUpdateWholePosition) {
			$portuPosition->updateCurrentValue(
				new AssetPriceEmbeddable(
					$currentValuePrice,
					$portuPosition->getCurrency(),
				),
				new AssetPriceEmbeddable(
					$totalInvestedToThisDatePrice,
					$portuPosition->getCurrency(),
				),
			);
		}

		$this->entityManager->flush();
		$this->entityManager->refresh($portuAssetPriceRecord);

		$this->jobRequestFacade->addToQueue(JobRequestTypeEnum::PORTFOLIO_GOAL_UPDATE);

		return $portuAssetPriceRecord;
	}

	public function getTotalInvestedAmountSummaryPrice(CurrencyEnum $inCurrency): SummaryPrice
	{
		return $this->summaryPriceService->getSummaryPriceForTotalInvestedAmountInBrokerCurrency(
			$inCurrency,
			$this->portuPositionRepository->findAllOpened(),
		);
	}

	public function getCurrentPortfolioValueSummaryPrice(
		CurrencyEnum $inCurrency,
	): SummaryPrice
	{
		return $this->summaryPriceService->getSummaryPriceForPositions(
			$inCurrency,
			$this->portuPositionRepository->findAllOpened(),
		);
	}

	public function includeToTotalValues(): bool
	{
		return true;
	}

}
