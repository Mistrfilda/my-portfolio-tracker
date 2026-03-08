<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260308154138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run ADD portfolio_performance7_days_summary LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result ADD performance7_days_comment LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ai_analysis_run DROP portfolio_performance7_days_summary');
        $this->addSql('ALTER TABLE stock_ai_analysis_stock_result DROP performance7_days_comment');
    }
}
