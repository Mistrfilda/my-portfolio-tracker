<?php

declare(strict_types = 1);

namespace App\Asset\Position;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface AssetClosedPosition
{

	public function getAssetPositon(): AssetPosition;

	public function getCloseTotalAmount(): AssetPrice;

	public function getClosePricePerPiece(): AssetPrice;

	public function getCurrency(): CurrencyEnum;

	/**
	 * Broker degiro uses euro for all operations
	 */
	public function getTotalCloseAmountInBrokerCurrency(): AssetPrice;

	public function getDate(): ImmutableDateTime;

}
