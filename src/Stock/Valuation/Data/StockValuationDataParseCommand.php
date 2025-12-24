<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('stock:asset:valuation:data')]
class StockValuationDataParseCommand extends Command
{

	public function __construct(
		private StockValuationDataFacade $stockValuationDataFacade,
	)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Parse valuation data from Puppeter');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Parsing data from Puppeter');
		$this->stockValuationDataFacade->processKeyStatistics();
		$this->stockValuationDataFacade->processAnalystInsights();
		$output->writeln('Data from Puppeter processed');
		return 0;
	}

}
