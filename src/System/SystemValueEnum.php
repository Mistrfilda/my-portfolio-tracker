<?php

declare(strict_types = 1);

namespace App\System;

use App\System\Resolver\SystemValueCurrentVersionResolver;
use App\System\Resolver\SystemValueDatabaseResolver;
use App\System\Resolver\SystemValueEnabledDividendStockAssetsResolver;
use App\System\Resolver\SystemValueEnabledStockAssetsResolver;
use App\System\Resolver\SystemValueEnabledStockValuationsAssetsResolver;
use App\System\Resolver\SystemValueLastUpdatedPricesCountResolver;

enum SystemValueEnum: string
{

	case CURRENT_PHP_DEPLOY_VERSION = 'current_php_deploy_version';

	case CURRENT_NGINX_DEPLOY_VERSION = 'current_nginx_deploy_version';

	case DIVIDENDS_STOCK_ASSETS_WEB = 'dividends_stock_assets_web';

	case DIVIDENDS_UPDATED_AT = 'dividends_updated_at';

	case DIVIDENDS_UPDATED_COUNT = 'dividends_updated_count';

	case ENABLED_STOCK_ASSETS = 'enabled_stock_assets';

	case LAST_UPDATED_STOCK_PRICES_COUNT = 'last_updated_stock_prices_count';

	case TWELVE_DATA_UPDATED_AT = 'twelve_data_updated_at';

	case PSE_DATA_UPDATED_AT = 'pse_data_updated_at';

	case PUPPETER_UPDATED_AT = 'puppeter_data_updated_at';

	case STOCK_VALUATION_COUNT = 'stock_valuation_count';

	case STOCK_VALUATION_DOWNLOADED_AT = 'stock_valuation_downloaded_at';

	case STOCK_VALUATION_DOWNLOADED_COUNT = 'stock_valuation_downloaded_count';

	case EXPENSE_TAGS_PROCESSED_AT = 'expense_tags_processed_at';

	case CNB_CURRENCY_DOWNLOADED_COUNT = 'cnb_currency_downloaded_count';

	case ECB_CURRENCY_DOWNLOADED_COUNT = 'ecb_currency_downloaded_count';

	public function getLabel(): string
	{
		return match ($this) {
			SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION => 'Aktuální verze PHP deploye',
			SystemValueEnum::CURRENT_NGINX_DEPLOY_VERSION => 'Aktuální verze NGINX deploye',
			SystemValueEnum::DIVIDENDS_STOCK_ASSETS_WEB => 'Počet akcií s automatickým stažením dividend',
			SystemValueEnum::DIVIDENDS_UPDATED_AT => 'Poslední aktualizace dividend',
			SystemValueEnum::DIVIDENDS_UPDATED_COUNT => 'Počet stažených dividend při poslední aktualizaci',
			SystemValueEnum::ENABLED_STOCK_ASSETS => 'Celkový počet akcíí s aktualizací ceny',
			SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT => 'Celkový počet cen akcií aktualizovaných během poslední aktualizace',
			SystemValueEnum::TWELVE_DATA_UPDATED_AT => 'Poslední aktualizace cen z Twelve data',
			SystemValueEnum::PSE_DATA_UPDATED_AT => 'Poslední aktualizace cen z PSE',
			SystemValueEnum::PUPPETER_UPDATED_AT => 'Poslední aktualizace cen z PUPPETER',
			SystemValueEnum::STOCK_VALUATION_COUNT => 'Počet akcíí se stažením valuace',
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_AT => 'Poslední aktualizace dat pro valuace',
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_COUNT => 'Počet stažených valuací',
			SystemValueEnum::EXPENSE_TAGS_PROCESSED_AT => 'Výdajové tagy naposledy zprocesovány',
			SystemValueEnum::CNB_CURRENCY_DOWNLOADED_COUNT => 'Počet aktualizovaných měn z ČNB',
			SystemValueEnum::ECB_CURRENCY_DOWNLOADED_COUNT => 'Počet aktualizovaných měn z ECB',
		};
	}

	public function getResolverClass(): string
	{
		return match ($this) {
			SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION => SystemValueCurrentVersionResolver::class,
			SystemValueEnum::CURRENT_NGINX_DEPLOY_VERSION => SystemValueCurrentVersionResolver::class,
			SystemValueEnum::DIVIDENDS_STOCK_ASSETS_WEB => SystemValueEnabledDividendStockAssetsResolver::class,
			SystemValueEnum::DIVIDENDS_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::DIVIDENDS_UPDATED_COUNT => SystemValueDatabaseResolver::class,
			SystemValueEnum::TWELVE_DATA_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::PSE_DATA_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::PUPPETER_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::ENABLED_STOCK_ASSETS => SystemValueEnabledStockAssetsResolver::class,
			SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT => SystemValueLastUpdatedPricesCountResolver::class,
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::STOCK_VALUATION_DOWNLOADED_COUNT => SystemValueDatabaseResolver::class,
			SystemValueEnum::STOCK_VALUATION_COUNT => SystemValueEnabledStockValuationsAssetsResolver::class,
			SystemValueEnum::EXPENSE_TAGS_PROCESSED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::CNB_CURRENCY_DOWNLOADED_COUNT => SystemValueDatabaseResolver::class,
			SystemValueEnum::ECB_CURRENCY_DOWNLOADED_COUNT => SystemValueDatabaseResolver::class,
		};
	}

}
