<?php

/**
 * Mink test class for autocomplete functionality.
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
 * Mink test class for autocomplete functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class AutocompleteTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\AutocompleteTrait;

    /**
     * For the provided search, assert the first autocomplete value and return the
     * associated page element.
     *
     * @param string  $search   Search term(s)
     * @param string  $expected First expected Autocomplete suggestion
     * @param ?string $type     Search type (null for default)
     *
     * @return Element
     */
    protected function assertAutocompleteValueAndReturnItem(
        string $search,
        string $expected,
        ?string $type = null
    ): Element {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        if ($type) {
            $this->findCss($page, '#searchForm_type')->setValue($type);
        }
        $this->findCss($page, '#searchForm_lookfor')->setValue($search);
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, $expected);
        return $acItem;
    }

    /**
     * Test that default autocomplete behavior is correct.
     *
     * @return void
     */
    public function testBasicAutocomplete(): void
    {
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem('fake doi test', 'Fake DOI test 1');
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22Fake+DOI+test+1%22&type=AllFields',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that titles containing quotes are properly escaped.
     *
     * @return void
     */
    public function testBasicAutocompleteQuoteEscaping(): void
    {
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem(
            'millers mechanical',
            'Letterhead enclosure: "The Millers Mechanical Battlefield: world\'s greatest exhibition", [1920?].'
        );
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22Letterhead+enclosure%3A+'
                . '%5C%22The+Millers+Mechanical+Battlefield%3A+world%27s+greatest+exhibition'
                . '%5C%22%2C+%5B1920%3F%5D.%22&type=AllFields',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that default autocomplete behavior is correct on a non-default search handler.
     *
     * @return void
     */
    public function testBasicAutocompleteForNonDefaultField(): void
    {
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem('jsto', 'JSTOR (Organization)', 'Author');
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22JSTOR+%28Organization%29%22&type=Author',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test two different autocomplete types in the same session to ensure that inappropriate
     * caching does not occur.
     *
     * @return void
     */
    public function testMultipleAutocompletesInSingleSession(): void
    {
        // First do a search in All Fields
        $this->assertAutocompleteValueAndReturnItem('jsto', 'Al Gore');

        // Now repeat the same search in Author
        $session = $this->getMinkSession();
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_type')
            ->setValue('Author');
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('jsto');
        // Make sure we get the right author match, and not a cached All Fields value!
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, 'JSTOR (Organization)');
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22JSTOR+%28Organization%29%22&type=Author',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that no-autosubmit autocomplete behavior is correct.
     *
     * @return void
     */
    public function testDisablingAutocompleteAutosubmit(): void
    {
        $this->changeConfigs(
            ['searches' => ['Autocomplete' => ['auto_submit' => false]]]
        );
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem('fake doi test', 'Fake DOI test 1');
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '"Fake DOI test 1"',
            $this->findCss($page, '#searchForm_lookfor')->getValue()
        );
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Home',
            $session->getCurrentUrl()
        );
    }

    /**
     * Get basic config settings to activate combined search handlers.
     *
     * @return array
     */
    protected function getCombinedSearchHandlersConfigs(): array
    {
        return [
            'searchbox' => [
                'General' => [
                    'combinedHandlers' => true,
                ],
                'CombinedHandlers' => [
                    'type' => ['VuFind', 'VuFind'],
                    'target' => ['Solr', 'SolrAuth'],
                    'label' => ['Catalog', 'Authorities'],
                    'group' => [false, false],
                ],
            ],
        ];
    }

    /**
     * Test that default autocomplete works correctly in a searchbox with combined handlers.
     *
     * @return void
     */
    public function testAutocompleteInCombinedSearchbox(): void
    {
        $this->changeConfigs($this->getCombinedSearchHandlersConfigs());
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem('fake doi test', 'Fake DOI test 1');
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22Fake+DOI+test+1%22&type=AllFields',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that author autocomplete works correctly in a searchbox with combined handlers.
     *
     * @return void
     */
    public function testAuthorAutocompleteInCombinedSearchbox(): void
    {
        $this->changeConfigs($this->getCombinedSearchHandlersConfigs());
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem('jsto', 'JSTOR (Organization)', 'VuFind:Solr|Author');
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22JSTOR+%28Organization%29%22&type=Author',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that authority autocomplete works correctly in a searchbox with combined handlers.
     *
     * @return void
     */
    public function testAuthorityAutocompleteInCombinedSearchbox(): void
    {
        $this->changeConfigs($this->getCombinedSearchHandlersConfigs());
        $session = $this->getMinkSession();
        $acItem = $this->assertAutocompleteValueAndReturnItem(
            'roy',
            'Royal Dublin Society',
            'VuFind:SolrAuth|MainHeading'
        );
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Authority/Search?lookfor=%22Royal+Dublin+Society%22&type=MainHeading',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test alphabrowse autocomplete in searchbox with combined handlers.
     *
     * @return void
     */
    public function testAlphaBrowseAutocompleteInCombinedSearchbox(): void
    {
        $config = $this->getCombinedSearchHandlersConfigs();
        $config['searchbox']['General']['includeAlphaBrowse'] = true;
        $this->changeConfigs($config);
        $session = $this->getMinkSession();
        $vufindUrl = $this->getVuFindUrl();
        $basePath = parse_url($vufindUrl, PHP_URL_PATH);
        $handler = "External:$basePath/Alphabrowse/Home?source=title&from=";
        $acItem = $this->assertAutocompleteValueAndReturnItem('test pu', 'test publication 20001', $handler);
        $acItem->click();
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $vufindUrl . '/Alphabrowse/Home?source=title&from=test+publication+20001',
            $session->getCurrentUrl()
        );
    }
}
