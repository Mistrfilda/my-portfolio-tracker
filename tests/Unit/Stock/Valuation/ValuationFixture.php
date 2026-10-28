<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

final class ValuationFixture
{

	/** @param array<string, float|null> $values */
	public static function create(
		array $values,
		StockAssetIndustry|null $industry = null,
		float $price = 100.0,
		CurrencyEnum $currency = CurrencyEnum::USD,
		bool $paysDividends = true,
	): StockValuation
	{
		$now = new ImmutableDateTime('2026-10-25 12:00:00');
		$asset = new StockAsset(
			'Test',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'TEST',
			StockAssetExchange::NYSE,
			$currency,
			$now,
			null,
			$paysDividends ? StockAssetDividendSourceEnum::WEB : null,
			null,
			null,
			true,
			true,
			false,
			$industry,
		);
		$asset->setCurrentPrice(
			new StockAssetPriceRecord($now, $currency, $price, $asset, StockAssetPriceDownloaderEnum::WEB_SCRAP, $now),
			$now,
		);
		return new StockValuation($asset, self::data($asset, $values, $now, $currency));
	}

	/**
	 * @param array<string, float|null> $values
	 * @return array<string, StockValuationData>
	 */
	public static function data(
		StockAsset $asset,
		array $values,
		ImmutableDateTime $at,
		CurrencyEnum $currency = CurrencyEnum::USD,
	): array
	{
		$data = [];
		foreach ($values as $key => $value) {
			$type = StockValuationTypeEnum::from($key);
			$raw = $value === null || !is_finite($value) ? null : (string) $value;
			$data[$key] = new StockValuationData(
				$asset,
				$type,
				$type->getTypeGroup(),
				$type->getTypeValueType(),
				$at,
				$raw,
				$value,
				$currency,
				$at,
			);
		}

		return $data;
	}

	public static function industry(
		string $mapping = 'Tobacco',
		float|null $pe = 20.0,
		float|null $pb = 2.0,
		float|null $ps = 3.0,
	): StockAssetIndustry
	{
		return new StockAssetIndustry(
			'Industry',
			$mapping,
			new ImmutableDateTime('2026-10-25'),
			$pe,
			null,
			null,
			12.0,
			$pb,
			$ps,
			null,
			null,
		);
	}

}
