<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Download;

use App\Utils\TypeValidator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('stock:asset:download-data', 'Download enabled data for one stock asset')]
class StockAssetDataDownloadCommand extends Command
{

	public function __construct(private StockAssetDataDownloadFacade $downloader)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('id', InputArgument::REQUIRED, 'Stock asset UUID');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->downloader->download(Uuid::fromString(TypeValidator::validateString($input->getArgument('id'))));
		$output->writeln('Stock data downloaded.');
		return self::SUCCESS;
	}

}
