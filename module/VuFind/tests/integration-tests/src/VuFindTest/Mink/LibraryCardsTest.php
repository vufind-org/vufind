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
        string $name,
        string $user,
        string $pass,
        bool $inModal = false,
        string $prefix = '.form-edit-card '
    ): void {
        $prefix = ($inModal ? '.modal-body ' : '') . $prefix;
        $this->findCssAndSetValue($page, $prefix . '[name="card_name"]', $name);
        $this->findCssAndSetValue($page, $prefix . '[name="username"]', $user);
        $this->findCssAndSetValue($page, $prefix . '[name="password"]', $pass);
    }

    /**
     * Set up configuration for library card functionality.
     *
     * @param bool  $enabled              Are library cards enabled?
     * @param array $extraCatalogSettings Extra settings for Catalog section of config.ini
     *
     * @return void
     */
    protected function setUpLibraryCardConfigs(bool $enabled = true, $extraCatalogSettings = []): void
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
                    'Catalog' => $extraCatalogSettings + [
                        'driver' => 'Demo',
                        'library_cards' => $enabled,
                    ],
                ],
            ]
        );
    }

    /**
     * Test adding two library cards.
     *
     * @return void
     */
    public function testAddCards(): void
    {
        $this->setUpLibraryCardConfigs();
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

        // Now click add card button:
        $this->clickCss($page, '.add-card span.icon-link__label');
        $this->waitForPageLoad($page);

        // Try to create a library card, but get the password wrong:
        $this->fillInLibraryCardForm($page, 'card 1', 'catuser1', 'bad');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Invalid login -- please try again.',
            $this->findCssAndGetText($page, '.alert-danger')
        );

        // Create the card successfully:
        $this->fillInLibraryCardForm($page, 'card 1', 'catuser1', 'catpass1');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'card 1',
            $this->findCssAndGetText($page, 'tr:nth-child(2) td')
        );

        // Now click add card button to add a second card:
        $this->clickCss($page, '.add-card span.icon-link__label');
        $this->waitForPageLoad($page);
        $this->fillInLibraryCardForm($page, 'card 2', 'catuser2', 'catpass2');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'card 2',
            $this->findCssAndGetText($page, 'tr:nth-child(3) td')
        );
    }

    /**
     * Test that disabling the allowUserLogin setting disables the "add card" button.
     *
     * @return void
     */
    public function testAllowUserLoginDisablesButton()
    {
        $this->setUpLibraryCardConfigs(true, ['allowUserLogin' => false]);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/LibraryCards/Home'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);
        $this->assertEquals('Library Cards', $this->findCssAndGetText($page, 'h2'));
        $this->unfindCss($page, '.add-card span.icon-link__label');
    }

    /**
     * Test switching between cards on the profile page.
     *
     * @depends testAddCards
     *
     * @return void
     */
    public function testSwitchingCards(): void
    {
        $this->setUpLibraryCardConfigs();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // Confirm the presence of a library card selector on the page:
        $firstCard = $this->findCss($page, '#library_card option:nth-child(1)');
        $secondCard = $this->findCss($page, '#library_card option:nth-child(2)');
        $card2Value = $secondCard->getValue();
        $this->assertEquals('card 1', $firstCard->getText());
        $this->assertEquals('card 2', $secondCard->getText());

        // Check that the appropriate username is reflected in the output:
        $this->assertEquals(
            'Lib-catuser1',
            $this->findCssAndGetText($page, '.catalog-profile tr:nth-child(1) td:nth-child(2)')
        );

        // Switch to the second card; don't try to verify the set value because it
        // conflicts with the behavior of jumpMenu.
        $this->findCssAndSetValue($page, '#library_card', $card2Value, null, 6, false);
        $this->waitForPageLoad($page);

        // Check that the appropriate username is reflected in the output:
        $this->assertEquals(
            'Lib-catuser2',
            $this->findCssAndGetText($page, '.catalog-profile tr:nth-child(1) td:nth-child(2)')
        );
    }

    /**
     * Test that library cards are disabled by default.
     *
     * @depends testAddCards
     *
     * @return void
     */
    public function testCardsDisabledByDefault(): void
    {
        $this->setUpLibraryCardConfigs(false);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/MyResearch/Profile'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);
        // Confirm that we are on the profile page with no cards showing:
        $this->assertEquals('Your Profile', $this->findCssAndGetText($page, 'h2'));
        $this->unFindCss($page, '#library_card');
    }

    /**
     * Test adding a card that duplicates an existing username.
     *
     * @depends testAddCards
     *
     * @return void
     */
    public function testAddingDuplicateCardUsername(): void
    {
        $this->setUpLibraryCardConfigs();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/LibraryCards/Home'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.add-card span.icon-link__label');
        $this->waitForPageLoad($page);
        $this->fillInLibraryCardForm($page, 'card 2 repeat', 'catuser2', 'catpass2');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);

        $this->assertEquals(
            'Username is already in use in another library card',
            $this->findCssAndGetText($page, '.alert-danger')
        );
    }

    /**
     * Test editing a card.
     *
     * @depends testAddCards
     *
     * @return void
     */
    public function testEditingCard(): void
    {
        $this->setUpLibraryCardConfigs();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/LibraryCards/Home'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        $this->clickCss($page, 'tr:nth-child(2) a[title="Edit Library Card"]');
        $this->waitForPageLoad($page);
        $this->findCssAndSetValue($page, '[name="card_name"]', 'Edited Card');
        $this->clickCss($page, '.form-edit-card .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Edited Card',
            $this->findCssAndGetText($page, 'tr:nth-child(2) td')
        );
    }

    /**
     * Test deleting a card.
     *
     * @depends testEditingCard
     *
     * @return void
     */
    public function testDeletingCard(): void
    {
        $this->setUpLibraryCardConfigs();
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/LibraryCards/Home'));
        $page = $session->getPage();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // Confirm that second item in table is card 2
        $this->assertEquals(
            'card 2',
            $this->findCss($page, 'tr:nth-child(3) td')->getText()
        );

        // Click the delete button
        $button = $this->findCss($page, 'tr:nth-child(3)')->findLink('Delete');
        $button->click();
        $this->waitForPageLoad($page);
        $this->clickCss($page, $this->firstOpenDropdownMenuItemSelector);
        $this->waitForPageLoad($page);

        // Check for success message
        $this->assertEquals(
            'Library Card Deleted',
            $this->findCssAndGetText($page, '.alert-success')
        );

        // Check that the deleted card is now gone, but the other card still exists.
        $this->assertEquals(
            'Edited Card',
            $this->findCssAndGetText($page, 'tr:nth-child(2) td')
        );
        $this->unFindCss($page, 'tr:nth-child(3) td');
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
