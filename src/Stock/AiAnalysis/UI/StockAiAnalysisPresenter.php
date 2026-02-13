<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisActionSuggestionEnum;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisResultTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
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
		if ($this->run === null) {
			$this->error('Analýza nebyla nalezena');
		}

		$this->template->heading = 'Detail AI analýzy';
		$this->template->run = $this->run;

		$results = $this->run->getResults();
		$portfolioResults = [];
		$watchlistResults = [];
		$singleStockResults = [];

		foreach ($results as $result) {
			if ($result->getType() === StockAiAnalysisResultTypeEnum::PORTFOLIO) {
				$portfolioResults[] = $result;
			} elseif ($result->getType() === StockAiAnalysisResultTypeEnum::WATCHLIST) {
				$watchlistResults[] = $result;
			} elseif ($result->getType() === StockAiAnalysisResultTypeEnum::SINGLE_STOCK) {
				$singleStockResults[] = $result;
			}
		}

		$sortFunction = function (StockAiAnalysisStockResult $a, StockAiAnalysisStockResult $b): int {
			$scoreA = $this->getActionScore($a->getActionSuggestion());
			$scoreB = $this->getActionScore($b->getActionSuggestion());

			return $scoreA <=> $scoreB;
		};

		usort($portfolioResults, $sortFunction);
		usort($watchlistResults, $sortFunction);

		$this->template->portfolioResults = $portfolioResults;
		$this->template->watchlistResults = $watchlistResults;
		$this->template->singleStockResults = $singleStockResults;
	}

	private function getActionScore(StockAiAnalysisActionSuggestionEnum|null $action): int
	{
		if ($action === null) {
			return 10;
		}

		return match ($action) {
			StockAiAnalysisActionSuggestionEnum::CONSIDER_SELLING => 1,
			StockAiAnalysisActionSuggestionEnum::ADD_MORE => 2,
			StockAiAnalysisActionSuggestionEnum::CONSIDER_BUYING => 2,
			StockAiAnalysisActionSuggestionEnum::HOLD => 3,
			StockAiAnalysisActionSuggestionEnum::WATCH_CLOSELY => 4,
			StockAiAnalysisActionSuggestionEnum::WAIT => 5,
			StockAiAnalysisActionSuggestionEnum::NOT_INTERESTING => 6,
		};
	}

	protected function createComponentAiAnalysisGrid(): Datagrid
	{
		return $this->stockAiAnalysisGridFactory->create();
	}

	protected function createComponentPortfolioAnalysisForm(): Form
	{
		$form = new Form();
		$form->addCheckbox('includesPortfolio', 'Akciové pozice (otevřené)')
			->setDefaultValue(true);
		$form->addCheckbox('includesWatchlist', 'Akcie na watchlistu')
			->setDefaultValue(true);
		$form->addCheckbox('includesMarketOverview', 'Obecná situace na trhu')
			->setDefaultValue(true);

		$form->addSubmit('submit', 'Sestavit prompt pro portfolio');

		$form->onSuccess[] = function (Form $form, $values): void {
			$run = $this->stockAiAnalysisFacade->createRun(
				TypeValidator::validateBool($form->getValues()->includesPortfolio),
				TypeValidator::validateBool($form->getValues()->includesWatchlist),
				TypeValidator::validateBool($form->getValues()->includesMarketOverview),
				null,
				null,
			);

			$this->flashMessage('Prompt pro portfolio byl úspěšně vygenerován.', 'success');
			$this->redirect('detail', ['id' => $run->getId()->toString()]);
		};

		return $form;
	}

	protected function createComponentSingleStockAnalysisForm(): Form
	{
		$form = new Form();
		$form->addText('stockTicker', 'Ticker akcie')
			->setRequired('Zadejte ticker akcie')
			->setHtmlAttribute('placeholder', 'např. AAPL');

		$form->addText('stockName', 'Název společnosti')
			->setRequired('Zadejte název společnosti')
			->setHtmlAttribute('placeholder', 'např. Apple Inc.');

		$form->addSubmit('submit', 'Sestavit prompt pro akcii');

		$form->onSuccess[] = function (Form $form, $values): void {
			$run = $this->stockAiAnalysisFacade->createRun(
				false,
				false,
				false,
				TypeValidator::validateString($form->getValues()->stockTicker),
				TypeValidator::validateString($form->getValues()->stockName),
			);

			$this->flashMessage('Prompt pro analýzu akcie byl úspěšně vygenerován.', 'success');
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
