<?php

declare(strict_types = 1);

namespace App\UI\Menu;

use App\Stock\Dividend\StockAssetDividendRepository;
use App\UI\Icon\SvgIcon;
use Mistrfilda\Datetime\DatetimeFactory;

class MenuBuilder
{

	public function __construct(
		private StockAssetDividendRepository $stockAssetDividendRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/**
	 * @return array<MenuItem|MenuGroup>
	 */
	public function buildMenu(): array
	{
		return [
			new MenuItem('Dashboard', 'default', SvgIcon::HOME, 'Dashboard'),
			new MenuGroup('Statistiky', SvgIcon::CHART_BAR_SQUARE, [
				new MenuItem('PortfolioStatisticChart', 'default', SvgIcon::CHART_BAR_SQUARE, 'Grafy'),
				new MenuItem('PortfolioStatisticTotal', 'default', SvgIcon::ARCHIVE_BOX, 'Statistiky'),
				new MenuItem('CurrencyOverview', 'default', SvgIcon::CIRCLE_STACK, 'Měnový přehled'),
				new MenuItem('PortfolioStatisticRecord', 'default', SvgIcon::TABLE_CELLS, 'Uložené statistiky'),
			]),
			new MenuGroup('Akcie', SvgIcon::COLLECTION, [
				new MenuItem(
					'StockAsset',
					'default',
					SvgIcon::COLLECTION,
					'Akcie',
					['StockAssetEdit', 'StockAssetDividend'],
				),
				new MenuItem('StockAssetIndustry', 'default', SvgIcon::BUILDING_STOREFRONT, 'Odvětví'),
				new MenuItem('StockValuation', 'default', SvgIcon::ACADEMIC_CAP, 'Valuace'),
				new MenuItem('StockValuationModel', 'default', SvgIcon::COLLECTION, 'Valuační modely'),
				new MenuItem('StockPosition', 'default', SvgIcon::DOCUMENT_DUPLICATE, 'Pozice', ['StockPositionEdit']),
				new MenuItem(
					'StockAssetPositionDetail',
					'default',
					SvgIcon::ADJUSTMENTS,
					'Otevřené pozice',
					['StockAssetDetail'],
				),
				new MenuItem('StockAssetClosedPositionDetail', 'default', SvgIcon::ADJUSTMENTS, 'Zavřené pozice'),
				new MenuItem('StockAiAnalysis', 'default', SvgIcon::MAGNIFYING_GLASS, 'AI analýza'),
			]),
			new MenuGroup('Dividendy', SvgIcon::ARROW_TRENDING_UP, [
				new MenuItem(
					'StockAssetDividendDetail',
					'default',
					SvgIcon::ARROW_TRENDING_UP,
					'Přehled',
					badge: (string) $this->stockAssetDividendRepository->getCountSinceDate(
						$this->datetimeFactory->createNow()->deductMonthsFromDatetime(1),
					),
				),
				new MenuItem('StockAssetDividendForecast', 'default', SvgIcon::CLOUD, 'Predikce'),
				new MenuItem('StockAssetDividendRecord', 'default', SvgIcon::ARROW_TRENDING_UP, 'Vyplacené'),
			]),
			new MenuItem(
				'PortuAsset',
				'default',
				SvgIcon::PORTU,
				'Portu',
				['PortuPosition', 'PortuPositionEdit', 'PortuAssetEdit', 'PortuPositionPrice'],
			),
			new MenuGroup('Finance', SvgIcon::BANKNOTES, [
				new MenuItem('ExpenseOverview', 'default', SvgIcon::BANKNOTES, 'Přehled výdajů'),
				new MenuItem('Expense', 'kb', SvgIcon::CREDIT_CARD, 'Výdaje'),
				new MenuItem('ExpenseTag', 'tags', SvgIcon::TAG, 'Výdajové tagy'),
				new MenuItem('WorkMonthlyIncome', 'default', SvgIcon::COLLECTION, 'Příjmy z práce'),
				new MenuItem('BankAccount', 'default', SvgIcon::BANKNOTES, 'Bankovní účty'),
				new MenuItem('BankIncome', 'default', SvgIcon::COLLECTION, 'Příjmy z banky'),
			]),
			new MenuItem('PortfolioGoal', 'default', SvgIcon::PRESENTATION_CHART_UP, 'Cíle'),
			new MenuGroup('Kryptoměny', SvgIcon::CRYPTO_BITCOIN, [
				new MenuItem('CryptoAsset', 'default', SvgIcon::CRYPTO_BITCOIN, 'Kryptoměny', ['CryptoAssetEdit']),
				new MenuItem('CryptoPosition', 'default', SvgIcon::CRYPTO_BITCOIN, 'Pozice', ['CryptoPositionEdit']),
				new MenuItem('CryptoAssetPositionDetail', 'default', SvgIcon::CRYPTO_BITCOIN, 'Přehled pozic'),
			]),
			new MenuItem('AppAdmin', 'default', SvgIcon::USERS, 'Uživatelé', ['AppAdminEdit'], true),
			new MenuGroup('Domov', SvgIcon::HOME, [
				new MenuItem('Home', 'default', SvgIcon::HOME, 'Domovy', ['HomeEdit', 'HomeDevice', 'HomeDeviceEdit']),
			]),
		];
	}

}
