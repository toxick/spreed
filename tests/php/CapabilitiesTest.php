<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Tests\Unit;

use OCA\Talk\Capabilities;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Config;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Capabilities\IPublicCapability;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CapabilitiesTest extends TestCase {

	/** @var IConfig|MockObject */
	protected $serverConfig;
	/** @var Config|MockObject */
	protected $talkConfig;
	/** @var CommentsManager|MockObject */
	protected $commentsManager;
	/** @var IUserSession|MockObject */
	protected $userSession;
	protected ?array $baseFeatures = null;

	public function setUp(): void {
		parent::setUp();
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->commentsManager = $this->createMock(CommentsManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->commentsManager->expects($this->any())
			->method('supportReactions')
			->willReturn(true);

		$this->baseFeatures = [
			'audio',
			'video',
			'chat-v2',
			'conversation-v4',
			'guest-signaling',
			'empty-group-room',
			'guest-display-names',
			'multi-room-users',
			'favorites',
			'last-room-activity',
			'no-ping',
			'system-messages',
			'delete-messages',
			'mention-flag',
			'in-call-flags',
			'conversation-call-flags',
			'notification-levels',
			'invite-groups-and-mails',
			'locked-one-to-one-rooms',
			'read-only-rooms',
			'listable-rooms',
			'chat-read-marker',
			'chat-unread',
			'webinary-lobby',
			'start-call-flag',
			'chat-replies',
			'circles-support',
			'force-mute',
			'sip-support',
			'sip-support-nopin',
			'chat-read-status',
			'phonebook-search',
			'raise-hand',
			'room-description',
			'rich-object-sharing',
			'temp-user-avatar-api',
			'geo-location-sharing',
			'voice-message-sharing',
			'signaling-v3',
			'publishing-permissions',
			'clear-history',
			'direct-mention-flag',
			'notification-calls',
			'conversation-permissions',
			'rich-object-list-media',
			'rich-object-delete',
			'unified-search',
			'chat-permission',
			'silent-send',
			'silent-call',
			'send-call-notification',
			'reactions',
		];
	}

	public function testGetCapabilitiesGuest(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->commentsManager,
			$this->userSession
		);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->talkConfig->expects($this->never())
			->method('isDisabledForUser');

		$this->serverConfig->expects($this->any())
			->method('getAppValue')
			->willReturnMap([
				['spreed', 'has_reference_id', 'no', 'no'],
				['spreed', 'max-gif-size', '3145728', '200000'],
				['spreed', 'start_calls', Room::START_CALL_EVERYONE, Room::START_CALL_EVERYONE],
				['spreed', 'session-ping-limit', '200', '200'],
			]);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([
			'spreed' => [
				'features' => $this->baseFeatures,
				'config' => [
					'attachments' => [
						'allowed' => false,
					],
					'call' => [
						'enabled' => true,
					],
					'chat' => [
						'max-length' => 32000,
						'read-privacy' => 0,
					],
					'conversations' => [
						'can-create' => false,
					],
					'previews' => [
						'max-gif-size' => 200000,
					],
					'signaling' => [
						'session-ping-limit' => 200,
					],
				],
			],
		], $capabilities->getCapabilities());
	}

	public function dataGetCapabilitiesUserAllowed(): array {
		return [
			[true, false, Participant::PRIVACY_PRIVATE],
			[false, true, Participant::PRIVACY_PUBLIC],
		];
	}

	/**
	 * @dataProvider dataGetCapabilitiesUserAllowed
	 * @param bool $isNotAllowed
	 * @param bool $canCreate
	 * @param int $readPrivacy
	 */
	public function testGetCapabilitiesUserAllowed(bool $isNotAllowed, bool $canCreate, int $readPrivacy): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->commentsManager,
			$this->userSession
		);

		$user = $this->createMock(IUser::class);
		$user->expects($this->atLeastOnce())
			->method('getUID')
			->willReturn('uid');
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->talkConfig->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(false);

		$this->talkConfig->expects($this->once())
			->method('getAttachmentFolder')
			->with('uid')
			->willReturn('/Talk');

		$this->talkConfig->expects($this->once())
			->method('isNotAllowedToCreateConversations')
			->with($user)
			->willReturn($isNotAllowed);

		$this->talkConfig->expects($this->once())
			->method('getUserReadPrivacy')
			->with('uid')
			->willReturn($readPrivacy);

		$this->serverConfig->expects($this->any())
			->method('getAppValue')
			->willReturnMap([
				['spreed', 'has_reference_id', 'no', 'yes'],
				['spreed', 'max-gif-size', '3145728', '200000'],
				['spreed', 'start_calls', Room::START_CALL_EVERYONE, Room::START_CALL_NOONE],
				['spreed', 'session-ping-limit', '200', '50'],
			]);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$data = $capabilities->getCapabilities();
		$this->assertSame([
			'spreed' => [
				'features' => array_merge(
					$this->baseFeatures, [
						'chat-reference-id'
					]
				),
				'config' => [
					'attachments' => [
						'allowed' => true,
						'folder' => '/Talk',
					],
					'call' => [
						'enabled' => false,
					],
					'chat' => [
						'max-length' => 32000,
						'read-privacy' => $readPrivacy,
					],
					'conversations' => [
						'can-create' => $canCreate,
					],
					'previews' => [
						'max-gif-size' => 200000,
					],
					'signaling' => [
						'session-ping-limit' => 50,
					],
				],
			],
		], $data);

		foreach ($data['spreed']['features'] as $feature) {
			$this->assertCapabilityIsDocumented("`$feature`");
		}

		foreach ($data['spreed']['config'] as $feature => $configs) {
			foreach ($configs as $config => $data) {
				$this->assertCapabilityIsDocumented("`config => $feature => $config`");
			}
		}
	}

	protected function assertCapabilityIsDocumented(string $capability): void {
		$docs = file_get_contents(__DIR__ . '/../../docs/capabilities.md');
		self::assertStringContainsString($capability, $docs, 'Asserting that capability ' . $capability . ' is documented');
	}

	public function testGetCapabilitiesUserDisallowed(): void {
		$capabilities = new Capabilities(
			$this->serverConfig,
			$this->talkConfig,
			$this->commentsManager,
			$this->userSession
		);

		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->talkConfig->expects($this->once())
			->method('isDisabledForUser')
			->with($user)
			->willReturn(true);

		$this->assertInstanceOf(IPublicCapability::class, $capabilities);
		$this->assertSame([], $capabilities->getCapabilities());
	}
}
