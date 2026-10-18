<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018122526 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Assign portfolio snapshots, monthly performance and period reports to filip';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$hasData = (int) $this->connection->fetchOne(
			'SELECT (SELECT COUNT(*) FROM portfolio_statistic_record) + (SELECT COUNT(*) FROM portfolio_performance_month) + (SELECT COUNT(*) FROM portfolio_period_statistic)',
		) > 0;
		$this->abortIf(
			$hasData && $this->connection->fetchOne("SELECT id FROM app_admin WHERE username = 'filip'") === false,
			'Cannot migrate portfolio statistics: user filip does not exist.',
		);
		$this->addSql('DROP INDEX portfolio_performance_month_unidx ON portfolio_performance_month');
		$this->addSql('ALTER TABLE portfolio_performance_month ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE portfolio_performance_month SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE portfolio_performance_month CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE portfolio_performance_month ADD CONSTRAINT FK_333BD0B76CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_333BD0B76CABE2BD ON portfolio_performance_month (app_admin_id)');
		$this->addSql('CREATE UNIQUE INDEX portfolio_performance_owner_month_unidx ON portfolio_performance_month (app_admin_id, period_month)');
		$this->addSql('DROP INDEX portfolio_period_statistic_created_at_idx ON portfolio_period_statistic');
		$this->addSql('ALTER TABLE portfolio_period_statistic ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE portfolio_period_statistic SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE portfolio_period_statistic CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE portfolio_period_statistic ADD CONSTRAINT FK_488C1E836CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_488C1E836CABE2BD ON portfolio_period_statistic (app_admin_id)');
		$this->addSql('CREATE INDEX portfolio_period_statistic_owner_created_idx ON portfolio_period_statistic (app_admin_id, created_at)');
		$this->addSql('DROP INDEX created_at_idx ON portfolio_statistic_record');
		$this->addSql('ALTER TABLE portfolio_statistic_record ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE portfolio_statistic_record SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE portfolio_statistic_record CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE portfolio_statistic_record ADD CONSTRAINT FK_7935D8B36CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_7935D8B36CABE2BD ON portfolio_statistic_record (app_admin_id)');
		$this->addSql('CREATE INDEX portfolio_statistic_record_owner_created_idx ON portfolio_statistic_record (app_admin_id, created_at)');
	}

	public function down(Schema $schema): void
	{
		foreach (['portfolio_statistic_record', 'portfolio_performance_month', 'portfolio_period_statistic'] as $table) {
			$this->abortIf(
				(int) $this->connection->fetchOne(
					sprintf("SELECT COUNT(*) FROM %s r JOIN app_admin a ON a.id = r.app_admin_id WHERE a.username <> 'filip'", $table),
				) > 0,
				'Cannot remove statistic ownership while another user has personal history.',
			);
		}
		$this->addSql('ALTER TABLE portfolio_performance_month DROP FOREIGN KEY FK_333BD0B76CABE2BD');
		$this->addSql('DROP INDEX IDX_333BD0B76CABE2BD ON portfolio_performance_month');
		$this->addSql('DROP INDEX portfolio_performance_owner_month_unidx ON portfolio_performance_month');
		$this->addSql('ALTER TABLE portfolio_performance_month DROP app_admin_id');
		$this->addSql('CREATE UNIQUE INDEX portfolio_performance_month_unidx ON portfolio_performance_month (period_month)');
		$this->addSql('ALTER TABLE portfolio_period_statistic DROP FOREIGN KEY FK_488C1E836CABE2BD');
		$this->addSql('DROP INDEX IDX_488C1E836CABE2BD ON portfolio_period_statistic');
		$this->addSql('DROP INDEX portfolio_period_statistic_owner_created_idx ON portfolio_period_statistic');
		$this->addSql('ALTER TABLE portfolio_period_statistic DROP app_admin_id');
		$this->addSql('CREATE INDEX portfolio_period_statistic_created_at_idx ON portfolio_period_statistic (created_at)');
		$this->addSql('ALTER TABLE portfolio_statistic_record DROP FOREIGN KEY FK_7935D8B36CABE2BD');
		$this->addSql('DROP INDEX IDX_7935D8B36CABE2BD ON portfolio_statistic_record');
		$this->addSql('DROP INDEX portfolio_statistic_record_owner_created_idx ON portfolio_statistic_record');
		$this->addSql('ALTER TABLE portfolio_statistic_record DROP app_admin_id');
		$this->addSql('CREATE INDEX created_at_idx ON portfolio_statistic_record (created_at)');
	}
}
