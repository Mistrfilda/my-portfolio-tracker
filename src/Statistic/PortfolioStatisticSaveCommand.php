<?php

declare(strict_types = 1);

namespace App\Statistic;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('portfolio:statistics:save')]
class PortfolioStatisticSaveCommand extends Command
{

	public function __construct(
		private readonly PortfolioStatisticFacade $portfolioStatisticFacade,
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
		$this->portfolioStatisticFacade->saveCurrentDashboardValues();
		$output->writeln('Saving of current porfolio statistics finished');

		return 0;
	}

}
