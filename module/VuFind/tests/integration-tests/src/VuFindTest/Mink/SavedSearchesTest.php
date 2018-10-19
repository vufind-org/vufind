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

/**
 * Mink saved searches test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SavedSearchesTest extends \VuFindTest\Unit\MinkTestCase
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
     * Test saving and clearing a search.
     *
     * @return void
     */
    public function testSaveSearch()
    {
        $page = $this->performSearch('test');
        $this->findCss($page, '.fa.fa-save')->click();
        $this->snooze();
        $this->findCss($page, '.createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->findCss($page, 'input.btn.btn-primary')->click();
        $this->snooze();
        $this->assertEquals(
            'Search saved successfully.',
            $this->findCss($page, '.alert.alert-success')->getText()
        );
    }

    /**
     * Test search history.
     *
     * @return void
     */
    public function testSearchHistory()
    {
        // Use "foo \ bar" as our search because the backslash has been known
        // to cause problems in some situations (e.g. PostgreSQL database with
        // incorrect escaping); this allows us to catch regressions for a few
        // different problems in a single test.
        $page = $this->performSearch('foo \ bar');
        $this->findAndAssertLink($page, 'Search History')->click();
        $this->snooze();
        // We should see our "foo \ bar" search in the history, but no saved
        // searches because we are logged out:
        $this->assertEquals(
            'foo \ bar', $this->findAndAssertLink($page, 'foo \ bar')->getText()
        );
        $this->assertFalse(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertNull($page->findLink('test'));

        // Now log in and see if our saved search shows up (without making the
        // unsaved search go away):
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->assertEquals(
            'foo \ bar', $this->findAndAssertLink($page, 'foo \ bar')->getText()
        );
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertEquals(
            'test', $this->findAndAssertLink($page, 'test')->getText()
        );

        // Now purge unsaved searches, confirm that unsaved search is gone
        // but saved search is still present:
        $this->findAndAssertLink($page, 'Purge unsaved searches')->click();
        $this->snooze();
        $this->assertNull($page->findLink('foo \ bar'));
        $this->assertEquals(
            'test', $this->findAndAssertLink($page, 'test')->getText()
        );
    }

    /**
     * Test that user A cannot delete user B's favorites.
     *
     * @return void
     */
    public function testSavedSearchSecurity()
    {
        // Log in as user A and get the ID of their saved search:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/History');
        $page = $session->getPage();
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $delete = $this->findAndAssertLink($page, 'Delete')->getAttribute('href');
        $this->findAndAssertLink($page, 'Log Out')->click();
        $this->snooze();

        // Use user A's delete link, but try to execute it as user B:
        list($base, $params) = explode('?', $delete);
        $session->visit($this->getVuFindUrl() . '/MyResearch/SaveSearch?' . $params);
        $page = $session->getPage();
        $this->findCss($page, '.createAccountLink')->click();
        $this->snooze();
        $this->fillInAccountForm(
            $page, ['username' => 'username2', 'email' => 'username2@example.com']
        );
        $this->findCss($page, 'input.btn.btn-primary')->click();
        $this->snooze();
        $this->findAndAssertLink($page, 'Log Out')->click();
        $this->snooze();

        // Go back in as user A -- see if the saved search still exists.
        $this->findAndAssertLink($page, 'Search History')->click();
        $this->snooze();
        $this->findCss($page, '#loginOptions a')->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->assertTrue(
            $this->hasElementsMatchingText($page, 'h2', 'Saved Searches')
        );
        $this->assertEquals(
            'test', $this->findAndAssertLink($page, 'test')->getText()
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers(['username1', 'username2']);
    }
}
