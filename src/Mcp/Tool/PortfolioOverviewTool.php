<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\Api\StockPortfolioProvider;
use App\Utils\TypeValidator;

class PortfolioOverviewTool
{

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly StockPortfolioProvider $stockPortfolioProvider,
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockAiAnalysisSettingsFacade $settingsFacade,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(bool $includeInvestorInstructions = true): array
	{
		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$portfolio = $this->stockPortfolioProvider->getPortfolio($owner);

		/** @var array<string, StockAsset> $assetsById */
		$assetsById = [];
		foreach ($this->stockAssetRepository->findWithPositionsForAppAdmin($owner, true) as $asset) {
			$assetsById[$asset->getId()->toString()] = $asset;
		}

		$total = TypeValidator::validateArray($portfolio['total']);
		$totalCurrentValueCzk = TypeValidator::validateFloat($total['currentValue']);
		$rawItems = TypeValidator::validateArray($portfolio['items']);

		$positions = [];
		/** @var array<string, array{sector: string, valueCzk: float, positionsCount: int}> $sectorMap */
		$sectorMap = [];
		/** @var array<string, array{currency: string, valueCzk: float, positionsCount: int}> $currencyMap */
		$currencyMap = [];

		foreach ($rawItems as $rawItem) {
			$item = TypeValidator::validateArray($rawItem);
			$stock = TypeValidator::validateArray($item['stock']);
			$amounts = TypeValidator::validateArray($item['amounts']);

			$stockId = TypeValidator::validateString($stock['id']);
			$asset = $assetsById[$stockId] ?? null;
			$sector = $asset?->getIndustry()?->getName();
			$sectorKey = $sector ?? 'Unassigned';
			$currency = TypeValidator::validateString($stock['currency']);
			$valueCzk = TypeValidator::validateFloat($item['currentValueCzk']);

			$positions[] = [
				'stockAssetId' => $stockId,
				'ticker' => TypeValidator::validateString($stock['ticker']),
				'name' => TypeValidator::validateString($stock['name']),
				'sector' => $sector,
				'currency' => $currency,
				'pieces' => TypeValidator::validateInt($item['pieces']),
				'averagePurchasePrice' => round(TypeValidator::validateFloat($item['averagePurchasePrice']), 4),
				'currentPrice' => round(TypeValidator::validateFloat($stock['currentPrice']), 4),
				'investedAmount' => round(TypeValidator::validateFloat($amounts['investedAmount']), 2),
				'currentValue' => round(TypeValidator::validateFloat($amounts['currentValue']), 2),
				'profit' => round(TypeValidator::validateFloat($amounts['profit']), 2),
				'profitPercent' => round(TypeValidator::validateFloat($amounts['profitPercent']), 2),
				'currentValueCzk' => round($valueCzk, 2),
				'weightPercent' => round(TypeValidator::validateFloat($item['portfolioSharePercent']), 2),
				'priceDownloadedAt' => TypeValidator::validateNullableString($stock['priceUpdatedAt']),
			];

			if (!isset($sectorMap[$sectorKey])) {
				$sectorMap[$sectorKey] = [
					'sector' => $sectorKey,
					'valueCzk' => 0.0,
					'positionsCount' => 0,
				];
			}

			$sectorMap[$sectorKey]['valueCzk'] += $valueCzk;
			$sectorMap[$sectorKey]['positionsCount']++;

			if (!isset($currencyMap[$currency])) {
				$currencyMap[$currency] = [
					'currency' => $currency,
					'valueCzk' => 0.0,
					'positionsCount' => 0,
				];
			}

			$currencyMap[$currency]['valueCzk'] += $valueCzk;
			$currencyMap[$currency]['positionsCount']++;
		}

		$sectorAllocation = $this->finalizeAllocation($sectorMap, $totalCurrentValueCzk);
		$currencyAllocation = $this->finalizeAllocation($currencyMap, $totalCurrentValueCzk);

		return [
			'generatedAt' => TypeValidator::validateString($portfolio['generatedAt']),
			'currency' => TypeValidator::validateString($portfolio['currency']),
			'total' => [
				'investedAmountCzk' => round(TypeValidator::validateFloat($total['investedAmount']), 2),
				'currentValueCzk' => round($totalCurrentValueCzk, 2),
				'profitCzk' => round(TypeValidator::validateFloat($total['profit']), 2),
				'profitPercent' => round(TypeValidator::validateFloat($total['profitPercent']), 2),
			],
			'positionsCount' => count($positions),
			'positions' => $positions,
			'sectorAllocation' => $sectorAllocation,
			'currencyAllocation' => $currencyAllocation,
			'investorInstructions' => $includeInvestorInstructions
				? $this->settingsFacade->getInvestorInstructions($owner)
				: null,
		];
	}

	/**
	 * @template T of array{valueCzk: float, positionsCount: int}
	 * @param array<string, T> $buckets
	 * @return list<array<string, float|int|string>>
	 */
	private function finalizeAllocation(array $buckets, float $totalValueCzk): array
	{
		$items = array_values($buckets);
		usort($items, static fn (array $a, array $b): int => $b['valueCzk'] <=> $a['valueCzk']);

		return array_map(
			static function (array $bucket) use ($totalValueCzk): array {
				$weight = $totalValueCzk > 0.0 ? $bucket['valueCzk'] / $totalValueCzk * 100 : 0.0;
				$bucket['valueCzk'] = round($bucket['valueCzk'], 2);
				$bucket['weightPercent'] = round($weight, 2);

				return $bucket;
			},
			$items,
		);
	}

}
