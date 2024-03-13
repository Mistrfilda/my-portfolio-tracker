<?php

declare(strict_types = 1);

namespace App\Stock\Price\Downloader\Pse;

use App\Stock\Price\StockAssetPriceRecord;
use App\Utils\MonologHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;


#[AsCommand('pse:download')]
class PseDataDownloaderCommand extends Command
{

	public function __construct(
		private readonly PseDataDownloaderFacade $pseDataDownloaderFacade,
		private readonly LoggerInterface $logger
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Download new price data from prague stock exchange');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Downloading new data from prague stock exchange');

		try {
			foreach ($this->pseDataDownloaderFacade->getPriceForAssets() as $newPrice) {
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
		} catch (Throwable $e) {
			$this->logger->critical(MonologHelper::formatMessageFromException($e));
		}

		$output->writeln('Rates successfully downloaded');

		return 0;
	}

}
