<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018112629 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Persist notification recipients and assign existing notifications to filip';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$this->abortIf(
			(int) $this->connection->fetchOne('SELECT COUNT(*) FROM notification') > 0
			&& $this->connection->fetchOne("SELECT id FROM app_admin WHERE username = 'filip'") === false,
			'Cannot migrate existing notifications: user filip does not exist.',
		);
		$this->addSql('ALTER TABLE notification ADD recipient_id CHAR(36) DEFAULT NULL');
		// Legacy trend reports can contain personal Portu values. Keep all history private to filip.
		$this->addSql("UPDATE notification SET recipient_id = (SELECT id FROM app_admin WHERE username = 'filip')");
		$this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES app_admin (id)');
		$this->addSql('CREATE INDEX IDX_BF5476CAE92F8F78 ON notification (recipient_id)');
	}

	public function down(Schema $schema): void
	{
		$this->abortIf(
			(int) $this->connection->fetchOne(
				"SELECT COUNT(*) FROM notification n JOIN app_admin a ON a.id = n.recipient_id WHERE a.username <> 'filip'",
			) > 0,
			'Cannot remove notification recipients while another user has notifications.',
		);
		$this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
		$this->addSql('DROP INDEX IDX_BF5476CAE92F8F78 ON notification');
		$this->addSql('ALTER TABLE notification DROP recipient_id');
	}
}
