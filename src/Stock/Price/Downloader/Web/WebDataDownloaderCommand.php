<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Web;

use App\Stock\Price\StockAssetPriceRecord;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebDataDownloaderCommand extends Command
{

	public function __construct(
		private readonly WebDataDownloaderFacade $webDataDownloaderFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setName('web:stock:download');
		$this->setDescription('Download new price data from web');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading new data from prague stock exchange');

		foreach ($this->webDataDownloaderFacade->getPriceForAssets() as $newPrice) {
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
