<?php

declare(strict_types = 1);

namespace App\Monitoring;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('monitoring:push-monitors')]
class MonitoringPushMonitorsCommand extends Command
{

	public function __construct(private MonitoringFacade $monitoringFacade)
	{
		parent::__construct();
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Processing push monitors');

		$this->monitoringFacade->processUptimeMonitors();
		$output->writeln('Done');
		return 0;
	}

}
