<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Support;

use App\Stock\Asset\Download\StockAssetDataImportGuard;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Asset\StockAsset;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

trait StockAssetDataImportGuardStub
{

	private function createImportGuardStub(): StockAssetDataImportGuard
	{
		$guard = $this->createStub(StockAssetDataImportGuard::class);
		$guard->method('import')->willReturnCallback(
			static function (
				StockAsset $asset,
				StockAssetDataType $type,
				ImmutableDateTime $at,
				callable $import,
			): void {
				$import();
			},
		);
		return $guard;
	}

}
