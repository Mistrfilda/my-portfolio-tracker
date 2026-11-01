<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Statistic\PortfolioStatisticCache;
use App\Stock\Asset\StockAsset;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDataImportGuard
{

	public function __construct(
		private EntityManagerInterface $entityManager,
		private readonly PortfolioStatisticCache $statisticCache,
	)
	{
	}

	/** @param callable(): void $import */
	public function import(
		StockAsset $asset,
		StockAssetDataType $type,
		ImmutableDateTime $downloadedAt,
		callable $import,
	): void
	{
		$changed = $this->entityManager->wrapInTransaction(
			function () use ($asset, $type, $downloadedAt, $import): bool {
				$this->entityManager->refresh($asset, LockMode::PESSIMISTIC_WRITE);
				if ($type->getUpdatedAt($asset) !== null && $type->getUpdatedAt($asset) >= $downloadedAt) {
					return false;
				}

				$import();
				return true;
			},
		);
		if ($changed && in_array($type, [StockAssetDataType::PRICE, StockAssetDataType::DIVIDENDS], true)) {
			$this->statisticCache->invalidateAll();
		}
	}

}
