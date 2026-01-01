<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101173845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_asset_industry ADD forward_peratio DOUBLE PRECISION DEFAULT NULL, ADD peg_ratio DOUBLE PRECISION DEFAULT NULL, ADD price_to_sales DOUBLE PRECISION DEFAULT NULL, ADD price_to_book DOUBLE PRECISION DEFAULT NULL, ADD price_to_cash_flow DOUBLE PRECISION DEFAULT NULL, ADD price_to_free_cash_flow DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_asset_industry DROP forward_peratio, DROP peg_ratio, DROP price_to_sales, DROP price_to_book, DROP price_to_cash_flow, DROP price_to_free_cash_flow');
    }
}
