<?php

/**
 * Mink library card actions test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

use Behat\Mink\Element\Element;

/**
 * Mink library card actions test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    0
 */
final class LibraryCardsTest extends \VuFindTest\Integration\MinkTestCase
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
     * Mink support function: fill in the change password form.
     *
     * @param Element $page    Page element.
     * @param string  $name    Library card name.
     * @param string  $user    Username
     * @param string  $pass    Password
     * @param bool    $inModal Should we assume the login box is in a lightbox?
     * @param string  $prefix  Extra selector prefix
     *
     * @return void
     */
    protected function fillInLibraryCardForm(
        Element $page,
        $name,
        $user,
        $pass,
        $inModal = false,
        $prefix = '.form-edit-card '
    ) {
        $prefix = ($inModal ? '.modal-body ' : '') . $prefix;
        $this->findCssAndSetValue($page, $prefix . '[name="card_name"]', $name);
        $this->findCssAndSetValue($page, $prefix . '[name="username"]', $user);
        $this->findCssAndSetValue($page, $prefix . '[name="password"]', $pass);
    }

    /**
     * Test adding a library card.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testAddCards()
    {
        // Setup config
        $demoSettings = $this->getDemoIniOverrides();
        $demoSettings['Users'] = [
            'catuser1' => 'catpass1',
            'catuser2' => 'catpass2',
        ];
        $this->changeConfigs(
            [
                'Demo' => $demoSettings,
                'config' => [
                    'Catalog' => [
                        'driver' => 'Demo',
                        'library_cards' => true,
                    ],
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();

        // Create account
        $this->clickCss($page, '#loginOptions a');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Go to library cards page:
        $session->visit($this->getVuFindUrl('/LibraryCards/Home'));
        $this->waitForPageLoad($page);

        // Now click change password button:
        $this->clickCss($page, '.add-card span.icon-link__label');
        $this->waitForPageLoad($page);

        // Try to create a library card, but get the password wrong:
        $this->fillInLibraryCardForm($page, 'card 1', 'catuser1', 'bad');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCss($page, '.alert-danger')->getText()
        );

        // Create the card successfully:
        $this->fillInLibraryCardForm($page, 'card 1', 'catuser1', 'catpass1');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'card 1',
            $this->findCss($page, 'td')->getText()
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
