<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Asset\Position\AssetClosedPosition;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceFactory;
use App\Crypto\Position\CryptoPosition;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('crypto_closed_position')]
class CryptoClosedPosition implements AssetClosedPosition
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\OneToOne(targetEntity: CryptoPosition::class, mappedBy: 'cryptoClosedPosition')]
	/**@phpstan-ignore-next-line */
	private CryptoPosition $cryptoPosition;

	#[ORM\Column(type: Types::FLOAT)]
	private float $pricePerPiece;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $orderDate;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $differentBrokerAmount;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency;

	public function __construct(
		CryptoPosition $cryptoPosition,
		float $pricePerPiece,
		ImmutableDateTime $orderDate,
		bool $differentBrokerAmount,
		AssetPriceEmbeddable $totalInvestedAmountInBrokerCurrency,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->cryptoPosition = $cryptoPosition;
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
		return $this->cryptoPosition;
	}

	public function getCloseTotalAmount(): AssetPrice
	{
		return AssetPriceFactory::createFromPieceCountPrice(
			$this->cryptoPosition->getAsset(),
			$this->cryptoPosition->getOrderPiecesCount(),
			$this->pricePerPiece,
		);
	}

	public function getClosePricePerPiece(): AssetPrice
	{
		return new AssetPrice(
			$this->cryptoPosition->getAsset(),
			$this->pricePerPiece,
			$this->cryptoPosition->getCurrency(),
		);
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->cryptoPosition->getCurrency();
	}

	public function getTotalCloseAmountInBrokerCurrency(): AssetPrice
	{
		return $this->totalInvestedAmountInBrokerCurrency->getAssetPrice($this->cryptoPosition->getAsset());
	}

	public function getDate(): ImmutableDateTime
	{
		return $this->orderDate;
	}

	public function getPricePerPiece(): AssetPrice
	{
		return new AssetPrice(
			$this->cryptoPosition->getAsset(),
			$this->pricePerPiece,
			$this->cryptoPosition->getCurrency(),
		);
	}

	public function isDifferentBrokerAmount(): bool
	{
		return $this->differentBrokerAmount;
	}

}
