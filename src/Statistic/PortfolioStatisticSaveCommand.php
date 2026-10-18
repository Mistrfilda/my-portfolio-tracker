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
		private readonly PortfolioStatisticBatchFacade $portfolioStatisticBatchFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Save current portfolio statistics for every account');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Saving current portfolio statistics for all accounts');
		if (!$this->portfolioStatisticBatchFacade->saveForAll()) {
			$output->writeln(
				'<error>Portfolio statistics failed for one or more accounts. See the application log.</error>',
			);
			return self::FAILURE;
		}

		$output->writeln('Portfolio statistics saved for all accounts');
		return self::SUCCESS;
	}

}
