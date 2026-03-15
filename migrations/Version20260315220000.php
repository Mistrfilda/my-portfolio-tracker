<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315220000 extends AbstractMigration
{

	public function getDescription(): string
	{
		return 'Add before-tax columns for special dividends in forecast record';
	}

	public function up(Schema $schema): void
	{
		$this->addSql(
			'ALTER TABLE stock_asset_dividend_forecast_record ADD special_dividends_last_year_per_stock_before_tax DOUBLE PRECISION DEFAULT NULL, ADD expected_special_dividend_this_year_per_stock_before_tax DOUBLE PRECISION DEFAULT NULL',
		);

		$this->addSql(
			'UPDATE stock_asset_dividend_forecast_record sadfr
			JOIN stock_asset sa ON sadfr.stock_asset_id = sa.id
			SET sadfr.special_dividends_last_year_per_stock_before_tax = CASE
				WHEN sadfr.special_dividends_last_year_per_stock IS NOT NULL AND sa.dividend_tax IS NOT NULL
					THEN sadfr.special_dividends_last_year_per_stock / (1 - (sa.dividend_tax * 0.01))
				WHEN sadfr.special_dividends_last_year_per_stock IS NOT NULL
					THEN sadfr.special_dividends_last_year_per_stock
				ELSE NULL
			END,
			sadfr.expected_special_dividend_this_year_per_stock_before_tax = CASE
				WHEN sadfr.expected_special_dividend_this_year_per_stock IS NOT NULL AND sa.dividend_tax IS NOT NULL
					THEN sadfr.expected_special_dividend_this_year_per_stock / (1 - (sa.dividend_tax * 0.01))
				WHEN sadfr.expected_special_dividend_this_year_per_stock IS NOT NULL
					THEN sadfr.expected_special_dividend_this_year_per_stock
				ELSE NULL
			END',
		);
	}

	public function down(Schema $schema): void
	{
		$this->addSql(
			'ALTER TABLE stock_asset_dividend_forecast_record DROP special_dividends_last_year_per_stock_before_tax, DROP expected_special_dividend_this_year_per_stock_before_tax',
		);
	}

}
