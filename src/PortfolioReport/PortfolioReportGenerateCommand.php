<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use InvalidArgumentException;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'portfolio-report:generate',
	description: 'Queue generation of a periodic portfolio report',
)]
class PortfolioReportGenerateCommand extends Command
{

	public function __construct(private readonly PortfolioReportFacade $portfolioReportFacade)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->addArgument('periodType', InputArgument::REQUIRED, 'daily|weekly|monthly|bimonthly');
		$this->addOption(
			'date',
			null,
			InputOption::VALUE_REQUIRED,
			'Reference date in Y-m-d format',
			(new ImmutableDateTime())->format('Y-m-d'),
		);
		$this->addOption('force', null, InputOption::VALUE_NONE, 'Force regenerate existing report for the period');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$periodTypeRaw = $input->getArgument('periodType');
		$dateRaw = $input->getOption('date');

		if (is_string($periodTypeRaw) === false || is_string($dateRaw) === false) {
			throw new InvalidArgumentException('Period type and date must be strings.');
		}

		$periodType = PortfolioReportPeriodTypeEnum::tryFrom($periodTypeRaw);
		if ($periodType === null) {
			throw new InvalidArgumentException(sprintf('Unknown period type "%s".', $periodTypeRaw));
		}

		$referenceDate = new ImmutableDateTime(sprintf('%s 12:00:00', $dateRaw));
		$portfolioReport = $this->portfolioReportFacade->requestGenerate(
			$periodType,
			$referenceDate,
			(bool) $input->getOption('force'),
		);

		$output->writeln(sprintf(
			'Queued portfolio report %s for %s - %s (%s).',
			$portfolioReport->getId()->toString(),
			$portfolioReport->getDateFrom()->format('Y-m-d'),
			$portfolioReport->getDateTo()->format('Y-m-d'),
			$portfolioReport->getStatus()->value,
		));

		return Command::SUCCESS;
	}

}
