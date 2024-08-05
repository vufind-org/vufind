<?php

/**
 * Shibboleth logout notification test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFind\Db\Service\ExternalSessionServiceInterface;

/**
 * Shibboleth logout notification test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ShibbolethLogoutNotificationTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\HttpRequestTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    /**
     * Test Shibboleth logout notification.
     *
     * @return void
     */
    public function testLogoutNotification()
    {
        $this->changeConfigs(
            [
                'permissions' => [
                    'api.ShibbolethLogoutNotification' => [
                        'permission' => 'access.api.ShibbolethLogoutNotification',
                        'require' => 'ANY',
                        'ipRange' => [
                            '127.0.0.1',
                            '::1',
                        ],
                    ],
                ],
            ]
        );

        // Do a search and make sure it's in history:
        $page = $this->performSearch('building:weird_ids.mrc');
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $this->findCss($page, 'table#recent-searches');

        // Add a session id mapping to external_session table:
        $sessionId = $session->getCookie('VUFIND_SESSION');
        $service = $this->getLiveDbServiceManager()->get(ExternalSessionServiceInterface::class);
        $service->addSessionMapping($sessionId, 'EXTERNAL_SESSION_ID');

        // Call the notification endpoint:
        $result = $this->httpPost(
            $this->getVuFindUrl() . '/soap/shiblogout',
            $this->getFixture('shibboleth/logout_notification.xml'),
            'application/xml'
        );
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(200, $result->getStatusCode());

        $session->visit($this->getVuFindUrl() . '/Search/History');
        $this->unFindCss($page, 'table#recent-searches');
    }
}
