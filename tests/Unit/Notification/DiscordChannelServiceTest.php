<?php

declare(strict_types = 1);

namespace App\Test\Unit\Notification;

use App\Admin\AppAdmin;
use App\Notification\Discord\DiscordChannelEnum;
use App\Notification\Discord\DiscordChannelService;
use App\Notification\Notification;
use App\Notification\NotificationParameterEnum;
use App\Notification\NotificationTypeEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DiscordChannelServiceTest extends TestCase
{

	public function testWebhookUrlRetrievedFromDirectMapping(): void
	{
		$mapping = [
			NotificationTypeEnum::NEW_DIVIDEND->value => 'https://example.com/webhook/new-dividend',
		];
		$notification = $this->createNotification(NotificationTypeEnum::NEW_DIVIDEND);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertSame('https://example.com/webhook/new-dividend', $result);
	}

	public function testWebhookUrlAssetTrendsWithValidThreshold(): void
	{
		$mapping = [
			DiscordChannelEnum::TREND_ALERT_7_DAYS->value => 'https://example.com/webhook/trend-alert-7-days',
		];
		$notification = $this->createNotification(
			NotificationTypeEnum::ASSET_TRENDS,
			[NotificationParameterEnum::TREND_DAYS_THRESHOLD->value => 7],
		);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertSame('https://example.com/webhook/trend-alert-7-days', $result);
	}

	public function testWebhookUrlPriceAlertWithDefaultThreshold(): void
	{
		$mapping = [
			DiscordChannelEnum::TREND_ALERT_DEFAULT->value => 'https://example.com/webhook/trend-alert-default',
		];
		$notification = $this->createNotification(
			NotificationTypeEnum::PRICE_ALERT_DOWN,
			[NotificationParameterEnum::TREND_DAYS_THRESHOLD->value => 30],
		);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertSame('https://example.com/webhook/trend-alert-default', $result);
	}

	public function testWebhookUrlPriceAlertWithMissingThreshold(): void
	{
		$mapping = [
			DiscordChannelEnum::TREND_ALERT_DEFAULT->value => 'https://example.com/webhook/trend-alert-default',
		];
		$notification = $this->createNotification(NotificationTypeEnum::PRICE_ALERT_UP);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertNull($result);
	}

	public function testWebhookUrlMissingMapping(): void
	{
		$mapping = [];
		$notification = $this->createNotification(NotificationTypeEnum::NEW_DIVIDEND);

		$service = new DiscordChannelService($mapping);
		$result = $service->getWebhookUrl($notification);

		$this->assertNull($result);
	}

	public function testPersonalMessagesUseOnlyRecipientsMappingInEitherOrder(): void
	{
		$service = new DiscordChannelService(
			['goals_update' => 'https://example.test/filip'],
			['alice' => ['goals_updates' => 'https://example.test/alice'],
				'bob' => ['goals_update' => 'https://example.test/bob']],
		);
		foreach (['alice', 'bob', 'bob', 'alice'] as $username) {
			self::assertSame(
				'https://example.test/' . $username,
				$service->getWebhookUrl($this->personalNotification($username)),
			);
		}

		self::assertNull($service->getWebhookUrl($this->personalNotification('unconfigured')));
		self::assertSame('https://example.test/filip', $service->getWebhookUrl($this->personalNotification('filip')));
	}

	public function testExplicitEmptyMappingDisablesLegacyRecipient(): void
	{
		$service = new DiscordChannelService(['goals_update' => 'https://example.test/filip'], ['filip' => []]);
		self::assertNull($service->getWebhookUrl($this->personalNotification('filip')));
	}

	public function testCanonicalNullDisablesLegacyGoalAlias(): void
	{
		$service = new DiscordChannelService([
			'goals_updates' => null,
			'goals_update' => 'https://example.test/filip',
		]);
		self::assertNull($service->getWebhookUrl($this->personalNotification('filip')));
	}

	#[TestWith([[], null])]
	#[TestWith([['trend_alert_default' => 'https://example.test/alice'], 'https://example.test/alice'])]
	#[TestWith([['trend_alert_default' => 'https://example.test/alice', 'trend_alert_7_days' => null], null])]
	#[TestWith([['trend_alert_7_days' => 'https://example.test/alice-7'], 'https://example.test/alice-7'])]
	/** @param array<string, string|null> $mapping */
	public function testPersonalTrendsNeverFallBackToSharedWebhook(array $mapping, string|null $expected): void
	{
		$service = new DiscordChannelService(
			['trend_alert_7_days' => 'https://example.test/shared', 'default' => 'https://example.test/default'],
			['alice' => $mapping],
		);
		$personal = $this->personalNotification('alice', NotificationTypeEnum::ASSET_TRENDS);
		$personal->addParameter(NotificationParameterEnum::TREND_DAYS_THRESHOLD, 7);
		self::assertSame($expected, $service->getWebhookUrl($personal));
		$shared = $this->createNotification(
			NotificationTypeEnum::ASSET_TRENDS,
			[NotificationParameterEnum::TREND_DAYS_THRESHOLD->value => 7],
		);
		self::assertSame('https://example.test/shared', $service->getWebhookUrl($shared));
	}

	private function personalNotification(
		string $username,
		NotificationTypeEnum $type = NotificationTypeEnum::GOALS_UPDATES,
	): Notification
	{
		$owner = $this->createStub(AppAdmin::class);
		$owner->method('getUsername')->willReturn($username);
		return new Notification($type, [], 'Personal notification', new ImmutableDateTime(), recipient: $owner);
	}

	/**
	 * @param array<string, int|string> $parameters
	 */
	private function createNotification(NotificationTypeEnum $typeEnum, array $parameters = []): Notification
	{
		$notification = $this->createStub(Notification::class);
		$notification->method('getRecipient')->willReturn(null);
		$notification->method('getNotificationTypeEnum')->willReturn($typeEnum);
		$notification->method('getParameter')->willReturnCallback(
			static fn (NotificationParameterEnum $key) => $parameters[$key->value] ?? null,
		);
		return $notification;
	}

}
