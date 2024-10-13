<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Downloader\Json;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('stock:asset:downloadJsonDividends')]
class StockAssetJsonDividendDownloaderCommand extends Command
{

	public function __construct(private StockAssetJsonDividendDownloader $stockAssetJsonDividendDownloader)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Download stock asset dividend records');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading stock asset dividend records from JSON');
		$this->stockAssetJsonDividendDownloader->downloadDividendRecords();
		$output->writeln('Downloading stock asset dividend records finished');

		return 0;
	}

}
