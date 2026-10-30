<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030233436 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Align date-only columns with mistrfilda/datetime 0.6 DATE mapping';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE crypto_asset_price_record CHANGE date date DATE NOT NULL');
		$this->addSql('ALTER TABLE currency_conversion CHANGE for_date for_date DATE NOT NULL');
		$this->addSql('ALTER TABLE portfolio_performance_month CHANGE period_month period_month DATE NOT NULL');
		$this->addSql('ALTER TABLE portu_asset_price_record CHANGE date date DATE NOT NULL');
		$this->addSql('ALTER TABLE stock_asset_price_record CHANGE date date DATE NOT NULL');
		$this->addSql('ALTER TABLE work_report CHANGE report_date report_date DATE NOT NULL');
	}

	public function down(Schema $schema): void
	{
		// Restored DATETIME values use midnight; discarded time components cannot be recovered.
		$this->addSql('ALTER TABLE crypto_asset_price_record CHANGE date date DATETIME NOT NULL');
		$this->addSql('ALTER TABLE currency_conversion CHANGE for_date for_date DATETIME NOT NULL');
		$this->addSql('ALTER TABLE portfolio_performance_month CHANGE period_month period_month DATETIME NOT NULL');
		$this->addSql('ALTER TABLE portu_asset_price_record CHANGE date date DATETIME NOT NULL');
		$this->addSql('ALTER TABLE stock_asset_price_record CHANGE date date DATETIME NOT NULL');
		$this->addSql('ALTER TABLE work_report CHANGE report_date report_date DATETIME NOT NULL');
	}
}
