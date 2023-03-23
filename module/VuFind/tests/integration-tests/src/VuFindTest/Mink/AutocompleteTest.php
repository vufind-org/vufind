<?php

/**
 * Mink test class for autocomplete functionality.
 *
 * PHP version 7
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
    /**
     * Get an autocomplete item, and assert its value.
     *
     * @param Element $page Page element
     * @param string  $text Expected text
     *
     * @return Element
     */
    public function getAndAssertFirstAutocompleteValue(Element $page, string $text): Element
    {
        $tries = 0;
        $loadMsg = 'Loading…';
        do {
            $acItem = $this->findCss($page, '.autocomplete-results .ac-item');
            $acItemText = $acItem->getText();
            if (strcasecmp($acItemText, $loadMsg) === 0) {
                $this->snooze(0.5);
            }
            $tries++;
        } while (strcasecmp($acItemText, $loadMsg) === 0 && $tries <= 5);
        $this->assertEquals(
            $text,
            $this->findCss($page, '.autocomplete-results .ac-item')->getText()
        );
        return $acItem;
    }

    /**
     * Test that default autocomplete behavior is correct.
     *
     * @return void
     */
    public function testBasicAutocomplete()
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
            $this->getVuFindUrl() . '/Search/Results?lookfor=Fake+DOI+test+1&type=AllFields',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that default autocomplete behavior is correct on a non-default search handler.
     *
     * @return void
     */
    public function testBasicAutocompleteForNonDefaultField()
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
            $this->getVuFindUrl() . '/Search/Results?lookfor=JSTOR+%28Organization%29&type=Author',
            $session->getCurrentUrl()
        );
    }

    /**
     * Test that default autocomplete behavior is correct.
     *
     * @return void
     */
    public function testDisablingAutocompleteAutosubmit()
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
            'Fake DOI test 1',
            $this->findCss($page, '#searchForm_lookfor')->getValue()
        );
        $this->assertEquals(
            $this->getVuFindUrl() . '/Search/Home',
            $session->getCurrentUrl()
        );
    }
}
