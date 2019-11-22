<?php
/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2019.
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
 * @package  RecordDrivers
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrQdc extends \VuFind\RecordDriver\SolrDefault
{
    use SolrFinna;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->searchSettings = $searchSettings;
    }

    /**
     * Record metadata
     *
     * @var \SimpleXMLElement
     */
    protected $simpleXML;

    /**
     * Return an associative array of abstracts associated with this record,
     * if available; false otherwise.
     *
     * @return array of abstracts using abstract languages as keys
     */
    public function getAbstracts()
    {
        $abstractValues = [];
        $abstracts = [];
        $abstract = '';
        $lang = '';
        foreach ($this->getSimpleXML()->xpath('/qualifieddc/abstract') as $node) {
            $abstract = (string)$node;
            $lang = (string)$node['lang'];
            if ($lang == 'en') {
                $lang = 'en-gb';
            }
            $abstracts[$lang] = $abstract;
        }

        return $abstracts;
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - url         Image URL
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return mixed
     */
    public function getAllImages($language = 'fi', $includePdf = true)
    {
        $result = [];
        $urls = [];
        $rights = [];
        $pdf = false;
        foreach ($this->getSimpleXML()->file as $node) {
            $attributes = $node->attributes();
            $size = $attributes->bundle == 'THUMBNAIL' ? 'small' : 'large';
            $mimes = ['image/jpeg', 'image/png'];
            if (isset($attributes->type)) {
                if (!in_array($attributes->type, $mimes)) {
                    continue;
                }
            }
            $url = isset($attributes->href)
                ? (string)$attributes->href : (string)$node;

            if (!preg_match('/\.(jpg|png)$/i', $url)) {
                continue;
            }
            $urls[$size] = $url;
        }

        // Attempt to find a PDF file to be converted to a coverimage
        if ($includePdf && empty($urls)) {
            foreach ($this->getSimpleXML()->file as $node) {
                $attributes = $node->attributes();
                if ((string)$attributes->bundle !== 'ORIGINAL') {
                    continue;
                }
                $mimes = ['application/pdf'];
                if (isset($attributes->type)) {
                    if (!in_array($attributes->type, $mimes)) {
                        continue;
                    }
                }
                $url = isset($attributes->href)
                    ? (string)$attributes->href : (string)$node;

                if (!preg_match('/\.(pdf)$/i', $url)) {
                    continue;
                }
                $urls['small'] = $urls['large'] = $url;
                $pdf = true;
                break;
            }
        }

        $xml = $this->getSimpleXML();
        $rights['copyright'] = !empty($xml->rights) ? (string)$xml->rights : '';
        $rights['link'] = $this->getRightsLink(
            strtoupper($rights['copyright']), $language
        );

        if ($urls) {
            if (!isset($urls['small'])) {
                $urls['small'] = $urls['large'];
            }
            $urls['medium'] = $urls['small'];

            $result[] = [
                'urls' => $urls,
                'description' => '',
                'rights' => $rights,
                'pdf' => $pdf
            ];
        }
        return $result;
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     */
    public function getDescriptionURL()
    {
        if ($isbn = $this->getCleanISBN()) {
            return 'http://s1.doria.fi/getText.php?query=' . $isbn;
        }
        return false;
    }

    /**
     * Return education programs
     *
     * @return array
     */
    public function getEducationPrograms()
    {
        $result = [];
        foreach ($this->getSimpleXML()->programme as $programme) {
            $result[] = (string)$programme;
        }
        return $result;
    }

    /**
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        $record = clone $this->getSimpleXML();
        while ($record->abstract) {
            unset($record->abstract[0]);
        }
        // Try to filter out any summary or abstract fields
        $filterTerms = [
            'tiivistelmä', 'abstract', 'abstracts', 'abstrakt', 'sammandrag',
            'sommario', 'summary', 'аннотация'
        ];
        for ($i = count($record->description) - 1; $i >= 0; $i--) {
            $node = $record->description[$i];
            $description = mb_strtolower((string)$node, 'UTF-8');
            $firstWords = array_slice(preg_split('/\s/', $description), 0, 5);
            if (array_intersect($firstWords, $filterTerms)) {
                unset($record->description[$i]);
            }
        }
        return $record->asXML();
    }

    /**
     * Return keywords
     *
     * @return array
     */
    public function getKeywords()
    {
        $result = [];
        foreach ($this->getSimpleXML()->keyword as $keyword) {
            $result[] = (string)$keyword;
        }
        return $result;
    }

    /**
     * Get the original record as a SimpleXML object
     *
     * @return SimpleXMLElement The record as SimpleXML
     */
    protected function getSimpleXML()
    {
        if ($this->simpleXML === null) {
            $this->simpleXML = new \SimpleXMLElement($this->fields['fullrecord']);
        }
        return $this->simpleXML;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
        parent::setRawData($data);
        $this->simpleXML = null;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $urls = [];
        foreach (parent::getURLs() as $url) {
            $blacklisted = $this->urlBlacklisted(
                $url['url'] ?? ''
            );
            if (!$blacklisted) {
                $urls[] = $url;
            }
        }
        $urls = $this->checkForAudioUrls($urls);
        return $urls;
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        if ('oai_qdc' === $format) {
            return $this->fields['fullrecord'];
        }
        return parent::getXML($format, $baseUrl, $recordLink);
    }
}
