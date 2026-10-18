<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('portfolio_statistic_record')]
#[ORM\Index(fields: ['appAdmin', 'createdAt'], name: 'portfolio_statistic_record_owner_created_idx')]
class PortfolioStatisticRecord implements Entity
{

	use Identifier;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	/** @var ArrayCollection<int, PortfolioStatistic> */
	#[ORM\OneToMany(targetEntity: PortfolioStatistic::class, mappedBy: 'portfolioStatisticRecord')]
	private Collection $portfolioStatistics;

	public function __construct(AppAdmin $appAdmin, ImmutableDateTime $now)
	{
		$this->appAdmin = $appAdmin;
		$this->createdAt = $now;
		$this->portfolioStatistics = new ArrayCollection();
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function addPortfolioStatistic(PortfolioStatistic $statistic): void
	{
		if (!$this->portfolioStatistics->contains($statistic)) {
			$this->portfolioStatistics->add($statistic);
		}
	}

	/**
	 * @return ArrayCollection<int, PortfolioStatistic>
	 */
	public function getPortfolioStatistics(): Collection
	{
		return $this->portfolioStatistics;
	}

	public function getPortfolioStatisticByType(PortolioStatisticType $portolioStatisticType): PortfolioStatistic|null
	{
		foreach ($this->getPortfolioStatistics()->toArray() as $portfolioStatistic) {
			if ($portfolioStatistic->getType() === $portolioStatisticType) {
				return $portfolioStatistic;
			}
		}

		return null;
	}

}
