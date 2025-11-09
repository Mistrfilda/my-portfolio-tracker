<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251109180727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_asset_dividend_forecast (id CHAR(36) NOT NULL, for_year INT NOT NULL, trend VARCHAR(255) NOT NULL, state VARCHAR(255) NOT NULL, last_recalculated_at DATETIME DEFAULT NULL, default_for_year TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_asset_dividend_forecast_record (id CHAR(36) NOT NULL, currency VARCHAR(255) NOT NULL, dividend_usually_paid_at_months JSON NOT NULL, received_dividend_months JSON NOT NULL, specific_trend VARCHAR(255) DEFAULT NULL, already_received_dividend_per_stock DOUBLE PRECISION NOT NULL, expected_dividend_per_stock DOUBLE PRECISION NOT NULL, original_dividend_used_for_calculation DOUBLE PRECISION NOT NULL, adjusted_dividend_used_for_calculation DOUBLE PRECISION NOT NULL, custom_dividend_used_for_calculation DOUBLE PRECISION DEFAULT NULL, pieces_currently_held INT NOT NULL, created_at DATETIME NOT NULL, stock_asset_dividend_forecast_id CHAR(36) NOT NULL, stock_asset_id CHAR(36) NOT NULL, INDEX IDX_9A2D1BCF4589BA77 (stock_asset_dividend_forecast_id), INDEX IDX_9A2D1BCF4A1C4D03 (stock_asset_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_asset_dividend_forecast_record ADD CONSTRAINT FK_9A2D1BCF4589BA77 FOREIGN KEY (stock_asset_dividend_forecast_id) REFERENCES stock_asset_dividend_forecast (id)');
        $this->addSql('ALTER TABLE stock_asset_dividend_forecast_record ADD CONSTRAINT FK_9A2D1BCF4A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_asset_dividend_forecast_record DROP FOREIGN KEY FK_9A2D1BCF4589BA77');
        $this->addSql('ALTER TABLE stock_asset_dividend_forecast_record DROP FOREIGN KEY FK_9A2D1BCF4A1C4D03');
        $this->addSql('DROP TABLE stock_asset_dividend_forecast');
        $this->addSql('DROP TABLE stock_asset_dividend_forecast_record');
    }
}
