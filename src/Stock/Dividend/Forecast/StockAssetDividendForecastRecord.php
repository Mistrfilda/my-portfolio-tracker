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
	private float $expectedDividendPerStock;

	#[ORM\Column(type: Types::FLOAT)]
	private float $originalDividendUsedForCalculation;

	#[ORM\Column(type: Types::FLOAT)]
	private float $adjustedDividendUsedForCalculation;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $customDividendUsedForCalculation;

	#[ORM\Column(type: Types::INTEGER)]
	private int $piecesCurrentlyHeld;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $specialDividendsLastYearPerStock;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $expectedSpecialDividendThisYearPerStock;

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
		int $piecesCurrentlyHeld,
		float $originalDividendUsedForCalculation,
		float $adjustedDividendUsedForCalculation,
		float $expectedDividendPerStock,
		float|null $customDividendUsedForCalculation,
		float|null $specialDividendsLastYearPerStock,
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
		$this->piecesCurrentlyHeld = $piecesCurrentlyHeld;
		$this->originalDividendUsedForCalculation = $originalDividendUsedForCalculation;
		$this->adjustedDividendUsedForCalculation = $adjustedDividendUsedForCalculation;
		$this->expectedDividendPerStock = $expectedDividendPerStock;
		$this->customDividendUsedForCalculation = $customDividendUsedForCalculation;
		$this->specialDividendsLastYearPerStock = $specialDividendsLastYearPerStock;
		$this->createdAt = $now;
	}

	/**
	 * @param array<int> $receivedDividendMonths
	 */
	public function recalculate(
		array $receivedDividendMonths,
		float $alreadyReceivedDividendPerStock,
		int $piecesCurrentlyHeld,
		float $originalDividendUsedForCalculation,
		float $adjustedDividendUsedForCalculation,
		float $expectedDividendPerStock,
		float|null $customDividendUsedForCalculation,
		float|null $specialDividendsLastYearPerStock,
	): void
	{
		$this->receivedDividendMonths = $receivedDividendMonths;
		$this->alreadyReceivedDividendPerStock = $alreadyReceivedDividendPerStock;
		$this->piecesCurrentlyHeld = $piecesCurrentlyHeld;
		$this->originalDividendUsedForCalculation = $originalDividendUsedForCalculation;
		$this->adjustedDividendUsedForCalculation = $adjustedDividendUsedForCalculation;
		$this->expectedDividendPerStock = $expectedDividendPerStock;
		$this->customDividendUsedForCalculation = $customDividendUsedForCalculation;
		$this->specialDividendsLastYearPerStock = $specialDividendsLastYearPerStock;
	}

	public function setCustomValues(
		float|null $customDividendUsedForCalculation,
		float|null $expectedSpecialDividendThisYearPerStock,
	): void
	{
		$this->customDividendUsedForCalculation = $customDividendUsedForCalculation;
		$this->expectedSpecialDividendThisYearPerStock = $expectedSpecialDividendThisYearPerStock;
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

	public function getExpectedDividendPerStock(): float
	{
		return $this->expectedDividendPerStock;
	}

	public function getPiecesCurrentlyHeld(): int
	{
		return $this->piecesCurrentlyHeld;
	}

	public function getOriginalDividendUsedForCalculation(): float
	{
		return $this->originalDividendUsedForCalculation;
	}

	public function getAdjustedDividendUsedForCalculation(): float
	{
		return $this->adjustedDividendUsedForCalculation;
	}

	public function getCustomDividendUsedForCalculation(): float|null
	{
		return $this->customDividendUsedForCalculation;
	}

	public function getSpecialDividendsLastYearPerStock(): float|null
	{
		return $this->specialDividendsLastYearPerStock;
	}

	public function getExpectedSpecialDividendThisYearPerStock(): float|null
	{
		return $this->expectedSpecialDividendThisYearPerStock;
	}

	public function getRemainingDividendPerStock(): float
	{
		return $this->expectedDividendPerStock;
	}

	public function getTotalYearDividendPerStock(): float
	{
		return $this->alreadyReceivedDividendPerStock + $this->expectedDividendPerStock;
	}

	public function getRemainingDividendTotal(): float
	{
		return $this->expectedDividendPerStock * $this->piecesCurrentlyHeld;
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
					$total += $record->getTotalAmountInBrokerCurrency();
				} elseif ($record->getCurrency() === $this->currency) {
					$total += $record->getTotalAmount();
				}
			}
		}

		return $total;
	}

	public function getTotalYearDividend(): float
	{
		return $this->getActualReceivedTotal() + $this->getRemainingDividendTotal();
	}

}
