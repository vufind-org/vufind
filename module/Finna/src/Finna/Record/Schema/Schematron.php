<?php

/**
 * ISO Schematron validator extensions.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Record\Schema;

use DOMDocument;
use DOMElement;
use DOMNode;
use Finna\Record\Schema\Milo\SchematronException;
use Finna\Record\Schema\Milo\SchematronHelpers;
use InvalidArgumentException;

use function in_array;

/**
 * ISO Schematron validator extensions.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Schematron extends Milo\Schematron
{
    /**
     * Constructor
     *
     * @param string $namespace Schema namespace (self::NS_*)
     *
     * @throws InvalidArgumentException when unsupported namespace passed
     */
    public function __construct($namespace = self::NS_DETECT)
    {
        static::$xPathClass = SchematronXPath::class;
        parent::__construct($namespace);
    }

    /**
     * Fills object members by basics schema properties.
     *
     * @param DOMDocument $schema Schema
     *
     * @return void
     *
     * @throws SchematronException
     */
    protected function loadSchemaBasics(DOMDocument $schema)
    {
        $list = $this->xPath->query('//sch:schema', $schema);
        if ($list->length > 1) {
            throw new SchematronException("Only one <schema> element in document is allowed, but $list->length found.");
        } elseif ($list->length < 1) {
            if (!($this->options & self::ALLOW_MISSING_SCHEMA_ELEMENT)) {
                throw new SchematronException('<schema> element not found.');
            }
        } else {
            $element = $list->item(0);

            $this->version = SchematronHelpers::getAttribute($element, 'schemaVersion', null);
            $this->defaultPhase = SchematronHelpers::getAttribute($element, 'defaultPhase', self::PHASE_ALL);
            $binding = SchematronHelpers::getAttribute($element, 'queryBinding', 'xslt');
            if (!in_array(strtolower($binding), ['xslt', 'xslt2'])) {
                throw new SchematronException("Query binding '$binding' is not supported.");
            }

            $titleElements = $this->xPath->query('sch:title', $element);
            if ($titleElements->length > 0) {
                $this->title = $titleElements->item(0)->textContent;
            }
        }
    }

    /**
     * Expands <sch:name> and <sch:value-of> in assertion/report message.
     *
     * @param DOMElement           $stmt    Statement
     * @param Milo\SchematronXPath $xPath   XPath
     * @param DOMNode              $current Current node
     * @param array                $lets    Rule's lets
     *
     * @return string
     */
    protected function statementToMessage(DOMElement $stmt, Milo\SchematronXPath $xPath, DOMNode $current, $lets = [])
    {
        $result = parent::statementToMessage($stmt, $xPath, $current, $lets);
        if ($lineNo = $current->getLineNo()) {
            $result = "[$lineNo] $result";
        }
        if ($role = $stmt->getAttribute('role')) {
            $result = "[$role] $result";
        }
        return $result;
    }
}
