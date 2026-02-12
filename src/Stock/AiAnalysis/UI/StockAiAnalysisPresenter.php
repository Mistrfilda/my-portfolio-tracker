<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\Utils\TypeValidator;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Throwable;

/**
 * @property-read StockAiAnalysisTemplate $template
 */
class StockAiAnalysisPresenter extends BaseAdminPresenter
{

	private StockAiAnalysisRun|null $run = null;

	public function __construct(
		private readonly StockAiAnalysisFacade $stockAiAnalysisFacade,
		private readonly StockAiAnalysisGridFactory $stockAiAnalysisGridFactory,
	)
	{
		parent::__construct();
	}

	public function actionDefault(): void
	{
		// Výchozí akce - zobrazení gridu a formuláře
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'AI analýzy';
		$this->template->run = null;
	}

	public function actionDetail(string $id): void
	{
		$this->run = $this->stockAiAnalysisFacade->getRun($id);
		$this->template->run = $this->run;
	}

	public function renderDetail(string $id): void
	{
		$this->template->heading = 'Detail AI analýzy';
		$this->template->run = $this->run;
	}

	protected function createComponentAiAnalysisGrid(): Datagrid
	{
		return $this->stockAiAnalysisGridFactory->create();
	}

	protected function createComponentPromptForm(): Form
	{
		$form = new Form();
		$form->addCheckbox('includesPortfolio', 'Akciové pozice (otevřené)')
			->setDefaultValue(true);
		$form->addCheckbox('includesWatchlist', 'Akcie na watchlistu')
			->setDefaultValue(true);
		$form->addCheckbox('includesMarketOverview', 'Obecná situace na trhu')
			->setDefaultValue(true);

		$form->addSubmit('submit', 'Sestavit prompt');

		$form->onSuccess[] = function (Form $form, $values): void {
			$run = $this->stockAiAnalysisFacade->createRun(
				TypeValidator::validateBool($form->getValues()->includesPortfolio),
				TypeValidator::validateBool($form->getValues()->includesWatchlist),
				TypeValidator::validateBool($form->getValues()->includesMarketOverview),
			);

			$this->flashMessage('Prompt byl úspěšně vygenerován.', 'success');
			$this->redirect('detail', ['id' => $run->getId()->toString()]);
		};

		return $form;
	}

	protected function createComponentResponseForm(): Form
	{
		$form = new Form();
		$form->addTextArea('rawResponse', 'JSON odpověď z AI')
			->setRequired('Zadejte prosím JSON odpověď z AI.');

		$form->addSubmit('submit', 'Zpracovat odpověď');

		$form->onSuccess[] = function (Form $form, $values): void {
			if ($this->run === null) {
				$this->flashMessage('Nebyla vybrána žádná analýza.', 'danger');
				return;
			}

			try {
				$this->stockAiAnalysisFacade->processResponse(
					$this->run->getId()->toString(),
					TypeValidator::validateString($form->getValues()->rawResponse),
				);
				$this->flashMessage('Odpověď byla úspěšně zpracována.', 'success');
				$this->redirect('detail', ['id' => $this->run->getId()->toString()]);
			} catch (AbortException $e) {
				throw $e;
			} catch (Throwable $e) {
				$this->flashMessage($e->getMessage(), 'danger');
			}
		};

		return $form;
	}

}
