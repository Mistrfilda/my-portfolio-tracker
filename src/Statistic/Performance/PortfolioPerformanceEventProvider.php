<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Crypto\Position\Closed\CryptoClosedPositionRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPerformanceEventProvider
{

	/** @var array<PortfolioPerformanceEvent>|null */
	private array|null $events = null;

	public function __construct(
		private readonly StockClosedPositionRepository $stockClosedPositionRepository,
		private readonly CryptoClosedPositionRepository $cryptoClosedPositionRepository,
		private readonly StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private readonly CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function getIncomeBetween(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortfolioPerformanceIncome
	{
		$realizedProfit = 0.0;
		$netDividends = 0.0;

		foreach ($this->getEvents() as $event) {
			if ($event->date <= $start || $event->date > $end) {
				continue;
			}

			$realizedProfit += $event->realizedProfit;
			$netDividends += $event->netDividends;
		}

		return new PortfolioPerformanceIncome($realizedProfit, $netDividends);
	}

	/**
	 * @return array<PortfolioPerformanceEvent>
	 */
	private function getEvents(): array
	{
		if ($this->events !== null) {
			return $this->events;
		}

		$events = [];
		$closedPositions = array_merge(
			$this->stockClosedPositionRepository->findAll(),
			$this->cryptoClosedPositionRepository->findAll(),
		);

		foreach ($closedPositions as $closedPosition) {
			$position = $closedPosition->getAssetPositon();
			$sellPrice = $this->currencyConversionFacade->getConvertedAssetPrice(
				$closedPosition->getTotalCloseAmountInBrokerCurrency(),
				CurrencyEnum::CZK,
				$closedPosition->getDate(),
			)->getPrice();
			$buyPrice = $this->currencyConversionFacade->getConvertedAssetPrice(
				$position->getTotalInvestedAmountInBrokerCurrency(),
				CurrencyEnum::CZK,
				$position->getOrderDate(),
			)->getPrice();

			$events[] = new PortfolioPerformanceEvent(
				$closedPosition->getDate(),
				$sellPrice - $buyPrice,
				0.0,
			);
		}

		foreach ($this->stockAssetDividendRecordRepository->findAll() as $dividendRecord) {
			$dividend = $dividendRecord->getStockAssetDividend();
			$paymentDate = $dividend->getPaymentDate() ?? $dividend->getExDate();
			$netDividend = $this->currencyConversionFacade->getConvertedSummaryPrice(
				$dividendRecord->getSummaryPriceInBrokerCurrency(true),
				CurrencyEnum::CZK,
				$paymentDate,
			)->getPrice();

			$events[] = new PortfolioPerformanceEvent($paymentDate, 0.0, $netDividend);
		}

		usort(
			$events,
			static fn (PortfolioPerformanceEvent $left, PortfolioPerformanceEvent $right): int =>
				$left->date <=> $right->date,
		);

		$this->events = $events;
		return $events;
	}

}
