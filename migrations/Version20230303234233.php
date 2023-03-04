<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230303234233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic CHANGE portfolio_statistic_record_id portfolio_statistic_record_id INT NOT NULL');
        $this->addSql('ALTER TABLE stock_asset_dividend CHANGE payment_date payment_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic CHANGE portfolio_statistic_record_id portfolio_statistic_record_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_asset_dividend CHANGE payment_date payment_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
