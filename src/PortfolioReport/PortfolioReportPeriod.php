<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioReportPeriod
{

	public function __construct(
		private readonly PortfolioReportPeriodTypeEnum $type,
		private readonly ImmutableDateTime $dateFrom,
		private readonly ImmutableDateTime $dateTo,
		private readonly ImmutableDateTime $referenceDate,
	)
	{
	}

	public function getType(): PortfolioReportPeriodTypeEnum
	{
		return $this->type;
	}

	public function getDateFrom(): ImmutableDateTime
	{
		return $this->dateFrom;
	}

	public function getDateTo(): ImmutableDateTime
	{
		return $this->dateTo;
	}

	public function getReferenceDate(): ImmutableDateTime
	{
		return $this->referenceDate;
	}

	public function getLabel(): string
	{
		$dateLabelFormat = $this->type->getDateLabelFormat();

		if ($this->type === PortfolioReportPeriodTypeEnum::DAILY) {
			return $this->dateFrom->format($dateLabelFormat);
		}

		if ($this->type === PortfolioReportPeriodTypeEnum::MONTHLY) {
			return $this->dateFrom->format($dateLabelFormat);
		}

		return sprintf(
			'%s - %s',
			$this->dateFrom->format('d. m. Y'),
			$this->dateTo->format('d. m. Y'),
		);
	}

}
