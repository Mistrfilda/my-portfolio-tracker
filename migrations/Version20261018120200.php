<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018120200 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Assign portfolio goals and AI analyses with personal investor instructions to filip';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$hasData = (int) $this->connection->fetchOne(
			'SELECT (SELECT COUNT(*) FROM portfolio_goal) + (SELECT COUNT(*) FROM stock_ai_analysis_run) + (SELECT COUNT(*) FROM stock_ai_analysis_settings)',
		) > 0;
		$this->abortIf(
			$hasData && $this->connection->fetchOne("SELECT id FROM app_admin WHERE username = 'filip'") === false,
			'Cannot migrate goals and AI analyses: user filip does not exist.',
		);
		$this->abortIf(
			(int) $this->connection->fetchOne('SELECT COUNT(*) FROM stock_ai_analysis_settings') > 1,
			'Cannot migrate AI settings: more than one legacy settings record exists.',
		);
		$this->addSql('ALTER TABLE portfolio_goal ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE portfolio_goal SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE portfolio_goal CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE portfolio_goal ADD CONSTRAINT FK_CCEDACD46CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_CCEDACD46CABE2BD ON portfolio_goal (app_admin_id)');
		$this->addSql('ALTER TABLE stock_ai_analysis_run ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE stock_ai_analysis_run SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE stock_ai_analysis_run CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE stock_ai_analysis_run ADD CONSTRAINT FK_37A612616CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_37A612616CABE2BD ON stock_ai_analysis_run (app_admin_id)');
		$this->addSql('ALTER TABLE stock_ai_analysis_settings ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE stock_ai_analysis_settings SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE stock_ai_analysis_settings CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL, CHANGE id id INT AUTO_INCREMENT NOT NULL');
		$this->addSql('ALTER TABLE stock_ai_analysis_settings ADD CONSTRAINT FK_372ECFF56CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE UNIQUE INDEX UNIQ_372ECFF56CABE2BD ON stock_ai_analysis_settings (app_admin_id)');
	}

	public function down(Schema $schema): void
	{
		foreach (['portfolio_goal', 'stock_ai_analysis_run', 'stock_ai_analysis_settings'] as $table) {
			$this->abortIf(
				(int) $this->connection->fetchOne(
					sprintf("SELECT COUNT(*) FROM %s r JOIN app_admin a ON a.id = r.app_admin_id WHERE a.username <> 'filip'", $table),
				) > 0,
				'Cannot remove goal and AI ownership while another user has personal records.',
			);
		}
		// The previous application reads the singleton with ID 1.
		$this->abortIf(
			(int) $this->connection->fetchOne('SELECT COUNT(*) FROM stock_ai_analysis_settings WHERE id <> 1') > 0,
			'Cannot restore legacy AI settings unless the remaining settings record has ID 1.',
		);
		$this->addSql('ALTER TABLE portfolio_goal DROP FOREIGN KEY FK_CCEDACD46CABE2BD');
		$this->addSql('DROP INDEX IDX_CCEDACD46CABE2BD ON portfolio_goal');
		$this->addSql('ALTER TABLE portfolio_goal DROP app_admin_id');
		$this->addSql('ALTER TABLE stock_ai_analysis_run DROP FOREIGN KEY FK_37A612616CABE2BD');
		$this->addSql('DROP INDEX IDX_37A612616CABE2BD ON stock_ai_analysis_run');
		$this->addSql('ALTER TABLE stock_ai_analysis_run DROP app_admin_id');
		$this->addSql('ALTER TABLE stock_ai_analysis_settings DROP FOREIGN KEY FK_372ECFF56CABE2BD');
		$this->addSql('DROP INDEX UNIQ_372ECFF56CABE2BD ON stock_ai_analysis_settings');
		$this->addSql('ALTER TABLE stock_ai_analysis_settings DROP app_admin_id, CHANGE id id INT NOT NULL');
	}
}
