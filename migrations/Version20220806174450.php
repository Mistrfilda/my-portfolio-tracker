<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220806174450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset ADD ticker VARCHAR(255) DEFAULT NULL, ADD exchange VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX exchange_idx ON asset (exchange)');
        $this->addSql('CREATE INDEX ticker_idx ON asset (ticker)');
        $this->addSql('ALTER TABLE currency_conversion ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX exchange_idx ON asset');
        $this->addSql('DROP INDEX ticker_idx ON asset');
        $this->addSql('ALTER TABLE asset DROP ticker, DROP exchange');
        $this->addSql('ALTER TABLE currency_conversion DROP updated_at');
    }
}
