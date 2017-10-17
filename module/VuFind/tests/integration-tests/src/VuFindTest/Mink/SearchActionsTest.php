<?php
/**
 * Mink search actions test class.
 *
 * PHP version 5
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
 * Mink search actions test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SearchActionsTest extends \VuFindTest\Unit\MinkTestCase
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
     * Search for the specified query.
     *
     * @param string $query Search term(s)
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function performSearch($query)
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')->setValue($query);
        $this->findCss($page, '.btn.btn-primary')->click();
        $this->snooze();
        return $page;
    }

    /**
     * Get filtered search
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function getFilteredSearch()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?filter%5B%5D=building%3A"weird_ids.mrc"');
        return $session->getPage();
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
     * Helper function for facets lists
     *
     * @param \Behat\Mink\Element\Element $page            Mink page object
     * @param int                         $limit           Configured lightbox length
     * @param bool                        $exclusionActive Is facet exclusion on?
     *
     * @return void
     */
    protected function facetListProcedure($page, $limit, $exclusionActive = false)
    {
        $this->snooze();
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit, count($items));
        $excludes = $page
            ->findAll('css', '#modal #facet-list-count .badge .fa-times');
        $this->assertEquals($exclusionActive ? $limit : 0, count($excludes));
        // more
        $this->findCss($page, '#modal .js-facet-next-page')->click();
        $this->snooze();
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit * 2, count($items));
        $this->assertEquals(
            'Weird IDs 9 '
            . 'Fiction 7 '
            . 'The Study Of P|pes 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 '
            . 'The Study of "Important" Things 1 '
            . 'The Study of %\'s? 1 '
            . 'The Study of +\'s? 1 '
            . 'The Study of @Twitter #test 1 '
            . 'more ...',
            $this->findCss($page, '#modal #facet-list-count')->getText()
        );
        $excludes = $page
            ->findAll('css', '#modal #facet-list-count .badge .fa-times');
        $this->assertEquals($exclusionActive ? $limit * 2 : 0, count($excludes));

        // sort by title
        $this->findCss($page, '[data-sort="index"]')->click();
        $this->snooze();
        $items = $page->findAll('css', '#modal #facet-list-index .js-facet-item');
        $this->assertEquals($limit, count($items)); // reset number of items
        $this->assertEquals(
            'Fiction 7 '
            . 'The Study Of P|pes 1 '
            . 'The Study and Scor_ng of Dots.and-Dashes:Colons 1 '
            . 'The Study of "Important" Things 1 '
            . 'more ...',
            $this->findCss($page, '#modal #facet-list-index')->getText()
        );
        $excludes = $page
            ->findAll('css', '#modal #facet-list-index .badge .fa-times');
        $this->assertEquals($exclusionActive ? $limit : 0, count($excludes));
        // sort by index again
        $this->findCss($page, '[data-sort="count"]')->click();
        $this->snooze();
        $items = $page->findAll('css', '#modal #facet-list-count .js-facet-item');
        $this->assertEquals($limit * 2, count($items)); // maintain number of items
        // When exclusion is active, the result count is outside of the link tag:
        $expectedLinkText = $exclusionActive ? 'Weird IDs' : 'Weird IDs 9';
        $weirdIDs = $this->findAndAssertLink(
            $page->findById('modal'), $expectedLinkText
        );
        $this->assertEquals($expectedLinkText, $weirdIDs->getText());
        // apply US facet
        $weirdIDs->click();
        $this->snooze();
    }

    /**
     * Test expanding facets into the lightbox
     *
     * @return void
     */
    public function testFacetLightbox()
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the geographic facet
        $genreMore = $this->findCss($page, '#more-narrowGroupHidden-genre_facet');
        $genreMore->click();
        $this->facetListProcedure($page, $limit);
        $genreMore->click();
        $this->findCss($page, '#modal .js-facet-item.active')->click();
        // remove facet
        $this->snooze();
        $this->assertNull($page->find('css', '.list-group.filters'));
    }

    /**
     * Test expanding facets into the lightbox
     *
     * @return void
     */
    public function testFacetLightboxMoreSetting()
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => 'more',
                        'lightboxLimit' => $limit
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the geographic facet
        $genreMore = $this->findCss($page, '#more-narrowGroupHidden-genre_facet');
        $genreMore->click();
        $this->findCss($page, '.narrowGroupHidden-genre_facet[data-lightbox]')->click();
        $this->facetListProcedure($page, $limit);
        $genreMore->click();
        $this->findCss($page, '.narrowGroupHidden-genre_facet[data-lightbox]')->click();
        $this->findCss($page, '#modal .js-facet-item.active')->click();
        // remove facet
        $this->snooze();
        $this->assertNull($page->find('css', '.list-group.filters'));
    }

    /**
     * Test that exclusion works properly deep in lightbox results.
     *
     * @return void
     */
    public function testFacetLightboxExclusion()
    {
        $limit = 4;
        $this->changeConfigs(
            [
                'facets' => [
                    'Results_Settings' => [
                        'showMoreInLightbox[*]' => true,
                        'lightboxLimit' => $limit,
                        'exclude' => '*',
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:weird_ids.mrc');
        // Open the geographic facet
        $genreMore = $this->findCss($page, '#more-narrowGroupHidden-genre_facet');
        $genreMore->click();
        $this->facetListProcedure($page, $limit, true);
        $this->assertEquals(1, count($page->find('css', '.active-filters')));
    }

    /**
     * Support method to click a hierarchical facet.
     *
     * @param \Behat\Mink\Element\Element $page Mink page object
     *
     * @return void
     */
    protected function clickHierarchyFacet($page)
    {
        $this->findCss($page, '#j1_1.jstree-closed .jstree-icon');
        $session = $this->getMinkSession();
        $session->executeScript("$('#j1_1.jstree-closed .jstree-icon').click();");
        $this->findCss($page, '#j1_1.jstree-open .jstree-icon');
        $this->findCss($page, '#j1_2 a')->click();
        $this->snooze();
        $filter = $this->findCss($page, '.active-filters .facet');
        $this->assertEquals('hierarchy: 1/level1a/level2a/', $filter->getText());
        $this->findCss($page, '#j1_2 .fa-check');
    }

    /**
     * Test that hierarchy facets work properly.
     *
     * @return void
     */
    public function testHierarchicalFacets()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy'
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv'
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:"hierarchy.mrc"');
        $this->clickHierarchyFacet($page);
    }

    /**
     * Test that we can persist uncollapsed state of collapsed facets
     *
     * @return void
     */
    public function testCollapseStatePersistence()
    {
        $this->changeConfigs(
            [
                'facets' => [
                    'Results' => [
                        'hierarchical_facet_str_mv' => 'hierarchy'
                    ],
                    'Results_Settings' => [
                        'collapsedFacets' => '*'
                    ],
                    'SpecialFacets' => [
                        'hierarchical[]' => 'hierarchical_facet_str_mv'
                    ]
                ]
            ]
        );
        $page = $this->performSearch('building:"hierarchy.mrc"');
        // Uncollapse format so we can check if it is still open after reload:
        $this->findCss($page, '#side-panel-format .collapsed')->click();
        // Uncollapse hierarchical facet so we can click it:
        $this->findCss($page, '#side-panel-hierarchical_facet_str_mv .collapsed')->click();
        $this->clickHierarchyFacet($page);

        // We have now reloaded the page. Let's toggle format off and on to confirm
        // that it was opened, and let's also toggle building on to confirm that
        // it was not alread opened.
        $this->findCss($page, '#side-panel-format .title')->click(); // off
        $this->snooze(); // wait for animation
        $this->findCss($page, '#side-panel-format .collapsed')->click(); // on
        $this->findCss($page, '#side-panel-building .collapsed')->click(); // on
    }

    /**
     * Test retrain current filters checkbox
     *
     * @return void
     */
    public function testRetainFilters()
    {
        $page = $this->getFilteredSearch();
        $this->findCss($page, '.active-filters'); // Make sure we're filtered
        // Perform search with retain
        $this->findCss($page, '#searchForm .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '.active-filters');
        // Perform search double click retain
        $this->findCss($page, '.searchFormKeepFilters')->click();
        $this->findCss($page, '.searchFormKeepFilters')->click();
        $this->findCss($page, '#searchForm .btn.btn-primary')->click();
        $this->snooze();
        $this->findCss($page, '.active-filters');
        // Perform search without retain
        $this->findCss($page, '.searchFormKeepFilters')->click();
        $this->findCss($page, '#searchForm .btn.btn-primary')->click();
        $items = $page->findAll('css', '.active-filters');
        $this->assertEquals(0, count($items));
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
