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
     * Test that default autocomplete behavior is correct.
     *
     * @return void
     */
    public function testBasicAutocomplete(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('fake doi test');
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, 'Fake DOI test 1');
        $acItem->click();
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
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('millers mechanical');
        $acItem = $this->getAndAssertFirstAutocompleteValue(
            $page,
            'Letterhead enclosure: "The Millers Mechanical Battlefield: world\'s greatest exhibition", [1920?].'
        );
        $acItem->click();
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
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_type')
            ->setValue('Author');
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('jsto');
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, 'JSTOR (Organization)');
        $acItem->click();
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
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        // First do a search in All Fields
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('jsto');
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, 'Al Gore');
        // Now repeat the same search in Author
        $this->findCss($page, '#searchForm_type')
            ->setValue('Author');
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('jsto');
        // Make sure we get the right author match, and not a cached All Fields value!
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, 'JSTOR (Organization)');
        $acItem->click();
        $this->waitForPageLoad($page);
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Results?lookfor=%22JSTOR+%28Organization%29%22&type=Author',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that default autocomplete behavior is correct.
     *
     * @return void
     */
    public function testDisablingAutocompleteAutosubmit(): void
    {
        $this->changeConfigs(
            ['searches' => ['Autocomplete' => ['auto_submit' => false]]]
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('fake doi test');
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, 'Fake DOI test 1');
        $acItem->click();
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
}
