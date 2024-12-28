<?php

declare(strict_types = 1);

namespace App\Asset\Trend;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('asset:trend:process')]
class AssetTrendCommand extends Command
{

	public function __construct(private AssetTrendFacade $assetTrendFacade)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Process trends for specified number of days and thresh hold');
		$this->addArgument('days', null, 'Number of days to process');
		$this->addArgument('threshold', null, 'Threshold to process');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$days = $input->getArgument('days');
		assert(is_numeric($days));
		$threshold = $input->getArgument('threshold');
		assert(is_numeric($threshold));

		$output->writeln(sprintf('Processing trends for %d days and threshold %d', $days, $threshold));
		$this->assetTrendFacade->processTrends((int) $days, (int) $threshold);
		$output->writeln('Done.');
		return 0;
	}

}
