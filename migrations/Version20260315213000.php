<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315213000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add gross (before tax) fields to stock_asset_dividend_forecast_record and custom gross dividend field';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast_record ADD already_received_dividend_per_stock_before_tax DOUBLE PRECISION NOT NULL DEFAULT 0, ADD expected_dividend_per_stock_before_tax DOUBLE PRECISION NOT NULL DEFAULT 0, ADD original_dividend_used_for_calculation_before_tax DOUBLE PRECISION NOT NULL DEFAULT 0, ADD adjusted_dividend_used_for_calculation_before_tax DOUBLE PRECISION NOT NULL DEFAULT 0, ADD custom_gross_dividend_used_for_calculation DOUBLE PRECISION DEFAULT NULL');

		// Migrate existing records: compute gross values from net values using dividend tax from stock_asset
		$this->addSql('
			UPDATE stock_asset_dividend_forecast_record r
			INNER JOIN stock_asset sa ON r.stock_asset_id = sa.id
			SET
				r.already_received_dividend_per_stock_before_tax = CASE
					WHEN sa.dividend_tax IS NOT NULL AND sa.dividend_tax > 0 THEN r.already_received_dividend_per_stock / (1 - (sa.dividend_tax * 0.01))
					ELSE r.already_received_dividend_per_stock
				END,
				r.expected_dividend_per_stock_before_tax = CASE
					WHEN sa.dividend_tax IS NOT NULL AND sa.dividend_tax > 0 THEN r.expected_dividend_per_stock / (1 - (sa.dividend_tax * 0.01))
					ELSE r.expected_dividend_per_stock
				END,
				r.original_dividend_used_for_calculation_before_tax = CASE
					WHEN sa.dividend_tax IS NOT NULL AND sa.dividend_tax > 0 THEN r.original_dividend_used_for_calculation / (1 - (sa.dividend_tax * 0.01))
					ELSE r.original_dividend_used_for_calculation
				END,
				r.adjusted_dividend_used_for_calculation_before_tax = CASE
					WHEN sa.dividend_tax IS NOT NULL AND sa.dividend_tax > 0 THEN r.adjusted_dividend_used_for_calculation / (1 - (sa.dividend_tax * 0.01))
					ELSE r.adjusted_dividend_used_for_calculation
				END,
				r.custom_gross_dividend_used_for_calculation = CASE
					WHEN r.custom_dividend_used_for_calculation IS NOT NULL AND sa.dividend_tax IS NOT NULL AND sa.dividend_tax > 0
						THEN r.custom_dividend_used_for_calculation / (1 - (sa.dividend_tax * 0.01))
					WHEN r.custom_dividend_used_for_calculation IS NOT NULL
						THEN r.custom_dividend_used_for_calculation
					ELSE NULL
				END
		');

		// Remove defaults after data migration
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast_record ALTER already_received_dividend_per_stock_before_tax DROP DEFAULT, ALTER expected_dividend_per_stock_before_tax DROP DEFAULT, ALTER original_dividend_used_for_calculation_before_tax DROP DEFAULT, ALTER adjusted_dividend_used_for_calculation_before_tax DROP DEFAULT');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast_record DROP already_received_dividend_per_stock_before_tax, DROP expected_dividend_per_stock_before_tax, DROP original_dividend_used_for_calculation_before_tax, DROP adjusted_dividend_used_for_calculation_before_tax, DROP custom_gross_dividend_used_for_calculation');
	}
}
