<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan\UI;

use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanFacade;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanValidationException;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Application\UI\Form;
use Nette\Forms\Form as NetteForm;
use Nette\Http\FileUpload;
use Nette\Utils\ArrayHash;
use Throwable;
use function assert;

class StockAiInvestmentPlanCodexResultFormFactory
{

	private const int MAX_FILE_SIZE = 2 * 1024 * 1024;

	public function __construct(
		private readonly AdminFormFactory $adminFormFactory,
		private readonly StockAiInvestmentPlanFacade $investmentPlanFacade,
	)
	{
	}

	public function create(StockAiInvestmentPlan $plan, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();
		$form->addUpload('resultFile', 'Codex result.json')
			->setRequired('Vyberte soubor result.json vytvořený Codexem.')
			->addRule(NetteForm::MaxFileSize, 'Soubor může mít nejvýše 2 MiB.', self::MAX_FILE_SIZE);
		$form->addSubmit('submit', 'Importovat výsledek z Codexu');

		$form->onSuccess[] = function (Form $form) use ($plan, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);
			$file = $values->resultFile;
			assert($file instanceof FileUpload);

			$contents = $file->getContents();
			if ($contents === null) {
				$form->addError('Nahraný soubor se nepodařilo přečíst.');
				return;
			}

			if (!$plan->canImportCodexResponse()) {
				$form->addError('Tento investiční plán už nemůže přijmout výsledek z Codexu.');
				return;
			}

			try {
				$this->investmentPlanFacade->processCodexResponse($plan->getId()->toString(), $contents);
			} catch (StockAiInvestmentPlanValidationException $exception) {
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
