<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json\Command;

use App\Stock\Price\Downloader\Json\JsonDataFolderService;
use App\Stock\Price\Downloader\Json\JsonDataSourceProviderFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('puppeter:data')]
class JsonDataSourceProviderCommand extends Command
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
		$output->writeln('Generating source data for Puppeter');
		$this->jsonDataSourceProviderFacade->generatePriceSourcesJsonFile($this->jsonDataFolderService->getFolder());
		$this->jsonDataSourceProviderFacade->generateDividendsJsonFile($this->jsonDataFolderService->getFolder());
		$output->writeln('Data for Puppeter generated');
		return 0;
	}

}
