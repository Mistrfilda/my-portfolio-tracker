<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723121516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_performance_month (period_month DATETIME NOT NULL, period_start_at DATETIME NOT NULL, period_end_at DATETIME NOT NULL, invested_at_start DOUBLE PRECISION NOT NULL, invested_at_end DOUBLE PRECISION NOT NULL, value_at_start DOUBLE PRECISION NOT NULL, value_at_end DOUBLE PRECISION NOT NULL, realized_profit DOUBLE PRECISION NOT NULL, net_dividends DOUBLE PRECISION NOT NULL, cash_at_start DOUBLE PRECISION NOT NULL, cash_at_end DOUBLE PRECISION NOT NULL, external_contribution DOUBLE PRECISION NOT NULL, return_factor DOUBLE PRECISION NOT NULL, id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX portfolio_performance_month_unidx (period_month), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE portfolio_performance_month');
    }
}
