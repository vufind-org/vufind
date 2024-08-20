<?php

/**
 * Trait for working with limits of search results.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;

use function intval;

/**
 * Trait for working with sorting of search results.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SearchLimitTrait
{
    /**
     * Selector for limit control
     *
     * @var string
     */
    protected $limitControlSelector = '#limit';

    /**
     * Assert the contents and selected element of the limit control.
     *
     * @param Element $page    Current page
     * @param int[]   $options Expected options
     * @param int     $active  Expected active option
     *
     * @return void
     */
    protected function assertLimitControl(Element $page, array $options, int $active)
    {
        $limit = $this->findCss($page, $this->limitControlSelector);
        $this->assertEquals((string)$active, $limit->getValue());
        $optionElements
            = $page->findAll('css', $this->limitControlSelector . ' option');
        $callback = function (Element $element): string {
            return intval($element->getText());
        };
        $actualOptions = array_map($callback, $optionElements);
        $this->assertEquals($options, $actualOptions);
    }

    /**
     * Assert that no limit control is present on the page.
     *
     * @param Element $page Current page
     *
     * @return void
     */
    protected function assertNoLimitControl(Element $page)
    {
        $this->assertNull($page->find('css', $this->limitControlSelector));
    }

    /**
     * Change sort order of search results
     *
     * @param Element $page  Current page
     * @param int     $value Limit option value
     *
     * @return void
     */
    protected function setResultLimit(Element $page, int $value): void
    {
        foreach ($page->findAll('css', $this->limitControlSelector . ' option') as $option) {
            if ((int)$option->getValue() === $value) {
                $option->click();
                return;
            }
        }
        $this->assertTrue(false, 'Limit option not found');
    }
}
