<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Json\Command;

use App\Stock\Price\Downloader\Json\JsonDataDownloaderFacade;
use App\Stock\Price\StockAssetPriceRecord;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('puppeter:import:prices')]
class JsonDataDownloaderCommand extends Command
{

	public function __construct(
		private JsonDataDownloaderFacade $jsonDataDownloaderFacade,
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
		$output->writeln('Processing stock prices from puppeter');
		foreach ($this->jsonDataDownloaderFacade->getPriceForAssets() as $newPrice) {
			assert($newPrice instanceof StockAssetPriceRecord);
			$output->writeln(
				sprintf(
					'<info>Downloaded new price for stock %s - current price: %s %s</info>',
					$newPrice->getStockAsset()->getTicker(),
					$newPrice->getPrice(),
					$newPrice->getCurrency()->format(),
				),
			);
		}

		$output->writeln('Data from Puppeter processed');
		return 0;
	}

}
