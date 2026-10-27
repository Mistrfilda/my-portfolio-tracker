<?php

declare(strict_types = 1);

namespace App\Work\Report\UI;

use App\Gotenberg\GotenbergPdfDocumentRenderer;
use App\Gotenberg\GotenbergPdfService;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Response\PdfResponse;
use App\Work\Report\WorkReport;
use App\Work\Report\WorkReportRepository;
use Ramsey\Uuid\Uuid;

/** @property-read WorkReportTemplate $template */
class WorkReportPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly WorkReportRepository $repository,
		private readonly WorkReportViewFactory $viewFactory,
		private readonly WorkReportGridFactory $gridFactory,
		private readonly GotenbergPdfDocumentRenderer $pdfRenderer,
		private readonly GotenbergPdfService $pdfService,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->getHttpResponse()->setHeader('Cache-Control', 'private, no-store');
		$this->template->heading = 'Pracovní přehledy';
	}

	public function renderDetail(string $id): void
	{
		$this->prepareReport($this->getReport($id));
		$this->template->heading = 'Pracovní přehled';
	}

	public function actionDownloadPdf(string $id): void
	{
		$report = $this->getReport($id);
		$this->setLayout(false);
		$this->prepareReport($report);
		$html = $this->template->renderToString(__DIR__ . '/templates/WorkReport.pdf.latte');
		$title = 'Pracovní přehled - ' . $report->getReportDate()->format('d. m. Y');
		$pdf = $this->pdfService->convertHtml($this->pdfRenderer->render($title, $html));
		$this->sendResponse(new PdfResponse($pdf, 'work-report-' . $report->getReportDate()->format('Y-m-d') . '.pdf'));
	}

	protected function createComponentWorkReportGrid(): Datagrid
	{
		return $this->gridFactory->create();
	}

	private function getReport(string $id): WorkReport
	{
		if (!Uuid::isValid($id)) {
			$this->error('Report not found.');
		}

		return $this->repository->findForCurrentAppAdmin(Uuid::fromString($id))
			?? $this->error('Report not found.');
	}

	private function prepareReport(WorkReport $report): void
	{
		$this->getHttpResponse()->setHeader('Cache-Control', 'private, no-store');
		$this->template->report = $report;
		$this->template->view = $this->viewFactory->create($report->getData());
	}

}
