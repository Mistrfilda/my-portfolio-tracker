<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Admin\AppAdmin;
use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFactory;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use App\Stock\Position\Closed\StockClosedPosition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_position')]
class StockPosition implements AssetPosition, Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAsset::class, inversedBy: 'positions')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::INTEGER)]
	private int $orderPiecesCount;

	#[ORM\Column(type: Types::FLOAT)]
	private float $pricePerPiece;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $orderDate;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $differentBrokerAmount;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency;

	#[ORM\OneToOne(targetEntity: StockClosedPosition::class, inversedBy: 'stockPosition')]
	private StockClosedPosition|null $stockClosedPosition = null;

	public function __construct(
		AppAdmin $appAdmin,
		StockAsset $stockAsset,
		int $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->appAdmin = $appAdmin;
		$this->stockAsset = $stockAsset;
		$this->orderPiecesCount = $orderPiecesCount;
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;
		$this->differentBrokerAmount = $differentBrokerAmount;

		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		StockAsset $stockAsset,
		int $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
		ImmutableDateTime $now,
	): void
	{
		$this->stockAsset = $stockAsset;
		$this->orderPiecesCount = $orderPiecesCount;
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;
		$this->differentBrokerAmount = $differentBrokerAmount;
		$this->updatedAt = $now;
	}

	public function closePosition(StockClosedPosition $stockClosedPosition): void
	{
		$this->stockClosedPosition = $stockClosedPosition;
	}

	public function getAsset(): Asset
	{
		return $this->stockAsset;
	}

	public function getOrderPiecesCount(): int
	{
		return $this->orderPiecesCount;
	}

	public function getTotalInvestedAmount(): AssetPrice
	{
		return AssetPriceFactory::createFromPieceCountPrice(
			$this->stockAsset,
			$this->orderPiecesCount,
			$this->pricePerPiece,
		);
	}

	public function getCurrentTotalAmount(): AssetPrice
	{
		if ($this->getStockClosedPosition() !== null) {
			return AssetPriceFactory::createFromPieceCountPrice(
				$this->stockAsset,
				$this->orderPiecesCount,
				$this->getStockClosedPosition()->getPricePerPiece()->getPrice(),
			);
		}

		return AssetPriceFactory::createFromPieceCountPrice(
			$this->stockAsset,
			$this->orderPiecesCount,
			$this->stockAsset->getAssetCurrentPrice()->getPrice(),
		);
	}

	public function getPricePerPiece(): AssetPrice
	{
		return new AssetPrice(
			$this->stockAsset,
			$this->pricePerPiece,
			$this->stockAsset->getCurrency(),
		);
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->stockAsset->getCurrency();
	}

	public function getTotalInvestedAmountInBrokerCurrency(): AssetPrice
	{
		return $this->totalInvestedAmountInBrokerCurrency->getAssetPrice($this->stockAsset);
	}

	public function getOrderDate(): ImmutableDateTime
	{
		return $this->orderDate;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function isDifferentBrokerAmount(): bool
	{
		return $this->differentBrokerAmount;
	}

	public function isPositionClosed(): bool
	{
		return $this->stockClosedPosition !== null;
	}

	public function getStockClosedPosition(): StockClosedPosition|null
	{
		return $this->stockClosedPosition;
	}

}
