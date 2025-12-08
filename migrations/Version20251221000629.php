<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221000629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE crypto_asset (name VARCHAR(255) NOT NULL, ticker VARCHAR(255) NOT NULL, main_conversion_currency VARCHAR(255) NOT NULL, price_downloaded_at DATETIME NOT NULL, id CHAR(36) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, current_asset_price_price DOUBLE PRECISION NOT NULL, current_asset_price_currency VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE crypto_asset_price_record (date DATETIME NOT NULL, currency VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, crypto_asset_id CHAR(36) NOT NULL, INDEX IDX_63519C8835BE9A0E (crypto_asset_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE crypto_closed_position (price_per_piece DOUBLE PRECISION NOT NULL, order_date DATETIME NOT NULL, different_broker_amount TINYINT NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, total_invested_amount_in_broker_currency_price DOUBLE PRECISION NOT NULL, total_invested_amount_in_broker_currency_currency VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE crypto_position (order_pieces_count DOUBLE PRECISION NOT NULL, price_per_piece DOUBLE PRECISION NOT NULL, order_date DATETIME NOT NULL, different_broker_amount TINYINT NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, total_invested_amount_in_broker_currency_price DOUBLE PRECISION NOT NULL, total_invested_amount_in_broker_currency_currency VARCHAR(255) NOT NULL, crypto_asset_id CHAR(36) NOT NULL, app_admin_id CHAR(36) NOT NULL, crypto_closed_position_id CHAR(36) DEFAULT NULL, INDEX IDX_764867BA35BE9A0E (crypto_asset_id), INDEX IDX_764867BA6CABE2BD (app_admin_id), UNIQUE INDEX UNIQ_764867BADE52DFA7 (crypto_closed_position_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crypto_asset_price_record ADD CONSTRAINT FK_63519C8835BE9A0E FOREIGN KEY (crypto_asset_id) REFERENCES crypto_asset (id)');
        $this->addSql('ALTER TABLE crypto_position ADD CONSTRAINT FK_764867BA35BE9A0E FOREIGN KEY (crypto_asset_id) REFERENCES crypto_asset (id)');
        $this->addSql('ALTER TABLE crypto_position ADD CONSTRAINT FK_764867BA6CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
        $this->addSql('ALTER TABLE crypto_position ADD CONSTRAINT FK_764867BADE52DFA7 FOREIGN KEY (crypto_closed_position_id) REFERENCES crypto_closed_position (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crypto_asset_price_record DROP FOREIGN KEY FK_63519C8835BE9A0E');
        $this->addSql('ALTER TABLE crypto_position DROP FOREIGN KEY FK_764867BA35BE9A0E');
        $this->addSql('ALTER TABLE crypto_position DROP FOREIGN KEY FK_764867BA6CABE2BD');
        $this->addSql('ALTER TABLE crypto_position DROP FOREIGN KEY FK_764867BADE52DFA7');
        $this->addSql('DROP TABLE crypto_asset');
        $this->addSql('DROP TABLE crypto_asset_price_record');
        $this->addSql('DROP TABLE crypto_closed_position');
        $this->addSql('DROP TABLE crypto_position');
    }
}
