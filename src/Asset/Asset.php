<?php

declare(strict_types = 1);

namespace App\Asset;

use App\Asset\Price\AssetPriceDownloaderEnum;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('asset_type')]
#[ORM\Index(fields: ['assetPriceDownloader'], name: 'type_idx')]
class Asset implements Entity
{

	use Identifier;

	#[ORM\Column(type: Types::STRING, enumType: AssetTypeEnum::class)]
	private AssetTypeEnum $type;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $shouldBeUpdated;

	#[ORM\Column(type: Types::STRING, enumType: AssetPriceDownloaderEnum::class)]
	private AssetPriceDownloaderEnum|null $assetPriceDownloader;

	public function __construct(
		AssetTypeEnum $type,
		string $name,
		bool $shouldBeUpdated,
		AssetPriceDownloaderEnum|null $assetPriceDownloader,
	)
	{
		$this->type = $type;
		$this->name = $name;
		$this->shouldBeUpdated = $shouldBeUpdated;
		$this->assetPriceDownloader = $assetPriceDownloader;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function shouldBeUpdated(): bool
	{
		return $this->shouldBeUpdated;
	}

	public function getAssetPriceDownloader(): AssetPriceDownloaderEnum|null
	{
		return $this->assetPriceDownloader;
	}

}
