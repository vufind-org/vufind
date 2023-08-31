<?php

/**
 * View helper to print an array formatted for HTML display.
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2023.
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
 * @package  View_Helpers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

use function count;
use function is_array;

/**
 * View helper to print an array formatted for HTML display.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PrintArrayHtml extends AbstractHelper
{
    /**
     * Print an array formatted for HTML display.
     * Function uses recursion to achieve desired results, so entry can be
     * either an array or a value to display.
     *
     * @param array|string $entry       An array or string to output
     * @param int          $indentLevel How many spaces to indent output
     * @param bool         $indentFirst Should first item in array be indented
     *
     * @return string                   The formatted HTML for output
     */
    public function __invoke($entry, $indentLevel = 0, $indentFirst = true)
    {
        $makeTag = $this->getView()->plugin('makeTag');
        $html = '';
        if (is_array($entry)) {
            $isList = $this->isArrayList($entry);
            $isFlat = $this->isArrayFlat($entry);
            foreach ($entry as $key => $value) {
                if ($indentFirst || $key != array_key_first($entry)) {
                    $html .= str_repeat('&ensp;', $indentLevel);
                }
                $valueIsSingleKeyList = $this->isSingleKeyList($value);

                $nextIndentLevel = $indentLevel;
                // Indent first line unless we're continuing from hyphen
                $nextIndentFirst = ($isFlat || !$isList || !is_array($value))
                                   && !$valueIsSingleKeyList;
                if (!$isFlat || !$isList) {
                    // Increase indent if entering new array
                    $nextIndentLevel = is_array($value) ? $indentLevel + 2 : 0;
                }
                if (!$isFlat && $isList && !$valueIsSingleKeyList) {
                    // Integer keyed arrays use a hyphen list unless they're flat
                    $html .= '&ndash;&ensp;';
                } elseif (!$isList) {
                    $html .= $makeTag('span', $key . ':', ['class' => 'term'])
                             . (
                                 (is_array($value) && !$valueIsSingleKeyList)
                                 ? "<br>\n" : ' '
                             );
                }

                $html .= $this->__invoke($value, $nextIndentLevel, $nextIndentFirst);
            }
        } else {
            $html = $makeTag('span', $entry, ['class' => 'detail']) . "<br>\n";
        }
        return $html;
    }

    /**
     * Check is a variable is and array and all keys are sequential
     * integers starting from index 0.
     * TODO This function can be replaced by array_is_list() in PHP8
     *
     * @param mixed $var A variable to perform the check on.
     *
     * @return bool
     */
    protected function isArrayList($var)
    {
        if (!is_array($var)) {
            return false;
        }
        $i = 0;
        foreach ($var as $key => $_) {
            if ($key !== $i++) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if variable is an array and has no arrays as values.
     *
     * @param mixed $var A variable to perform the check on.
     *
     * @return bool
     */
    protected function isArrayFlat($var)
    {
        if (!is_array($var)) {
            return false;
        }
        foreach ($var as $val) {
            if (is_array($val)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if variable is an integer keyed array of size 1
     * whose single value is not an array.
     *
     * @param mixed $var A variable to perform the check on.
     *
     * @return bool
     */
    protected function isSingleKeyList($var)
    {
        return $this->isArrayList($var)
               && $this->isArrayFlat($var)
               && count($var) == 1;
    }
}
