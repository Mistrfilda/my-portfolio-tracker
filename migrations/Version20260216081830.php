<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216081830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run ADD stock_asset_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_ai_analysis_run ADD CONSTRAINT FK_37A612614A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
        $this->addSql('CREATE INDEX IDX_37A612614A1C4D03 ON stock_ai_analysis_run (stock_asset_id)');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD fair_price DOUBLE PRECISION DEFAULT NULL, ADD fair_price_currency VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run DROP FOREIGN KEY FK_37A612614A1C4D03');
        $this->addSql('DROP INDEX IDX_37A612614A1C4D03 ON stock_ai_analysis_run');
        $this->addSql('ALTER TABLE stock_ai_analysis_run DROP stock_asset_id');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP fair_price, DROP fair_price_currency');
    }
}
