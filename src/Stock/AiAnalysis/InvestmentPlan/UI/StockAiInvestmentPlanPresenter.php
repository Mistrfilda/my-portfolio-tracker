<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan\UI;

use App\Gotenberg\GotenbergPdfDocumentRenderer;
use App\Gotenberg\GotenbergPdfService;
use App\Stock\AiAnalysis\InvestmentPlan\Codex\StockAiInvestmentPlanCodexBundleFactory;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlanFacade;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\Response\PdfResponse;
use Nette\Application\Responses\CallbackResponse;
use Nette\Application\Responses\FileResponse;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\FileSystem;
use RuntimeException;

/**
 * @property-read StockAiInvestmentPlanTemplate $template
 */
class StockAiInvestmentPlanPresenter extends BaseAdminPresenter
{

	private StockAiInvestmentPlan|null $plan = null;

	public function __construct(
		private readonly StockAiInvestmentPlanFacade $investmentPlanFacade,
		private readonly StockAiInvestmentPlanFormFactory $investmentPlanFormFactory,
		private readonly StockAiInvestmentPlanCodexResultFormFactory $codexResultFormFactory,
		private readonly StockAiInvestmentPlanGridFactory $investmentPlanGridFactory,
		private readonly StockAiInvestmentPlanCodexBundleFactory $codexBundleFactory,
		private readonly GotenbergPdfDocumentRenderer $gotenbergPdfDocumentRenderer,
		private readonly GotenbergPdfService $gotenbergPdfService,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'AI investiční plán';
		$this->template->plan = null;
	}

	public function actionDetail(string $id): void
	{
		$this->plan = $this->investmentPlanFacade->get($id);
		$this->template->plan = $this->plan;
	}

	public function renderDetail(string $id): void
	{
		if ($this->plan === null) {
			$this->error('Investiční plán nebyl nalezen.');
		}

		$this->prepareDetailTemplate($this->plan);
	}

	public function actionDownloadCodexBundle(string $id): void
	{
		$plan = $this->investmentPlanFacade->get($id);
		$bundle = $this->codexBundleFactory->create($plan);
		$fileResponse = new FileResponse($bundle->filePath, $bundle->downloadName, 'application/zip');

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

	public function actionDownloadPdf(string $id): void
	{
		$plan = $this->investmentPlanFacade->get($id);
		if ($plan->getProcessedAt() === null) {
			$this->flashMessage('PDF lze stáhnout až po zpracování investičního plánu.', 'danger');
			$this->redirect('detail', ['id' => $plan->getId()->toString()]);
		}

		$this->setLayout(false);
		$this->prepareDetailTemplate($plan, true);
		$detailHtml = $this->template->renderToString(__DIR__ . '/templates/StockAiInvestmentPlan.detail.latte');
		$title = sprintf('AI investiční plán - %s', $plan->getCreatedAt()->format('d. m. Y'));
		$pdfHtml = $this->gotenbergPdfDocumentRenderer->render($title, $detailHtml);
		$pdf = $this->gotenbergPdfService->convertHtml($pdfHtml);

		$this->sendResponse(new PdfResponse(
			$pdf,
			sprintf('ai-investment-plan-%s.pdf', $plan->getId()->toString()),
		));
	}

	protected function createComponentInvestmentPlanForm(): AdminForm
	{
		return $this->investmentPlanFormFactory->create(function (StockAiInvestmentPlan $plan): void {
			$this->flashMessage('Investiční plán byl vytvořen. Nyní jej zpracujte v Codexu.', 'success');
			$this->redirect('detail', ['id' => $plan->getId()->toString()]);
		});
	}

	protected function createComponentInvestmentPlanGrid(): Datagrid
	{
		return $this->investmentPlanGridFactory->create();
	}

	protected function createComponentCodexResultForm(): AdminForm
	{
		if ($this->plan === null) {
			throw new RuntimeException('No stock investment plan is selected.');
		}

		return $this->codexResultFormFactory->create($this->plan, function (): void {
			if ($this->plan === null) {
				return;
			}

			$this->flashMessage('Výsledek investičního plánu z Codexu byl úspěšně importován.', 'success');
			$this->redirect('detail', ['id' => $this->plan->getId()->toString()]);
		});
	}

	private function prepareDetailTemplate(StockAiInvestmentPlan $plan, bool $pdfExport = false): void
	{
		$this->template->heading = $pdfExport ? null : 'Detail AI investičního plánu';
		$this->template->plan = $plan;
		$this->template->pdfExport = $pdfExport;
		$this->template->generatedPromptForDisplay = $this->investmentPlanFacade
			->getGeneratedPromptForDisplay($plan);
		$this->template->codexStartPrompt = StockAiInvestmentPlanCodexBundleFactory::START_PROMPT;
	}

}
