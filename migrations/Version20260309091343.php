<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309091343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run ADD portfolio_prompt_type VARCHAR(255) DEFAULT NULL, ADD daily_brief_summary LONGTEXT DEFAULT NULL, ADD daily_brief_market_pulse LONGTEXT DEFAULT NULL, ADD daily_brief_portfolio_impact_summary LONGTEXT DEFAULT NULL, ADD daily_brief_watchlist_summary LONGTEXT DEFAULT NULL, ADD daily_brief_important_alerts LONGTEXT DEFAULT NULL, ADD daily_brief_next_days_checklist LONGTEXT DEFAULT NULL, ADD daily_brief_action_needed VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD performance1_day_comment LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run DROP portfolio_prompt_type, DROP daily_brief_summary, DROP daily_brief_market_pulse, DROP daily_brief_portfolio_impact_summary, DROP daily_brief_watchlist_summary, DROP daily_brief_important_alerts, DROP daily_brief_next_days_checklist, DROP daily_brief_action_needed');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP performance1_day_comment');
    }
}
