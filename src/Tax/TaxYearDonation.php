<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('tax_year_donation')]
#[ORM\Index(fields: ['taxYearProfile'], name: 'tax_year_donation_profile_idx')]
class TaxYearDonation implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\ManyToOne(targetEntity: TaxYearProfile::class, inversedBy: 'donations')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private TaxYearProfile $taxYearProfile;

	#[ORM\Column(type: Types::STRING)]
	private string $recipientName;

	#[ORM\Column(type: Types::INTEGER)]
	private int $amountCzk;

	#[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $donatedAt;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $note;

	public function __construct(
		AppAdmin $appAdmin,
		TaxYearProfile $taxYearProfile,
		string $recipientName,
		int $amountCzk,
		ImmutableDateTime|null $donatedAt,
		string|null $note,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->appAdmin = $appAdmin;
		$this->taxYearProfile = $taxYearProfile;
		$this->recipientName = $recipientName;
		$this->amountCzk = max(0, $amountCzk);
		$this->donatedAt = $donatedAt?->setTime(0, 0);
		$this->note = $note;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function getTaxYearProfile(): TaxYearProfile
	{
		return $this->taxYearProfile;
	}

	public function getRecipientName(): string
	{
		return $this->recipientName;
	}

	public function getAmountCzk(): int
	{
		return $this->amountCzk;
	}

	public function getDonatedAt(): ImmutableDateTime|null
	{
		return $this->donatedAt;
	}

	public function getNote(): string|null
	{
		return $this->note;
	}

}
