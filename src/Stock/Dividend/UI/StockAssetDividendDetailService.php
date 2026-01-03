<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI;

use App\Currency\CurrencyConversionFacade;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividendRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDividendDetailService
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function getDetailDTOFromDate(
		StockAsset $stockAsset,
		ImmutableDateTime $from,
	): StockAssetDividendDetailDTO
	{
		return new StockAssetDividendDetailDTO(
			$this->currencyConversionFacade,
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
			$this->currencyConversionFacade,
			$stockAsset,
			$this->stockAssetDividendRecordRepository->findByStockAssetForYear($stockAsset, $year),
			$this->stockAssetDividendRepository->findByStockAssetForYear($stockAsset, $year),
		);
	}

	public function getDetailDTOTotal(
		StockAsset $stockAsset,
	): StockAssetDividendDetailDTO
	{
		$dividendRecords = $this->stockAssetDividendRecordRepository->findByStockAsset($stockAsset);
		$dividends = [];
		foreach ($dividendRecords as $dividendRecord) {
			$dividends[] = $dividendRecord->getStockAssetDividend();
		}

		return new StockAssetDividendDetailDTO(
			$this->currencyConversionFacade,
			$stockAsset,
			$this->stockAssetDividendRecordRepository->findByStockAsset($stockAsset),
			$dividends,
		);
	}

}
