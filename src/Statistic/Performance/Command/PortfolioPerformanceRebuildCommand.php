<?php

declare(strict_types = 1);

namespace App\Statistic\Performance\Command;

use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('portfolio:performance:rebuild')]
class PortfolioPerformanceRebuildCommand extends Command
{

	public function __construct(
		private readonly PortfolioPerformanceRebuildFacade $portfolioPerformanceRebuildFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Rebuild monthly portfolio performance cache');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$count = $this->portfolioPerformanceRebuildFacade->rebuild();
		$output->writeln(sprintf('Rebuilt %d portfolio performance months', $count));

		return self::SUCCESS;
	}

}
