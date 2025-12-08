<?php

declare(strict_types = 1);

namespace App\Crypto\Asset\UI\Detail\Control;

use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Asset\UI\Detail\List\CryptoAssetListDetailControlEnum;
use App\Crypto\Position\CryptoPositionFacade;
use App\UI\Base\BaseControl;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use Mistrfilda\Datetime\DatetimeFactory;
use Ramsey\Uuid\UuidInterface;
use function assert;

class CryptoAssetDetailControl extends BaseControl
{

	private const CHART_OPTIONS = [
		365 => '365 dní',
		180 => '180 dní',
		90 => '90 dní',
		60 => '60 dní',
		30 => '30 dní',
		5 => '5 dní',
		1 => '1 den',
	];

	public function __construct(
		private UuidInterface $id,
		private int $currentChartDays,
		private CryptoAssetRepository $cryptoAssetRepository,
		private CryptoPositionFacade $cryptoPositionFacade,
		private CryptoAssetDetailPriceChartProvider $cryptoAssetDetailPriceChartProvider,
		private DatetimeFactory $datetimeFactory,
		private ChartControlFactory $chartControlFactory,
	)
	{
	}

	public function render(): void
	{
		$template = $this->createTemplate(CryptoAssetDetailControlTemplate::class);
		assert($template instanceof CryptoAssetDetailControlTemplate);

		$template->cryptoAsset = $this->cryptoAssetRepository->getById($this->id);
		$template->openCryptoAssetDetailDTO = $this->cryptoPositionFacade->getCryptoAssetDetailDTO($this->id);
		$template->closedCryptoAssetDetailDTO = $this->cryptoPositionFacade->getCryptoAssetDetailDTO(
			$this->id,
			CryptoAssetListDetailControlEnum::CLOSED_POSITIONS,
		);
		$template->now = $this->datetimeFactory->createNow();
		$template->chartOptions = self::CHART_OPTIONS;
		$template->currentChartDays = $this->currentChartDays;
		$template->setFile(__DIR__ . '/CryptoAssetDetailControl.latte');
		$template->render();
	}

	protected function createComponentCryptoAssetPriceChart(): ChartControl
	{
		$chartProvider = clone $this->cryptoAssetDetailPriceChartProvider;
		$chartProvider->setId($this->id);
		$chartProvider->setNumberOfDays($this->currentChartDays);

		return $this->chartControlFactory->create(
			ChartType::LINE,
			$this->cryptoAssetDetailPriceChartProvider,
		);
	}

}
