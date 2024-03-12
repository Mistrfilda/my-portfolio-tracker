<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240311232947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic ADD type VARCHAR(255) DEFAULT NULL');

		$this->addSql('UPDATE portfolio_statistic set type = "total_invested_in_czk" where label = "Aktuální zainvestováno ve všech assetech"');
	    $this->addSql('UPDATE portfolio_statistic set type = "total_value_in_czk" where label = "Aktuální hodnota ve všech assetech"');
	    $this->addSql('UPDATE portfolio_statistic set type = "total_profit" where label = "Celkový zisk/ztráta ve všech assetech" and value LIKE "%CZK%"');
	    $this->addSql('UPDATE portfolio_statistic set type = "total_profit_percentage" where label = "Celkový zisk/ztráta ve všech assetech"');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_statistic DROP type');
    }
}
