<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413131400 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Add async portfolio periodic reports with performance, dividends and goal progress.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('CREATE TABLE portfolio_report_asset_performance (ranking_type VARCHAR(255) NOT NULL, direction VARCHAR(255) NOT NULL, ticker VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, base_currency VARCHAR(255) NOT NULL, price_start_in_base_currency DOUBLE PRECISION NOT NULL, price_end_in_base_currency DOUBLE PRECISION NOT NULL, price_start_czk DOUBLE PRECISION NOT NULL, price_end_czk DOUBLE PRECISION NOT NULL, price_absolute_change DOUBLE PRECISION NOT NULL, price_percentage_change DOUBLE PRECISION NOT NULL, position_value_start_czk DOUBLE PRECISION NOT NULL, position_value_end_czk DOUBLE PRECISION NOT NULL, position_absolute_change_czk DOUBLE PRECISION NOT NULL, contribution_to_portfolio_percentage DOUBLE PRECISION NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, portfolio_report_id CHAR(36) NOT NULL, INDEX IDX_E26F2D32B57A35EC (portfolio_report_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE portfolio_report (period_type VARCHAR(255) NOT NULL, date_from DATETIME NOT NULL, date_to DATETIME NOT NULL, status VARCHAR(255) NOT NULL, portfolio_value_start_czk DOUBLE PRECISION NOT NULL, portfolio_value_end_czk DOUBLE PRECISION NOT NULL, portfolio_value_diff_czk DOUBLE PRECISION NOT NULL, portfolio_value_diff_percentage DOUBLE PRECISION NOT NULL, invested_amount_start_czk DOUBLE PRECISION NOT NULL, invested_amount_end_czk DOUBLE PRECISION NOT NULL, invested_amount_diff_czk DOUBLE PRECISION NOT NULL, dividends_total_czk DOUBLE PRECISION NOT NULL, goals_progress_summary LONGTEXT DEFAULT NULL, summary_text LONGTEXT DEFAULT NULL, ai_prompt LONGTEXT DEFAULT NULL, ai_response_raw LONGTEXT DEFAULT NULL, ai_summary LONGTEXT DEFAULT NULL, processing_started_at DATETIME DEFAULT NULL, generated_at DATETIME DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, snapshot LONGTEXT DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, force_regenerated_from_id CHAR(36) DEFAULT NULL, INDEX IDX_C2C49AB1A5978971 (force_regenerated_from_id), UNIQUE INDEX portfolio_report_period_unique (period_type, date_from, date_to), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE portfolio_report_dividend (ticker VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, payment_date DATETIME NOT NULL, amount_in_source_currency DOUBLE PRECISION NOT NULL, source_currency VARCHAR(255) NOT NULL, amount_czk DOUBLE PRECISION NOT NULL, net_amount DOUBLE PRECISION DEFAULT NULL, tax_percentage DOUBLE PRECISION DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, portfolio_report_id CHAR(36) NOT NULL, INDEX IDX_86E034A9B57A35EC (portfolio_report_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE portfolio_report_goal_progress (goal_type VARCHAR(255) NOT NULL, goal_start_value DOUBLE PRECISION NOT NULL, goal_end_value DOUBLE PRECISION NOT NULL, goal_target_value DOUBLE PRECISION NOT NULL, completion_percentage_start DOUBLE PRECISION NOT NULL, completion_percentage_end DOUBLE PRECISION NOT NULL, completion_percentage_diff DOUBLE PRECISION NOT NULL, summary LONGTEXT DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, portfolio_report_id CHAR(36) NOT NULL, portfolio_goal_id CHAR(36) DEFAULT NULL, INDEX IDX_8D4FE48B57A35EC (portfolio_report_id), INDEX IDX_8D4FE4830C83530 (portfolio_goal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('ALTER TABLE portfolio_report_asset_performance ADD CONSTRAINT FK_E26F2D32B57A35EC FOREIGN KEY (portfolio_report_id) REFERENCES portfolio_report (id)');
		$this->addSql('ALTER TABLE portfolio_report ADD CONSTRAINT FK_C2C49AB1A5978971 FOREIGN KEY (force_regenerated_from_id) REFERENCES portfolio_report (id)');
		$this->addSql('ALTER TABLE portfolio_report_dividend ADD CONSTRAINT FK_86E034A9B57A35EC FOREIGN KEY (portfolio_report_id) REFERENCES portfolio_report (id)');
		$this->addSql('ALTER TABLE portfolio_report_goal_progress ADD CONSTRAINT FK_8D4FE48B57A35EC FOREIGN KEY (portfolio_report_id) REFERENCES portfolio_report (id)');
		$this->addSql('ALTER TABLE portfolio_report_goal_progress ADD CONSTRAINT FK_8D4FE4830C83530 FOREIGN KEY (portfolio_goal_id) REFERENCES portfolio_goal (id)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE portfolio_report_asset_performance DROP FOREIGN KEY FK_E26F2D32B57A35EC');
		$this->addSql('ALTER TABLE portfolio_report DROP FOREIGN KEY FK_C2C49AB1A5978971');
		$this->addSql('ALTER TABLE portfolio_report_dividend DROP FOREIGN KEY FK_86E034A9B57A35EC');
		$this->addSql('ALTER TABLE portfolio_report_goal_progress DROP FOREIGN KEY FK_8D4FE48B57A35EC');
		$this->addSql('ALTER TABLE portfolio_report_goal_progress DROP FOREIGN KEY FK_8D4FE4830C83530');
		$this->addSql('DROP TABLE portfolio_report_asset_performance');
		$this->addSql('DROP TABLE portfolio_report');
		$this->addSql('DROP TABLE portfolio_report_dividend');
		$this->addSql('DROP TABLE portfolio_report_goal_progress');
	}
}
