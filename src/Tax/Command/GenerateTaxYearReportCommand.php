<?php

declare(strict_types = 1);

namespace App\Tax\Command;

use App\Admin\AppAdminRepository;
use App\Tax\TaxYearReportFacade;
use App\Utils\TypeValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'tax:year-report:generate',
	description: 'Generates and stores a tax and insurance report for the given year',
)]
class GenerateTaxYearReportCommand extends Command
{

	public function __construct(
		private TaxYearReportFacade $taxYearReportFacade,
		private AppAdminRepository $appAdminRepository,
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('year', InputArgument::REQUIRED, 'Tax year to generate (e.g. 2025 or 2026)');
		$this->addArgument('username', InputArgument::OPTIONAL, 'Optional AppAdmin username (defaults to all users)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$year = (int) TypeValidator::validateString($input->getArgument('year'));
		$username = TypeValidator::validateNullableString($input->getArgument('username'));

		$admins = $username !== null
			? [$this->appAdminRepository->findByUsername($username)]
			: $this->appAdminRepository->findAll();

		foreach ($admins as $admin) {
			$report = $this->taxYearReportFacade->generateSync($year, $admin);
			$output->writeln(sprintf(
				'Generated tax report %s for %s (year %d, status: %s)',
				$report->getId()->toString(),
				$admin->getUsername(),
				$year,
				$report->getStatus()->value,
			));
		}

		return Command::SUCCESS;
	}

}
