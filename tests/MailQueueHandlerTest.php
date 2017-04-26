<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Activity\Tests;

use OCA\Activity\Extension\LegacyParser;
use OCA\Activity\MailQueueHandler;
use OCP\IL10N;
use OCP\L10N\IFactory;
use OCP\Activity\IEvent;
use OCP\IUserManager;
use OCP\Activity\IManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OC\Mail\Message;
use OCA\Activity\DataHelper;
use OCP\IURLGenerator;
use OCP\IDateTimeFormatter;
use OCP\IUser;

/**
 * Class MailQueueHandlerTest
 *
 * @group DB
 * @package OCA\Activity\Tests
 */
class MailQueueHandlerTest extends TestCase {
	/** @var MailQueueHandler */
	protected $mailQueueHandler;

	/** @var \PHPUnit_Framework_MockObject_MockObject|IMailer */
	protected $mailer;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $message;

	/** @var IUserManager */
	protected $oldUserManager;

	/** @var \PHPUnit_Framework_MockObject_MockObject|IUserManager */
	protected $userManager;

	/** @var \PHPUnit_Framework_MockObject_MockObject|IFactory */
	protected $lFactory;

	/** @var \PHPUnit_Framework_MockObject_MockObject|IManager */
	protected $activityManager;

	/** @var \PHPUnit_Framework_MockObject_MockObject|DataHelper */
	protected $dataHelper;

	/** @var \PHPUnit_Framework_MockObject_MockObject|LegacyParser */
	protected $legacyParser;

	protected function setUp() {
		parent::setUp();

		$app = self::getUniqueID('MailQueueHandlerTest');
		$this->userManager = $this->createMock(IUserManager::class);
		$this->lFactory = $this->createMock(IFactory::class);
		$this->legacyParser = $this->createMock(LegacyParser::class);

		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->prepare('INSERT INTO `*PREFIX*activity_mq` '
			. ' (`amq_appid`, `amq_subject`, `amq_subjectparams`, `amq_affecteduser`, `amq_timestamp`, `amq_type`, `amq_latest_send`) '
			. ' VALUES(?, ?, ?, ?, ?, ?, ?)');

		$query->execute(array($app, 'Test data', json_encode(['Param1']), 'user1', 150, 'phpunit', 152));
		$query->execute(array($app, 'Test data', json_encode(['Param1']), 'user1', 150, 'phpunit', 153));
		$query->execute(array($app, 'Test data', json_encode(['Param1']), 'user2', 150, 'phpunit', 150));
		$query->execute(array($app, 'Test data', json_encode(['Param1']), 'user2', 150, 'phpunit', 151));
		$query->execute(array($app, 'Test data', json_encode(['Param1']), 'user3', 150, 'phpunit', 154));
		$query->execute(array($app, 'Test data', json_encode(['Param1']), 'user3', 150, 'phpunit', 155));

		$event = $this->createMock(IEvent::class);
		$event->expects($this->any())
			->method('setApp')
			->willReturnSelf();
		$event->expects($this->any())
			->method('setType')
			->willReturnSelf();
		$event->expects($this->any())
			->method('setAffectedUser')
			->willReturnSelf();
		$event->expects($this->any())
			->method('setTimestamp')
			->willReturnSelf();
		$event->expects($this->any())
			->method('setSubject')
			->willReturnSelf();

		$this->activityManager = $this->createMock(IManager::class);
		$this->activityManager->expects($this->any())
			->method('generateEvent')
			->willReturn($event);
		$this->activityManager->expects($this->any())
			->method('getProviders')
			->willReturn([]);

		$this->legacyParser->expects($this->any())
			->method('parse')
			->willReturnArgument(1);

		$this->dataHelper = $this->createMock(DataHelper::class);
		$this->dataHelper->expects($this->any())
			->method('getParameters')
			->willReturn([]);

		$this->message = $this->createMock(Message::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->mailer->expects($this->any())
			->method('createMessage')
			->willReturn($this->message);
		$this->mailQueueHandler = new MailQueueHandler(
			$this->createMock(IDateTimeFormatter::class),
			$connection,
			$this->dataHelper,
			$this->mailer,
			$this->createMock(IURLGenerator::class),
			$this->userManager,
			$this->lFactory,
			$this->activityManager,
			$this->legacyParser
		);
	}

	protected function tearDown() {
		$query = \OC::$server->getDatabaseConnection()->prepare('DELETE FROM `*PREFIX*activity_mq` WHERE `amq_timestamp` < 500');
		$query->execute();

		parent::tearDown();
	}

	public function getAffectedUsersData()
	{
		return [
			[null, ['user2', 'user1', 'user3'], []],
			[5, ['user2', 'user1', 'user3'], []],
			[3, ['user2', 'user1', 'user3'], []],
			[2, ['user2', 'user1'], ['user3']],
			[1, ['user2'], ['user1', 'user3']],
		];
	}

	/**
	 * @dataProvider getAffectedUsersData
	 *
	 * @param int $limit
	 * @param array $affected
	 * @param array $untouched
	 */
	public function testGetAffectedUsers($limit, $affected, $untouched) {
		$maxTime = 200;

		$this->assertRemainingMailEntries($untouched, $maxTime, 'before doing anything');
		$users = $this->mailQueueHandler->getAffectedUsers($limit, $maxTime);
		$this->assertRemainingMailEntries($untouched, $maxTime, 'after getting the affected users');

		$this->assertEquals($affected, $users);
		foreach ($users as $user) {
			list($data, $skipped) = self::invokePrivate($this->mailQueueHandler, 'getItemsForUser', [$user, $maxTime]);
			$this->assertNotEmpty($data, 'Failed asserting that each user has a mail entry');
			$this->assertSame(0, $skipped);
		}
		$this->assertRemainingMailEntries($untouched, $maxTime, 'after getting the affected items');

		$this->mailQueueHandler->deleteSentItems($users, $maxTime);

		foreach ($users as $user) {
			list($data, $skipped) = self::invokePrivate($this->mailQueueHandler, 'getItemsForUser', [$user, $maxTime]);
			$this->assertEmpty($data, 'Failed to assert that all entries for the affected users have been deleted');
			$this->assertSame(0, $skipped);
		}
		$this->assertRemainingMailEntries($untouched, $maxTime, 'after deleting the affected items');
	}

	public function testGetItemsForUser() {
		list($data, $skipped) = self::invokePrivate($this->mailQueueHandler, 'getItemsForUser', ['user1', 200]);
		$this->assertCount(2, $data, 'Failed to assert the user has 2 entries');
		$this->assertSame(0, $skipped);

		$connection = \OC::$server->getDatabaseConnection();
		$query = $connection->prepare('INSERT INTO `*PREFIX*activity_mq` '
			. ' (`amq_appid`, `amq_subject`, `amq_subjectparams`, `amq_affecteduser`, `amq_timestamp`, `amq_type`, `amq_latest_send`) '
			. ' VALUES(?, ?, ?, ?, ?, ?, ?)');

		$app = $this->getUniqueID('MailQueueHandlerTest');
		for ($i = 0; $i < 15; $i++) {
			$query->execute(array($app, 'Test data', 'Param1', 'user1', 150, 'phpunit', 160 + $i));
		}

		list($data, $skipped) = self::invokePrivate($this->mailQueueHandler, 'getItemsForUser', ['user1', 200, 5]);
		$this->assertCount(5, $data, 'Failed to assert the user has 2 entries');
		$this->assertSame(12, $skipped);
	}

	public function testSendEmailToUser() {
		$maxTime = 200;
		$user = 'user2';
		$userDisplayName = 'user two';
		$email = $user . '@localhost';

		$template = $this->createMock(IEMailTemplate::class);
		$this->mailer->expects($this->once())
			->method('send')
			->with($this->message);
		$this->mailer->expects($this->once())
			->method('createEMailTemplate')
			->willReturn($template);

		$template->expects($this->once())
			->method('addHeader');
		$template->expects($this->once())
			->method('addHeading');
		$template->expects($this->once())
			->method('addBodyText');
		$template->expects($this->once())
			->method('addFooter');

		$this->message->expects($this->once())
			->method('setTo')
			->with([$email => $userDisplayName]);
		$this->message->expects($this->once())
			->method('setSubject');
		$this->message->expects($this->once())
			->method('setPlainBody');
		$this->message->expects($this->once())
			->method('setFrom');

		$userObject = $this->createMock(IUser::class);
		$userObject->expects($this->any())
			->method('getDisplayName')
			->willReturn($userDisplayName);
		$this->userManager->expects($this->any())
			->method('get')
			->willReturnMap([
				[$user, $userObject],
				[$user . $user, null],
			]);
		$this->lFactory->expects($this->once())
			->method('get')
			->with('activity', 'en')
			->willReturn($this->getMockBuilder(IL10N::class)->getMock());

		$this->activityManager->expects($this->exactly(2))
			->method('setCurrentUserId')
			->withConsecutive(
				[$user],
				[null]
			);

		$users = $this->mailQueueHandler->getAffectedUsers(1, $maxTime);
		$this->assertEquals([$user], $users);
		$this->mailQueueHandler->sendEmailToUser($user, $email, 'en', 'UTC', $maxTime);

		// Invalid user, no object no email
		$this->mailQueueHandler->sendEmailToUser($user . $user, $email, 'en', 'UTC', $maxTime);
	}

	/**
	 * @param array $users
	 * @param int $maxTime
	 * @param string $explain
	 */
	protected function assertRemainingMailEntries(array $users, $maxTime, $explain) {
		if (!empty($untouched)) {
			foreach ($users as $user) {
				list($data,) = self::invokePrivate($this->mailQueueHandler, 'getItemsForUser', [$user, $maxTime]);
				$this->assertNotEmpty(
					$data,
					'Failed asserting that the remaining user ' . $user. ' still has mails in the queue ' . $explain
				);
			}
		}
	}
}
