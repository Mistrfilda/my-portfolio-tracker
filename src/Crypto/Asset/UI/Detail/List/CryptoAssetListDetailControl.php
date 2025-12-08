<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\List;

use App\Crypto\Asset\CryptoAssetDetailDTO;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPositionFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class CryptoAssetListDetailControl extends BaseControl
{

	/** @var array<UuidInterface> */
	private array $cryptoAssetIds;

	/**
	 * @param array<UuidInterface> $cryptoAssetsIds
	 */
	public function __construct(
		array $cryptoAssetsIds,
		private CryptoAssetListDetailControlEnum $assetDetailControlEnum,
		private readonly CryptoAssetRepository $cryptoAssetRepository,
		private readonly CryptoPositionFacade $cryptoPositionFacade,
	)
	{
		$this->cryptoAssetIds = $cryptoAssetsIds;
	}

	public function render(): void
	{
		$assets = count($this->cryptoAssetIds) === 0
			? $this->cryptoAssetRepository->findAll()
			: $this->cryptoAssetRepository->findByIds($this->cryptoAssetIds);

		$cryptoAssetsPositionDTOs = [];
		$totalInvestedAmountInCzk = 0;
		foreach ($assets as $asset) {
			if ($asset->hasOpenPositions()) {
				if ($this->assetDetailControlEnum === CryptoAssetListDetailControlEnum::CLOSED_POSITIONS) {
					continue;
				}
			} else {
				if ($this->assetDetailControlEnum === CryptoAssetListDetailControlEnum::OPEN_POSITIONS) {
					continue;
				}
			}

			$detailDTO = $this->cryptoPositionFacade->getCryptoAssetDetailDTO(
				$asset->getId(),
				$this->assetDetailControlEnum,
			);

			$cryptoAssetsPositionDTOs[] = $detailDTO;
			$totalInvestedAmountInCzk += $detailDTO->getCurrentPriceInCzk()->getPrice();
		}

		$template = $this->getTemplate();
		$template->cryptoAssetsPositionDTOs = $cryptoAssetsPositionDTOs;

		$sortedCryptoAssetsPositionsDTOs = $cryptoAssetsPositionDTOs;
		usort(
			$sortedCryptoAssetsPositionsDTOs,
			static function (CryptoAssetDetailDTO $a, CryptoAssetDetailDTO $b): int {
				if ($a->getCurrentPriceInCzk()->getPrice() > $b->getCurrentPriceInCzk()->getPrice()) {
					return -1;
				}

				return 1;
			},
		);

		$template->assetDetailControlEnum = $this->assetDetailControlEnum;
		$template->totalInvestedAmountInCzk = $totalInvestedAmountInCzk;
		$template->sortedCryptoAssetsPositionsDTOs = $sortedCryptoAssetsPositionsDTOs;
		$template->setFile(__DIR__ . '/templates/CryptoAssetListDetailControl.latte');
		$template->render();
	}

}
