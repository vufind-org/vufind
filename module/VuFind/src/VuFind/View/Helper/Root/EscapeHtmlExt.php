<?php

/**
 * Extended Escape HTML view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Escaper\Escaper;
use VuFind\String\PropertyStringInterface;

/**
 * Extended Escape HTML view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EscapeHtmlExt extends \Laminas\View\Helper\Escaper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param Escaper   $escaper   Escaper
     * @param CleanHtml $cleanHtml Clean HTML helper
     */
    public function __construct(Escaper $escaper, protected CleanHtml $cleanHtml)
    {
        parent::__construct($escaper);
    }

    /**
     * Invoke this helper: escape a value
     *
     * @param mixed $value     Value to escape
     * @param int   $recurse   Expects one of the recursion constants;
     *                         used to decide whether or not to recurse the given value when escaping
     * @param bool  $allowHtml Whether to allow sanitized HTML if passed a PropertyString
     *
     * @return mixed Given a scalar, a scalar value is returned. Given an object, with the $recurse flag not
     *               allowing object recursion, returns a string. Otherwise, returns an array.
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __invoke($value, $recurse = self::RECURSE_NONE, bool $allowHtml = false)
    {
        if ($value instanceof PropertyStringInterface) {
            if ($allowHtml && $html = $value->getHtml()) {
                return ($this->cleanHtml)($html);
            }
            $value = (string)$value;
        }
        return $this->escape($value);
    }

    /**
     * Escape a string
     *
     * @param string $value String to escape
     *
     * @return string
     */
    protected function escape($value)
    {
        return $this->escaper->escapeHtml($value);
    }
}
