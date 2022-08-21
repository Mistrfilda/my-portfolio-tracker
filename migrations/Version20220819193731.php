<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220819193731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portu_asset (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, currency VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE portu_asset_price_record (id INT AUTO_INCREMENT NOT NULL, portu_position_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', currency VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', current_value_price DOUBLE PRECISION NOT NULL, current_value_currency VARCHAR(255) NOT NULL, total_invested_amount_price DOUBLE PRECISION NOT NULL, total_invested_amount_currency VARCHAR(255) NOT NULL, INDEX IDX_BBB1C1F318DE1750 (portu_position_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE portu_position (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', portu_asset_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', app_admin_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', start_investment_price DOUBLE PRECISION NOT NULL, start_investment_currency VARCHAR(255) NOT NULL, monthly_increase_price DOUBLE PRECISION NOT NULL, monthly_increase_currency VARCHAR(255) NOT NULL, current_value_price DOUBLE PRECISION NOT NULL, current_value_currency VARCHAR(255) NOT NULL, total_invested_to_this_date_price DOUBLE PRECISION NOT NULL, total_invested_to_this_date_currency VARCHAR(255) NOT NULL, INDEX IDX_A58001449AEFDA0 (portu_asset_id), INDEX IDX_A5800146CABE2BD (app_admin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portu_asset_price_record ADD CONSTRAINT FK_BBB1C1F318DE1750 FOREIGN KEY (portu_position_id) REFERENCES portu_position (id)');
        $this->addSql('ALTER TABLE portu_position ADD CONSTRAINT FK_A58001449AEFDA0 FOREIGN KEY (portu_asset_id) REFERENCES portu_asset (id)');
        $this->addSql('ALTER TABLE portu_position ADD CONSTRAINT FK_A5800146CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portu_asset_price_record DROP FOREIGN KEY FK_BBB1C1F318DE1750');
        $this->addSql('ALTER TABLE portu_position DROP FOREIGN KEY FK_A58001449AEFDA0');
        $this->addSql('ALTER TABLE portu_position DROP FOREIGN KEY FK_A5800146CABE2BD');
        $this->addSql('DROP TABLE portu_asset');
        $this->addSql('DROP TABLE portu_asset_price_record');
        $this->addSql('DROP TABLE portu_position');
    }
}
