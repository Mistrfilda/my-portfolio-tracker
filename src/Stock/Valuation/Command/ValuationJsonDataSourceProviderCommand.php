<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Command;

use App\Stock\Price\Downloader\Json\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('puppeter:valulation:data')]
class ValuationJsonDataSourceProviderCommand extends Command
{

	public function __construct(
		private JsonDataFolderService $jsonDataFolderService,
		private JsonDataSourceProviderFacade $jsonDataSourceProviderFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Prepare source data for Puppeter');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Generating source valulation data for Puppeter');
		$this->jsonDataSourceProviderFacade->generateStockValuationJsonFile($this->jsonDataFolderService->getFolder());
		$output->writeln('Data for Puppeter generated');
		return 0;
	}

}
