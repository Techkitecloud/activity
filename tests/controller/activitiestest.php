<?php

/**
 * ownCloud
 *
 * @author Joas Schilling
 * @copyright 2015 Joas Schilling nickvergessen@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Activity\Tests\Controller;

use OCA\Activity\Controller\Activities;
use OCA\Activity\Tests\TestCase;
use OCP\Template;

class ActivitiesTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $request;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $data;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $display;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $helper;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $navigation;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $userSettings;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $dateTimeFormatter;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $preview;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $view;

	/** @var \OCP\IL10N */
	protected $l10n;

	/** @var Activities */
	protected $controller;

	protected function setUp() {
		parent::setUp();

		$this->data = $this->getMockBuilder('OCA\Activity\Data')
			->disableOriginalConstructor()
			->getMock();
		$this->display = $this->getMockBuilder('OCA\Activity\Display')
			->disableOriginalConstructor()
			->getMock();
		$this->helper = $this->getMockBuilder('OCA\Activity\GroupHelper')
			->disableOriginalConstructor()
			->getMock();
		$this->navigation = $this->getMockBuilder('OCA\Activity\Navigation')
			->disableOriginalConstructor()
			->getMock();
		$this->userSettings = $this->getMockBuilder('OCA\Activity\UserSettings')
			->disableOriginalConstructor()
			->getMock();
		$this->dateTimeFormatter = $this->getMockBuilder('OCP\IDateTimeFormatter')
			->disableOriginalConstructor()
			->getMock();
		$this->preview = $this->getMockBuilder('OCP\IPreview')
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator = $this->getMockBuilder('OCP\IURLGenerator')
			->disableOriginalConstructor()
			->getMock();
		$this->view = $this->getMockBuilder('OC\Files\View')
			->disableOriginalConstructor()
			->getMock();

		$this->request = $this->getMock('OCP\IRequest');

		$this->controller = new Activities(
			'activity',
			$this->request,
			$this->data,
			$this->display,
			$this->helper,
			$this->navigation,
			$this->userSettings,
			$this->dateTimeFormatter,
			$this->preview,
			$this->urlGenerator,
			$this->view,
			'test'
		);
	}

	public function testShowList() {
		$template = new Template('activity', 'stream.app.navigation', '');
		$template->assign('activeNavigation', 'all');
		$template->assign('navigations', []);
		$template->assign('rssLink', '');
		$this->navigation->expects($this->any())
			->method('getTemplate')
			->willReturn($template);

		$templateResponse = $this->controller->showList();
		$this->assertInstanceOf('\OCP\AppFramework\Http\TemplateResponse', $templateResponse, 'Asserting type of return is \OCP\AppFramework\Http\TemplateResponse');

		$renderedResponse = $templateResponse->render();
		$this->assertNotEmpty($renderedResponse);
	}

	public function testFetch() {
		$this->data->expects($this->any())
			->method('read')
			->willReturn([]);

		/** @var \OCP\AppFramework\Http\JSONResponse $response */
		$response = $this->controller->fetch(1);
		$this->assertInstanceOf('\OCP\AppFramework\Http\JSONResponse', $response, 'Asserting type of return is \OCP\AppFramework\Http\TemplateResponse');

		$this->assertSame([], $response->getData());
	}
}
