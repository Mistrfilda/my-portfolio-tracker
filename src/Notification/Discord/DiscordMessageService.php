<?php

declare(strict_types = 1);

namespace App\Notification\Discord;

use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationTypeEnum;
use App\Utils\TypeValidator;
use Mistrfilda\Datetime\DatetimeFactory;

class DiscordMessageService
{

	public const string ASSET_TRENDS_IMAGE_FILENAME = 'asset-trends.png';

	private const COLOR_GREEN = 3066993;

	private const COLOR_RED = 15158332;

	private const COLOR_BLUE = 3447003;

	public function __construct(private DatetimeFactory $datetimeFactory)
	{

	}

	/**
	 * @return array<mixed>
	 */
	public function getMessage(Notification $notification): array
	{
		$timestamp = $this->datetimeFactory->createNow()->format('Y-m-d\TH:i:s.u\Z');
		if ($notification->getNotificationTypeEnum() === NotificationTypeEnum::ASSET_TRENDS) {
			return $this->getAssetTrendsMessage($notification, $timestamp);
		}

		return [
			'embeds' => [
				[
					'title' => $this->getTitle($notification->getNotificationTypeEnum()),
					'description' => $notification->getMessage(),
					'color' => $this->getColor($notification->getNotificationTypeEnum()),
					'timestamp' => $timestamp,
				],
			],
		];
	}

	/**
	 * @return array<mixed>
	 */
	private function getAssetTrendsMessage(Notification $notification, string $timestamp): array
	{
		$numberOfDaysToCompare = TypeValidator::validateInt(
			$notification->getParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD),
		);
		$daysUnit = match (true) {
			$numberOfDaysToCompare === 1 => 'den',
			$numberOfDaysToCompare >= 2 && $numberOfDaysToCompare <= 4 => 'dny',
			default => 'dní',
		};

		return [
			'embeds' => [
				[
					'title' => $this->getTitle(NotificationTypeEnum::ASSET_TRENDS),
					'description' => sprintf(
						'Výrazné cenové pohyby za posledních **%d %s**',
						$numberOfDaysToCompare,
						$daysUnit,
					),
					'color' => self::COLOR_BLUE,
					'timestamp' => $timestamp,
					'image' => ['url' => 'attachment://' . self::ASSET_TRENDS_IMAGE_FILENAME],
				],
			],
			'attachments' => [
				[
					'id' => 0,
					'filename' => self::ASSET_TRENDS_IMAGE_FILENAME,
					'description' => sprintf(
						'Přehled růstu a poklesu cen aktiv za %d %s',
						$numberOfDaysToCompare,
						$daysUnit,
					),
				],
			],
		];
	}

	private function getTitle(NotificationTypeEnum $type): string
	{
		return match ($type) {
			NotificationTypeEnum::NEW_DIVIDEND => 'Nová dividenda',
			NotificationTypeEnum::PRICE_ALERT_UP => '📈 Price alert up',
			NotificationTypeEnum::PRICE_ALERT_DOWN => '📉 Price alert down',
			NotificationTypeEnum::ASSET_TRENDS => '📊 Přehled trendů aktiv',
			NotificationTypeEnum::GOALS_UPDATES => 'Aktualizace cíle portfolia',
		};
	}

	private function getColor(NotificationTypeEnum $type): int
	{
		return match ($type) {
			NotificationTypeEnum::NEW_DIVIDEND,
			NotificationTypeEnum::PRICE_ALERT_UP,
			NotificationTypeEnum::GOALS_UPDATES => self::COLOR_GREEN,
			NotificationTypeEnum::PRICE_ALERT_DOWN => self::COLOR_RED,
			NotificationTypeEnum::ASSET_TRENDS => self::COLOR_BLUE,
		};
	}

}
