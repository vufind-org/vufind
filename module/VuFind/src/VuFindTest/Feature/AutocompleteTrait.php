<?php

/**
 * Trait adding autocomplete checking functionality to a Mink test class.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;
use Behat\Mink\Element\NodeElement;

/**
 * Trait adding autocomplete checking functionality to a Mink test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait AutocompleteTrait
{
    /**
     * Get an autocomplete item, and assert its value.
     *
     * @param Element $page Page element
     * @param string  $text Expected text
     *
     * @return NodeElement
     */
    public function getAndAssertFirstAutocompleteValue(Element $page, string $text): NodeElement
    {
        $tries = 0;
        $snoozeTime = 0;
        $loadMsg = 'Loading…';
        do {
            $acItemText = $this->findCssAndGetText($page, '.autocomplete-results .ac-item');
            if (strcasecmp($acItemText, $loadMsg) === 0) {
                $this->snooze(0.5);
                $snoozeTime += 0.5 * $this->getSnoozeMultiplier();
            }
            $tries++;
        } while (strcasecmp($acItemText, $loadMsg) === 0 && $tries <= 5);
        $this->assertEquals(
            $text,
            $acItemText,
            "Failed after $tries tries, with $snoozeTime seconds snooze time."
        );
        return $this->findCss($page, '.autocomplete-results .ac-item');
    }

    /**
     * For the provided search, assert the first autocomplete value and return the
     * associated page element.
     *
     * @param Element $page     Page to use for searching
     * @param string  $search   Search term(s)
     * @param string  $expected First expected Autocomplete suggestion
     * @param ?string $type     Search type (null for default)
     *
     * @return NodeElement
     */
    protected function assertAutocompleteValueAndReturnItem(
        Element $page,
        string $search,
        string $expected,
        ?string $type = null,
    ): NodeElement {
        if ($type) {
            $this->findCssAndSetValue($page, '#searchForm_type', $type);
        }
        $this->findCssAndSetValue($page, '#searchForm_lookfor', $search, reFocus: true);
        $acItem = $this->getAndAssertFirstAutocompleteValue($page, $expected);
        return $acItem;
    }
}
