<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Dashboard\DashboardValueGroupEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\UI\Icon\SvgIcon;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;

#[ORM\Entity]
#[ORM\Table('portfolio_statistic')]
class PortfolioStatistic implements Entity
{

	use Identifier;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: PortfolioStatisticRecord::class, inversedBy: 'portfolioStatistics')]
	#[ORM\JoinColumn(nullable: false)]
	private PortfolioStatisticRecord $portfolioStatisticRecord;

	#[ORM\Column(type: Types::STRING, enumType: DashboardValueGroupEnum::class)]
	private DashboardValueGroupEnum $dashboardValueGroup;

	#[ORM\Column(type: Types::STRING)]
	private string $label;

	#[ORM\Column(type: Types::STRING)]
	private string $value;

	#[ORM\Column(type: Types::STRING)]
	private string $color;

	#[ORM\Column(type: Types::STRING, enumType: SvgIcon::class, nullable: true)]
	private SvgIcon|null $svgIcon;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $description;

	#[ORM\Column(type: Types::STRING, enumType: PortolioStatisticType::class, nullable: true)]
	private PortolioStatisticType|null $type;

	#[ORM\Column(type: Types::STRING, enumType: PortfolioStatisticControlTypeEnum::class)]
	private PortfolioStatisticControlTypeEnum $portfolioStatisticControlTypeEnum;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private string|null $structuredData;

	/**
	 * @param array<mixed>|null $structuredData
	 */
	public function __construct(
		PortfolioStatisticRecord $portfolioStatisticRecord,
		ImmutableDateTime $now,
		DashboardValueGroupEnum $dashboardValueGroup,
		string $label,
		string $value,
		string $color,
		SvgIcon|null $svgIcon,
		string|null $description,
		PortolioStatisticType|null $type,
		PortfolioStatisticControlTypeEnum $portfolioStatisticControlTypeEnum,
		array|null $structuredData,
	)
	{
		$this->portfolioStatisticRecord = $portfolioStatisticRecord;
		$this->createdAt = $now;
		$this->dashboardValueGroup = $dashboardValueGroup;
		$this->label = $label;
		$this->value = $value;
		$this->color = $color;
		$this->svgIcon = $svgIcon;
		$this->description = $description;
		$this->type = $type;
		$this->portfolioStatisticControlTypeEnum = $portfolioStatisticControlTypeEnum;
		$this->structuredData = Json::encode($structuredData);
	}

	public function getPortfolioStatisticRecord(): PortfolioStatisticRecord
	{
		return $this->portfolioStatisticRecord;
	}

	public function getDashboardValueGroup(): DashboardValueGroupEnum
	{
		return $this->dashboardValueGroup;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getValue(): string
	{
		return $this->value;
	}

	public function getColor(): string
	{
		return $this->color;
	}

	public function getSvgIcon(): SvgIcon|null
	{
		return $this->svgIcon;
	}

	public function getDescription(): string|null
	{
		return $this->description;
	}

	public function getType(): PortolioStatisticType|null
	{
		return $this->type;
	}

	public function getPortfolioStatisticControlTypeEnum(): PortfolioStatisticControlTypeEnum
	{
		return $this->portfolioStatisticControlTypeEnum;
	}

	/**
	 * @return array<mixed>|null
	 */
	public function getStructuredData(): array|null
	{
		if ($this->structuredData === null) {
			return null;
		}

		return (array) Json::decode($this->structuredData, true);
	}

}
