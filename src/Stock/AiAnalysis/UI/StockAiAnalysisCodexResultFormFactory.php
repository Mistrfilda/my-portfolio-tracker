<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ValidationException;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\ArrayHash;
use Throwable;
use function assert;

class StockAiAnalysisCodexResultFormFactory
{

	private const int MAX_FILE_SIZE = 2 * 1024 * 1024;

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockAiAnalysisFacade $stockAiAnalysisFacade,
	)
	{
	}

	public function create(StockAiAnalysisRun $run, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->addUpload('resultFile', 'Codex result.json')
			->setRequired('Select the Codex result.json file.')
			->addRule(Form::MaxFileSize, 'The result file may be at most 10 MiB.', self::MAX_FILE_SIZE);
		$form->addSubmit('submit', 'Importovat výsledek z Codexu');

		$form->onSuccess[] = function (Form $form) use ($run, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);
			$file = $values->resultFile;
			assert($file instanceof FileUpload);

			$contents = $file->getContents();
			if ($contents === null) {
				$form->addError('The uploaded result file could not be read.');
				return;
			}

			if (!$run->canImportCodexResponse()) {
				$form->addError('This analysis run cannot accept a Codex result.');
				return;
			}

			try {
				$this->stockAiAnalysisFacade->processResponse(
					$run->getId()->toString(),
					$contents,
					StockAiAnalysisProcessingSourceEnum::CODEX,
				);
			} catch (StockAiAnalysisV2ValidationException $exception) {
				foreach ($exception->getErrors() as $error) {
					$form->addError($error);
				}

				return;
			} catch (Throwable $exception) {
				$form->addError($exception->getMessage());
				return;
			}

			$onSuccess();
		};

		return $form;
	}

}
