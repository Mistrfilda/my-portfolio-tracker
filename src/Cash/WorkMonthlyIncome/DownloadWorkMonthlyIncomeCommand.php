<?php

declare(strict_types = 1);

namespace App\Cash\WorkMonthlyIncome;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('workMonthlyIncome:download')]
class DownloadWorkMonthlyIncomeCommand extends Command
{

	public function __construct(private WorkMonthlyIncomeFacade $workMonthlyIncomeFacade)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Download current work monthly income');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading current work monthly income');
		$this->workMonthlyIncomeFacade->download();
		$output->writeln('Done.');
		return 0;
	}

}
