<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\TwelveData;

use App\Stock\Price\StockAssetPriceRecord;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TwelveDataDownloaderCommand extends Command
{

	public function __construct(
		private readonly TwelveDataDownloaderFacade $twelveDataDownloader,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setName('twelve-data:download');
		$this->setDescription('Download new price data from twelve data');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading new data from twelve data');

		foreach ($this->twelveDataDownloader->getPriceForAssets() as $newPrice) {
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

		$output->writeln('Rates successfully downloaded');

		return 0;
	}

}
