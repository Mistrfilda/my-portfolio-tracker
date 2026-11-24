<?php

declare(strict_types = 1);

namespace App\Tax\Rules;

use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;

class TaxTreatyRateProvider
{

	private const array TREATY_MAX_RATES = [
		'CZ' => 0.15,
		'US' => 0.15,
		'DE' => 0.15,
		'PL' => 0.05,
		'AT' => 0.10,
		'GB' => 0.00,
		'IE' => 0.15,
		'NL' => 0.10,
		'FR' => 0.10,
		'NO' => 0.15,
		'SE' => 0.15,
		'ES' => 0.15,
		'CH' => 0.15,
		'CA' => 0.15,
		'DK' => 0.15,
		'FI' => 0.15,
		'BE' => 0.15,
		'IT' => 0.15,
		'SK' => 0.15,
	];

	private const array COUNTRY_NAMES = [
		'CZ' => 'Česká republika',
		'US' => 'USA',
		'DE' => 'Německo',
		'PL' => 'Polsko',
		'AT' => 'Rakousko',
		'GB' => 'Velká Británie',
		'IE' => 'Irsko',
		'NL' => 'Nizozemsko',
		'FR' => 'Francie',
		'NO' => 'Norsko',
		'SE' => 'Švédsko',
		'ES' => 'Španělsko',
		'CH' => 'Švýcarsko',
		'CA' => 'Kanada',
		'DK' => 'Dánsko',
		'FI' => 'Finsko',
		'BE' => 'Belgie',
		'IT' => 'Itálie',
		'SK' => 'Slovensko',
		'UNKNOWN' => 'Neznámý stát',
	];

	public function resolveCountryCodeFromStockAsset(StockAsset $stockAsset): string
	{
		$isin = $stockAsset->getIsin();
		if ($isin !== null && strlen($isin) >= 2) {
			$prefix = strtoupper(substr(trim($isin), 0, 2));
			if (preg_match('/^[A-Z]{2}$/', $prefix) === 1) {
				return $prefix;
			}
		}

		return match ($stockAsset->getExchange()) {
			StockAssetExchange::PRAGUE_STOCK_EXCHANGE => 'CZ',
			StockAssetExchange::NYSE,
			StockAssetExchange::NASDAQ => 'US',
			StockAssetExchange::XETRA => 'DE',
			StockAssetExchange::WARSAW => 'PL',
			StockAssetExchange::VIE => 'AT',
			StockAssetExchange::LSE => 'GB',
			StockAssetExchange::EURONEXT_AMSTERDAM => 'NL',
			StockAssetExchange::EURONEXT_PARIS => 'FR',
			StockAssetExchange::EURONEXT_OSLO => 'NO',
			StockAssetExchange::NASDAQ_STOCKHOLM => 'SE',
			StockAssetExchange::MADRID_STOCK_EXCHANGE => 'ES',
		};
	}

	public function isCountryInferredWithoutIsin(StockAsset $stockAsset): bool
	{
		$isin = $stockAsset->getIsin();

		return $isin === null || strlen(trim($isin)) < 2;
	}

	public function getTreatyMaxRate(string $countryCode): float|null
	{
		$normalized = strtoupper($countryCode);

		return self::TREATY_MAX_RATES[$normalized] ?? null;
	}

	public function getCountryName(string $countryCode): string
	{
		$normalized = strtoupper($countryCode);

		return self::COUNTRY_NAMES[$normalized] ?? $normalized;
	}

	public function isCzechDomestic(string $countryCode): bool
	{
		return strtoupper($countryCode) === 'CZ';
	}

}
