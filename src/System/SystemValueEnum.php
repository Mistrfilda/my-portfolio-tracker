<?php

declare(strict_types = 1);

namespace App\System;

use App\System\Resolver\SystemValueCurrentVersionResolver;
use App\System\Resolver\SystemValueDatabaseResolver;
use App\System\Resolver\SystemValueEnabledStockAssetsResolver;
use App\System\Resolver\SystemValueLastUpdatedPricesCountResolver;

enum SystemValueEnum: string
{

	case CURRENT_PHP_DEPLOY_VERSION = 'current_php_deploy_version';

	case CURRENT_NGINX_DEPLOY_VERSION = 'current_nginx_deploy_version';

	case DIVIDENDS_UPDATED_AT = 'dividends_updated_at';

	case ENABLED_STOCK_ASSETS = 'enabled_stock_assets';

	case LAST_UPDATED_STOCK_PRICES_COUNT = 'last_updated_stock_prices_count';

	case TWELVE_DATA_UPDATED_AT = 'twelve_data_updated_at';

	case PSE_DATA_UPDATED_AT = 'pse_data_updated_at';

	case PUPPETER_UPDATED_AT = 'puppeter_data_updated_at';

	public function getLabel(): string
	{
		return match ($this) {
			SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION => 'Aktuální verze PHP deploye',
			SystemValueEnum::CURRENT_NGINX_DEPLOY_VERSION => 'Aktuální verze NGINX deploye',
			SystemValueEnum::DIVIDENDS_UPDATED_AT => 'Poslední aktualizace dividend',
			SystemValueEnum::ENABLED_STOCK_ASSETS => 'Celkový počet akcíí s aktualizací ceny',
			SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT => 'Celkový počet cen akcií aktualizovaných během poslední aktualizace',
			SystemValueEnum::TWELVE_DATA_UPDATED_AT => 'Poslední aktualizace cen z Twelve data',
			SystemValueEnum::PSE_DATA_UPDATED_AT => 'Poslední aktualizace cen z PSE',
			SystemValueEnum::PUPPETER_UPDATED_AT => 'Poslední aktualizace cen z PUPPETER',
		};
	}

	public function getResolverClass(): string
	{
		return match ($this) {
			SystemValueEnum::CURRENT_PHP_DEPLOY_VERSION => SystemValueCurrentVersionResolver::class,
			SystemValueEnum::CURRENT_NGINX_DEPLOY_VERSION => SystemValueCurrentVersionResolver::class,
			SystemValueEnum::DIVIDENDS_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::TWELVE_DATA_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::PSE_DATA_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::PUPPETER_UPDATED_AT => SystemValueDatabaseResolver::class,
			SystemValueEnum::ENABLED_STOCK_ASSETS => SystemValueEnabledStockAssetsResolver::class,
			SystemValueEnum::LAST_UPDATED_STOCK_PRICES_COUNT => SystemValueLastUpdatedPricesCountResolver::class,
			//          default => throw new SystemValueNotResolvableException()
		};
	}

}
