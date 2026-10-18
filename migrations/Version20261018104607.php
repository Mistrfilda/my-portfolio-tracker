<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018104607 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Assign bank accounts, expense tags, work income and homes to filip and scope personal uniqueness';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$hasLegacyData = (int) $this->connection->fetchOne(
			'SELECT (SELECT COUNT(*) FROM bank_acount) + (SELECT COUNT(*) FROM expense_tag)'
			. ' + (SELECT COUNT(*) FROM home) + (SELECT COUNT(*) FROM work_monthly_income)',
		) > 0;
		$this->abortIf(
			$hasLegacyData && $this->connection->fetchOne("SELECT id FROM app_admin WHERE username = 'filip'") === false,
			'Cannot migrate existing data: user filip does not exist.',
		);
		$this->abortIf(
			(int) $this->connection->fetchOne(
				'SELECT COUNT(*) FROM (SELECT year, month FROM work_monthly_income GROUP BY year, month HAVING COUNT(*) > 1) duplicates',
			) > 0,
			'Cannot migrate work income: duplicate year and month records exist.',
		);
		$this->addSql('ALTER TABLE bank_acount ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE bank_acount SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE bank_acount CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE bank_acount ADD CONSTRAINT FK_11F4E15E6CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_11F4E15E6CABE2BD ON bank_acount (app_admin_id)');
		$this->addSql('CREATE UNIQUE INDEX bank_expense_account_identifier_unique ON bank_expense (bank_account_id, identifier)');
		$this->addSql('DROP INDEX UNIQ_3AEE508772E836A ON bank_expense');
		$this->addSql('CREATE UNIQUE INDEX bank_income_account_identifier_unique ON bank_income (bank_account_id, identifier)');
		$this->addSql('DROP INDEX UNIQ_1FCD4DAD772E836A ON bank_income');
		$this->addSql('ALTER TABLE expense_tag ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE expense_tag SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE expense_tag CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE expense_tag ADD CONSTRAINT FK_AF79E27F6CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_AF79E27F6CABE2BD ON expense_tag (app_admin_id)');
		$this->addSql('ALTER TABLE home ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE home SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE home CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE home ADD CONSTRAINT FK_71D60CD06CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_71D60CD06CABE2BD ON home (app_admin_id)');
		$this->addSql('ALTER TABLE work_monthly_income ADD app_admin_id CHAR(36) DEFAULT NULL');
		$this->addSql("UPDATE work_monthly_income SET app_admin_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE work_monthly_income CHANGE app_admin_id app_admin_id CHAR(36) NOT NULL');
		$this->addSql('ALTER TABLE work_monthly_income ADD CONSTRAINT FK_FDFCD8D76CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_FDFCD8D76CABE2BD ON work_monthly_income (app_admin_id)');
		$this->addSql('CREATE UNIQUE INDEX work_income_owner_period_unique ON work_monthly_income (app_admin_id, year, month)');
	}

	public function down(Schema $schema): void
	{
		foreach (['bank_acount', 'expense_tag', 'home', 'work_monthly_income'] as $table) {
			$this->abortIf(
				(int) $this->connection->fetchOne(
					sprintf("SELECT COUNT(*) FROM %s r JOIN app_admin a ON a.id = r.app_admin_id WHERE a.username <> 'filip'", $table),
				) > 0,
				'Cannot remove ownership while another user has personal records.',
			);
		}
		foreach (['bank_expense', 'bank_income'] as $table) {
			$this->abortIf(
				(int) $this->connection->fetchOne(
					sprintf('SELECT COUNT(*) FROM (SELECT identifier FROM %s GROUP BY identifier HAVING COUNT(*) > 1) duplicates', $table),
				) > 0,
				'Cannot restore global transaction uniqueness: identifiers are shared by multiple bank accounts.',
			);
		}
		$this->addSql('ALTER TABLE bank_acount DROP FOREIGN KEY FK_11F4E15E6CABE2BD');
		$this->addSql('DROP INDEX IDX_11F4E15E6CABE2BD ON bank_acount');
		$this->addSql('ALTER TABLE bank_acount DROP app_admin_id');
		$this->addSql('CREATE UNIQUE INDEX UNIQ_3AEE508772E836A ON bank_expense (identifier)');
		$this->addSql('DROP INDEX bank_expense_account_identifier_unique ON bank_expense');
		$this->addSql('CREATE UNIQUE INDEX UNIQ_1FCD4DAD772E836A ON bank_income (identifier)');
		$this->addSql('DROP INDEX bank_income_account_identifier_unique ON bank_income');
		$this->addSql('ALTER TABLE expense_tag DROP FOREIGN KEY FK_AF79E27F6CABE2BD');
		$this->addSql('DROP INDEX IDX_AF79E27F6CABE2BD ON expense_tag');
		$this->addSql('ALTER TABLE expense_tag DROP app_admin_id');
		$this->addSql('ALTER TABLE home DROP FOREIGN KEY FK_71D60CD06CABE2BD');
		$this->addSql('DROP INDEX IDX_71D60CD06CABE2BD ON home');
		$this->addSql('ALTER TABLE home DROP app_admin_id');
		$this->addSql('ALTER TABLE work_monthly_income DROP FOREIGN KEY FK_FDFCD8D76CABE2BD');
		$this->addSql('DROP INDEX IDX_FDFCD8D76CABE2BD ON work_monthly_income');
		$this->addSql('DROP INDEX work_income_owner_period_unique ON work_monthly_income');
		$this->addSql('ALTER TABLE work_monthly_income DROP app_admin_id');
	}
}
