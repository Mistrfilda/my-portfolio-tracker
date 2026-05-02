<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502170354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run ADD gemini_processing_status VARCHAR(255) DEFAULT NULL, ADD gemini_processing_queued_at DATETIME DEFAULT NULL, ADD gemini_processing_started_at DATETIME DEFAULT NULL, ADD gemini_processing_finished_at DATETIME DEFAULT NULL, ADD gemini_processing_error LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run DROP gemini_processing_status, DROP gemini_processing_queued_at, DROP gemini_processing_started_at, DROP gemini_processing_finished_at, DROP gemini_processing_error');
    }
}
