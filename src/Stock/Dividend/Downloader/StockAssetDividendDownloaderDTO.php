<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Downloader;

use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class StockAssetDividendDownloaderDTO
{

	public function __construct(
		private ImmutableDateTime $exDate,
		private ImmutableDateTime|null $paymentDate,
		private ImmutableDateTime|null $declarationDate,
		private CurrencyEnum $currency,
		private float $amount,
	)
	{
	}

	public function getExDate(): ImmutableDateTime
	{
		return $this->exDate;
	}

	public function getPaymentDate(): ImmutableDateTime|null
	{
		return $this->paymentDate;
	}

	public function getDeclarationDate(): ImmutableDateTime|null
	{
		return $this->declarationDate;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getAmount(): float
	{
		return $this->amount;
	}

}
