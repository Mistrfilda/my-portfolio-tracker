<?php

declare(strict_types = 1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124160000 extends AbstractMigration
{

	public function getDescription(): string
	{
		return 'Add tax year profile, donations, reports and tax payment role classification on expense tags and bank expenses';
	}

	public function isTransactional(): bool
	{
		// MariaDB schema changes commit implicitly.
		return false;
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE expense_tag ADD tax_payment_role VARCHAR(255) DEFAULT NULL');
		$this->addSql('ALTER TABLE bank_expense ADD tax_payment_role_override VARCHAR(255) DEFAULT NULL, ADD tax_year_override INT DEFAULT NULL');

		$this->addSql('CREATE TABLE tax_year_profile (year INT NOT NULL, expense_flat_rate VARCHAR(255) NOT NULL, active_months_health INT NOT NULL, active_months_social INT NOT NULL, dividend_mode VARCHAR(255) NOT NULL, include_previous_december_work_income TINYINT NOT NULL, manual_business_income_adjustment_czk INT NOT NULL, manual_business_income_adjustment_note VARCHAR(255) DEFAULT NULL, custom_forecast_annual_income_czk INT DEFAULT NULL, use_custom_forecast TINYINT NOT NULL, reserve_target_date VARCHAR(10) DEFAULT NULL, expected_monthly_health_advance_czk INT DEFAULT NULL, expected_monthly_social_advance_czk INT DEFAULT NULL, expected_remaining_income_tax_advances_czk INT DEFAULT NULL, confirmed_income_tax_advances_czk INT DEFAULT NULL, confirmed_health_advances_czk INT DEFAULT NULL, confirmed_social_advances_czk INT DEFAULT NULL, pension_and_life_deduction_czk INT NOT NULL, mortgage_interest_deduction_czk INT NOT NULL, other_deductions_czk INT NOT NULL, other_tax_credits_czk INT NOT NULL, is_closed TINYINT NOT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, app_admin_id CHAR(36) NOT NULL, UNIQUE INDEX tax_year_profile_owner_year_unique (app_admin_id, year), INDEX IDX_3C8F4EE6CABE2BD (app_admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE tax_year_donation (recipient_name VARCHAR(255) NOT NULL, amount_czk INT NOT NULL, donated_at DATE DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, app_admin_id CHAR(36) NOT NULL, tax_year_profile_id CHAR(36) NOT NULL, INDEX tax_year_donation_profile_idx (tax_year_profile_id), INDEX IDX_E66ACC106CABE2BD (app_admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
		$this->addSql('CREATE TABLE tax_year_report (year INT NOT NULL, status VARCHAR(255) NOT NULL, as_of_date DATETIME NOT NULL, snapshot_json LONGTEXT DEFAULT NULL, processing_started_at DATETIME DEFAULT NULL, processing_finished_at DATETIME DEFAULT NULL, processing_error LONGTEXT DEFAULT NULL, id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, app_admin_id CHAR(36) NOT NULL, INDEX tax_year_report_owner_year_created_idx (app_admin_id, year, created_at), INDEX IDX_538557D26CABE2BD (app_admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');

		$this->addSql('ALTER TABLE tax_year_profile ADD CONSTRAINT FK_3C8F4EE6CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('ALTER TABLE tax_year_donation ADD CONSTRAINT FK_E66ACC106CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
		$this->addSql('ALTER TABLE tax_year_donation ADD CONSTRAINT FK_E66ACC10205256A2 FOREIGN KEY (tax_year_profile_id) REFERENCES tax_year_profile (id) ON DELETE CASCADE');
		$this->addSql('ALTER TABLE tax_year_report ADD CONSTRAINT FK_538557D26CABE2BD FOREIGN KEY (app_admin_id) REFERENCES app_admin (id)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE tax_year_donation DROP FOREIGN KEY FK_E66ACC106CABE2BD');
		$this->addSql('ALTER TABLE tax_year_donation DROP FOREIGN KEY FK_E66ACC10205256A2');
		$this->addSql('ALTER TABLE tax_year_profile DROP FOREIGN KEY FK_3C8F4EE6CABE2BD');
		$this->addSql('ALTER TABLE tax_year_report DROP FOREIGN KEY FK_538557D26CABE2BD');

		$this->addSql('DROP TABLE tax_year_donation');
		$this->addSql('DROP TABLE tax_year_report');
		$this->addSql('DROP TABLE tax_year_profile');

		$this->addSql('ALTER TABLE bank_expense DROP tax_payment_role_override, DROP tax_year_override');
		$this->addSql('ALTER TABLE expense_tag DROP tax_payment_role');
	}

}
