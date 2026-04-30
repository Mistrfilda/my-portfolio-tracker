<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

#[AsCommand(name: 'rabbitmq:consumer', description: 'Run a RabbitMQ consumer')]
class RabbitMQConsumerCommand extends Command
{

	public function __construct(private RabbitMQConsumerRunner $consumerRunner)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addArgument('consumerName', InputArgument::REQUIRED, 'Name of the consumer');
		$this->addArgument(
			'secondsToLive',
			InputArgument::OPTIONAL,
			'Max seconds for consumer to run, skip parameter to run indefinitely',
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$consumerName = $input->getArgument('consumerName');
		$secondsToLive = $input->getArgument('secondsToLive');

		if (!is_string($consumerName)) {
			throw new UnexpectedValueException();
		}

		if ($secondsToLive !== null) {
			if (!is_numeric($secondsToLive)) {
				throw new UnexpectedValueException();
			}

			$secondsToLive = (int) $secondsToLive;
			$this->validateSecondsToLive($secondsToLive);
		}

		$output->writeln('Starting consumer ' . $consumerName);
		if ($secondsToLive !== null) {
			$output->writeln('Max seconds for consumer to run: ' . $secondsToLive);
		}

		$this->consumerRunner->consume($consumerName, $secondsToLive);

		return Command::SUCCESS;
	}

	private function validateSecondsToLive(int $secondsToLive): void
	{
		if ($secondsToLive <= 0) {
			throw new UnexpectedValueException('Parameter [secondsToLive] has to be greater than 0');
		}
	}

}
