<?php

declare(strict_types = 1);

namespace App\Work\Report\Command;

use App\Utils\TypeValidator;
use App\Work\Report\WorkReportException;
use App\Work\Report\WorkReportFacade;
use App\Work\Report\WorkReportPeriod;
use DateTimeZone;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\IOException;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('work:report:generate', 'Download Harvest and GitLab data and save a work report JSON snapshot')]
class GenerateWorkReportCommand extends Command
{

	public function __construct(
		private readonly WorkReportFacade $facade,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption(
			'date',
			null,
			InputOption::VALUE_REQUIRED,
			'Standup/report date, YYYY-MM-DD; defaults to today in Europe/Prague',
		);
		$this->addOption(
			'from',
			null,
			InputOption::VALUE_REQUIRED,
			'First included work date, YYYY-MM-DD; use with --to',
		);
		$this->addOption(
			'to',
			null,
			InputOption::VALUE_REQUIRED,
			'Last included work date, YYYY-MM-DD; use with --from',
		);
		$this->addOption(
			'output',
			null,
			InputOption::VALUE_REQUIRED,
			'Also export the saved report JSON to this private file',
		);
		$this->addOption('no-ai', null, InputOption::VALUE_NONE, 'Save only the Harvest and GitLab source report');
		$this->addOption(
			'summary-only',
			null,
			InputOption::VALUE_NONE,
			'Summarize the saved report without downloading its sources',
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$summaryOnly = TypeValidator::validateBool($input->getOption('summary-only'));
			$withAi = !TypeValidator::validateBool($input->getOption('no-ai'));
			if ($summaryOnly && (!$withAi || $input->getOption('from') !== null || $input->getOption('to') !== null)) {
				throw new InvalidArgumentException('Do not combine --summary-only with --no-ai, --from, or --to.');
			}

			$date = TypeValidator::validateNullableString($input->getOption('date'))
				?? $this->datetimeFactory->createNow()->setTimezone(
					new DateTimeZone(WorkReportPeriod::TIMEZONE),
				)->format('Y-m-d');
			$period = WorkReportPeriod::create(
				$date,
				TypeValidator::validateNullableString($input->getOption('from')),
				TypeValidator::validateNullableString($input->getOption('to')),
			);
		} catch (InvalidArgumentException $exception) {
			$output->writeln('<error>' . $exception->getMessage() . '</error>');
			return self::INVALID;
		}

		try {
			$report = $summaryOnly
				? $this->facade->summarize($period->reportDate)
				: $this->facade->generate($period, $withAi);
		} catch (WorkReportException $exception) {
			$output->writeln('<error>' . $exception->getMessage() . '</error>');
			return self::FAILURE;
		}

		$data = $report->getData();
		$output->writeln(sprintf(
			'Saved work report %s for %s (%s to %s): %d entries, %d projects, %.4f hours, %d warnings.',
			$report->getId()->toString(),
			$date,
			$data['period']['from'],
			$data['period']['to'],
			$data['totals']['entryCount'],
			$data['totals']['projectCount'],
			$data['totals']['hours'],
			count($data['warnings']),
		));
		if (isset($data['aiSummary'])) {
			$output->writeln(match ($data['aiSummary']['status']) {
				'complete' => sprintf(
					'AI standup summary saved (%d bullets, %s).',
					count($data['aiSummary']['bullets']),
					$data['aiSummary']['model'],
				),
				'skipped' => 'AI standup summary skipped: no Harvest entries.',
				'failed' => '<comment>AI summary failed. The Harvest/GitLab report is saved and remains available.</comment>',
			});
		}

		$path = TypeValidator::validateNullableString($input->getOption('output'));
		if ($path !== null) {
			$output->writeln('Exporting work report JSON...');
			try {
				FileSystem::write($path, Json::encode($data, pretty: true) . "\n", 0o600);
			} catch (IOException) {
				$output->writeln('<error>The report was saved, but its JSON export could not be written.</error>');
				return self::FAILURE;
			}

			$output->writeln('Work report JSON exported.');
		}

		if ($data['status'] !== 'complete') {
			$output->writeln('<comment>The saved report is partial. Inspect its warnings before using it.</comment>');
			return self::FAILURE;
		}

		return self::SUCCESS;
	}

}
