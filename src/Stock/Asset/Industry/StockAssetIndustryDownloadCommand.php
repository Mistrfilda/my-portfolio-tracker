<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('stock:asset:industryData')]
class StockAssetIndustryDownloadCommand extends Command
{

	public function __construct(private StockAssetIndustryDownloadFacade $stockAssetIndustryDownloadFacade)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Parse stock industries data');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading stock industries data');
		$this->stockAssetIndustryDownloadFacade->process();
		$output->writeln('Data downloaded');
		return 0;
	}

}
