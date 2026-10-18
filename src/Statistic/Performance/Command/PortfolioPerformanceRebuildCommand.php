<?php

declare(strict_types = 1);

namespace App\Statistic\Performance\Command;

use App\Statistic\PortfolioStatisticBatchFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('portfolio:performance:rebuild')]
class PortfolioPerformanceRebuildCommand extends Command
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
		$this->setDescription('Rebuild monthly portfolio performance for every account');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		if (!$this->portfolioStatisticBatchFacade->rebuildForAll()) {
			$output->writeln(
				'<error>Portfolio performance failed for one or more accounts. See the application log.</error>',
			);
			return self::FAILURE;
		}

		$output->writeln('Portfolio performance rebuilt for all accounts');
		return self::SUCCESS;
	}

}
