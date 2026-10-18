<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018102336 extends AbstractMigration
{

	public function getDescription(): string
	{
		return 'Assign Portu portfolios and simple watchlist entries to filip and scope ticker uniqueness by owner';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$hasLegacyData = (int) $this->connection->fetchOne(
			'SELECT (SELECT COUNT(*) FROM portu_asset) + (SELECT COUNT(*) FROM stock_asset_watchlist)',
		) > 0;
		$ownerId = $this->connection->fetchOne("SELECT id FROM app_admin WHERE username = 'filip'");
		$this->abortIf($hasLegacyData && $ownerId === false, 'Cannot migrate existing data: user filip does not exist.');
		$this->abortIf(
			(int) $this->connection->fetchOne(
				"SELECT COUNT(*) FROM portu_position p JOIN app_admin a ON a.id = p.app_admin_id WHERE a.username <> 'filip'",
			) > 0,
			'Cannot assign Portu portfolios to filip: some positions belong to another user.',
		);

		$this->addSql('ALTER TABLE portu_asset ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE portu_asset SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE portu_asset CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('CREATE INDEX IDX_C40320346CABE2BD ON portu_asset (app_admin_id)');
		$this->addSql('ALTER TABLE portu_asset ADD CONSTRAINT FK_C40320346CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');

		$this->addSql('ALTER TABLE stock_asset_watchlist ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE stock_asset_watchlist SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE stock_asset_watchlist CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('CREATE INDEX IDX_38901B486CABE2BD ON stock_asset_watchlist (app_admin_id)');
		$this->addSql('ALTER TABLE stock_asset_watchlist ADD CONSTRAINT FK_38901B486CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE UNIQUE INDEX watchlist_owner_ticker_unique ON stock_asset_watchlist (app_admin_id, ticker)');
		$this->addSql('DROP INDEX UNIQ_38901B487EC30896 ON stock_asset_watchlist');
	}

	public function down(Schema $schema): void
	{
		$this->abortIf(
			(int) $this->connection->fetchOne(
				"SELECT (SELECT COUNT(*) FROM portu_asset p JOIN app_admin a ON a.id = p.app_admin_id WHERE a.username <> 'filip')"
				. " + (SELECT COUNT(*) FROM stock_asset_watchlist w JOIN app_admin a ON a.id = w.app_admin_id WHERE a.username <> 'filip')",
			) > 0,
			'Cannot remove ownership while another user has Portu portfolios or watchlist entries.',
		);

		$this->addSql('CREATE UNIQUE INDEX UNIQ_38901B487EC30896 ON stock_asset_watchlist (ticker)');
		$this->addSql('ALTER TABLE stock_asset_watchlist DROP FOREIGN KEY FK_38901B486CABE2BD');
		$this->addSql('DROP INDEX watchlist_owner_ticker_unique ON stock_asset_watchlist');
		$this->addSql('DROP INDEX IDX_38901B486CABE2BD ON stock_asset_watchlist');
		$this->addSql('ALTER TABLE stock_asset_watchlist DROP app_admin_id');
		$this->addSql('ALTER TABLE portu_asset DROP FOREIGN KEY FK_C40320346CABE2BD');
		$this->addSql('DROP INDEX IDX_C40320346CABE2BD ON portu_asset');
		$this->addSql('ALTER TABLE portu_asset DROP app_admin_id');
	}

}
