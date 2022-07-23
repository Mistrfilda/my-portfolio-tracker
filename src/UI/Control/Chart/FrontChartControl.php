<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use App\UI\Base\BaseControl;
use Nette\Application\Responses\JsonResponse;
use Nette\Utils\Random;
use function str_replace;

class FrontChartControl extends BaseControl
{

	public function __construct(
		private string $type,
		private IChartDataProvider $chartDataProvider,
	)
	{
		ChartType::typeExists($type);
	}

	public function render(): void
	{
		$template = $this->createTemplate(FrontChartControlTemplate::class);

		$template->chartId = $this->getChartId();
		$template->chartType = $this->type;
		$template->setFile(str_replace('.php', '.latte', __FILE__));
		$template->render();
	}

	public function handleGetChartData(): void
	{
		$response = new JsonResponse($this->chartDataProvider->getChartData());
		$this->getPresenter()->sendResponse($response);
	}

	private function getChartId(): string
	{
		return Random::generate(12);
	}

}
