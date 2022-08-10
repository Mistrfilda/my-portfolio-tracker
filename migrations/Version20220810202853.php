<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220810202853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9EBF46AD7EC30896 ON stock_asset (ticker)');
        $this->addSql('ALTER TABLE stock_position ADD total_invested_amount_in_broker_currency_price DOUBLE PRECISION NOT NULL, ADD total_invested_amount_in_broker_currency_currency VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_9EBF46AD7EC30896 ON stock_asset');
        $this->addSql('ALTER TABLE stock_position DROP total_invested_amount_in_broker_currency_price, DROP total_invested_amount_in_broker_currency_currency');
    }
}
