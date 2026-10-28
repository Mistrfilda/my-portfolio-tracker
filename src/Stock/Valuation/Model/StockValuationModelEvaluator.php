<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

use App\Stock\Valuation\Model\Price\StockValuationPriceModelResponse;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum as Type;

final class StockValuationModelEvaluator
{

	public function evaluate(StockValuationModel $model, StockValuation $valuation): StockValuationPriceModelResponse
	{
		$response = $model->calculateResponse($valuation);
		$asset = $valuation->getStockAsset();
		$industry = $asset->getIndustry();
		$isBank = str_starts_with(strtolower($industry?->getMappingName() ?? ''), 'banks');
		if ($isBank && (
			$model instanceof Price\FreeCashFlowValuationModel
			|| $model instanceof Price\EnterpriseValueValuationModel
			|| $model instanceof Price\DebtAdjustedValuationModel
			|| $model instanceof Price\PriceToSalesValuationModel
		)) {
			return $response->withAvailability(
				StockValuationModelAvailability::NOT_APPLICABLE,
				'U bank mají dluh a cash flow jiný význam. Pro tento přehled model nepoužíváme.',
			);
		}

		$book = $valuation->getValuationDataByType(Type::BOOK_VALUE_PER_SHARE)?->getFloatValue();
		if ($book !== null && $book <= 0 && (
			$model instanceof Price\BookValueValuationModel
			|| $model instanceof Price\GrahamNumberValuationModel
			|| $model instanceof Price\RoeQualityValuationModel
			|| $model instanceof Price\DebtAdjustedValuationModel
		)) {
			return $response->withAvailability(
				StockValuationModelAvailability::NOT_APPLICABLE,
				'Vlastní kapitál je nulový nebo záporný; tento model není smysluplný.',
			);
		}

		$required = array_values(array_filter(
			$response->getUsedStockValuationDataTypes(),
			static fn (Type $type): bool => $type !== Type::CURRENT_PRICE && $type !== Type::EV_EBITDA,
		));
		if (
			$model instanceof Price\DividendDiscountValuationModel
			|| $model instanceof Price\DividendYieldFairValueModel
		) {
			if (!$asset->doesPaysDividends()) {
				return $response->withAvailability(
					StockValuationModelAvailability::NOT_APPLICABLE,
					'Akcie není nastavena jako dividendová. Dividendový model se nepoužije.',
				);
			}

			$forward = $valuation->getValuationDataByType(Type::FORWARD_ANNUAL_DIVIDEND_RATE)?->getFloatValue();
			$required = [$forward !== null ? Type::FORWARD_ANNUAL_DIVIDEND_RATE : Type::TRAILING_ANNUAL_DIVIDEND_RATE];
		}

		$missing = [];
		$importDates = [];
		foreach ($required as $type) {
			$data = $valuation->getValuationDataByType($type);
			$value = $data?->getFloatValue();
			if ($value === null) {
				$raw = $data?->getStringValue();
				if ($raw !== null && !in_array(trim($raw), ['', '-', '--', 'N/A'], true)) {
					return $response->withAvailability(
						StockValuationModelAvailability::INVALID_DATA,
						'Vstup se nepodařilo přečíst jako číslo: ' . $type->format() . '.',
					);
				}

				$missing[] = $type->format();
				continue;
			}

			if (!is_finite($value)) {
				return $response->withAvailability(
					StockValuationModelAvailability::INVALID_DATA,
					'Neplatné číslo: ' . $type->format() . '.',
				);
			}

			if ($type->isCurrencyValue() && $data->getCurrency() !== $asset->getCurrency()) {
				return $response->withAvailability(
					StockValuationModelAvailability::INVALID_DATA,
					'Měna vstupu neodpovídá měně akcie: ' . $type->format() . '.',
				);
			}

			$importDates[$data->getParsedAt()->format('Y-m-d H:i:s')] = true;
			if (
				$value <= 0
				&& in_array(
					$type,
					[
						Type::DILUTED_EPS,
						Type::LEVERED_FREE_CASH_FLOW,
						Type::EBITDA,
						Type::REVENUE_PER_SHARE,
						Type::FORWARD_ANNUAL_DIVIDEND_RATE,
						Type::TRAILING_ANNUAL_DIVIDEND_RATE,
						Type::PEG_RATIO,
					],
					true,
				)
			) {
				return $response->withAvailability(
					StockValuationModelAvailability::NOT_APPLICABLE,
					'Model vyžaduje kladnou hodnotu: ' . $type->format() . '.',
				);
			}

			if (
				(
					$type === Type::SHARES_OUTSTANDING
					&& $value <= 0
				)
				|| (
					$value < 0
					&& in_array(
						$type,
						[Type::TOTAL_DEBT, Type::TOTAL_CASH, Type::TOTAL_DEBT_EQUITY, Type::PAYOUT_RATIO],
						true,
					)
				)
			) {
				return $response->withAvailability(
					StockValuationModelAvailability::INVALID_DATA,
					'Nepřípustná hodnota: ' . $type->format() . '.',
				);
			}
		}

		if ($missing !== []) {
			return $response->withAvailability(
				StockValuationModelAvailability::MISSING_DATA,
				'Chybí vstupy: ' . implode(', ', $missing) . '.',
			);
		}

		if (count($importDates) > 1) {
			return $response->withAvailability(
				StockValuationModelAvailability::INVALID_DATA,
				'Vstupy pocházejí z různých importů. Je potřeba společná aktualizace.',
			);
		}

		if (
			$model instanceof Price\BookValueValuationModel
			|| $model instanceof Price\PriceEarningsValuationModel
			|| $model instanceof Price\PriceToSalesValuationModel
		) {
			$multiple = match (true) {
				$model instanceof Price\BookValueValuationModel => $industry?->getPriceToBook(),
				$model instanceof Price\PriceEarningsValuationModel => $industry?->getCurrentPERatio(),
				default => $industry?->getPriceToSales(),
			};
			if ($multiple === null) {
				return $response->withAvailability(
					StockValuationModelAvailability::MISSING_DATA,
					'Chybí odvětví nebo jeho srovnávací násobek. Univerzální násobek nepoužíváme.',
				);
			}

			if (!is_finite($multiple) || $multiple <= 0) {
				return $response->withAvailability(
					StockValuationModelAvailability::INVALID_DATA,
					'Odvětvový násobek musí být kladný.',
				);
			}
		}

		if ($response->getPurpose() !== StockValuationModelPurpose::DIAGNOSTIC) {
			$current = $asset->getAssetCurrentPrice();
			if (
				!is_finite(
					$current->getPrice(),
				)
				|| $current->getPrice() <= 0
				|| $current->getCurrency() !== $asset->getCurrency()
			) {
				return $response->withAvailability(
					StockValuationModelAvailability::INVALID_DATA,
					'Chybí platná tržní cena ve stejné měně jako vstupy.',
				);
			}

			$price = $response->getAssetPrice()?->getPrice();
			if ($price === null || !is_finite($price) || $price <= 0) {
				return $response->withAvailability(
					StockValuationModelAvailability::INVALID_DATA,
					'Vstupy a předpoklady nedávají kladný konečný odhad. U diskontních modelů musí být výnosnost vyšší než růst.',
				);
			}
		}

		return $response;
	}

}
