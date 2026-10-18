<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Admin\AppAdmin;
use App\Doctrine\Entity;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table(name: 'stock_ai_analysis_settings')]
class StockAiAnalysisSettings implements Entity
{

	use UpdatedAt;

	#[ORM\OneToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: Types::INTEGER)]
	private int $id;

	#[ORM\Column(type: Types::TEXT)]
	private string $investorInstructions;

	public function __construct(AppAdmin $appAdmin, string $investorInstructions, ImmutableDateTime $now)
	{
		$this->appAdmin = $appAdmin;
		$this->update($investorInstructions, $now);
	}

	public function update(string $investorInstructions, ImmutableDateTime $now): void
	{
		$this->investorInstructions = trim($investorInstructions);
		$this->updatedAt = $now;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function getId(): int
	{
		return $this->id;
	}

	public function getInvestorInstructions(): string
	{
		return $this->investorInstructions;
	}

}
