<?php

declare(strict_types = 1);

namespace App\Asset;

use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

interface Asset
{

	public function getId(): UuidInterface;

	public function getType(): AssetTypeEnum;

	public function getName(): string;

	public function shouldBeUpdated(): bool;

	public function hasMultiplePositions(): bool;

	/**
	 * @return array<AssetPosition>
	 */
	public function getPositions(): array;

	public function getCurrency(): CurrencyEnum;

	public function getAssetCurrentPrice(): AssetPrice;

	public function getTrend(ImmutableDateTime $date): float;

}
