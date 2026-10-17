<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017121209 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add database-backed OAuth clients, tokens, authorization requests and rate limits';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE oauth_authorization_request (id VARCHAR(64) NOT NULL, csrf_hash VARCHAR(64) NOT NULL, parameters JSON NOT NULL, expires_at DATETIME NOT NULL, consumed TINYINT NOT NULL, INDEX oauth_authorization_expiry_idx (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE oauth_client (id VARCHAR(80) NOT NULL, name VARCHAR(255) NOT NULL, confidential TINYINT NOT NULL, secret_hash VARCHAR(255) DEFAULT NULL, redirect_uris JSON NOT NULL, scopes JSON NOT NULL, created_at DATETIME NOT NULL, active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE oauth_rate_limit (id VARCHAR(64) NOT NULL, attempts INT NOT NULL, reset_at INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE oauth_token (id VARCHAR(80) NOT NULL, type VARCHAR(16) NOT NULL, client_id VARCHAR(80) NOT NULL, user_id VARCHAR(80) DEFAULT NULL, scopes JSON NOT NULL, expires_at DATETIME NOT NULL, revoked TINYINT NOT NULL, family_id VARCHAR(64) DEFAULT NULL, family_expires_at DATETIME DEFAULT NULL, access_token_id VARCHAR(80) DEFAULT NULL, INDEX oauth_token_client_idx (client_id), INDEX oauth_token_family_idx (family_id), INDEX oauth_token_expiry_idx (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE oauth_authorization_request');
		$this->addSql('DROP TABLE oauth_client');
		$this->addSql('DROP TABLE oauth_rate_limit');
		$this->addSql('DROP TABLE oauth_token');
	}
}
