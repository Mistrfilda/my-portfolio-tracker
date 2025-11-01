<?php

declare(strict_types = 1);

namespace App\Utils\Console;

use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCurrentOutputHelper
{

	private OutputInterface|null $output = null;

	public function setOutput(OutputInterface $output): void
	{
		$this->output = $output;
	}

	public function getOutput(): OutputInterface|null
	{
		return $this->output;
	}

}
