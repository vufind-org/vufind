<?php

/**
 * VuFind SimpleXML enhancement functionality
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  SimpleXML
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind;

use SimpleXMLElement;

/**
 * VuFind SimpleXML enhancement functionality
 *
 * @category VuFind
 * @package  SimpleXML
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SimpleXML
{
    /**
     * Attach $child to $parent.
     *
     * @param SimpleXMLElement        $parent Parent element to modify
     * @param SimpleXMLElement|string $child  Child element (or XML fragment) to
     * attach
     *
     * @return void
     */
    public static function appendElement($parent, $child)
    {
        $xml = $child instanceof SimpleXMLElement
            ? $child->asXML() : $child;

        // strip off xml header
        $mark = strpos($xml, '?' . '>');
        if ($mark > 0 && $mark < 40) {
            $xml = substr($xml, $mark + 2);
        }

        $dom = dom_import_simplexml($parent);
        $fragment = $dom->ownerDocument->createDocumentFragment();
        $fragment->appendXML($xml);
        $dom->appendChild($fragment);
    }
}
