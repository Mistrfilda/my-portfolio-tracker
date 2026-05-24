<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260524165300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_ai_analysis_follow_up_question (question LONGTEXT NOT NULL, generated_prompt LONGTEXT NOT NULL, raw_response LONGTEXT DEFAULT NULL, gemini_processing_status VARCHAR(255) DEFAULT NULL, gemini_processing_queued_at DATETIME DEFAULT NULL, gemini_processing_started_at DATETIME DEFAULT NULL, gemini_processing_finished_at DATETIME DEFAULT NULL, gemini_processing_error LONGTEXT DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stock_ai_analysis_run_id CHAR(36) NOT NULL, INDEX IDX_ACB4E8106815121E (stock_ai_analysis_run_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_ai_analysis_follow_up_question ADD CONSTRAINT FK_ACB4E8106815121E FOREIGN KEY (stock_ai_analysis_run_id) REFERENCES stock_ai_analysis_run (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_follow_up_question DROP FOREIGN KEY FK_ACB4E8106815121E');
        $this->addSql('DROP TABLE stock_ai_analysis_follow_up_question');
    }
}