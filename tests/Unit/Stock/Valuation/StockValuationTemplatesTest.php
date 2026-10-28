<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation;

use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusProvider;
use App\Stock\Valuation\Model\Price\BookValueValuationModel;
use App\Stock\Valuation\Model\Price\DividendDiscountValuationModel;
use App\Stock\Valuation\Model\Price\DividendPayoutSafetyModel;
use App\Stock\Valuation\Model\StockValuationModelEvaluator;
use App\Stock\Valuation\StockValuation;
use App\UI\Filter\AssetPriceFilter;
use App\UI\Filter\CompactCurrencyFilter;
use Latte\Engine;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockValuationTemplatesTest extends TestCase
{

	public function testSharedCardsRenderDiagnosticsReasonsScenariosAndHistory(): void
	{
		$current = ValuationFixture::create([
			'book_value_per_share' => -1.6,
			'forward_annual_dividend_rate' => 4.44,
			'payout_ratio' => 89.26,
		]);
		$previous = ValuationFixture::data(
			$current->getStockAsset(),
			['payout_ratio' => null],
			new ImmutableDateTime('2026-09-25'),
		);
		$valuation = new StockValuation($current->getStockAsset(), $current->getCurrentStockValuationData(), $previous);
		$models = [new BookValueValuationModel(), new DividendDiscountValuationModel(), new DividendPayoutSafetyModel()];
		$responses = array_map(
			static fn ($model) => (new StockValuationModelEvaluator())->evaluate($model, $valuation),
			$models,
		);
		$consensus = (new StockValuationModelConsensusProvider())->getForStockAsset(
			$valuation->getStockAsset(),
			$responses,
		);
		$latte = new Engine();
		$latte->addFilter('assetPriceFormat', AssetPriceFilter::format(...));
		$latte->addFilter('compactCurrency', CompactCurrencyFilter::format(...));
		$directory = __DIR__ . '/../../../../src/Stock/Valuation/UI/Control/';
		$cards = $latte->renderToString($directory . 'modelCards.latte', ['modelResponses' => $responses]);
		self::assertStringContainsString('Nevhodný model', $cards);
		self::assertStringContainsString('63.43 USD', $cards);
		self::assertStringContainsString('89.26 %', $cards);
		self::assertStringNotContainsString('89.26 USD', $cards);
		self::assertStringContainsString('Opatrný', $cards);
		$summary = $latte->renderToString(
			$directory . 'valuationDataSummary.latte',
			['valuation' => $valuation, 'consensus' => $consensus],
		);
		self::assertStringContainsString('Nově dostupné', $summary);
		self::assertStringContainsString('4.44 USD', $summary);
		self::assertStringContainsString('25.09.2026', $summary);
	}

}
