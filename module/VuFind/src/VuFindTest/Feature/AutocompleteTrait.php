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
}
