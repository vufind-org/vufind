<?php

/**
 * Truncate view helper
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\String\PropertyStringInterface;

/**
 * Truncate view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Truncate extends AbstractHelper
{
    /**
     * Truncate a string
     *
     * Note that any PropertyString with a plain string value that exceeds the maximum length is converted to a plain
     * string before truncation. This means that the returned value is a plain string without e.g. any HTML content.
     *
     * @param string|PropertyStringInterface $str    The string to be truncated
     * @param int                            $len    Maximum length of the resulting string
     * @param string                         $append Truncation indicator to append to truncated strings
     *
     * @return string|PropertyStringInterface
     */
    public function __invoke($str, $len, $append = '...')
    {
        if ($len == 0) {
            return '';
        } elseif (mb_strlen((string)$str, 'UTF-8') > $len) {
            return trim(mb_substr((string)$str, 0, $len, 'UTF-8')) . $append;
        }
        return $str;
    }
}
