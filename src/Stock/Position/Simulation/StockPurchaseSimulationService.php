<?php

declare(strict_types = 1);

namespace App\Stock\Position\Simulation;

use App\Admin\AppAdmin;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\Api\StockPortfolioProvider;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

final readonly class StockPurchaseSimulationService
{

	private const int STALE_PRICE_WARNING_DAYS = 7;

	public function __construct(
		private DatetimeFactory $datetimeFactory,
		private StockPortfolioProvider $stockPortfolioProvider,
		private StockAssetRepository $stockAssetRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
		private StockValuationDataRepository $valuationDataRepository,
		private StockAssetDividendDetailService $dividendDetailService,
	)
	{
	}

	public function simulate(
		AppAdmin $appAdmin,
		StockAsset $stockAsset,
		float|null $amount,
		CurrencyEnum|null $amountCurrency,
		float|null $shares,
		float|null $priceOverride = null,
	): StockPurchaseSimulationResult
	{
		if ($amount !== null && $shares !== null) {
			throw new StockPurchaseSimulationException('Provide either amount or shares, not both.');
		}

		if ($amount === null && $shares === null) {
			throw new StockPurchaseSimulationException('Provide either amount (with amountCurrency) or shares.');
		}

		if ($amount !== null) {
			if (!is_finite($amount) || $amount <= 0.0) {
				throw new StockPurchaseSimulationException('Purchase amount must be greater than zero.');
			}

			if ($amountCurrency === null) {
				throw new StockPurchaseSimulationException('amountCurrency is required when specifying amount.');
			}
		}

		if ($shares !== null && (!is_finite($shares) || $shares <= 0.0)) {
			throw new StockPurchaseSimulationException('Purchase shares count must be greater than zero.');
		}

		if ($priceOverride !== null && (!is_finite($priceOverride) || $priceOverride <= 0.0)) {
			throw new StockPurchaseSimulationException('priceOverride must be greater than zero.');
		}

		$now = $this->datetimeFactory->createNow();
		$warnings = [];

		$usedPriceOverride = $priceOverride !== null;
		$usedPrice = $priceOverride ?? $stockAsset->getAssetCurrentPrice()->getPrice();
		if (!is_finite($usedPrice) || $usedPrice <= 0.0) {
			throw new StockPurchaseSimulationException(
				sprintf(
					'Current price for "%s" is unavailable or zero. Provide priceOverride to run a simulation.',
					$stockAsset->getTicker(),
				),
			);
		}

		if (!$usedPriceOverride) {
			$priceDownloadedAt = $stockAsset->getPriceDownloadedAt();
			if ($priceDownloadedAt === null) {
				$warnings[] = 'Stock price download timestamp is missing; verify the current price.';
			} elseif ($priceDownloadedAt < $now->deductDaysFromDatetime(self::STALE_PRICE_WARNING_DAYS)) {
				$warnings[] = sprintf(
					'Stock price was last downloaded on %s (older than %d days).',
					$priceDownloadedAt->format('Y-m-d'),
					self::STALE_PRICE_WARNING_DAYS,
				);
			}
		}

		$stockCurrency = $stockAsset->getCurrency();
		$fxRateStockToCzk = $this->resolveFxRate($stockCurrency, CurrencyEnum::CZK);

		if ($amount !== null && $amountCurrency !== null) {
			$investedInStockCurrency = $amountCurrency === $stockCurrency
				? $amount
				: $this->convertAmount($amount, $amountCurrency, $stockCurrency);
			$simulatedShares = $investedInStockCurrency / $usedPrice;
			$investedInCzk = $amountCurrency === CurrencyEnum::CZK
				? $amount
				: $investedInStockCurrency * $fxRateStockToCzk;
		} else {
			assert($shares !== null);
			$simulatedShares = $shares;
			$investedInStockCurrency = $simulatedShares * $usedPrice;
			$investedInCzk = $investedInStockCurrency * $fxRateStockToCzk;
		}

		$portfolio = $this->stockPortfolioProvider->getPortfolio($appAdmin);
		$heldAssetsById = [];
		foreach ($this->stockAssetRepository->findWithPositionsForAppAdmin($appAdmin, true) as $heldAsset) {
			$heldAssetsById[$heldAsset->getId()->toString()] = $heldAsset;
		}

		$total = TypeValidator::validateArray($portfolio['total']);
		$existingPortfolioValueCzk = TypeValidator::validateFloat($total['currentValue']);
		$rawItems = TypeValidator::validateArray($portfolio['items']);

		$targetId = $stockAsset->getId()->toString();
		$targetIndustryName = $stockAsset->getIndustry()?->getName();
		$sectorName = $targetIndustryName ?? 'Unassigned';
		if ($targetIndustryName === null) {
			$warnings[] = 'Stock has no assigned sector/industry; sector weight is grouped under Unassigned.';
		}

		$existingShares = 0;
		$existingAveragePurchasePrice = null;
		$existingPositionValueCzk = 0.0;
		$existingSectorValueCzk = 0.0;

		foreach ($rawItems as $rawItem) {
			$item = TypeValidator::validateArray($rawItem);
			$stock = TypeValidator::validateArray($item['stock']);
			$itemStockId = TypeValidator::validateString($stock['id']);
			$itemValueCzk = TypeValidator::validateFloat($item['currentValueCzk']);
			$itemSector = ($heldAssetsById[$itemStockId] ?? null)?->getIndustry()?->getName() ?? 'Unassigned';

			if ($itemSector === $sectorName) {
				$existingSectorValueCzk += $itemValueCzk;
			}

			if ($itemStockId === $targetId) {
				$existingShares = TypeValidator::validateInt($item['pieces']);
				$existingAveragePurchasePrice = TypeValidator::validateFloat($item['averagePurchasePrice']);
				$existingPositionValueCzk = $itemValueCzk;
			}
		}

		$existingPositionWeightPercent = $existingPortfolioValueCzk > 0.0
			? $existingPositionValueCzk / $existingPortfolioValueCzk * 100
			: 0.0;
		$existingSectorWeightPercent = $existingPortfolioValueCzk > 0.0
			? $existingSectorValueCzk / $existingPortfolioValueCzk * 100
			: 0.0;

		$projectedShares = $existingShares + $simulatedShares;
		$projectedAveragePurchasePrice = $existingShares > 0 && $existingAveragePurchasePrice !== null
			? (($existingShares * $existingAveragePurchasePrice) + $investedInStockCurrency) / $projectedShares
			: $usedPrice;

		$projectedPositionValueCzk = $existingPositionValueCzk + $investedInCzk;
		$projectedPortfolioValueCzk = $existingPortfolioValueCzk + $investedInCzk;
		$projectedSectorValueCzk = $existingSectorValueCzk + $investedInCzk;

		$projectedPositionWeightPercent = $projectedPortfolioValueCzk > 0.0
			? $projectedPositionValueCzk / $projectedPortfolioValueCzk * 100
			: 0.0;
		$projectedSectorWeightPercent = $projectedPortfolioValueCzk > 0.0
			? $projectedSectorValueCzk / $projectedPortfolioValueCzk * 100
			: 0.0;

		[$annualDividendPerShare, $dividendRateSource] = $this->resolveAnnualDividendPerShare($stockAsset, $now);

		$estimatedAnnualGrossDividendInStockCurrency = null;
		$estimatedAnnualNetDividendInStockCurrency = null;
		$estimatedAnnualGrossDividendCzk = null;
		$estimatedAnnualNetDividendCzk = null;
		$estimatedGrossYieldOnPurchasePercent = null;
		$estimatedNetYieldOnPurchasePercent = null;

		if ($annualDividendPerShare !== null && $annualDividendPerShare > 0.0) {
			$taxPercent = $stockAsset->getDividendTax() ?? 0.0;
			$taxMultiplier = max(0.0, 1.0 - ($taxPercent / 100));

			$estimatedAnnualGrossDividendInStockCurrency = $simulatedShares * $annualDividendPerShare;
			$estimatedAnnualNetDividendInStockCurrency = $estimatedAnnualGrossDividendInStockCurrency * $taxMultiplier;
			$estimatedAnnualGrossDividendCzk = $estimatedAnnualGrossDividendInStockCurrency * $fxRateStockToCzk;
			$estimatedAnnualNetDividendCzk = $estimatedAnnualNetDividendInStockCurrency * $fxRateStockToCzk;
			$estimatedGrossYieldOnPurchasePercent = $annualDividendPerShare / $usedPrice * 100;
			$estimatedNetYieldOnPurchasePercent = $estimatedGrossYieldOnPurchasePercent * $taxMultiplier;
		} elseif ($stockAsset->doesPaysDividends()) {
			$warnings[] = 'No annual dividend rate or trailing 12-month dividend history is available to estimate dividend income.';
		}

		return new StockPurchaseSimulationResult(
			generatedAt: $now,
			stockAsset: $stockAsset,
			usedPrice: $usedPrice,
			usedPriceOverride: $usedPriceOverride,
			simulatedShares: $simulatedShares,
			investedAmountInStockCurrency: $investedInStockCurrency,
			investedAmountInCzk: $investedInCzk,
			fxRateStockToCzk: $fxRateStockToCzk,
			inputAmount: $amount,
			inputAmountCurrency: $amountCurrency,
			sectorName: $sectorName,
			existingShares: $existingShares,
			existingAveragePurchasePrice: $existingAveragePurchasePrice,
			existingPositionValueCzk: $existingPositionValueCzk,
			existingPortfolioValueCzk: $existingPortfolioValueCzk,
			existingPositionWeightPercent: $existingPositionWeightPercent,
			existingSectorValueCzk: $existingSectorValueCzk,
			existingSectorWeightPercent: $existingSectorWeightPercent,
			projectedShares: $projectedShares,
			projectedAveragePurchasePrice: $projectedAveragePurchasePrice,
			projectedPositionValueCzk: $projectedPositionValueCzk,
			projectedPortfolioValueCzk: $projectedPortfolioValueCzk,
			projectedPositionWeightPercent: $projectedPositionWeightPercent,
			projectedSectorValueCzk: $projectedSectorValueCzk,
			projectedSectorWeightPercent: $projectedSectorWeightPercent,
			annualDividendPerShare: $annualDividendPerShare,
			dividendRateSource: $dividendRateSource,
			estimatedAnnualGrossDividendInStockCurrency: $estimatedAnnualGrossDividendInStockCurrency,
			estimatedAnnualNetDividendInStockCurrency: $estimatedAnnualNetDividendInStockCurrency,
			estimatedAnnualGrossDividendCzk: $estimatedAnnualGrossDividendCzk,
			estimatedAnnualNetDividendCzk: $estimatedAnnualNetDividendCzk,
			estimatedGrossYieldOnPurchasePercent: $estimatedGrossYieldOnPurchasePercent,
			estimatedNetYieldOnPurchasePercent: $estimatedNetYieldOnPurchasePercent,
			warnings: $warnings,
		);
	}

	private function resolveFxRate(CurrencyEnum $from, CurrencyEnum $to): float
	{
		if ($from === $to) {
			return 1.0;
		}

		return $this->convertAmount(1.0, $from, $to);
	}

	private function convertAmount(float $value, CurrencyEnum $from, CurrencyEnum $to): float
	{
		try {
			return $this->currencyConversionFacade->convertSimpleValue($value, $from, $to);
		} catch (MissingCurrencyPairException $e) {
			throw new StockPurchaseSimulationException(
				sprintf('Missing exchange rate for currency pair %s -> %s.', $from->value, $to->value),
				0,
				$e,
			);
		}
	}

	/**
	 * @return array{float|null, string|null}
	 */
	private function resolveAnnualDividendPerShare(StockAsset $stockAsset, ImmutableDateTime $now): array
	{
		$metrics = $this->valuationDataRepository->findTypesLatestForStockAsset($stockAsset, [
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
		]);

		$forwardRate = ($metrics[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE->value] ?? null)?->getFloatValue();
		if ($forwardRate !== null && is_finite($forwardRate) && $forwardRate > 0.0) {
			return [$forwardRate, 'forward_annual_dividend_rate'];
		}

		$trailingRate = ($metrics[StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE->value] ?? null)?->getFloatValue();
		if ($trailingRate !== null && is_finite($trailingRate) && $trailingRate > 0.0) {
			return [$trailingRate, 'trailing_annual_dividend_rate'];
		}

		if (!$stockAsset->doesPaysDividends()) {
			return [null, null];
		}

		$marketDetail = $this->dividendDetailService->getMarketDetailDTOFromDate(
			$stockAsset,
			$now->deductDaysFromDatetime(365),
		);
		$trailing12mGross = $marketDetail->getDividendsSummaryPriceWithTax()->getPrice();
		if (is_finite($trailing12mGross) && $trailing12mGross > 0.0) {
			return [$trailing12mGross, 'trailing_12m_dividends'];
		}

		return [null, null];
	}

}
