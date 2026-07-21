<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistProvider;
use App\Stock\AiAnalysis\Codex\StockAiAnalysisCodexBundleFactory;
use App\Stock\AiAnalysis\StockAiAnalysisActionSuggestionEnum;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestionFacade;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisResultTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\Stock\Asset\StockAssetRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\Utils\TypeValidator;
use Nette\Application\AbortException;
use Nette\Application\Responses\CallbackResponse;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use RuntimeException;
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
		private readonly StockAssetRepository $stockAssetRepository,
		private readonly StockAiAnalysisActionChecklistProvider $stockAiAnalysisActionChecklistProvider,
		private readonly StockAiAnalysisGeminiProcessorFacade $stockAiAnalysisGeminiProcessorFacade,
		private readonly StockAiAnalysisFollowUpQuestionFacade $stockAiAnalysisFollowUpQuestionFacade,
		private readonly StockAiAnalysisCodexBundleFactory $stockAiAnalysisCodexBundleFactory,
		private readonly StockAiAnalysisCodexResultFormFactory $stockAiAnalysisCodexResultFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'AI analýzy';
		$this->template->run = null;
		$this->template->manualOpenPositionsPrompt = $this->stockAiAnalysisFacade->getManualOpenPositionsPrompt();

		$assets = $this->stockAssetRepository->findAll();
		$stockAssetsData = [];
		foreach ($assets as $asset) {
			$stockAssetsData[$asset->getId()->toString()] = [
				'ticker' => $asset->getTicker(),
				'name' => $asset->getName(),
			];
		}

		$this->template->stockAssetsDataJson = Json::encode($stockAssetsData);
		$this->template->portfolioPromptTypes = [
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION->value =>
				StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION->getLabel(),
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->value =>
				StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->getLabel(),
		];
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
		$this->template->generatedPromptForDisplay = $this->stockAiAnalysisFacade->getGeneratedPromptForDisplay(
			$this->run,
		);
		$this->template->codexStartPrompt = StockAiAnalysisCodexBundleFactory::START_PROMPT;
		$this->template->geminiResponseTempFileCount = $this->stockAiAnalysisGeminiProcessorFacade
			->getCachedGeminiResponseFileCount($this->run);
		$this->template->dailyBriefActionChecklistItems = $this->stockAiAnalysisActionChecklistProvider->getForRun(
			$this->run,
		);
		$this->template->followUpQuestions = $this->stockAiAnalysisFollowUpQuestionFacade->getQuestionsForRun(
			$this->run,
		);
	}

	public function actionDownloadCodexBundle(string $id): void
	{
		$run = $this->stockAiAnalysisFacade->getRun($id);
		$bundle = $this->stockAiAnalysisCodexBundleFactory->create($run);
		$fileResponse = new FileResponse(
			$bundle->filePath,
			$bundle->downloadName,
			'application/zip',
		);

		$this->sendResponse(new CallbackResponse(static function (
			IRequest $httpRequest,
			IResponse $httpResponse,
		) use (
			$bundle,
			$fileResponse
): void {
			$httpResponse->setHeader('Cache-Control', 'private, no-store');
			try {
				$fileResponse->send($httpRequest, $httpResponse);
			} finally {
				FileSystem::delete($bundle->filePath);
			}
		}));
	}

	public function handleEnqueueFollowUpGemini(string $questionId): void
	{
		if ($this->run === null) {
			$this->flashMessage('Nebyla vybrána žádná analýza.', 'danger');
			return;
		}

		try {
			$this->stockAiAnalysisFollowUpQuestionFacade->enqueueGeminiProcessing($questionId);
			$this->flashMessage('Doplňující dotaz byl zařazen do fronty pro zpracování přes Gemini.', 'success');
		} catch (Throwable $e) {
			$this->flashMessage($e->getMessage(), 'danger');
		}

		$this->redirect('detail', ['id' => $this->run->getId()->toString()]);
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
		$form->addSelect('portfolioPromptType', 'Typ portfolio promptu', [
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION->value =>
				StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION->getLabel(),
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->value =>
				StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF->getLabel(),
		])
			->setDefaultValue(StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION->value)
			->setRequired('Vyberte typ portfolio promptu.');
		$form->addCheckbox('includesPortfolio', 'Akciové pozice (otevřené)')
			->setDefaultValue(true);
		$form->addCheckbox('includesWatchlist', 'Akcie na watchlistu')
			->setDefaultValue(true);
		$form->addCheckbox('includesMarketOverview', 'Obecná situace na trhu')
			->setDefaultValue(true);

		$form->addSubmit('submit', 'Sestavit prompt pro portfolio');

		$form->onSuccess[] = function (Form $form, $values): void {
			$portfolioPromptType = StockAiAnalysisPortfolioPromptTypeEnum::from(
				TypeValidator::validateString($form->getValues()->portfolioPromptType),
			);

			$run = $this->stockAiAnalysisFacade->createRun(
				TypeValidator::validateBool($form->getValues()->includesPortfolio),
				TypeValidator::validateBool($form->getValues()->includesWatchlist),
				TypeValidator::validateBool($form->getValues()->includesMarketOverview),
				$portfolioPromptType,
				null,
				null,
			);

			$this->flashMessage(
				$portfolioPromptType === StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF
					? 'Denní briefing prompt byl úspěšně vygenerován.'
					: 'Prompt pro portfolio byl úspěšně vygenerován.',
				'success',
			);
			$this->redirect('detail', ['id' => $run->getId()->toString()]);
		};

		return $form;
	}

	protected function createComponentSingleStockAnalysisForm(): Form
	{
		$form = new Form();

		$assets = $this->stockAssetRepository->findAll();
		$assetPairs = [];
		foreach ($assets as $asset) {
			$assetPairs[$asset->getId()->toString()] = sprintf('%s (%s)', $asset->getName(), $asset->getTicker());
		}

		$form->addSelect('existingAsset', 'Existující akcie', $assetPairs)
			->setPrompt('Vyberte existující akcii...')
			->setRequired(false);

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
				null,
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

	protected function createComponentGeminiProcessingForm(): Form
	{
		$form = new Form();
		$form->addSubmit('submit', 'Zařadit ke zpracování přes Gemini');

		$form->onSuccess[] = function (): void {
			if ($this->run === null) {
				$this->flashMessage('Nebyla vybrána žádná analýza.', 'danger');
				return;
			}

			try {
				$this->stockAiAnalysisFacade->enqueueGeminiProcessing($this->run->getId()->toString());
				$this->flashMessage('AI analýza byla zařazena do fronty pro zpracování přes Gemini.', 'success');
				$this->redirect('detail', ['id' => $this->run->getId()->toString()]);
			} catch (AbortException $e) {
				throw $e;
			} catch (Throwable $e) {
				$this->flashMessage($e->getMessage(), 'danger');
			}
		};

		return $form;
	}

	protected function createComponentCodexResultForm(): AdminForm
	{
		if ($this->run === null) {
			throw new RuntimeException('No stock AI analysis run is selected.');
		}

		return $this->stockAiAnalysisCodexResultFormFactory->create($this->run, function (): void {
			if ($this->run === null) {
				return;
			}

			$this->flashMessage('Výsledek z Codexu byl úspěšně importován.', 'success');
			$this->redirect('detail', ['id' => $this->run->getId()->toString()]);
		});
	}

	protected function createComponentFollowUpQuestionForm(): Form
	{
		$form = new Form();
		$form->addTextArea('question', 'Doplňující dotaz')
			->setRequired('Zadejte prosím doplňující dotaz.');
		$form->addSubmit('submit', 'Vygenerovat doplňující prompt');

		$form->onSuccess[] = function (Form $form): void {
			if ($this->run === null) {
				$this->flashMessage('Nebyla vybrána žádná analýza.', 'danger');
				return;
			}

			$this->stockAiAnalysisFollowUpQuestionFacade->createQuestion(
				$this->run->getId()->toString(),
				TypeValidator::validateString($form->getValues()->question),
			);
			$this->flashMessage('Doplňující prompt byl úspěšně vygenerován.', 'success');
			$this->redirect('detail', ['id' => $this->run->getId()->toString()]);
		};

		return $form;
	}

	protected function createComponentFollowUpManualResponseForm(): Form
	{
		$form = new Form();
		$form->addHidden('questionId')
			->setRequired('Chybí identifikátor doplňujícího dotazu.');
		$form->addTextArea('rawResponse', 'Odpověď z AI')
			->setRequired('Zadejte prosím odpověď z AI.');
		$form->addSubmit('submit', 'Uložit odpověď');

		$form->onSuccess[] = function (Form $form): void {
			if ($this->run === null) {
				$this->flashMessage('Nebyla vybrána žádná analýza.', 'danger');
				return;
			}

			$this->stockAiAnalysisFollowUpQuestionFacade->processManualResponse(
				TypeValidator::validateString($form->getValues()->questionId),
				TypeValidator::validateString($form->getValues()->rawResponse),
			);
			$this->flashMessage('Odpověď na doplňující dotaz byla úspěšně uložena.', 'success');
			$this->redirect('detail', ['id' => $this->run->getId()->toString()]);
		};

		return $form;
	}

}
