<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924085437 extends AbstractMigration
{

	public function getDescription(): string
	{
		return 'Track successful stock data downloads per asset and allow prices that have not been downloaded yet';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE stock_asset ADD dividends_checked_at DATETIME DEFAULT NULL, ADD valuation_downloaded_at DATETIME DEFAULT NULL, ADD analyst_insights_downloaded_at DATETIME DEFAULT NULL, CHANGE price_downloaded_at price_downloaded_at DATETIME DEFAULT NULL');
		$this->addSql('UPDATE stock_asset a LEFT JOIN (SELECT stock_asset_id, MAX(updated_at) AS downloaded_at FROM stock_asset_price_record GROUP BY stock_asset_id) p ON p.stock_asset_id = a.id SET a.price_downloaded_at = p.downloaded_at');
		$this->addSql("UPDATE stock_asset a LEFT JOIN (SELECT stock_asset_id, MAX(CASE WHEN type_group <> 'analyst_insight' THEN parsed_at END) AS valuation_at, MAX(CASE WHEN type_group = 'analyst_insight' THEN parsed_at END) AS analyst_at FROM stock_valuation_data GROUP BY stock_asset_id) v ON v.stock_asset_id = a.id SET a.valuation_downloaded_at = v.valuation_at, a.analyst_insights_downloaded_at = v.analyst_at");
	}

	public function down(Schema $schema): void
	{
		$this->addSql("DELETE FROM system_value WHERE system_value_enum = 'stock_data_monitoring_enabled_at'");
		$this->addSql('UPDATE stock_asset SET price_downloaded_at = created_at WHERE price_downloaded_at IS NULL');
		$this->addSql('ALTER TABLE stock_asset DROP dividends_checked_at, DROP valuation_downloaded_at, DROP analyst_insights_downloaded_at, CHANGE price_downloaded_at price_downloaded_at DATETIME NOT NULL');
	}

}
