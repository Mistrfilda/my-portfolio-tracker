<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Stock\Asset\StockAsset;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDataImportGuard
{

	public function __construct(private EntityManagerInterface $entityManager)
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
		$this->entityManager->wrapInTransaction(function () use ($asset, $type, $downloadedAt, $import): void {
			$this->entityManager->refresh($asset, LockMode::PESSIMISTIC_WRITE);
			if ($type->getUpdatedAt($asset) !== null && $type->getUpdatedAt($asset) >= $downloadedAt) {
				return;
			}

			$import();
		});
	}

}
