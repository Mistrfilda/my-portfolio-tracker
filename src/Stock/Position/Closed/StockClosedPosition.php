<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Asset\Position\AssetClosedPosition;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFactory;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Position\StockPosition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_closed_position')]
class StockClosedPosition implements AssetClosedPosition
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\OneToOne(targetEntity: StockPosition::class, mappedBy: 'stockClosedPosition')]
	#[ORM\JoinColumn(nullable: false)]
	private StockPosition $stockPosition;

	#[ORM\Column(type: Types::FLOAT)]
	private float $pricePerPiece;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $orderDate;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $differentBrokerAmount;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency;

	public function __construct(
		StockPosition $stockPosition,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		bool $differentBrokerAmount,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->stockPosition = $stockPosition;
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->differentBrokerAmount = $differentBrokerAmount;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		bool $differentBrokerAmount,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		ImmutableDateTime $now,
	): void
	{
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->differentBrokerAmount = $differentBrokerAmount;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;
		$this->updatedAt = $now;
	}

	public function getAssetPositon(): AssetPosition
	{
		return $this->stockPosition;
	}

	public function getCloseTotalAmount(): AssetPrice
	{
		return AssetPriceFactory::createFromPieceCountPrice(
			$this->stockPosition->getAsset(),
			$this->stockPosition->getOrderPiecesCount(),
			$this->pricePerPiece,
		);
	}

	public function getClosePricePerPiece(): AssetPrice
	{
		return new AssetPrice(
			$this->stockPosition->getAsset(),
			$this->pricePerPiece,
			$this->stockPosition->getCurrency(),
		);
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->stockPosition->getCurrency();
	}

	public function getTotalCloseAmountInBrokerCurrency(): AssetPrice
	{
		return $this->totalInvestedAmountInBrokerCurrency->getAssetPrice($this->stockPosition->getAsset());
	}

	public function getDate(): ImmutableDateTime
	{
		return $this->orderDate;
	}

	public function getPricePerPiece(): AssetPrice
	{
		return new AssetPrice(
			$this->stockPosition->getAsset(),
			$this->pricePerPiece,
			$this->stockPosition->getCurrency(),
		);
	}

	public function isDifferentBrokerAmount(): bool
	{
		return $this->differentBrokerAmount;
	}

}
