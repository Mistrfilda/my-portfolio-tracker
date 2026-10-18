<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\List;

use App\Crypto\Asset\CryptoAssetDetailDTO;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPositionFacade;
use App\UI\Base\BaseControl;
use Ramsey\Uuid\UuidInterface;

class CryptoAssetListSummaryDetailControl extends BaseControl
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
			? $this->cryptoAssetRepository->findWithPositionsForCurrentAppAdmin(
				$this->assetDetailControlEnum === CryptoAssetListDetailControlEnum::OPEN_POSITIONS,
			)
			: $this->cryptoAssetRepository->findByIds($this->cryptoAssetIds);

		$cryptoAssetsPositionDTOs = [];
		$totalInvestedAmountInCzk = 0;
		foreach ($assets as $asset) {
			$detailDTO = $this->cryptoPositionFacade->getCryptoAssetDetailDTO(
				$asset->getId(),
				$this->assetDetailControlEnum,
			);

			if ($detailDTO->getPositions() === []) {
				continue;
			}

			$cryptoAssetsPositionDTOs[] = $detailDTO;
			$totalInvestedAmountInCzk += $detailDTO->getCurrentPriceInCzk()->getPrice();
		}

		$template = $this->createTemplate(CryptoAssetListSummaryDetailControlTemplate::class);
		assert($template instanceof CryptoAssetListSummaryDetailControlTemplate);

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

		$fields = [
			'Kryptoměna',
			'Aktuální cena',
		];

		if ($this->assetDetailControlEnum === CryptoAssetListDetailControlEnum::OPEN_POSITIONS) {
			$fields = array_merge($fields, [
				'Množství',
				'Celková hodnota v CZK',
				'% z portfolia',
				'% Ziskovost',
				'Zisk/Ztráta',
			]);
		}

		$template->fields = $fields;
		$template->totalInvestedAmountInCzk = $totalInvestedAmountInCzk;
		$template->sortedCryptoAssetsPositionsDTOs = $sortedCryptoAssetsPositionsDTOs;
		$template->assetDetailControlEnum = $this->assetDetailControlEnum;
		$template->setFile(__DIR__ . '/templates/CryptoAssetListSummaryDetailControl.latte');
		$template->render();
	}

}
