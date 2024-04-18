<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag\Command;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('expense:tag:process')]
class ExpenseTagProcessCommand extends Command
{

	public function __construct(private ExpenseTagFacade $expenseTagFacade)
	{
		parent::__construct();
	}

	public function configure(): void
	{
		parent::configure();
		$this->setDescription('Process all expenses and match tags');
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Processing expenses and tags');

		$this->expenseTagFacade->processExpenses($output);

		$output->writeln('Processing expenses and tags finished');

		return 0;
	}

}
