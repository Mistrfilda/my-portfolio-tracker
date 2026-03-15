<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Stock\Asset\StockAsset;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_asset_dividend_forecast_record')]
class StockAssetDividendForecastRecord implements Entity
{

	use SimpleUuid;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: StockAssetDividendForecast::class, inversedBy: 'records')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAssetDividendForecast $stockAssetDividendForecast;

	#[ORM\ManyToOne(targetEntity: StockAsset::class)]
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	/** @var array<int> */
	#[ORM\Column(type: Types::JSON)]
	private array $dividendUsuallyPaidAtMonths;

	/** @var array<int> */
	#[ORM\Column(type: Types::JSON)]
	private array $receivedDividendMonths;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetDividendTrendEnum::class, nullable: true)]
	private StockAssetDividendTrendEnum|null $specificTrend;

	#[ORM\Column(type: Types::FLOAT)]
	private float $alreadyReceivedDividendPerStock;

	#[ORM\Column(type: Types::FLOAT)]
	private float $alreadyReceivedDividendPerStockBeforeTax;

	#[ORM\Column(type: Types::FLOAT)]
	private float $expectedDividendPerStock;

	#[ORM\Column(type: Types::FLOAT)]
	private float $expectedDividendPerStockBeforeTax;

	#[ORM\Column(type: Types::FLOAT)]
	private float $originalDividendUsedForCalculation;

	#[ORM\Column(type: Types::FLOAT)]
	private float $originalDividendUsedForCalculationBeforeTax;

	#[ORM\Column(type: Types::FLOAT)]
	private float $adjustedDividendUsedForCalculation;

	#[ORM\Column(type: Types::FLOAT)]
	private float $adjustedDividendUsedForCalculationBeforeTax;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $customDividendUsedForCalculation;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $customGrossDividendUsedForCalculation;

	#[ORM\Column(type: Types::INTEGER)]
	private int $piecesCurrentlyHeld;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $specialDividendsLastYearPerStock;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $specialDividendsLastYearPerStockBeforeTax;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $expectedSpecialDividendThisYearPerStock;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $expectedSpecialDividendThisYearPerStockBeforeTax;

	/**
	 * @param array<int> $dividendUsuallyPaidAtMonths
	 * @param array<int> $receivedDividendMonths
	 */
	public function __construct(
		StockAssetDividendForecast $stockAssetDividendForecast,
		StockAsset $stockAsset,
		CurrencyEnum $currency,
		array $dividendUsuallyPaidAtMonths,
		array $receivedDividendMonths,
		float $alreadyReceivedDividendPerStock,
		float $alreadyReceivedDividendPerStockBeforeTax,
		int $piecesCurrentlyHeld,
		float $originalDividendUsedForCalculation,
		float $originalDividendUsedForCalculationBeforeTax,
		float $adjustedDividendUsedForCalculation,
		float $adjustedDividendUsedForCalculationBeforeTax,
		float $expectedDividendPerStock,
		float $expectedDividendPerStockBeforeTax,
		float|null $customDividendUsedForCalculation,
		float|null $customGrossDividendUsedForCalculation,
		float|null $specialDividendsLastYearPerStock,
		float|null $specialDividendsLastYearPerStockBeforeTax,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->stockAssetDividendForecast = $stockAssetDividendForecast;
		$this->stockAsset = $stockAsset;
		$this->currency = $currency;
		$this->dividendUsuallyPaidAtMonths = $dividendUsuallyPaidAtMonths;
		$this->receivedDividendMonths = $receivedDividendMonths;
		$this->alreadyReceivedDividendPerStock = $alreadyReceivedDividendPerStock;
		$this->alreadyReceivedDividendPerStockBeforeTax = $alreadyReceivedDividendPerStockBeforeTax;
		$this->piecesCurrentlyHeld = $piecesCurrentlyHeld;
		$this->originalDividendUsedForCalculation = $originalDividendUsedForCalculation;
		$this->originalDividendUsedForCalculationBeforeTax = $originalDividendUsedForCalculationBeforeTax;
		$this->adjustedDividendUsedForCalculation = $adjustedDividendUsedForCalculation;
		$this->adjustedDividendUsedForCalculationBeforeTax = $adjustedDividendUsedForCalculationBeforeTax;
		$this->expectedDividendPerStock = $expectedDividendPerStock;
		$this->expectedDividendPerStockBeforeTax = $expectedDividendPerStockBeforeTax;
		$this->customDividendUsedForCalculation = $customDividendUsedForCalculation;
		$this->customGrossDividendUsedForCalculation = $customGrossDividendUsedForCalculation;
		$this->specialDividendsLastYearPerStock = $specialDividendsLastYearPerStock;
		$this->specialDividendsLastYearPerStockBeforeTax = $specialDividendsLastYearPerStockBeforeTax;
		$this->createdAt = $now;
	}

	/**
	 * @param array<int> $dividendUsuallyPaidAtMonths
	 * @param array<int> $receivedDividendMonths
	 */
	public function recalculate(
		array $receivedDividendMonths,
		float $alreadyReceivedDividendPerStock,
		float $alreadyReceivedDividendPerStockBeforeTax,
		array $dividendUsuallyPaidAtMonths,
		int $piecesCurrentlyHeld,
		float $originalDividendUsedForCalculation,
		float $originalDividendUsedForCalculationBeforeTax,
		float $adjustedDividendUsedForCalculation,
		float $adjustedDividendUsedForCalculationBeforeTax,
		float $expectedDividendPerStock,
		float $expectedDividendPerStockBeforeTax,
		float|null $customDividendUsedForCalculation,
		float|null $customGrossDividendUsedForCalculation,
		float|null $specialDividendsLastYearPerStock,
		float|null $specialDividendsLastYearPerStockBeforeTax,
	): void
	{
		$this->receivedDividendMonths = $receivedDividendMonths;
		$this->alreadyReceivedDividendPerStock = $alreadyReceivedDividendPerStock;
		$this->alreadyReceivedDividendPerStockBeforeTax = $alreadyReceivedDividendPerStockBeforeTax;
		$this->dividendUsuallyPaidAtMonths = $dividendUsuallyPaidAtMonths;
		$this->piecesCurrentlyHeld = $piecesCurrentlyHeld;
		$this->originalDividendUsedForCalculation = $originalDividendUsedForCalculation;
		$this->originalDividendUsedForCalculationBeforeTax = $originalDividendUsedForCalculationBeforeTax;
		$this->adjustedDividendUsedForCalculation = $adjustedDividendUsedForCalculation;
		$this->adjustedDividendUsedForCalculationBeforeTax = $adjustedDividendUsedForCalculationBeforeTax;
		$this->expectedDividendPerStock = $expectedDividendPerStock;
		$this->expectedDividendPerStockBeforeTax = $expectedDividendPerStockBeforeTax;
		$this->customDividendUsedForCalculation = $customDividendUsedForCalculation;
		$this->customGrossDividendUsedForCalculation = $customGrossDividendUsedForCalculation;
		$this->specialDividendsLastYearPerStock = $specialDividendsLastYearPerStock;
		$this->specialDividendsLastYearPerStockBeforeTax = $specialDividendsLastYearPerStockBeforeTax;
	}

	public function setCustomValues(
		float|null $customDividendUsedForCalculation,
		float|null $customGrossDividendUsedForCalculation,
		float|null $expectedSpecialDividendThisYearPerStock,
		float|null $expectedSpecialDividendThisYearPerStockBeforeTax,
	): void
	{
		$this->customDividendUsedForCalculation = $customDividendUsedForCalculation;
		$this->customGrossDividendUsedForCalculation = $customGrossDividendUsedForCalculation;
		$this->expectedSpecialDividendThisYearPerStock = $expectedSpecialDividendThisYearPerStock;
		$this->expectedSpecialDividendThisYearPerStockBeforeTax = $expectedSpecialDividendThisYearPerStockBeforeTax;
	}

	public function updateSpecificTrend(StockAssetDividendTrendEnum $specificTrend): void
	{
		$this->specificTrend = $specificTrend;
	}

	public function getStockAssetDividendForecast(): StockAssetDividendForecast
	{
		return $this->stockAssetDividendForecast;
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	/**
	 * @return array<int>
	 */
	public function getDividendUsuallyPaidAtMonths(): array
	{
		return $this->dividendUsuallyPaidAtMonths;
	}

	/**
	 * @return array<int>
	 */
	public function getReceivedDividendMonths(): array
	{
		return $this->receivedDividendMonths;
	}

	public function getSpecificTrend(): StockAssetDividendTrendEnum|null
	{
		return $this->specificTrend;
	}

	public function getAlreadyReceivedDividendPerStock(): float
	{
		return $this->alreadyReceivedDividendPerStock;
	}

	public function getAlreadyReceivedDividendPerStockBeforeTax(): float
	{
		return $this->alreadyReceivedDividendPerStockBeforeTax;
	}

	public function getExpectedDividendPerStock(): float
	{
		return $this->expectedDividendPerStock;
	}

	public function getExpectedDividendPerStockBeforeTax(): float
	{
		return $this->expectedDividendPerStockBeforeTax;
	}

	public function getPiecesCurrentlyHeld(): int
	{
		return $this->piecesCurrentlyHeld;
	}

	public function getOriginalDividendUsedForCalculation(): float
	{
		return $this->originalDividendUsedForCalculation;
	}

	public function getOriginalDividendUsedForCalculationBeforeTax(): float
	{
		return $this->originalDividendUsedForCalculationBeforeTax;
	}

	public function getAdjustedDividendUsedForCalculation(): float
	{
		return $this->adjustedDividendUsedForCalculation;
	}

	public function getAdjustedDividendUsedForCalculationBeforeTax(): float
	{
		return $this->adjustedDividendUsedForCalculationBeforeTax;
	}

	public function getCustomDividendUsedForCalculation(): float|null
	{
		return $this->customDividendUsedForCalculation;
	}

	public function getCustomGrossDividendUsedForCalculation(): float|null
	{
		return $this->customGrossDividendUsedForCalculation;
	}

	public function getSpecialDividendsLastYearPerStock(): float|null
	{
		return $this->specialDividendsLastYearPerStock;
	}

	public function getSpecialDividendsLastYearPerStockBeforeTax(): float|null
	{
		return $this->specialDividendsLastYearPerStockBeforeTax;
	}

	public function getExpectedSpecialDividendThisYearPerStock(): float|null
	{
		return $this->expectedSpecialDividendThisYearPerStock;
	}

	public function getExpectedSpecialDividendThisYearPerStockBeforeTax(): float|null
	{
		return $this->expectedSpecialDividendThisYearPerStockBeforeTax;
	}

	public function getRemainingDividendPerStock(): float
	{
		return $this->expectedDividendPerStock;
	}

	public function getRemainingDividendPerStockBeforeTax(): float
	{
		return $this->expectedDividendPerStockBeforeTax;
	}

	public function getTotalYearDividendPerStock(): float
	{
		return $this->alreadyReceivedDividendPerStock + $this->expectedDividendPerStock;
	}

	public function getTotalYearDividendPerStockBeforeTax(): float
	{
		return $this->alreadyReceivedDividendPerStockBeforeTax + $this->expectedDividendPerStockBeforeTax;
	}

	public function getRemainingDividendTotal(): float
	{
		return $this->expectedDividendPerStock * $this->piecesCurrentlyHeld;
	}

	public function getRemainingDividendTotalBeforeTax(): float
	{
		return $this->expectedDividendPerStockBeforeTax * $this->piecesCurrentlyHeld;
	}

	/**
	 * Get actual received amount from dividend records (respects broker currency and actual pieces held at ex-date)
	 */
	public function getActualReceivedTotal(): float
	{
		$total = 0.0;
		$forYear = $this->stockAssetDividendForecast->getForYear();

		foreach ($this->stockAsset->getDividends() as $dividend) {
			if ($dividend->getExDate()->getYear() !== $forYear) {
				continue;
			}

			foreach ($dividend->getRecords() as $record) {
				if (
					$record->getBrokerCurrency() === $this->currency
					&& $record->getTotalAmountInBrokerCurrency() !== null
				) {
					$total += $record->getSummaryPriceInBrokerCurrency()->getPrice();
				} elseif ($record->getCurrency() === $this->currency) {
					$total += $record->getSummaryPrice()->getPrice();
				}
			}
		}

		return $total;
	}

	public function getTotalYearDividend(): float
	{
		return $this->getActualReceivedTotal() + $this->getRemainingDividendTotal();
	}

	public function getActualReceivedTotalBeforeTax(): float
	{
		$total = 0.0;
		$forYear = $this->stockAssetDividendForecast->getForYear();

		foreach ($this->stockAsset->getDividends() as $dividend) {
			if ($dividend->getExDate()->getYear() !== $forYear) {
				continue;
			}

			foreach ($dividend->getRecords() as $record) {
				if (
					$record->getBrokerCurrency() === $this->currency
					&& $record->getTotalAmountInBrokerCurrency() !== null
				) {
					$total += $record->getSummaryPriceInBrokerCurrency(false)->getPrice();
				} elseif ($record->getCurrency() === $this->currency) {
					$total += $record->getSummaryPrice(false)->getPrice();
				}
			}
		}

		return $total;
	}

	public function getTotalYearDividendBeforeTax(): float
	{
		return $this->getActualReceivedTotalBeforeTax() + $this->getRemainingDividendTotalBeforeTax();
	}

}
