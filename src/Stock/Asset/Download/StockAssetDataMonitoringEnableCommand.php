<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('stock:monitoring:enable', 'Enable stock freshness monitoring after the initial batch downloads')]
class StockAssetDataMonitoringEnableCommand extends Command
{

	public function __construct(private StockAssetDataMonitoring $monitoring)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->monitoring->enable();
		$output->writeln('Stock freshness monitoring enabled.');
		return self::SUCCESS;
	}

}
