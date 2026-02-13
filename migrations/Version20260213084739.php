<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213084739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD stock_ticker VARCHAR(255) DEFAULT NULL, ADD stock_name VARCHAR(255) DEFAULT NULL, ADD business_summary LONGTEXT DEFAULT NULL, ADD moat_analysis LONGTEXT DEFAULT NULL, ADD financial_health LONGTEXT DEFAULT NULL, ADD growth_catalysts LONGTEXT DEFAULT NULL, ADD valuation_assessment LONGTEXT DEFAULT NULL, ADD conclusion LONGTEXT DEFAULT NULL, ADD risks LONGTEXT DEFAULT NULL, CHANGE stock_asset_id stock_asset_id CHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP stock_ticker, DROP stock_name, DROP business_summary, DROP moat_analysis, DROP financial_health, DROP growth_catalysts, DROP valuation_assessment, DROP conclusion, DROP risks, CHANGE stock_asset_id stock_asset_id CHAR(36) NOT NULL');
    }
}
