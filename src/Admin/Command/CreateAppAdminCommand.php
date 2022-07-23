<?php

declare(strict_types = 1);

namespace App\Admin\Command;

use App\Admin\AppAdminFacade;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAppAdminCommand extends Command
{

	public function __construct(private AppAdminFacade $appAdminFacade)
	{
		parent::__construct(null);
	}

	public function configure(): void
	{
		parent::configure();
		$this->setName('user:create');
		$this->addArgument('name', InputArgument::REQUIRED);
		$this->addArgument('username', InputArgument::REQUIRED);
		$this->addArgument('email', InputArgument::REQUIRED);
		$this->addArgument('password', InputArgument::REQUIRED);
	}

	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getArgument('name');
		$username = $input->getArgument('username');
		$email = $input->getArgument('email');
		$password = $input->getArgument('password');

		if (
			is_string($name) === false
			|| is_string($username) === false
			|| is_string($email) === false
			|| is_string($password) === false
		) {
			throw new InvalidArgumentException();
		}

		$this->appAdminFacade->createAppAdmin(
			$name,
			$username,
			$email,
			$password,
			true,
			true,
		);

		return 0;
	}

}
