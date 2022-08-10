<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFactory;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('stock_position')]
class StockPosition implements AssetPosition
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAsset::class, inversedBy: 'positions')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\Column(type: Types::INTEGER)]
	private int $orderPiecesCount;

	#[ORM\Column(type: Types::FLOAT)]
	private float $pricePerPiece;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $orderDate;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency;

	public function __construct(
		StockAsset $stockAsset,
		int $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		ImmutableDateTime $now,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
	)
	{
		$this->stockAsset = $stockAsset;
		$this->orderPiecesCount = $orderPiecesCount;
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;

		$this->createdAt = $now;
		$this->updatedAt = $now;
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

}
