<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231225192839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_closed_position (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', price_per_piece DOUBLE PRECISION NOT NULL, order_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', different_broker_amount TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', total_invested_amount_in_broker_currency_price DOUBLE PRECISION NOT NULL, total_invested_amount_in_broker_currency_currency VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_position ADD stock_closed_position_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE stock_position ADD CONSTRAINT FK_9EAB0B7A98238D4 FOREIGN KEY (stock_closed_position_id) REFERENCES stock_closed_position (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9EAB0B7A98238D4 ON stock_position (stock_closed_position_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_position DROP FOREIGN KEY FK_9EAB0B7A98238D4');
        $this->addSql('DROP TABLE stock_closed_position');
        $this->addSql('DROP INDEX UNIQ_9EAB0B7A98238D4 ON stock_position');
        $this->addSql('ALTER TABLE stock_position DROP stock_closed_position_id');
    }
}
