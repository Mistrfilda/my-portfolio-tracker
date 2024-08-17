<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Dividend\StockAssetDividend;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_asset_dividend_record')]
class StockAssetDividendRecord implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAssetDividend::class, inversedBy: 'records')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAssetDividend $stockAssetDividend;

	#[ORM\Column(type: Types::INTEGER)]
	private int $totalPiecesHeldAtExDate;

	#[ORM\Column(type: Types::FLOAT)]
	private float $totalAmount;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $totalAmountInBrokerCurrency;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class, nullable: true)]
	private CurrencyEnum|null $brokerCurrency;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $reinvested;

	public function __construct(
		StockAssetDividend $stockAssetDividend,
		int $totalPiecesHeldAtExDate,
		float $totalAmount,
		CurrencyEnum $currency,
		float|null $totalAmountInBrokerCurrency,
		CurrencyEnum|null $brokerCurrency,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->stockAssetDividend = $stockAssetDividend;
		$this->totalPiecesHeldAtExDate = $totalPiecesHeldAtExDate;
		$this->totalAmount = $totalAmount;
		$this->currency = $currency;
		$this->totalAmountInBrokerCurrency = $totalAmountInBrokerCurrency;
		$this->brokerCurrency = $brokerCurrency;
		$this->reinvested = true;

		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		int $totalPiecesHeldAtExDate,
		float $totalAmount,
		CurrencyEnum $currency,
		float|null $totalAmountInBrokerCurrency,
		CurrencyEnum|null $brokerCurrency,
		ImmutableDateTime $now,
	): void
	{
		$this->totalPiecesHeldAtExDate = $totalPiecesHeldAtExDate;
		$this->totalAmount = $totalAmount;
		$this->currency = $currency;
		$this->totalAmountInBrokerCurrency = $totalAmountInBrokerCurrency;
		$this->brokerCurrency = $brokerCurrency;
		$this->updatedAt = $now;
	}

	public function getStockAssetDividend(): StockAssetDividend
	{
		return $this->stockAssetDividend;
	}

	public function getStockAssetChartLabel(): string
	{
		return sprintf(
			'%s (%s)',
			$this->stockAssetDividend->getStockAsset()->getName(),
			$this->stockAssetDividend->getStockAsset()->getTicker(),
		);
	}

	public function getTotalPiecesHeldAtExDate(): int
	{
		return $this->totalPiecesHeldAtExDate;
	}

	public function getTotalAmount(): float
	{
		return $this->totalAmount;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getTotalAmountInBrokerCurrency(): float|null
	{
		return $this->totalAmountInBrokerCurrency;
	}

	public function getBrokerCurrency(): CurrencyEnum|null
	{
		return $this->brokerCurrency;
	}

	public function getDividendTax(): float|null
	{
		return $this->stockAssetDividend->getStockAsset()->getDividendTax();
	}

	public function getSummaryPrice(bool $shouldDeductTax = true): SummaryPrice
	{
		$dividendTax = $this->getDividendTax();
		if ($dividendTax !== null && $shouldDeductTax) {
			$totalAmountAfterTax = $this->totalAmount * (1 - ($dividendTax * 0.01));
			return new SummaryPrice($this->currency, $totalAmountAfterTax, 1);
		}

		return new SummaryPrice($this->currency, $this->totalAmount, 1);
	}

	public function isReinvested(): bool
	{
		return $this->reinvested;
	}

}
