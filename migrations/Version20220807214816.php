<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220807214816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_admin (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, force_new_password TINYINT(1) NOT NULL, sys_admin TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_5EDD80BBF85E0677 (username), UNIQUE INDEX UNIQ_5EDD80BBE7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE currency_conversion (id INT AUTO_INCREMENT NOT NULL, from_currency VARCHAR(255) NOT NULL, to_currency VARCHAR(255) NOT NULL, current_price DOUBLE PRECISION NOT NULL, source VARCHAR(255) NOT NULL, for_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX from_to_currency_idx (from_currency, to_currency), UNIQUE INDEX from_to_date_unidx (from_currency, to_currency, for_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_asset (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, asset_price_downloader VARCHAR(255) NOT NULL, ticker VARCHAR(255) NOT NULL, exchange VARCHAR(255) NOT NULL, currency VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX type_idx (asset_price_downloader), INDEX exchange_idx (exchange), INDEX ticker_idx (ticker), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_position (id INT AUTO_INCREMENT NOT NULL, stock_asset_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', order_pieces_count INT NOT NULL, price_per_piece DOUBLE PRECISION NOT NULL, order_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9EAB0B74A1C4D03 (stock_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_position ADD CONSTRAINT FK_9EAB0B74A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_position DROP FOREIGN KEY FK_9EAB0B74A1C4D03');
        $this->addSql('DROP TABLE app_admin');
        $this->addSql('DROP TABLE currency_conversion');
        $this->addSql('DROP TABLE stock_asset');
        $this->addSql('DROP TABLE stock_position');
    }
}
