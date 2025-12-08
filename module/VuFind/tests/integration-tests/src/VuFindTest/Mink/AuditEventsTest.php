<?php

/**
 * Mink audit event test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

/**
 * Mink audit event test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class AuditEventsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;
    use \VuFindTest\Feature\DemoDriverTestTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Test audit events disabled.
     *
     * @return void
     */
    public function testEventsDisabled(): void
    {
        // Setup config
        $this->changeConfigs(
            [
                'Demo' => [
                    'Users' => ['username1' => 'catpass'],
                ],
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                    'Authentication' => [
                        'method' => 'ILS',
                    ],
                    'Logging' => [
                        'log_audit_events' => '',
                    ],
                ],
            ]
        );

        // Purge events:
        $eventService = $this->getDbService(AuditEventServiceInterface::class);
        $eventService->purgeEvents();

        // Log in:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#login_ILS_username', 'username1');
        $this->findCssAndSetValue($page, '#login_ILS_password', 'catpass');
        $this->clickCss($page, 'input.btn.btn-primary');

        // Log out:
        $this->clickCss($page, '.logoutOptions a.logout');

        // Check events:
        $events = $eventService->getEvents();
        $this->assertEmpty($events);
    }

    /**
     * Test login events.
     *
     * @return void
     */
    public function testLoginEvents(): void
    {
        // Setup config
        $this->changeConfigs(
            [
                'Demo' => [
                    'Users' => ['username2' => 'catpass'],
                ],
                'config' => [
                    'Catalog' => ['driver' => 'Demo'],
                    'Authentication' => [
                        'method' => 'ILS',
                        'account_deletion' => true,
                    ],
                    'Logging' => [
                        'log_audit_events' => 'ils,user',
                    ],
                ],
            ]
        );

        // Purge events:
        $eventService = $this->getDbService(AuditEventServiceInterface::class);
        $eventService->purgeEvents();

        // Log in:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#login_ILS_username', 'username2');
        $this->findCssAndSetValue($page, '#login_ILS_password', 'catpass');
        $this->clickCss($page, 'input.btn.btn-primary');

        // Log out:
        $this->clickCss($page, '.logoutOptions a.logout');

        // Log in again:
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $this->findCssAndSetValue($page, '#login_ILS_username', 'username2');
        $this->findCssAndSetValue($page, '#login_ILS_password', 'catpass');
        $this->clickCss($page, 'input.btn.btn-primary');

        // Delete the account:
        $this->clickCss($page, '.fa-trash-o');
        $this->clickCss($page, '.modal #delete-account-submit');
        $this->waitForPageLoad($page);

        // Check events:
        $eventService = $this->getDbService(AuditEventServiceInterface::class);
        $events = $eventService->getEvents(sort: ['date ASC']);

        $expectedEvents = [
            [
                'user',
                'login',
                'username2',
                null,
                '{"main_method":"ILS","delegate_method":false,"request":{"username":"username2","password":"***",'
                . '"auth_method":"ILS","csrf":"***","processLogin":"Login"},'
                . '"__method":"VuFind\\\\Auth\\\\Manager::login"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'ils_login',
                'username2',
                null,
                '{"cat_username":"username2","__method":"VuFind\\\\Auth\\\\Manager::login"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'update',
                'username2',
                null,
                '{"auth_method":"ils","last_login":"<datetime>","__method":"VuFind\\\\Auth\\\\Manager::updateUser"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'logout',
                'username2',
                'logout',
                '{"__method":"VuFind\\\\Auth\\\\Manager::clearLoginState"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'login',
                'username2',
                null,
                '{"main_method":"ILS","delegate_method":false,"request":{"username":"username2","password":"***",'
                . '"auth_method":"ILS","csrf":"***","processLogin":"Login"},'
                . '"__method":"VuFind\\\\Auth\\\\Manager::login"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'update',
                'username2',
                null,
                '{"auth_method":"ils","last_login":"<datetime>","__method":"VuFind\\\\Auth\\\\Manager::updateUser"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'delete',
                'username2',
                null,
                '{"user_id":<userid>,"__method":"VuFind\\\\Controller\\\\MyResearchController::deleteAccountAction"}',
                true,
                true,
                true,
                true,
            ],
            [
                'user',
                'logout',
                null,
                'logout',
                '{"__method":"VuFind\\\\Auth\\\\Manager::clearLoginState"}',
                true,
                true,
                true,
                true,
            ],
        ];

        $eventData = array_map(
            function ($event) {
                $data = preg_replace(
                    '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
                    '<datetime>',
                    json_encode($event->getData())
                );
                $data = preg_replace('/"user_id":\d+/', '"user_id":<userid>', $data);
                return [
                    $event->getType(),
                    $event->getSubType(),
                    $event->getUsername(),
                    $event->getMessage(),
                    null !== $event->getSessionId(),
                    null !== $event->getClientIp(),
                    null !== $event->getServerIp(),
                    null !== $event->getServerName(),
                    $data,
                ];
            },
            $events
        );

        $this->assertEquals($expectedEvents, $eventData);

        // Try another event search:
        $events = $eventService->getEvents(username: 'username2', type: 'user', subtype: 'login');
        $this->assertCount(2, $events);
    }

    /**
     * Test custom events.
     *
     * @return void
     */
    public function testCustomEvents(): void
    {
        // Setup config
        $this->changeConfigs(
            [
                'config' => [
                    'Logging' => [
                        'log_audit_events' => 'ils,user,custom',
                    ],
                ],
            ]
        );

        // Get event service:
        $eventService = $this->getDbService(AuditEventServiceInterface::class);
        $this->assertInstanceOf(AuditEventServiceInterface::class, $eventService);

        // Purge events:
        $eventService->purgeEvents();
        $this->assertEmpty($eventService->getEvents());

        // Add an event with built-in type:
        $eventService->addEvent(
            AuditEventType::ILS,
            AuditEventSubtype::SaveSearch,
            null,
            'Standard',
            ['foo' => 'bar']
        );

        // Add a custom event:
        $eventService->addEvent(
            'custom',
            'foobar',
            null,
            'Custom',
            ['foo' => 'bar']
        );

        // Add a custom event that should not be logged:
        $eventService->addEvent(
            'disabled',
            'foobar',
            null,
            'Disabled event',
            ['foo' => 'bar']
        );

        // Check results:
        $events = $eventService->getEvents(type: AuditEventType::ILS);
        $this->assertCount(1, $events);
        $this->assertEquals('Standard', $events[0]->getMessage());

        $events = $eventService->getEvents(type: 'custom');
        $this->assertCount(1, $events);
        $this->assertEquals('Custom', $events[0]->getMessage());

        $this->assertEmpty($eventService->getEvents(type: 'disabled'));
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'username2']);
    }
}
