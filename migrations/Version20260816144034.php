<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816144034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_ai_investment_plan (requested_amount_to_portfolio_percent DOUBLE PRECISION NOT NULL, requested_amount_to_projected_portfolio_percent DOUBLE PRECISION NOT NULL, generated_prompt LONGTEXT NOT NULL, schema_version INT DEFAULT 1 NOT NULL, input_snapshot JSON NOT NULL, raw_response LONGTEXT DEFAULT NULL, structured_data JSON DEFAULT NULL, processing_source VARCHAR(255) DEFAULT NULL, processed_at DATETIME DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, requested_amount_price DOUBLE PRECISION NOT NULL, requested_amount_currency VARCHAR(255) NOT NULL, requested_amount_czk_price DOUBLE PRECISION NOT NULL, requested_amount_czk_currency VARCHAR(255) NOT NULL, portfolio_value_price DOUBLE PRECISION NOT NULL, portfolio_value_currency VARCHAR(255) NOT NULL, reference_analysis_run_id CHAR(36) NOT NULL, INDEX IDX_F9D10CFAEAE6816E (reference_analysis_run_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_ai_investment_plan ADD CONSTRAINT FK_F9D10CFAEAE6816E FOREIGN KEY (reference_analysis_run_id) REFERENCES stock_ai_analysis_run (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_investment_plan DROP FOREIGN KEY FK_F9D10CFAEAE6816E');
        $this->addSql('DROP TABLE stock_ai_investment_plan');
    }
}
