<?php

declare(strict_types = 1);

namespace App\Crypto\Position;

use App\Admin\AppAdmin;
use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFactory;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Position\Closed\CryptoClosedPosition;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('crypto_position')]
class CryptoPosition implements AssetPosition, Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: CryptoAsset::class, inversedBy: 'positions')]
	#[ORM\JoinColumn(nullable: false)]
	private CryptoAsset $cryptoAsset;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::FLOAT)]
	private float $orderPiecesCount;

	#[ORM\Column(type: Types::FLOAT)]
	private float $pricePerPiece;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $orderDate;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $differentBrokerAmount;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency;

	#[ORM\OneToOne(targetEntity: CryptoClosedPosition::class, inversedBy: 'cryptoPosition')]
	private CryptoClosedPosition|null $cryptoClosedPosition;

	public function __construct(
		AppAdmin $appAdmin,
		CryptoAsset $cryptoAsset,
		float $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->appAdmin = $appAdmin;
		$this->cryptoAsset = $cryptoAsset;
		$this->orderPiecesCount = $orderPiecesCount;
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;
		$this->differentBrokerAmount = $differentBrokerAmount;

		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		CryptoAsset $cryptoAsset,
		float $orderPiecesCount,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		bool $differentBrokerAmount,
		ImmutableDateTime $now,
	): void
	{
		$this->cryptoAsset = $cryptoAsset;
		$this->orderPiecesCount = $orderPiecesCount;
		$this->pricePerPiece = $pricePerPiece;
		$this->orderDate = $orderDate;
		$this->totalInvestedAmountInBrokerCurrency = $totalInvestedAmountInBrokerCurrency;
		$this->differentBrokerAmount = $differentBrokerAmount;
		$this->updatedAt = $now;
	}

	public function closePosition(CryptoClosedPosition $cryptoClosedPosition): void
	{
		$this->cryptoClosedPosition = $cryptoClosedPosition;
	}

	public function getAsset(): Asset
	{
		return $this->cryptoAsset;
	}

	public function getOrderPiecesCount(): float
	{
		return $this->orderPiecesCount;
	}

	public function getTotalInvestedAmount(): AssetPrice
	{
		return AssetPriceFactory::createFromPieceCountPrice(
			$this->cryptoAsset,
			$this->orderPiecesCount,
			$this->pricePerPiece,
		);
	}

	public function getCurrentTotalAmount(): AssetPrice
	{
		if ($this->getCryptoClosedPosition() !== null) {
			return AssetPriceFactory::createFromPieceCountPrice(
				$this->cryptoAsset,
				$this->orderPiecesCount,
				$this->getCryptoClosedPosition()->getPricePerPiece()->getPrice(),
			);
		}

		return AssetPriceFactory::createFromPieceCountPrice(
			$this->cryptoAsset,
			$this->orderPiecesCount,
			$this->cryptoAsset->getAssetCurrentPrice()->getPrice(),
		);
	}

	public function getPricePerPiece(): AssetPrice
	{
		return new AssetPrice(
			$this->cryptoAsset,
			$this->pricePerPiece,
			$this->cryptoAsset->getCurrency(),
		);
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->cryptoAsset->getCurrency();
	}

	public function getTotalInvestedAmountInBrokerCurrency(): AssetPrice
	{
		return $this->totalInvestedAmountInBrokerCurrency->getAssetPrice($this->cryptoAsset);
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
		return $this->cryptoClosedPosition !== null;
	}

	public function getCryptoClosedPosition(): CryptoClosedPosition|null
	{
		return $this->cryptoClosedPosition;
	}

}
