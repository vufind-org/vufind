<?php

/**
 * ISO Schematron validator XPath extensions.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Record\Schema;

use DOMNode;

/**
 * ISO Schematron validator XPath extensions.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SchematronXPath extends Milo\SchematronXPath
{
    /**
     * Evaluate an XPath expression
     *
     * ($registerNodeNS is FALSE in opposition to DOMXPath default value)
     *
     * @param string  $expression     Expression
     * @param DOMNode $context        Context
     * @param bool    $registerNodeNS Register in-node namespaces?
     *
     * @return mixed
     */
    public function evaluate($expression, ?DOMNode $context = null, $registerNodeNS = false): mixed
    {
        // Emulate XSLT 2.0 'matches' function:
        if (preg_match('/^boolean\(matches\((.*),\s*\'(.*)\'\)\)$/', $expression, $matches)) {
            $subExpression = $matches[1];
            $matchExpression = $matches[2];
            $subResult = parent::evaluate($subExpression, $context, $registerNodeNS);
            return preg_match('/' . addcslashes($matchExpression, '\\') . '/', $subResult);
        }
        return parent::evaluate($expression, $context, $registerNodeNS);
    }
}
