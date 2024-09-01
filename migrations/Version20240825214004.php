<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240825214004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic ADD portfolio_statistic_control_type_enum VARCHAR(255) NOT NULL, ADD structured_data LONGTEXT DEFAULT NULL');
		$this->addSql('UPDATE portfolio_statistic set portfolio_statistic_control_type_enum = "simple_value"');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic DROP portfolio_statistic_control_type_enum, DROP structured_data');
    }
}
