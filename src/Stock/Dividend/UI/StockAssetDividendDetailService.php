<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDividendDetailService
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
	)
	{
	}

	public function getDetailDTOFromDate(
		StockAsset $stockAsset,
		ImmutableDateTime $from,
	): StockAssetDividendDetailDTO
	{
		return new StockAssetDividendDetailDTO(
			$stockAsset,
			$this->stockAssetDividendRecordRepository->findByStockAssetSinceDate($stockAsset, $from),
			$this->stockAssetDividendRepository->findByStockAssetSinceDate($stockAsset, $from),
		);
	}

	public function getDetailDTOForYear(
		StockAsset $stockAsset,
		int $year,
	): StockAssetDividendDetailDTO
	{
		return new StockAssetDividendDetailDTO(
			$stockAsset,
			$this->stockAssetDividendRecordRepository->findByStockAssetForYear($stockAsset, $year),
			$this->stockAssetDividendRepository->findByStockAssetForYear($stockAsset, $year),
		);
	}

}
