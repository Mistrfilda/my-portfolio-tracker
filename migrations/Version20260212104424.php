<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212104424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_ai_analysis_run (generated_prompt LONGTEXT NOT NULL, raw_response LONGTEXT DEFAULT NULL, includes_portfolio TINYINT NOT NULL, includes_watchlist TINYINT NOT NULL, includes_market_overview TINYINT NOT NULL, market_overview_summary LONGTEXT DEFAULT NULL, market_overview_sentiment VARCHAR(255) DEFAULT NULL, processed_at DATETIME DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_ai_analysis_stock_result (type VARCHAR(255) NOT NULL, positive_news LONGTEXT DEFAULT NULL, negative_news LONGTEXT DEFAULT NULL, interesting_news LONGTEXT DEFAULT NULL, ai_opinion LONGTEXT DEFAULT NULL, action_suggestion VARCHAR(255) DEFAULT NULL, reasoning LONGTEXT DEFAULT NULL, news LONGTEXT DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, stock_ai_analysis_run_id CHAR(36) NOT NULL, stock_asset_id CHAR(36) NOT NULL, INDEX IDX_843866AD6815121E (stock_ai_analysis_run_id), INDEX IDX_843866AD4A1C4D03 (stock_asset_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD CONSTRAINT FK_843866AD6815121E FOREIGN KEY (stock_ai_analysis_run_id) REFERENCES stock_ai_analysis_run (id)');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD CONSTRAINT FK_843866AD4A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP FOREIGN KEY FK_843866AD6815121E');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP FOREIGN KEY FK_843866AD4A1C4D03');
        $this->addSql('DROP TABLE stock_ai_analysis_run');
        $this->addSql('DROP TABLE stock_ai_analysis_stock_result');
    }
}
