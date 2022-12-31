<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('portfolio_statistic_record')]
class PortfolioStatisticRecord implements Entity
{

	use Identifier;
	use CreatedAt;

	/** @var ArrayCollection<int, PortfolioStatistic> */
	#[ORM\OneToMany(targetEntity: PortfolioStatistic::class, mappedBy: 'portfolioStatisticRecord')]
	private Collection $portfolioStatistics;

	public function __construct(ImmutableDateTime $now)
	{
		$this->createdAt = $now;
		$this->portfolioStatistics = new ArrayCollection();
	}

	/**
	 * @return array<int, PortfolioStatistic>
	 */
	public function getPortfolioStatistics(): array
	{
		return $this->portfolioStatistics->toArray();
	}

}
