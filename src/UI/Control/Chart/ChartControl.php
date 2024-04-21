<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use App\UI\Base\BaseControl;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\JsonResponse;
use function str_replace;

class ChartControl extends BaseControl
{

	public function __construct(
		private ChartType $type,
		private ChartDataProvider $chartDataProvider,
		private bool $shouldUpdateOnAjaxRequest = false,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(ChartControlTemplate::class);

		if ($this->getPresenter()->isAjax()) {
			throw new BadRequestException(
				'Graph render called during ajax redrawing, exclude graph component from snippet and use $shouldUpdateOnAjaxRequest',
			);
		}

		$template->shouldUpdateOnAjaxRequest = (int) $this->shouldUpdateOnAjaxRequest;
		$template->chartId = $this->getChartId();
		$template->chartType = $this->type->value;
		$template->setFile(str_replace('.php', '.latte', __FILE__));
		$template->render();
	}

	public function handleGetChartData(): void
	{
		$parameters = [];
		foreach ($_GET as $key => $value) {
			if (str_starts_with($key, 'originalRequest')) {
				$parameters[$key] = $value;
			}
		}

		$this->chartDataProvider->processParametersFromRequest($parameters);
		$response = new JsonResponse($this->chartDataProvider->getChartData());
		$this->getPresenter()->sendResponse($response);
	}

	private function getChartId(): string
	{
		return $this->chartDataProvider->getIdForChart();
	}

}
