<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report\UI;

use App\Gotenberg\GotenbergPdfDocumentRenderer;
use App\Gotenberg\GotenbergPdfService;
use App\Work\Report\UI\WorkReportGridFactory;
use App\Work\Report\UI\WorkReportPresenter;
use App\Work\Report\UI\WorkReportViewFactory;
use App\Work\Report\WorkReportRepository;
use Nette\Application\BadRequestException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class WorkReportPresenterTest extends TestCase
{

	#[TestWith(['invalid', false])]
	#[TestWith(['invalid', true])]
	#[TestWith(['00000000-0000-4000-8000-000000000001', false])]
	#[TestWith(['00000000-0000-4000-8000-000000000001', true])]
	public function testDetailAndPdfRejectUnknownReportsBeforeRendering(string $id, bool $pdf): void
	{
		$repository = $this->createStub(WorkReportRepository::class);
		$repository->method('findForCurrentAppAdmin')->willReturn(null);
		$pdfService = $this->createMock(GotenbergPdfService::class);
		$pdfService->expects(self::never())->method('convertHtml');
		$presenter = new WorkReportPresenter(
			$repository,
			$this->createStub(WorkReportViewFactory::class),
			$this->createStub(WorkReportGridFactory::class),
			$this->createStub(GotenbergPdfDocumentRenderer::class),
			$pdfService,
		);
		$this->expectException(BadRequestException::class);
		$this->expectExceptionCode(404);
		if ($pdf) {
			$presenter->actionDownloadPdf($id);
		} else {
			$presenter->renderDetail($id);
		}
	}

}
