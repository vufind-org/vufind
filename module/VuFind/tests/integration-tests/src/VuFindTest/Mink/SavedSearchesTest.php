<?php

/**
 * Mink saved searches test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * Mink saved searches test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class SavedSearchesTest extends \VuFindTest\Integration\MinkTestCase
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
     * Click on the "Save Search" link in a search result set (or fail trying).
     *
     * @param Element $page Current page
     *
     * @return void
     */
    protected function clickSaveLink(Element $page): void
    {
        $links = $page->findAll('css', '.searchtools a');
        foreach ($links as $link) {
            if (
                $this->checkVisibility($link)
                && str_contains($link->getHtml(), 'Save Search')
            ) {
                $link->click();
                return;
            }
        }

        $this->fail('Could not find save link.');
    }

    /**
     * Test saving and clearing a search.
     *
     * @return void
     */
    public function testSaveSearch(): void
    {
        $page = $this->performSearch('test');
        $this->clickSaveLink($page);
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, 'input.btn.btn-primary');

        $this->assertEquals(
            'Search saved successfully.',
            $this->findCssAndGetText($page, '.alert.alert-success')
        );
    }

    /**
     * Assert that the search history contains the provided list of searches (and
     * nothing else).
     *
     * @param string[] $expected Array of search strings
     * @param Element  $page     Page object to check
     *
     * @return void
     */
    protected function assertSavedSearchList(array $expected, Element $page): void
    {
        // Pull all the links from the search history table and format them into
        // a string:
        $saved = $page->findAll('css', '#saved-searches td a');
        $callback = function ($link) {
            return trim($link->getText());
        };
        $linkText = implode("\n", array_map($callback, $saved));

        // Each expected search link should have a corresponding Delete link; create
        // an expectation accordingly:
        $expectedCallback = function ($link) {
            return "$link\nDelete";
        };
        $expectedLinkText = implode("\n", array_map($expectedCallback, $expected));

        // Compare the expected and actual strings:
        $this->assertEquals($expectedLinkText, $linkText);
    }

    /**
     * Test that saving a search while logging in does not create a duplicate.
     *
     * @depends testSaveSearch
     *
     * @return void
     */
    public function testSavedSearchDeduplication(): void
    {
        // Perform the same search that was already done in testSaveSearch above,
        // prior to logging in...
        $page = $this->performSearch('test');
        $this->clickSaveLink($page);
        $this->waitForPageLoad($page);

        // When we hit save, we'll be prompted to log in...
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // We want to be sure that this does not create two copies of the same search
        // in our search history.
        $this->findAndAssertLink($page, 'Search History')->click();
        $this->waitForPageLoad($page);
        $this->assertSavedSearchList(['test'], $page);
    }

    /**
     * Test search history.
     *
     * @depends testSaveSearch
     *
     * @return void
     */
    public function testSearchHistory(): void
    {
        // Use "foo \ bar" as our search because the backslash has been known
        // to cause problems in some situations (e.g. PostgreSQL database with
        // incorrect escaping); this allows us to catch regressions for a few
        // different problems in a single test.
        $page = $this->performSearch('foo \ bar');
        $this->findAndAssertLink($page, 'Search History')->click();
        // We should see our "foo \ bar" search in the history, and a login link
        // under saved searches because we are logged out:
        $this->assertEquals(
            'foo \ bar',
            $this->findAndAssertLink($page, 'foo \ bar')->getText()
        );
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'a', 'log in')
        );
        $this->waitForPageLoad($page);
        $this->assertNull($page->findLink('test'));

        // Now log in and see if our saved search shows up (without making the
        // unsaved search go away):
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'foo \ bar',
            $this->findAndAssertLink($page, 'foo \ bar')->getText()
        );
        // Make sure we see a Saved Searches header WITHOUT a log in link to ensure
        // saved searches are actually displaying:
        $this->assertFalse(
            $this->hasElementsMatchingText($page, 'a', 'log in')
        );
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertEquals(
            'test',
            $this->findAndAssertLink($page, 'test')->getText()
        );

        // Now purge unsaved searches, confirm that unsaved search is gone
        // but saved search is still present:
        $this->findAndAssertLink($page, 'Purge unsaved searches')->click();
        $this->waitForPageLoad($page);
        $this->assertNull($page->findLink('foo \ bar'));
        $this->assertEquals(
            'test',
            $this->findAndAssertLink($page, 'test')->getText()
        );
    }

    /**
     * Test that user A cannot delete user B's favorites.
     *
     * @depends testSaveSearch
     *
     * @return void
     */
    public function testSavedSearchSecurity(): void
    {
        // Log in as user A and get the ID of their saved search:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $page = $session->getPage();
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);
        $delete = $this->findAndAssertLink($page, 'Delete')->getAttribute('href');
        $this->findAndAssertLink($page, 'Log Out')->click();

        // Use user A's delete link, but try to execute it as user B:
        [, $params] = explode('?', $delete);
        // We expect an error, so let's act like production mode for realistic testing:
        $session->setWhoopsDisabled(true);
        $session->visit($this->getVuFindUrl() . '/MyResearch/SaveSearch?' . $params);
        $page = $session->getPage();
        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm(
            $page,
            ['username' => 'username2', 'email' => 'username2@example.com']
        );
        $this->clickCss($page, 'input.btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->findAndAssertLink($page, 'Log Out')->click();

        // Go back to stricter error handling:
        $session->setWhoopsDisabled(false);
        // Go back in as user A -- see if the saved search still exists.
        $this->findAndAssertLink($page, 'Search History')->click();
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);
        // Make sure we see a Saved Searches header WITHOUT a log in link to ensure
        // saved searches are actually displaying:
        $this->assertFalse(
            $this->hasElementsMatchingText($page, 'a', 'log in')
        );
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertEquals(
            'test',
            $this->findAndAssertLink($page, 'test')->getText()
        );
    }

    /**
     * Turn on notifications and reload the page.
     *
     * @return Element
     */
    protected function activateNotifications(): Element
    {
        $this->changeConfigs(
            [
                'config' => ['Account' => ['schedule_searches' => true]],
            ]
        );
        $session = $this->getMinkSession();
        $session->reload();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Test that notification settings work correctly.
     *
     * @depends testSaveSearch
     *
     * @return void
     */
    public function testNotificationSettings(): void
    {
        // Add a search to history...
        $page = $this->performSearch('journal');

        // Now log in and go to search history...
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);
        $this->findAndAssertLink($page, 'Search History')->click();

        // By default, there should be no alert option at all:
        $scheduleSelector = 'select[name="schedule"]';
        $this->assertNull($page->find('css', $scheduleSelector));

        // Now reconfigure to allow notifications, and refresh the page:
        $page = $this->activateNotifications();

        // Now there should be two alert options visible (one in saved, one in
        // unsaved):
        $this->assertCount(2, $page->findAll('css', $scheduleSelector));
        $this->assertCount(
            1,
            $page->findAll('css', '#recent-searches ' . $scheduleSelector)
        );
        $this->assertCount(
            1,
            $page->findAll('css', '#saved-searches ' . $scheduleSelector)
        );

        // At this point, our journals search should be in the unsaved list; let's
        // set it up for alerts and confirm that this auto-saves it.
        $select = $this->findCss($page, '#recent-searches ' . $scheduleSelector);
        $select->selectOption(7);
        $this->waitForPageLoad($page);
        $this->assertCount(
            2,
            $page->findAll('css', '#saved-searches ' . $scheduleSelector)
        );

        // Now let's delete the saved search and confirm that this clears the
        // alert subscription.
        $this->findAndAssertLink($page, 'Delete')->click();
        $this->waitForPageLoad($page);
        $select = $this->findCss($page, '#recent-searches ' . $scheduleSelector);
        $this->assertEquals(0, $select->getValue());
    }

    /**
     * Test that notifications are accessible via the search toolbar
     *
     * @depends testSaveSearch
     *
     * @return void
     */
    public function testNotificationsInSearchToolbar()
    {
        // Add a search to history...
        $page = $this->performSearch('employment');

        // By default, there should be no schedule option in the toolbar:
        $this->unFindCss($page, '.searchtools .manageSchedule');

        // Now reconfigure to allow alerts, and refresh the page:
        $page = $this->activateNotifications();

        // Now confirm that we have the expected text:
        $link = $this->findCss($page, '.searchtools .manageSchedule');
        $this->assertEquals('Alert schedule: None', $link->getText());
        $link->click();
        $this->waitForPageLoad($page);

        // We should now be prompted to log in:
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // We should now be on a page with a schedule selector; let's pick something:
        $scheduleSelector = 'select[name="schedule"]';
        $select = $this->findCss($page, $scheduleSelector);
        $select->selectOption(7);
        $this->waitForPageLoad($page);

        // Let's confirm that if we repeat the search, the alert will now be set:
        $page = $this->performSearch('employment');
        $this->assertEquals('Alert schedule: Weekly', $this->findCssAndGetText($page, '.searchtools .manageSchedule'));
    }

    /**
     * Test that accessing the "manage schedule" screen properly deduplicates
     * existing saved searches if clicked prior to user login.
     *
     * @depends testNotificationsInSearchToolbar
     *
     * @return void
     */
    public function testNotificationsInSearchToolbarDeduplication()
    {
        // Perform the same search as the previous test, and turn on notifications.
        $this->performSearch('employment');
        $page = $this->activateNotifications();

        // We are not logged in, so we won't see the appropriate alert schedule yet
        // (it's always "None" for logged-out users).
        $link = $this->findCss($page, '.searchtools .manageSchedule');
        $this->assertEquals('Alert schedule: None', $link->getText());
        $link->click();
        $this->waitForPageLoad($page);

        // We should now be prompted to log in:
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // We should now be on a page with a schedule selector; because of the
        // setting we set in the previous test, and with login deduplication, we
        // should now see the "7" option already selected:
        $scheduleSelector = 'select[name="schedule"]';
        $this->assertEquals(7, $this->findCssAndGetValue($page, $scheduleSelector));
    }

    /**
     * Test that scheduling a search from the history screen properly deduplicates
     * existing saved searches if clicked prior to user login.
     *
     * @depends testNotificationsInSearchToolbar
     *
     * @return void
     */
    public function testNotificationsInSearchHistoryDeduplication()
    {
        // Perform the same search as the previous test, and turn on notifications.
        $this->performSearch('employment');
        $page = $this->activateNotifications();

        // Now go to search history.
        $this->findAndAssertLink($page, 'Search History')->click();
        $this->waitForPageLoad($page);

        // Now there should be one alert option visible (in unsaved):
        $scheduleSelector = 'select[name="schedule"]';
        $this->assertCount(1, $page->findAll('css', $scheduleSelector));
        $this->assertCount(
            1,
            $page->findAll('css', '#recent-searches ' . $scheduleSelector)
        );
        $this->assertCount(
            0,
            $page->findAll('css', '#saved-searches ' . $scheduleSelector)
        );

        // Let's set up our search for alerts and make sure it's handled correctly:
        $select = $this->findCss($page, '#recent-searches ' . $scheduleSelector);
        $select->selectOption(1);
        $this->waitForPageLoad($page);

        // We should now be prompted to log in:
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);
        $this->waitForPageLoad($page);

        // Verify that the search is now saved, and that our notification setting (1)
        // has overridden the previously saved value from the earlier test (7).
        // Note that we want to make sure we're looking at the search we expect to
        // look at! From previous tests, we expect to have two in our history, but
        // the important one ("employment") should be first, which enables us to
        // safely rely on the final assertion below.
        $this->assertSavedSearchList(['employment', 'test'], $page);
        $this->assertCount(
            2,
            $page->findAll('css', '#saved-searches ' . $scheduleSelector)
        );
        $this->assertEquals(1, $this->findCssAndGetValue($page, $scheduleSelector));
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
