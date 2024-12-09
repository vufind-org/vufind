<?php

/**
 * Mink "private user" test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink "private user" test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class PrivateUserTest extends \VuFindTest\Integration\MinkTestCase
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
     * Move the current page to a record by performing a search.
     *
     * @param string $query Search query to perform.
     *
     * @return Element
     */
    protected function gotoRecord(string $query = 'Dewey'): Element
    {
        $page = $this->performSearch($query);
        $this->clickCss($page, '.result a.title');
        return $page;
    }

    /**
     * Set up private user configuration.
     *
     * @return void
     */
    protected function setUpPrivateUser(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'privacy' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Test that comments are disabled in private user mode.
     *
     * @return void
     */
    public function testCommentsDisabled(): void
    {
        // Set up configs:
        $this->setUpPrivateUser();
        // Go to a record view
        $page = $this->gotoRecord();
        // Comment control should not be present
        $this->unfindCss($page, '.record-tabs .usercomments a');
    }

    /**
     * Test that tags are disabled in private user mode.
     *
     * @return void
     */
    public function testTagsDisabled(): void
    {
        // Set up configs:
        $this->setUpPrivateUser();
        // Go to a record view
        $page = $this->gotoRecord();
        // Click to add tag
        $this->unfindCss($page, '.tag-record');
    }

    /**
     * Test that login does not create database data.
     *
     * @return void
     */
    public function testLoginDoesNotAddUserToDatabase(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Authentication' => [
                        'method' => 'SimulatedSSO',
                        'privacy' => true,
                    ],
                ],
                'SimulatedSSO' => [
                    'General' => [
                        'username' => 'ssofakeuser1',
                    ],
                ],
            ]
        );

        // Login
        $page = $this->gotoRecord();
        $this->clickCss($page, '#loginOptions a');
        // Log out
        $this->clickCss($page, '.logoutOptions a.logout');
        // Check that login link is back
        $this->assertNotEmpty($this->findCss($page, '#loginOptions a'));
        // Assert that nothing was added to the user database:
        static::failIfDataExists('User data should not have been created in private user mode.');
    }
}
