<?php

/**
 * Trait with utility methods for user creation/management. Assumes that it
 * will be applied to a subclass of DbTestCase.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTheme\View\Helper;

/**
 * Trait with utility methods for user creation/management. Assumes that it
 * will be applied to a subclass of DbTestCase.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait ConcatTrait
{
    /**
     * Process and return items in index order
     *
     * @param \stdClass  $concat    Concatinated data item
     * @param string     $concatkey Index of the concaninated file
     * @param array      $other     Concatination-excempt data items, keyed by index
     * @param string|int $limit     Highest index present
     * @param string|int $indent    Amount of whitespace/string to use for indention
     *
     * @return string
     */
    protected function outputInOrder($concat, $concatkey, $other, $limit, $indent)
    {
        // Copied from parent
        $indent = (null !== $indent)
            ? $this->getWhitespace($indent)
            : $this->getIndent();

        if ($this->view) {
            $useCdata = $this->view->plugin('doctype')->isXhtml();
        } else {
            $useCdata = $this->useCdata;
        }

        $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
        $escapeEnd   = ($useCdata) ? '//]]>' : '//-->';

        $output = [];
        for ($i = 0; $i <= $limit; $i++) {
            if ($i == $concatkey) {
                $output[] = parent::itemToString(
                    $concat, $indent, $escapeStart, $escapeEnd
                );
            }
            if (isset($other[$i])) {
                $output[] = $this->itemToString(
                    $other[$i], $indent, $escapeStart, $escapeEnd
                );
            }
        }

        return $indent . implode(
            $this->escape($this->getSeparator()) . $indent, $output
        );
    }
}
