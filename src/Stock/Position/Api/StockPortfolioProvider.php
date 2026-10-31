<?php

declare(strict_types = 1);

namespace App\Stock\Position\Api;

use App\Admin\AppAdmin;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\UI\Detail\List\StockAssetListDetailControlEnum;
use App\Stock\Dividend\UI\Detail\StockDividendDetailControl;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\StockPositionFacade;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class StockPortfolioProvider
{

	public function __construct(
		private StockAssetRepository $assets,
		private StockPositionFacade $positions,
		private StockAssetDividendDetailService $dividends,
		private StockHoldingSerializer $serializer,
		private SummaryPriceService $prices,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/** @return array<string, mixed> */
	public function getPortfolio(AppAdmin $owner): array
	{
		$now = $this->datetimeFactory->createNow();
		$from = $now->deductDaysFromDatetime(StockDividendDetailControl::DAYS_SINCE);
		$details = [];
		$total = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($this->assets->findWithPositionsForAppAdmin($owner, true) as $asset) {
			$detail = $this->positions->getStockAssetDetailDTO(
				$asset->getId(),
				StockAssetListDetailControlEnum::OPEN_POSITIONS,
				$owner,
			);
			if ($detail->getPositions() === []) {
				continue;
			}

			$details[] = $detail;
			$total->addSummaryPrice($detail->getCurrentPriceInCzk());
		}

		usort($details, static fn (StockAssetDetailDTO $a, StockAssetDetailDTO $b): int =>
			$b->getCurrentPriceInCzk()->getPrice() <=> $a->getCurrentPriceInCzk()->getPrice());
		$invested = $this->positions->getTotalInvestedAmountSummaryPrice(CurrencyEnum::CZK, $owner);
		$diff = $this->prices->getSummaryPriceDiff($total, $invested);
		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'currency' => 'CZK',
			'total' => [
				'investedAmount' => $invested->getPrice(),
				'currentValue' => $total->getPrice(),
				'profit' => $diff->getPriceDifference(),
				'profitPercent' => $diff->getPercentageDifference(),
			],
			'items' => array_map(
				fn (StockAssetDetailDTO $detail): array => $this->serializer->serialize(
					$detail,
					$this->dividends->getMarketDetailDTOFromDate($detail->getStockAsset(), $from),
					$from,
					$total->getPrice(),
				),
				$details,
			),
		];
	}

}
