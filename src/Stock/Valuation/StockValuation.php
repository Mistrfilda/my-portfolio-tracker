<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataChange;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockValuation
{

	/** @var ArrayCollection<string, StockValuationData> */
	private ArrayCollection $currentStockValuationDataCollection;

	/**
	 * @param array<string, StockValuationData> $currentStockValuationData
	 * @param array<string, StockValuationData> $previousStockValuationData
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $currentStockValuationData,
		private array $previousStockValuationData = [],
	)
	{
		$this->currentStockValuationDataCollection = new ArrayCollection($currentStockValuationData);
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<string, StockValuationData>
	 */
	public function getCurrentStockValuationData(): array
	{
		return $this->currentStockValuationData;
	}

	/**
	 * @return ArrayCollection<string, StockValuationData>
	 */
	public function getCurrentStockValuationDataCollection(): ArrayCollection
	{
		return $this->currentStockValuationDataCollection;
	}

	public function getValuationDataByType(StockValuationTypeEnum $type): StockValuationData|null
	{
		return $this->currentStockValuationData[$type->value] ?? null;
	}

	public function getImportedAt(): ImmutableDateTime|null
	{
		return $this->getLatestImportDate($this->currentStockValuationData);
	}

	public function getPreviousImportedAt(): ImmutableDateTime|null
	{
		return $this->getLatestImportDate($this->previousStockValuationData);
	}

	/** @return array<StockValuationDataChange> */
	public function getInputChanges(): array
	{
		if ($this->previousStockValuationData === []) {
			return [];
		}

		$changes = [];
		$trackedTypes = [
			StockValuationTypeEnum::DILUTED_EPS,
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
			StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW,
			StockValuationTypeEnum::EBITDA,
			StockValuationTypeEnum::TOTAL_DEBT,
			StockValuationTypeEnum::TOTAL_CASH,
			StockValuationTypeEnum::SHARES_OUTSTANDING,
			StockValuationTypeEnum::REVENUE_PER_SHARE,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::RETURN_ON_EQUITY,
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
			StockValuationTypeEnum::PAYOUT_RATIO,
			StockValuationTypeEnum::PEG_RATIO,
		];
		foreach ($trackedTypes as $type) {
			$previous = $this->previousStockValuationData[$type->value] ?? null;
			$current = $this->currentStockValuationData[$type->value] ?? null;
			if ($previous?->getFloatValue() !== $current?->getFloatValue()
				|| (
					$type->isCurrencyValue()
					&& $current?->getFloatValue() !== null
					&& $previous?->getFloatValue() !== null
					&& $previous->getCurrency() !== $current->getCurrency()
				)
			) {
				$changes[] = new StockValuationDataChange($type, $previous, $current);
			}
		}

		return $changes;
	}

	/** @param array<string, StockValuationData> $data */
	private function getLatestImportDate(array $data): ImmutableDateTime|null
	{
		$latest = null;
		foreach ($data as $value) {
			if ($value->getTypeGroup() === StockValuationTypeGroupEnum::ANALYST_INSIGHT) {
				continue;
			}

			if ($latest === null || $value->getParsedAt() > $latest) {
				$latest = $value->getParsedAt();
			}
		}

		return $latest;
	}

}
