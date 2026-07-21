<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721113054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run ADD analysis_schema_version INT DEFAULT 1 NOT NULL, ADD input_snapshot JSON DEFAULT NULL, ADD structured_data JSON DEFAULT NULL, ADD processing_source VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD structured_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run DROP analysis_schema_version, DROP input_snapshot, DROP structured_data, DROP processing_source');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP structured_data');
    }
}
