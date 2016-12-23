<?php
/**
 * Mink ChoiceAuth test class.
 *
 * PHP version 5
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
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ChoiceAuthTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        return static::failIfUsersExist();
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
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
     * Test creating a DB user....
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
        $this->snooze();
        $this->findCss($page, '.createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->findCss($page, 'input.btn.btn-primary')->click();
        $this->snooze();

        // Log out
        $this->findCss($page, '.logoutOptions a.logout')->click();
        $this->snooze();

        // Log back in to confirm that creation worked
        $element = $this->findCss($page, '#loginOptions a');
        $this->assertEquals('Login', $element->getText());
        $element->click();
        $this->fillInLoginForm($page, 'username1', 'test', true, '.authmethod0 ');
        $this->submitLoginForm($page, true, '.authmethod0 ');

        // Log out again to confirm that login worked
        $this->findCss($page, '.logoutOptions a.logout')->click();
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
            'Lib-catuser', 'Somewhere...', 'Over the Rainbow'
        ];
        foreach ($texts as $text) {
            $this->assertTrue($this->hasElementsMatchingText($page, 'td', $text));
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1', 'catuser']);
    }
}
