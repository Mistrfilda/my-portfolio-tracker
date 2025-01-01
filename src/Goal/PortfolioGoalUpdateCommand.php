<?php

declare(strict_types = 1);

namespace App\Goal;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'goal:portfolio:update',
	description: 'Update all portfolio goals',
)]
class PortfolioGoalUpdateCommand extends Command
{

	public function __construct(
		private PortfolioGoalUpdateFacade $portfolioGoalUpdateFacade,
	)
	{
		parent::__construct();
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Updating portfolio goals');
		$this->portfolioGoalUpdateFacade->updateAllActive();
		$output->writeln('Done');
		return 0;
	}

}
