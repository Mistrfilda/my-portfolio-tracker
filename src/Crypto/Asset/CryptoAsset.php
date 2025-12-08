<?php

declare(strict_types = 1);

namespace App\Crypto\Asset;

use App\Asset\Asset;
use App\Asset\AssetTypeEnum;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\UI\Filter\RuleOfThreeFilter;
use App\UI\Icon\SvgIcon;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table('crypto_asset')]
class CryptoAsset implements Entity, Asset
{

	use SimpleUuid;
	use UpdatedAt;
	use CreatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING)]
	private string $ticker;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $mainConversionCurrency;

	/** @var ArrayCollection<int, CryptoPosition> */
	#[ORM\OneToMany(targetEntity: CryptoPosition::class, mappedBy: 'cryptoAsset')]
	#[ORM\OrderBy(['orderDate' => 'asc'])]
	private Collection $positions;

	/** @var ArrayCollection<int, CryptoAssetPriceRecord> */
	#[ORM\OneToMany(targetEntity: CryptoAssetPriceRecord::class, mappedBy: 'cryptoAsset')]
	private Collection $priceRecords;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $currentAssetPrice;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $priceDownloadedAt;

	#[ORM\Column(type: Types::STRING, enumType: SvgIcon::class)]
	private SvgIcon $svgIcon;

	public function __construct(
		string $name,
		string $ticker,
		SvgIcon $svgIcon,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->name = $name;
		$this->ticker = $ticker;
		$this->svgIcon = $svgIcon;
		$this->mainConversionCurrency = CurrencyEnum::USD;

		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->currentAssetPrice = new AssetPriceEmbeddable(0, CurrencyEnum::USD);
		$this->priceDownloadedAt = $now;

		$this->positions = new ArrayCollection();
		$this->priceRecords = new ArrayCollection();
	}

	public function update(
		string $name,
		string $ticker,
		SvgIcon $svgIcon,
		ImmutableDateTime $now,
	): void
	{
		$this->name = $name;
		$this->ticker = $ticker;
		$this->svgIcon = $svgIcon;
		$this->updatedAt = $now;
	}

	public function setCurrentPrice(
		CryptoAssetPriceRecord $cryptoAssetPriceRecord,
		ImmutableDateTime $now,
	): void
	{
		$this->currentAssetPrice = new AssetPriceEmbeddable(
			$cryptoAssetPriceRecord->getPrice(),
			$cryptoAssetPriceRecord->getCurrency(),
		);

		$this->priceDownloadedAt = $now;
	}

	public function getId(): UuidInterface
	{
		return $this->id;
	}

	public function getType(): AssetTypeEnum
	{
		return AssetTypeEnum::CRYPTO;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function shouldBeUpdated(): bool
	{
		return true;
	}

	public function hasMultiplePositions(): bool
	{
		return true;
	}

	/**
	 * @return array<CryptoPosition>
	 */
	public function getPositions(bool|null $onlyOpenPositions = null): array
	{
		if ($onlyOpenPositions === true) {
			return $this->positions->filter(
				static fn (CryptoPosition $position) => $position->isPositionClosed() === false,
			)->toArray();
		}

		return $this->positions->toArray();
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->mainConversionCurrency;
	}

	public function getAssetCurrentPrice(): AssetPrice
	{
		return $this->currentAssetPrice->getAssetPrice($this);
	}

	public function getTicker(): string
	{
		return $this->ticker;
	}

	public function getMainConversionCurrency(): CurrencyEnum
	{
		return $this->mainConversionCurrency;
	}

	public function getCurrentAssetPrice(): AssetPriceEmbeddable
	{
		return $this->currentAssetPrice;
	}

	public function getTrend(ImmutableDateTime $date): float
	{
		$priceRecords = $this->priceRecords->filter(
			fn (CryptoAssetPriceRecord $cryptoAssetPriceRecord) => $cryptoAssetPriceRecord->getDate()->format(
				'Y-m-d',
			) === $date->format(
				'Y-m-d',
			) && $cryptoAssetPriceRecord->getCurrency() === $this->mainConversionCurrency,
		);

		$deductDays = 1;
		while (count($priceRecords) === 0) {
			$modifiedDate = $date->deductDaysFromDatetime($deductDays);
			$priceRecords = $this->priceRecords->filter(
				fn (CryptoAssetPriceRecord $cryptoAssetPriceRecord) => $cryptoAssetPriceRecord->getDate()->format(
					'Y-m-d',
				) === $modifiedDate->format('Y-m-d')
					&& $cryptoAssetPriceRecord->getCurrency() === $this->mainConversionCurrency,
			);

			if ($modifiedDate->diff($date)->days > 7) {
				break;
			}

			$deductDays++;
		}

		if (count($priceRecords) === 0) {
			return 0;
		}

		$lastDayPriceRecord = $priceRecords->first();
		assert($lastDayPriceRecord instanceof CryptoAssetPriceRecord);

		$percentage = RuleOfThreeFilter::getPercentage(
			$this->currentAssetPrice->getPrice(),
			$lastDayPriceRecord->getPrice(),
		);

		return round((float) ($percentage - 100), 2);
	}

	public function getPriceDownloadedAt(): ImmutableDateTime
	{
		return $this->priceDownloadedAt;
	}

	public function hasPositions(): bool
	{
		return $this->positions->count() > 0;
	}

	public function hasOpenPositions(): bool
	{
		foreach ($this->positions->toArray() as $position) {
			if ($position->isPositionClosed() === false) {
				return true;
			}
		}

		return false;
	}

	public function hasClosedPositions(): bool
	{
		foreach ($this->positions->toArray() as $position) {
			if ($position->isPositionClosed() === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<CryptoPosition>
	 */
	public function getClosedPositions(): array
	{
		return $this->positions->filter(
			static fn (CryptoPosition $position) => $position->isPositionClosed(),
		)->toArray();
	}

	public function getFirstPosition(): CryptoPosition|null
	{
		return $this->positions->first() === false ? null : $this->positions->first();
	}

	public function getSvgIcon(): SvgIcon
	{
		return $this->svgIcon;
	}

}
