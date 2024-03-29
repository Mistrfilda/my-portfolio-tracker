<?php

declare(strict_types = 1);

namespace App\Asset\Position;

use App\Admin\AppAdmin;
use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface AssetPosition
{

	public function getAsset(): Asset;

	public function getAppAdmin(): AppAdmin;

	public function getOrderPiecesCount(): int;

	public function getTotalInvestedAmount(): AssetPrice;

	public function getCurrentTotalAmount(): AssetPrice;

	public function getPricePerPiece(): AssetPrice;

	public function getCurrency(): CurrencyEnum;

	public function getOrderDate(): ImmutableDateTime;

	/**
	 * Broker degiro uses euro for all operations
	 */
	public function getTotalInvestedAmountInBrokerCurrency(): AssetPrice;

}
