<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027084053 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Store owner-scoped daily work reports as one JSON snapshot per report date';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE work_report (report_date DATETIME NOT NULL, data JSON NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, app_admin_id CHAR(36) NOT NULL, UNIQUE INDEX work_report_owner_date_unique (app_admin_id, report_date), INDEX IDX_6FD6B1A66CABE2BD (app_admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('ALTER TABLE work_report ADD CONSTRAINT FK_6FD6B1A66CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE work_report DROP FOREIGN KEY FK_6FD6B1A66CABE2BD');
		$this->addSql('DROP TABLE work_report');
	}
}
