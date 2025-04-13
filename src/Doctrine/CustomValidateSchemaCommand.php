<?php

declare(strict_types = 1);

namespace App\Doctrine;

use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * WORKAROUND FOR https://github.com/doctrine/migrations/issues/1406
 */
#[AsCommand('orm:validate-schema')]
class CustomValidateSchemaCommand extends ValidateSchemaCommand
{

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$ui = (new SymfonyStyle($input, $output))->getErrorStyle();

		$em = $this->getEntityManager($input);
		$validator = new SchemaValidator($em, !$input->getOption('skip-property-types'));
		$exit = 0;

		$ui->section('Mapping');

		if ($input->getOption('skip-mapping')) {
			$ui->text('<comment>[SKIPPED] The mapping was not checked.</comment>');
		} else {
			$errors = $validator->validateMapping();
			if ($errors) {
				foreach ($errors as $className => $errorMessages) {
					$ui->text(
						sprintf(
							'<error>[FAIL]</error> The entity-class <comment>%s</comment> mapping is invalid:',
							$className,
						),
					);

					$ui->listing($errorMessages);
					$ui->newLine();
				}

				++$exit;
			} else {
				$ui->success('The mapping files are correct.');
			}
		}

		$ui->section('Database');

		if ($input->getOption('skip-sync')) {
			$ui->text('<comment>[SKIPPED] The database was not checked for synchronicity.</comment>');
		} elseif (!$validator->schemaInSyncWithMetadata()) {
			$sqls = $validator->getUpdateSchemaList();
			foreach ($sqls as $key => $sql) {
				if ($sql === 'DROP TABLE doctrine_migrations') {
					unset($sqls[$key]);
					continue;
				}

				$ui->text(sprintf('    %s;', $sql));
			}

			if (count($sqls) > 0) {
				$ui->error('The database schema is not in sync with the current mapping file.');
				if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
					$sqls = $validator->getUpdateSchemaList();
					$ui->comment(sprintf('<info>%d</info> schema diff(s) detected:', count($sqls)));
					foreach ($sqls as $sql) {
						$ui->text(sprintf('    %s;', $sql));
					}
				}

				$exit += 2;
			} else {
				$ui->success('The database schema is in sync with the mapping files.');
			}
		} else {
			$ui->success('The database schema is in sync with the mapping files.');
		}

		return $exit;
	}

}
