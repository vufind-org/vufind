<?php

/**
 * Mink ChoiceAuth test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink ChoiceAuth test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ChoiceAuthTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

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
     * Get config.ini override settings for testing ChoiceAuth.
     *
     * @return array
     */
    public function getConfigIniOverrides()
    {
        return [
            'Catalog' => [
                'driver' => 'Demo',
            ],
            'Authentication' => [
                'method' => 'ChoiceAuth',
            ],
            'ChoiceAuth' => [
                'choice_order' => 'Database, ILS',
            ],
        ];
    }

    /**
     * Get config.ini override settings for testing ChoiceAuth with SSO.
     *
     * @return array
     */
    public function getConfigIniSSOOverrides()
    {
        return [
            'ChoiceAuth' => [
                'choice_order' => 'ILS, SimulatedSSO',
            ],
        ];
    }

    /**
     * Get Demo.ini override settings for testing ILS functions.
     *
     * @param string $bibId Bibliographic record ID to create fake item info for.
     *
     * @return array
     */
    public function getDemoIniOverrides($bibId = 'testsample1')
    {
        return [
            'Users' => ['catuser' => 'catpass'],
        ];
    }

    /**
     * Get SimulatedSSO.ini override settings for testing ChoiceAuth with SSO.
     *
     * @return array
     */
    public function getSimulatedSSOIniOverrides()
    {
        return [
            'General' => [
                'username' => 'ssofakeuser1',
            ],
        ];
    }

    /**
     * Test creating a DB user....
     *
     * @return void
     */
    public function testCreateDatabaseUser()
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Create account
        $element = $this->findCss($page, '#loginOptions a');
        $this->assertEquals('Login', $element->getText());
        $element->click();
        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, 'input.btn.btn-primary');

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');

        // Log back in to confirm that creation worked
        $element = $this->findCss($page, '#loginOptions a');
        $this->assertEquals('Login', $element->getText());
        $element->click();
        $this->fillInLoginForm($page, 'username1', 'test', true, '.authmethod0 ');
        $this->submitLoginForm($page, true, '.authmethod0 ');

        // Log out again to confirm that login worked
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Test user profile action.
     *
     * @return void
     */
    public function testProfile()
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Go to user profile screen:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/MyResearch/Profile');
        $page = $session->getPage();

        // Log in
        $this->fillInLoginForm($page, 'catuser', 'catpass', false, '.authmethod1 ');
        $this->submitLoginForm($page, false, '.authmethod1 ');

        // Confirm that demo driver expected values are present:
        $texts = [
            'Lib-catuser', 'Somewhere...', 'Over the Rainbow',
        ];
        foreach ($texts as $text) {
            $this->assertTrue($this->hasElementsMatchingText($page, 'td', $text));
        }
    }

    /**
     * Test login on record page with ILS and SSO authentication
     *
     * @return void
     */
    public function testRecordPageWithILSAndSSO()
    {
        // Set up configs and session
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniSSOOverrides() + $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
                'SimulatedSSO' => $this->getSimulatedSSOIniOverrides(),
            ]
        );
        $recordUrl = $this->getVuFindUrl() . '/Record/testsample1';
        $session = $this->getMinkSession();
        $session->visit($recordUrl);
        $page = $session->getPage();

        // Click login
        $this->clickCss($page, '#loginOptions a');

        // login with ILS
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'catuser', 'catpass', false, '.authmethod0 ');
        $this->submitLoginForm($page, false, '.authmethod0 ');

        // Check that we're still on the same page after login
        $this->findCss($page, '.logoutOptions');
        $this->assertEquals($recordUrl, $this->getCurrentUrlWithoutSid());

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');

        // Click login
        $this->clickCss($page, '#loginOptions a');

        // Login with SSO
        $this->assertEquals(
            'Institutional Login',
            $this->findCssAndGetText($page, '.modal-body .authmethod1 .btn.btn-link')
        );
        $this->clickCss($page, '.modal-body .btn.btn-link');

        // Check that we're still on the same page after login
        $this->findCss($page, '.logoutOptions');
        $this->assertEquals($recordUrl, $this->getCurrentUrlWithoutSid());

        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'catuser', 'ssofakeuser1']);
    }
}
