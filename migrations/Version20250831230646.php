<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250831230646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_valuation_data (valuation_type VARCHAR(255) NOT NULL, type_group VARCHAR(255) NOT NULL, type_value_type VARCHAR(255) NOT NULL, last_active TINYINT(1) NOT NULL, parsed_at DATETIME NOT NULL, string_value VARCHAR(255) DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, id CHAR(36) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, stock_asset_id CHAR(36) NOT NULL, INDEX IDX_308784D84A1C4D03 (stock_asset_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_valuation_data ADD CONSTRAINT FK_308784D84A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
        $this->addSql('ALTER TABLE stock_asset ADD should_download_valuation TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_valuation_data DROP FOREIGN KEY FK_308784D84A1C4D03');
        $this->addSql('DROP TABLE stock_valuation_data');
        $this->addSql('ALTER TABLE stock_asset DROP should_download_valuation');
    }
}
