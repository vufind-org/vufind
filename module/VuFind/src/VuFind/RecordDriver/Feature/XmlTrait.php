<?php

/**
 * Functions for reading XML records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Feature;

use VuFindXml\XmlDoc;

/**
 * Functions for reading XML records.
 *
 * Assumption: raw XML data can be found in $this->fields['fullrecord'].
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait XmlTrait
{
    /**
     * The XML namespace.
     *
     * Note: this is a property instead of a constant to make use of it in strings cleaner.
     *
     * @var string
     */
    protected string $xmlNs = 'http://www.w3.org/2000/xmlns/';

    /**
     * XML class to use.
     *
     * @var string
     */
    protected string $xmlClass = \VuFindXml\XmlDoc::class;

    /**
     * XML instance. Access only via getXmlReader() as this is initialized lazily.
     *
     * @var XmlDoc
     */
    protected ?XmlDoc $lazyXmlReader = null;

    /**
     * Get access to the XML object.
     *
     * @return XmlDoc
     */
    public function getXmlReader(): XmlDoc
    {
        if (null === $this->lazyXmlReader) {
            $this->lazyXmlReader = new $this->xmlClass();
            $this->lazyXmlReader->parse($this->fields['fullrecord']);
        }

        return $this->lazyXmlReader;
    }

    /**
     * Get lang attribute from xml namespace with fallback to default namespace.
     *
     * @param array $node XmlDoc node
     *
     * @return ?string
     */
    protected function getLangAttr(array $node): ?string
    {
        $xml = $this->getXmlReader();
        return $xml->attr($node, '{{$this->xmlNs}}lang') ?? $xml->attr($node, 'lang');
    }
}
