<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('rabbitmq:declare-queues')]
class RabbitMQDeclareQueuesCommand extends Command
{

	public function __construct(private RabbitMQQueueDeclarator $queueDeclarator)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Declaring RabbitMQ queues');
		$this->queueDeclarator->declareQueues();
		$output->writeln('Done');

		return Command::SUCCESS;
	}

}
