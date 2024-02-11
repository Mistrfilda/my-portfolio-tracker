<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('stock:asset:dividendRecords')]
class StockAssetDividendRecordCommand extends Command
{

	public function __construct(private StockAssetDividendRecordFacade $stockAssetDividendRecordFacade)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Process stock asset dividend records');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Processing stock asset dividend records');
		$this->stockAssetDividendRecordFacade->processAllDividends();
		$output->writeln('Processing of stock asset dividend records finished');

		return 0;
	}

}
