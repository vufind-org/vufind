<?php

/**
 * Model for "Qualified Dublin Core" (using the DCMI Metadata Terms) records in Solr.
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

namespace VuFind\RecordDriver;

use VuFind\I18n\Locale\LocaleSettingsAwareInterface;
use VuFind\I18n\Locale\LocaleSettingsAwareTrait;
use VuFind\RecordDriver\Feature\LocaleSupportTrait;
use VuFind\RecordDriver\Feature\XmlTrait;

/**
 * Model for "Qualified Dublin Core" (using the DCMI Metadata Terms) records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrQdc extends SolrDefault implements LocaleSettingsAwareInterface
{
    use LocaleSettingsAwareTrait;
    use LocaleSupportTrait;
    use XmlTrait;

    /**
     * Dublin Core XML namespace.
     *
     * Note: this is a property instead of a constant to make use of it in strings cleaner.
     *
     * @var string
     */
    protected string $dcNs = 'http://purl.org/dc/elements/1.1/';

    /**
     * Dublin Core Terms vocabulary namespace.
     *
     * Note: this is a property instead of a constant to make use of it in strings cleaner.
     *
     * @var string
     */
    protected string $dcTermsNs = 'http://purl.org/dc/terms/';

    /**
     * Get the abstract notes.
     *
     * @return array
     */
    public function getAbstractNotes(): array
    {
        $allAbstracts = [];
        $localeAbstracts = [];
        $xml = $this->getXmlReader();
        foreach ($this->getDcTermsElements('abstract') as $node) {
            $abstract = $xml->value($node);
            if ($lang = $this->getLangAttr($node)) {
                $localeAbstracts[$lang][] = $abstract;
            }
            $allAbstracts[] = $abstract;
        }

        return $this->getLocaleSpecificResults($localeAbstracts, $allAbstracts);
    }

    /**
     * Get elements from the terms or elements namespaces with fallback to default namespace.
     *
     * @param string $nodeName   Node name
     * @param bool   $valuesOnly Return only values?
     *
     * @return array Array of XML elements for further processing with VuFindXml methods, or a string array of element
     * values if $valuesOnly is true
     */
    protected function getElements(string $nodeName, bool $valuesOnly = false): array
    {
        $xml = $this->getXmlReader();
        // Prefer elements in the terms namespace:
        $method = $valuesOnly ? 'allValues' : 'all';
        return $this->getDcTermsElements($nodeName, $valuesOnly, false)
            ?: $xml->$method(path: "{{$this->dcNs}}$nodeName")
            ?: $xml->$method(path: "$nodeName");
    }

    /**
     * Get elements from the DcTerms namespace with optional fallback to default namespace.
     *
     * @param string $nodeName          Node name
     * @param bool   $valuesOnly        Return only values?
     * @param bool   $defaultNsFallback Try with default namespace if not found in the DcTerms namespace?
     *
     * @return array Array of XML elements for further processing with VuFindXml methods, or a string array of element
     * values if $valuesOnly is true
     */
    protected function getDcTermsElements(
        string $nodeName,
        bool $valuesOnly = false,
        bool $defaultNsFallback = true
    ): array {
        $xml = $this->getXmlReader();
        $method = $valuesOnly ? 'allValues' : 'all';
        return $xml->$method(path: "{{$this->dcTermsNs}}$nodeName")
            ?: ($defaultNsFallback ? $xml->$method(path: $nodeName) : []);
    }
}
