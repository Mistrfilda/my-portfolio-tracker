<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018113749 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Assign personal dividend records and forecasts to filip and scope dividend uniqueness';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$hasData = (int) $this->connection->fetchOne(
			'SELECT (SELECT COUNT(*) FROM stock_asset_dividend_record) + (SELECT COUNT(*) FROM stock_asset_dividend_forecast)',
		) > 0;
		$this->abortIf(
			$hasData && $this->connection->fetchOne("SELECT id FROM app_admin WHERE username = 'filip'") === false,
			'Cannot migrate existing dividends: user filip does not exist.',
		);
		$this->abortIf(
			(int) $this->connection->fetchOne(
				'SELECT COUNT(*) FROM (SELECT stock_asset_dividend_id FROM stock_asset_dividend_record GROUP BY stock_asset_dividend_id HAVING COUNT(*) > 1) duplicates',
			) > 0,
			'Cannot migrate dividend records: duplicate dividend events exist.',
		);
		$this->abortIf(
			(int) $this->connection->fetchOne(
				'SELECT COUNT(*) FROM (SELECT stock_asset_dividend_forecast_id, stock_asset_id FROM stock_asset_dividend_forecast_record GROUP BY stock_asset_dividend_forecast_id, stock_asset_id HAVING COUNT(*) > 1) duplicates',
			) > 0,
			'Cannot migrate forecasts: duplicate forecast assets exist.',
		);
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE stock_asset_dividend_forecast SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast ADD CONSTRAINT FK_9F9DAA966CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_9F9DAA966CABE2BD ON stock_asset_dividend_forecast (app_admin_id)');
		$this->addSql('CREATE UNIQUE INDEX dividend_forecast_asset_unique ON stock_asset_dividend_forecast_record (stock_asset_dividend_forecast_id, stock_asset_id)');
		$this->addSql('ALTER TABLE stock_asset_dividend_record ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE stock_asset_dividend_record SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE stock_asset_dividend_record CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE stock_asset_dividend_record ADD CONSTRAINT FK_3DB0D3356CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_3DB0D3356CABE2BD ON stock_asset_dividend_record (app_admin_id)');
		$this->addSql('CREATE UNIQUE INDEX dividend_record_owner_event_unique ON stock_asset_dividend_record (app_admin_id, stock_asset_dividend_id)');
	}

	public function down(Schema $schema): void
	{
		foreach (['stock_asset_dividend_record', 'stock_asset_dividend_forecast'] as $table) {
			$this->abortIf(
				(int) $this->connection->fetchOne(
					sprintf("SELECT COUNT(*) FROM %s r JOIN app_admin a ON a.id = r.app_admin_id WHERE a.username <> 'filip'", $table),
				) > 0,
				'Cannot remove dividend ownership while another user has personal records.',
			);
		}
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast DROP FOREIGN KEY FK_9F9DAA966CABE2BD');
		$this->addSql('DROP INDEX IDX_9F9DAA966CABE2BD ON stock_asset_dividend_forecast');
		$this->addSql('ALTER TABLE stock_asset_dividend_forecast DROP app_admin_id');
		$this->addSql('DROP INDEX dividend_forecast_asset_unique ON stock_asset_dividend_forecast_record');
		$this->addSql('ALTER TABLE stock_asset_dividend_record DROP FOREIGN KEY FK_3DB0D3356CABE2BD');
		$this->addSql('DROP INDEX IDX_3DB0D3356CABE2BD ON stock_asset_dividend_record');
		$this->addSql('DROP INDEX dividend_record_owner_event_unique ON stock_asset_dividend_record');
		$this->addSql('ALTER TABLE stock_asset_dividend_record DROP app_admin_id');
	}
}
