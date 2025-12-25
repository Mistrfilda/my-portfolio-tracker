<?php

declare(strict_types = 1);

namespace App\Crypto\Price\Downloader\TwelveData;

use App\Crypto\Asset\CryptoAsset;
use App\Currency\CurrencyEnum;

class TwelveDataCryptoRequest
{

	private const URL = 'https://api.twelvedata.com/exchange_rate?symbol=%s/%s&apikey=%s';

	public function __construct(
		private string $apiKey,
		private CryptoAsset $fromCrypto,
		private CurrencyEnum $toCurrency,
	)
	{
	}

	public function getFormattedRequestUrl(): string
	{
		return sprintf(
			self::URL,
			$this->fromCrypto->getTicker(),
			$this->toCurrency->value,
			$this->apiKey,
		);
	}

}
