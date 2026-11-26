<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Stock\Dividend\Forecast\StockAssetDividendRemainingForecastProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\Safety\StockDividendSafetyScoreProvider;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use const DATE_ATOM;

class StockDividendsTool
{

	private const int RECENT_DIVIDENDS_LIMIT = 12;

	private const int RECENT_PORTFOLIO_RECORDS_LIMIT = 15;

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockAssetResolver $stockAssetResolver,
		private readonly StockAssetDividendDetailService $dividendDetailService,
		private readonly StockAssetDividendRepository $dividendRepository,
		private readonly StockAssetDividendRecordRepository $dividendRecordRepository,
		private readonly StockAssetDividendRecordFacade $dividendRecordFacade,
		private readonly StockAssetDividendRemainingForecastProvider $remainingForecastProvider,
		private readonly StockDividendSafetyScoreProvider $safetyScoreProvider,
		private readonly StockValuationDataRepository $valuationDataRepository,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(
		string $scope = 'stock',
		string|null $ticker = null,
		string|null $stockAssetId = null,
	): array
	{
		$normalizedScope = strtolower(trim($scope));
		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();

		return match ($normalizedScope) {
			'stock' => $this->getStockDividends($owner, $now, $ticker, $stockAssetId),
			'portfolio' => $this->getPortfolioDividends($owner, $now),
			default => throw new McpInvalidInputException(
				sprintf('Unsupported scope "%s". Allowed values: stock, portfolio.', $scope),
			),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getStockDividends(
		AppAdmin $owner,
		ImmutableDateTime $now,
		string|null $ticker,
		string|null $stockAssetId,
	): array
	{
		$asset = $this->stockAssetResolver->resolve($ticker, $stockAssetId);
		$trailingFrom = $now->deductDaysFromDatetime(365);
		$marketDetail = $this->dividendDetailService->getMarketDetailDTOFromDate($asset, $trailingFrom);

		$valuationMetrics = $this->valuationDataRepository->findTypesLatestForStockAsset($asset, [
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD,
			StockValuationTypeEnum::PAYOUT_RATIO,
		]);

		$currentPrice = $asset->getAssetCurrentPrice()->getPrice();
		$trailingGrossPerShare = $marketDetail->getDividendsSummaryPriceWithTax()->getPrice();
		$trailingNetPerShare = $marketDetail->getDividendsSummaryPrice()->getPrice();

		$allDividends = $this->dividendRepository->findByStockAsset($asset);
		$upcoming = [];
		$historical = [];
		$today = $now->format('Y-m-d');

		foreach ($allDividends as $dividend) {
			$exDateStr = $dividend->getExDate()->format('Y-m-d');
			$paymentDate = $dividend->getPaymentDate();
			$isUpcoming = $exDateStr >= $today || ($paymentDate !== null && $paymentDate > $now);
			if ($isUpcoming) {
				$upcoming[] = $dividend;
			} else {
				$historical[] = $dividend;
			}
		}

		usort(
			$upcoming,
			static fn (StockAssetDividend $a, StockAssetDividend $b): int => $a->getExDate() <=> $b->getExDate(),
		);
		usort(
			$historical,
			static fn (StockAssetDividend $a, StockAssetDividend $b): int => $b->getExDate() <=> $a->getExDate(),
		);
		$recentHistorical = array_slice($historical, 0, self::RECENT_DIVIDENDS_LIMIT);

		$safetyScore = $this->safetyScoreProvider->getForStockAsset($asset);
		$personalRecords = $this->dividendRecordRepository->findByStockAsset($asset, $owner);
		$personalTotal = $this->dividendRecordFacade->getTotalSummaryPriceForStockAsset($asset, $owner);

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'scope' => 'stock',
			'stock' => [
				'stockAssetId' => $asset->getId()->toString(),
				'ticker' => $asset->getTicker(),
				'name' => $asset->getName(),
				'currency' => $asset->getCurrency()->value,
				'currentPrice' => round($currentPrice, 4),
				'priceDownloadedAt' => $asset->getPriceDownloadedAt()?->format(DATE_ATOM),
				'dividendsCheckedAt' => $asset->getDividendsCheckedAt()?->format(DATE_ATOM),
				'paysDividends' => $asset->doesPaysDividends(),
				'dividendTaxPercent' => $asset->getDividendTax(),
			],
			'yieldMetrics' => [
				'trailing12mGrossPerShare' => round($trailingGrossPerShare, 4),
				'trailing12mNetPerShare' => round($trailingNetPerShare, 4),
				'trailing12mGrossYieldPercent' => $currentPrice > 0.0
					? round($trailingGrossPerShare / $currentPrice * 100, 2)
					: null,
				'trailing12mNetYieldPercent' => $currentPrice > 0.0
					? round($trailingNetPerShare / $currentPrice * 100, 2)
					: null,
				'forwardAnnualDividendRate' => (
					$valuationMetrics[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE->value] ?? null
				)?->getFloatValue(),
				'forwardAnnualDividendYieldPercent' => (
					$valuationMetrics[StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD->value] ?? null
				)?->getFloatValue(),
				'trailingAnnualDividendRate' => (
					$valuationMetrics[StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE->value] ?? null
				)?->getFloatValue(),
				'trailingAnnualDividendYieldPercent' => (
					$valuationMetrics[StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_YIELD->value] ?? null
				)?->getFloatValue(),
				'payoutRatioPercent' => ($valuationMetrics[StockValuationTypeEnum::PAYOUT_RATIO->value] ?? null)?->getFloatValue(),
			],
			'safetyScore' => [
				'score' => $safetyScore->getScore(),
				'status' => $safetyScore->getStatus()->value,
				'reasons' => $safetyScore->getReasons(),
			],
			'upcomingDividends' => array_map($this->serializeDividend(...), $upcoming),
			'recentDividends' => array_map($this->serializeDividend(...), $recentHistorical),
			'personalReceivedSummary' => [
				'recordsCount' => count($personalRecords),
				'totalReceivedAfterTax' => $personalTotal !== null ? round($personalTotal->getPrice(), 2) : 0.0,
				'currency' => $personalTotal?->getCurrency()->value ?? ($asset->getBrokerDividendCurrency() ?? $asset->getCurrency())->value,
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getPortfolioDividends(AppAdmin $owner, ImmutableDateTime $now): array
	{
		$currentYear = $now->getYear();
		$totalReceived = $this->dividendRecordFacade->getTotalSummaryPrice(false, $owner);
		$yearlyDtos = $this->dividendRecordFacade->getDividendsByYears($owner);
		$remainingForecast = $this->remainingForecastProvider->getForYear($currentYear, $owner);
		$currentYearRecords = $this->dividendRecordRepository->findByYearAndMonth($currentYear, null, $owner);
		$recentRecords = $this->dividendRecordRepository->findLastDividendRecords(
			self::RECENT_PORTFOLIO_RECORDS_LIMIT,
			$owner,
		);

		$monthlyTotals = [];
		$warnings = [];
		foreach ($currentYearRecords as $record) {
			$month = $record->getStockAssetDividend()->getExDate()->format('Y-m');
			if (!isset($monthlyTotals[$month])) {
				$monthlyTotals[$month] = [
					'month' => $month,
					'netCzk' => 0.0,
					'grossCzk' => 0.0,
					'paymentsCount' => 0,
				];
			}

			try {
				$netCzk = $this->convertRecordToCzk($record, true);
				$grossCzk = $this->convertRecordToCzk($record, false);
				$monthlyTotals[$month]['netCzk'] += $netCzk;
				$monthlyTotals[$month]['grossCzk'] += $grossCzk;
				$monthlyTotals[$month]['paymentsCount']++;
			} catch (MissingCurrencyPairException) {
				$warnings[] = sprintf(
					'Missing exchange rate for %s dividend on %s; omitted from monthly CZK total.',
					$record->getStockAssetDividend()->getStockAsset()->getTicker(),
					$record->getStockAssetDividend()->getExDate()->format('Y-m-d'),
				);
			}
		}

		ksort($monthlyTotals);
		$monthlySummary = array_values(array_map(
			static fn (array $row): array => [
				'month' => $row['month'],
				'netCzk' => round($row['netCzk'], 2),
				'grossCzk' => round($row['grossCzk'], 2),
				'paymentsCount' => $row['paymentsCount'],
			],
			$monthlyTotals,
		));

		$yearlySummary = [];
		foreach ($yearlyDtos as $dto) {
			$yearlySummary[] = [
				'year' => $dto->getYear(),
				'netCzk' => round($dto->getSummaryPriceWithoutTax()->getPrice(), 2),
				'grossCzk' => round($dto->getSummaryPriceWithTax()->getPrice(), 2),
			];
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'scope' => 'portfolio',
			'currency' => CurrencyEnum::CZK->value,
			'totalReceivedNetCzk' => round($totalReceived->getPrice(), 2),
			'currentYear' => $currentYear,
			'remainingForecastCurrentYear' => $remainingForecast === null
				? null
				: [
					'year' => $remainingForecast->getYear(),
					'remainingNetCzk' => round($remainingForecast->getSummaryPriceWithoutTax()->getPrice(), 2),
					'remainingGrossCzk' => round($remainingForecast->getSummaryPriceWithTax()->getPrice(), 2),
				],
			'yearlySummary' => $yearlySummary,
			'currentYearMonthlySummary' => $monthlySummary,
			'recentRecords' => array_map($this->serializeRecord(...), $recentRecords),
			'warnings' => array_values(array_unique($warnings)),
		];
	}

	private function convertRecordToCzk(StockAssetDividendRecord $record, bool $deductTax): float
	{
		$price = $record->getSummaryPrice($deductTax);
		if ($price->getCurrency() === CurrencyEnum::CZK) {
			return $price->getPrice();
		}

		return $this->currencyConversionFacade->getConvertedSummaryPrice(
			$price,
			CurrencyEnum::CZK,
			$record->getStockAssetDividend()->getExDate(),
		)->getPrice();
	}

	/**
	 * @return array<string, float|string|null>
	 */
	private function serializeDividend(StockAssetDividend $dividend): array
	{
		return [
			'exDate' => $dividend->getExDate()->format('Y-m-d'),
			'paymentDate' => $dividend->getPaymentDate()?->format('Y-m-d'),
			'declarationDate' => $dividend->getDeclarationDate()?->format('Y-m-d'),
			'amountPerShare' => round($dividend->getAmount(), 4),
			'currency' => $dividend->getCurrency()->value,
			'dividendType' => $dividend->getDividendType()->value,
		];
	}

	/**
	 * @return array<string, float|int|string|null>
	 */
	private function serializeRecord(StockAssetDividendRecord $record): array
	{
		$dividend = $record->getStockAssetDividend();
		$asset = $dividend->getStockAsset();

		return [
			'ticker' => $asset->getTicker(),
			'name' => $asset->getName(),
			'exDate' => $dividend->getExDate()->format('Y-m-d'),
			'paymentDate' => $dividend->getPaymentDate()?->format('Y-m-d'),
			'piecesHeldAtExDate' => $record->getTotalPiecesHeldAtExDate(),
			'grossAmount' => round($record->getSummaryPrice(false)->getPrice(), 2),
			'netAmount' => round($record->getSummaryPrice(true)->getPrice(), 2),
			'currency' => $record->getCurrency()->value,
		];
	}

}
