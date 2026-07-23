<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('portfolio:statistics:save')]
class PortfolioStatisticSaveCommand extends Command
{

	public function __construct(
		private readonly PortfolioStatisticFacade $portfolioStatisticFacade,
		private readonly PortfolioPerformanceRebuildFacade $portfolioPerformanceRebuildFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Save current portfolio statistics');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Saving current porfolio statistics');
		$record = $this->portfolioStatisticFacade->saveCurrentDashboardValues(false);
		$months = $this->portfolioPerformanceRebuildFacade->rebuild();
		$this->portfolioStatisticFacade->appendPortfolioPerformanceValues($record);
		$output->writeln(sprintf('Rebuilt %d portfolio performance months', $months));
		$output->writeln('Saving of current porfolio statistics finished');

		return 0;
	}

}
