<?php

/**
 * Mink SSO test class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink SSO test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class SsoTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\HttpRequestTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;

    /**
     * Get config.ini override settings for testing SSO login.
     *
     * @return array
     */
    public function getConfigIniOverrides(): array
    {
        return [
            'config' => [
                'Authentication' => [
                    'method' => 'SimulatedSSO',
                ],
            ],
            'SimulatedSSO' => [
                'General' => [
                    'username' => 'ssofakeuser1',
                ],
            ],
        ];
    }

    /**
     * Data provider for testLogin()
     *
     * @return array[]
     */
    public static function loginConfigProvider(): array
    {
        return [
            'with cat_username mapping' => [ // test for regression of #3992
                [
                    'General' => [
                        'attributes' => [
                            'cat_username' => 'foo',
                        ],
                    ],
                ],
            ],
            'defaults' => [],
        ];
    }

    /**
     * Test SSO login
     *
     * @param array $extraSsoConfigs Extra configurations for SimulatedSSO.ini
     *
     * @return void
     *
     * @dataProvider loginConfigProvider
     */
    public function testLogin(array $extraSsoConfigs = []): void
    {
        // Set up configs
        $configs = $this->getConfigIniOverrides();
        $configs['SimulatedSSO'] = array_merge_recursive($configs['SimulatedSSO'], $extraSsoConfigs);
        $this->changeConfigs($configs);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Login
        $this->clickCss($page, '#loginOptions a');

        // Log out
        $this->logoutAndAssertSuccess();
    }

    /**
     * SSO login in lightbox
     *
     * @return void
     */
    public function testLightboxLogin(): void
    {
        // Set up configs
        $this->changeConfigs($this->getConfigIniOverrides());
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Record/testsample1');
        $page = $session->getPage();

        // Try to save record to list
        $this->clickCss($page, '.record-nav .save-record');

        // Login in lightbox
        $this->assertEquals('Institutional Login', $this->findCssAndGetText($page, '.modal-body .btn.btn-link'));
        $this->clickCss($page, '.modal-body .btn.btn-link');

        // Check if save form is in lightbox
        $this->waitForPageLoad($page);
        $this->assertLightboxTitle($page, 'Add Journal of rational emotive therapy : to saved items');

        // Close lightbox
        $this->closeLightbox($page);

        // Check that we are still on the record page
        $this->assertEquals(
            'Journal of rational emotive therapy : the journal of the Institute for Rational-Emotive Therapy.',
            $this->findCssAndGetText($page, '.record .media-body h1')
        );

        // Log out
        $this->logoutAndAssertSuccess();
    }

    /**
     * Test SSO external logout
     *
     * @return void
     */
    public function testExternalLogout(): void
    {
        // Set up configs
        $this->changeConfigs(
            $this->getConfigIniOverrides()
            + [
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
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Login
        $this->clickCss($page, '#loginOptions a');

        // Check that logout link is visible:
        $this->findCss($page, '.logoutOptions a.logout');

        // Call the notification endpoint:
        $result = $this->httpPost(
            $this->getVuFindUrl() . '/soap/shiblogout',
            $this->getFixture('shibboleth/logout_notification.xml'),
            'application/xml'
        );
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(200, $result->getStatusCode());

        // Check that login link is back:
        $session->reload();
        $this->assertNotEmpty($this->findCss($page, '#loginOptions a'));
    }

    /**
     * Logs out on the current page and checks if logout was successful
     *
     * @return void
     */
    protected function logoutAndAssertSuccess(): void
    {
        $session = $this->getMinkSession();
        $page = $session->getPage();

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');

        // Check that login link is back
        $this->assertNotEmpty($this->findCss($page, '#loginOptions a'));
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['ssofakeuser1']);
    }
}
