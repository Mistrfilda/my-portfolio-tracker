<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260714190108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_period_statistic (requested_start_at DATETIME NOT NULL, requested_end_at DATETIME NOT NULL, effective_start_at DATETIME DEFAULT NULL, effective_end_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, summary_json LONGTEXT DEFAULT NULL, asset_section_json LONGTEXT DEFAULT NULL, dividend_section_json LONGTEXT DEFAULT NULL, chart_section_json LONGTEXT DEFAULT NULL, processing_started_at DATETIME DEFAULT NULL, processing_finished_at DATETIME DEFAULT NULL, processing_error LONGTEXT DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX portfolio_period_statistic_created_at_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE portfolio_period_statistic');
    }
}
