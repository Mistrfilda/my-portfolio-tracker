<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220810214408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_asset_price_record (id INT AUTO_INCREMENT NOT NULL, stock_asset_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', currency VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, asset_price_downloader VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_14817BFB4A1C4D03 (stock_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_asset_price_record ADD CONSTRAINT FK_14817BFB4A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
        $this->addSql('ALTER TABLE stock_asset ADD current_asset_price_price DOUBLE PRECISION NOT NULL, ADD current_asset_price_currency VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE stock_asset_price_record');
        $this->addSql('ALTER TABLE stock_asset DROP current_asset_price_price, DROP current_asset_price_currency');
    }
}
