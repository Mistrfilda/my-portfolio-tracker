<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230226174643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_asset_dividend (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', stock_asset_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', ex_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', payment_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', declaration_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', currency VARCHAR(255) NOT NULL, amount DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_885AAC24A1C4D03 (stock_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_asset_dividend_record (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', stock_asset_dividend_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', total_pieces_held_at_ex_date INT NOT NULL, total_amount DOUBLE PRECISION NOT NULL, currency VARCHAR(255) NOT NULL, total_amount_in_broker_currency DOUBLE PRECISION DEFAULT NULL, broker_currency VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3DB0D3353D50A4DA (stock_asset_dividend_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_asset_dividend ADD CONSTRAINT FK_885AAC24A1C4D03 FOREIGN KEY (stock_asset_id) REFERENCES stock_asset (id)');
        $this->addSql('ALTER TABLE stock_asset_dividend_record ADD CONSTRAINT FK_3DB0D3353D50A4DA FOREIGN KEY (stock_asset_dividend_id) REFERENCES stock_asset_dividend (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_asset_dividend DROP FOREIGN KEY FK_885AAC24A1C4D03');
        $this->addSql('ALTER TABLE stock_asset_dividend_record DROP FOREIGN KEY FK_3DB0D3353D50A4DA');
        $this->addSql('DROP TABLE stock_asset_dividend');
        $this->addSql('DROP TABLE stock_asset_dividend_record');
    }
}
