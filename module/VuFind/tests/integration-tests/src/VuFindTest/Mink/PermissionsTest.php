<?php

/**
 * Mink test class for permission behaviors.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink test class for permission behaviors.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class PermissionsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

    /**
     * Test that a default permission can be applied to all controllers and
     * configured to display a custom error message.
     *
     * @return void
     */
    public function testDefaultControllerPermissionWithCustomErrorMessage(): void
    {
        $this->changeConfigs(
            [
                'permissionBehavior' => [
                    'global' => [
                        'controllerAccess' => [
                            '*' => 'access.VuFindInterface',
                        ],
                    ],
                    'access.VuFindInterface' => [
                        'deniedControllerBehavior' => 'showMessage:test-error-message',
                    ],
                ],
                'permissions' => [
                    'access.VuFindInterface' => [
                        'permission' => 'access.VuFindInterface',
                        'require' => 'ANY',
                        'role' => 'loggedin',
                    ],
                ],
            ]
        );

        // Logged out user gets denied access and shown custom error message:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'test-error-message',
            $this->findCssAndGetText($page, '.alert-danger')
        );
        $this->assertEquals(
            'Error',
            $this->findCssAndGetText($page, '.breadcrumb .active')
        );

        // Create an account:
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Now that we're logged in, we should see search results:
        $session->visit($this->getVuFindUrl() . '/Search/Results');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Search Results',
            $this->findCssAndGetText($page, '.breadcrumb .active')
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }
}
