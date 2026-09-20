<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920084841 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add persistent investor instructions for stock AI analysis';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE stock_ai_analysis_settings (id INT NOT NULL, investor_instructions LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP TABLE stock_ai_analysis_settings');
	}
}
