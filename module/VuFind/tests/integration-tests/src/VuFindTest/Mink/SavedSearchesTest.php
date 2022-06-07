<?php
/**
 * Mink saved searches test class.
 *
 * PHP version 7
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
 * @retry    4
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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Test saving and clearing a search.
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testSaveSearch(): void
    {
        $page = $this->performSearch('test');
        $this->clickCss($page, '.fa.fa-save');
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm($page);
        $this->clickCss($page, 'input.btn.btn-primary');
        $this->assertEquals(
            'Search saved successfully.',
            $this->findCss($page, '.alert.alert-success')->getText()
        );
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
        // We should see our "foo \ bar" search in the history, but no saved
        // searches because we are logged out:
        $this->assertEquals(
            'foo \ bar',
            $this->findAndAssertLink($page, 'foo \ bar')->getText()
        );
        $this->assertFalse(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
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
     * @depends       testSaveSearch
     * @retryCallback removeUsername2
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
        [$base, $params] = explode('?', $delete);
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

        // Go back in as user A -- see if the saved search still exists.
        $this->findAndAssertLink($page, 'Search History')->click();
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);
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
                'config' => ['Account' => ['schedule_searches' => true]]
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
        $this->assertEquals(2, count($page->findAll('css', $scheduleSelector)));
        $this->assertEquals(
            1,
            count($page->findAll('css', '#recent-searches ' . $scheduleSelector))
        );
        $this->assertEquals(
            1,
            count($page->findAll('css', '#saved-searches ' . $scheduleSelector))
        );

        // At this point, our journals search should be in the unsaved list; let's
        // set it up for alerts and confirm that this auto-saves it.
        $select = $this->findCss($page, '#recent-searches ' . $scheduleSelector);
        $select->selectOption(7);
        $this->waitForPageLoad($page);
        $this->assertEquals(
            2,
            count($page->findAll('css', '#saved-searches ' . $scheduleSelector))
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
        $this->assertEquals("Alert schedule: None", $link->getText());
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
        $link = $this->findCss($page, '.searchtools .manageSchedule');
        $this->assertEquals("Alert schedule: Weekly", $link->getText());
    }

    /**
     * Retry cleanup method in case of failure during testSavedSearchSecurity.
     *
     * @return void
     */
    protected function removeUsername2(): void
    {
        static::removeUsers(['username2']);
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
