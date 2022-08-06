<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220806215729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_asset (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, asset_price_downloader VARCHAR(255) NOT NULL, ticker VARCHAR(255) NOT NULL, exchange VARCHAR(255) NOT NULL, currency VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX type_idx (asset_price_downloader), INDEX exchange_idx (exchange), INDEX ticker_idx (ticker), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_position (id INT AUTO_INCREMENT NOT NULL, stock_asset_id INT DEFAULT NULL, order_pieces_count INT NOT NULL, price_per_piece DOUBLE PRECISION NOT NULL, order_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9EAB0B74A1C4D03 (stock_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_position ADD CONSTRAINT FK_9EAB0B74A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
        $this->addSql('DROP TABLE asset');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_position DROP FOREIGN KEY FK_9EAB0B74A1C4D03');
        $this->addSql('CREATE TABLE asset (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, should_be_updated TINYINT(1) NOT NULL, asset_price_downloader VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, ticker VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, exchange VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, INDEX ticker_idx (ticker), INDEX type_idx (asset_price_downloader), INDEX exchange_idx (exchange), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE stock_asset');
        $this->addSql('DROP TABLE stock_position');
    }
}
