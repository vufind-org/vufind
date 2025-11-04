<?php

/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2013-2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use function array_slice;
use function count;
use function in_array;

/**
 * Model for Qualified Dublin Core records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Anna Pienimäki <anna.pienimaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrQdc extends \VuFind\RecordDriver\SolrDefault implements \Psr\Log\LoggerAwareInterface
{
    use Feature\SolrFinnaTrait;
    use Feature\FinnaXmlReaderTrait;
    use Feature\FinnaUrlCheckTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Image size mappings
     *
     * @var array
     */
    protected $imageSizeMappings = [
        'thumbnail' => 'small',
        'square' => 'small',
        'small' => 'small',
        'medium' => 'medium',
        'large' => 'large',
        'original' => 'original',
    ];

    /**
     * Image media types
     *
     * @var array
     */
    protected $imageMediaTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Mappings for series information, type => key
     *
     * @var array
     */
    protected $seriesInfoMappings = [
        'ispartofseries' => 'name',
        'numberinseries' => 'partNumber',
    ];

    /**
     * Default value for no_locale definition used for no language
     *
     * @var string
     */
    protected const NO_LOCALE = 'no_locale';

    /**
     * Array of excluded descriptions
     *
     * @var array
     */
    protected $excludedDescriptions = ['notification'];

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \VuFind\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \VuFind\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->searchSettings = $searchSettings;
    }

    /**
     * Return an associative array of abstracts associated with this record
     *
     * @return array of abstracts using abstract languages as keys
     */
    public function getAbstracts()
    {
        $abstracts = [];
        $abstract = '';
        $lang = '';
        $xml = $this->getXmlRecord();
        foreach ($xml->abstract ?? [] as $node) {
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
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        return $this->fields['title_alt'] ?? [];
    }

    /**
     * Get descriptions as an array
     *
     * @return array
     */
    public function getDescriptions(): array
    {
        return $this->getDescriptionsByType();
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return $this->getDescriptionsByType(['notification']);
    }

    /**
     * Get an array of mediums for the record
     *
     * @return array
     */
    public function getPhysicalMediums(): array
    {
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ($xml->medium as $medium) {
            $results[] = trim((string)$medium);
        }
        return $results;
    }

    /**
     * Get an array of formats/extents for the record
     *
     * @return array
     */
    public function getPhysicalDescriptions(): array
    {
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ([$xml->format, $xml->extent] as $nodes) {
            foreach ($nodes as $node) {
                $results[] = trim((string)$node);
            }
        }
        return $results;
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
        $cacheKey = __FUNCTION__ . "/$language" . ($includePdf ? '/1' : '/0');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $results = [];
        $rights = [];
        $xml = $this->getXmlRecord();
        $thumbnails = [];
        $otherSizes = [];
        $highResolution = [];
        $rights = $this->getRights($language);
        $addToResults = function ($imageData) use (&$results): void {
            if (!$this->maxAmountOfImages()) {
                if (!isset($imageData['urls']['small'])) {
                    $imageData['urls']['small'] = $imageData['urls']['medium']
                        ?? $imageData['urls']['large']
                        ?? $imageData['urls']['original'];
                }
                $imageData = $this->ensureImageSizes($imageData);
                $imageData['downloadable'] = $this->allowRecordImageDownload($imageData);
                $results[] = $imageData;
            }
            $this->imagesCount++;
        };

        foreach ($xml->file as $node) {
            $attributes = $node->attributes();
            $type = (string)($attributes->type ?? '');
            $url = (string)($attributes->href ?? $node);
            if (
                ($type && !in_array($type, array_keys($this->imageMediaTypes)))
                || (!$type && !preg_match('/\.(jpg|png)$/i', $url))
            ) {
                continue;
            }
            if (!$this->isUrlLoadable($url, $this->getUniqueID())) {
                continue;
            }

            $bundle = strtolower((string)$attributes->bundle);
            if ($bundle === 'thumbnail' && !$otherSizes) {
                // Lets see if the record contains only thumbnails
                $thumbnails[] = $url;
            } else {
                // QDC has no way of telling how to link
                // images so take only first in this situation
                $size = $this->imageSizeMappings[$bundle] ?? false;
                if ($size && !isset($otherSizes[$size])) {
                    if (in_array($size, ['master', 'original'])) {
                        $currentHiRes = [
                            'data' => [],
                            'url' => $url,
                            'format' => $this->imageMediaTypes[$type] ?? 'jpg',
                        ];
                        $highResolution[$size][] = $currentHiRes;
                    }
                    $otherSizes[$size] = $url;
                }
            }
        }

        if ($thumbnails && !$otherSizes) {
            foreach ($thumbnails as $url) {
                $addToResults(
                    [
                        'urls' => ['large' => $url],
                        'description' => '',
                        'rights' => $rights,
                    ]
                );
            }
        } elseif ($otherSizes) {
            $addToResults(
                [
                    'urls' => $otherSizes,
                    'description' => '',
                    'rights' => $rights,
                    'highResolution' => $highResolution,
                ]
            );
        }
        $thumbnails = [];
        $otherSizes = [];
        // Attempt to find a PDF file to be converted to a coverimage
        if ($includePdf && empty($results)) {
            $urls = [];
            foreach ($xml->file as $node) {
                $attributes = $node->attributes();
                if ((string)$attributes->bundle !== 'ORIGINAL') {
                    continue;
                }
                $url = isset($attributes->href)
                    ? (string)$attributes->href : (string)$node;
                $type = trim((string)$attributes->type);
                if (
                    ($type && $type !== 'application/pdf')
                    || (!$type && !preg_match('/\.pdf$/i', $url))
                ) {
                    continue;
                }
                $urls['small'] = $urls['large'] = $url;
                $addToResults(
                    [
                        'urls' => $urls,
                        'description' => '',
                        'rights' => $rights,
                        'pdf' => true,
                    ]
                );
                break;
            }
        }
        return $this->cache[$cacheKey] = $results;
    }

    /**
     * Get image rights
     *
     * @param string $language Language for the copyright
     *
     * @return array [copyright, link, description = []]
     */
    protected function getRights(string $language): array
    {
        $xml = $this->getXmlRecord();
        $result = [
            'copyright' => '',
            'link' => '',
            'description' => [],
        ];
        $firstElementPriority = null;
        $cache = [];
        // Get all the copyrights and save them in an array identified by language.
        foreach ($xml->rights as $right) {
            $strRight = trim((string)$right);
            $type = trim((string)$right->attributes()->type);
            $rightLanguage = trim((string)$right->attributes()->lang);
            // QDC sometimes marks languageless elements with a dash
            if (!$rightLanguage || '-' === $rightLanguage) {
                $rightLanguage = self::NO_LOCALE;
            }
            // If no type and language is set and it is the first rights element,
            // then we can assume it is the main copyright to display
            if (null === $firstElementPriority) {
                $firstElementPriority = !$type && self::NO_LOCALE === $rightLanguage;
            }
            $cache[$rightLanguage][] = [
                'txt' => $strRight,
                'type' => $type,
            ];
        }
        if (empty($cache)) {
            return $result;
        }
        // Check that there is proper values to use for displaying the rights.
        $localizedRights = [];
        foreach ($this->getPrioritizedLanguages([$language], self::NO_LOCALE) as $lang) {
            if (empty($cache[$lang])) {
                continue;
            }
            // Check if there is an rights element with priority.
            $localizedRights = (self::NO_LOCALE !== $lang && $firstElementPriority)
                ? array_merge($cache[self::NO_LOCALE], $cache[$lang])
                : $cache[$lang];
            break;
        }
        if (empty($localizedRights)) {
            return $result;
        }
        // Try to get the main copyright to display, normally the first in array.
        $priorityRight = array_shift($localizedRights);
        $mappedRight = $this->getMappedRights($priorityRight['txt']);
        $result['copyright'] = $mappedRight;
        $result['link'] = $this->getRightsLink($mappedRight, $language);
        foreach ($localizedRights as $right) {
            // Add rights as descriptions which have the same localization
            // as the primary right.
            if (
                'copyright' === $right['type']
                && $result['copyright'] !== $right['txt']
            ) {
                $result['description'][] = $right['txt'];
            }
        }
        return $result;
    }

    /**
     * Return education programs
     *
     * @return array
     */
    public function getEducationPrograms()
    {
        $result = [];
        foreach ($this->getXmlRecord()->programme as $programme) {
            $result[] = (string)$programme;
        }
        return $result;
    }

    /**
     * Get human readable publication dates for display purposes (may not be suitable
     * for computer processing -- use getPublicationDates() for that).
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        if ($dates = $this->getPublicationDateRange()) {
            return [implode('–', $dates)];
        }
        return [];
    }

    /**
     * Get publication date or date range.
     *
     * @return ?array Array of one or two dates or null if not available.
     * If date range is still continuing end year will be an empty string.
     */
    public function getPublicationDateRange()
    {
        return $this->getDateRange('publication');
    }

    /**
     * Return full record as a filtered SimpleXMLElement for public APIs.
     *
     * @return \SimpleXMLElement
     */
    public function getFilteredXMLElement(): \SimpleXMLElement
    {
        $record = clone $this->getXmlRecord();
        while ($record->abstract) {
            unset($record->abstract[0]);
        }
        // Try to filter out any summary or abstract fields
        $filterTerms = [
            'tiivistelmä', 'abstract', 'abstracts', 'abstrakt', 'sammandrag',
            'sommario', 'summary', 'аннотация',
        ];
        for ($i = count($record->description) - 1; $i >= 0; $i--) {
            $node = $record->description[$i];
            $description = mb_strtolower((string)$node, 'UTF-8');
            $firstWords = array_slice(preg_split('/\s/', $description), 0, 5);
            if (array_intersect($firstWords, $filterTerms)) {
                unset($record->description[$i]);
            }
        }

        return $record;
    }

    /**
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        return $this->getFilteredXMLElement()->asXML();
    }

    /**
     * Get identifier
     *
     * @return array
     */
    public function getIdentifier()
    {
        $xml = $this->getXmlRecord();
        foreach ($xml->identifier ?? [] as $identifier) {
            // Inventory number
            if ((string)$identifier['type'] === 'wikidata:P217') {
                return [trim((string)$identifier)];
            }
        }
        return [];
    }

    /**
     * Get identifiers as an array
     *
     * @return array
     */
    public function getOtherIdentifiers(): array
    {
        $results = [];
        $xml = $this->getXmlRecord();
        foreach ([$xml->identifier, $xml->isFormatOf] as $field) {
            foreach ($field as $identifier) {
                $type = (string)$identifier['type'];
                $identifierTrimmed = trim((string)$identifier);
                if (in_array($type, ['issn', 'isbn'])) {
                    continue;
                }
                $trimmed = str_replace('-', '', $identifierTrimmed);
                // ISBN
                if (preg_match('{^[0-9]{9,12}[0-9xX]}', $trimmed)) {
                    continue;
                }
                $trimmed = $identifierTrimmed;
                // ISSN
                if (preg_match('{(issn:)[\S]{4}\-[\S]{4}}', $trimmed)) {
                    continue;
                }

                // Leave out some obvious matches like urls or urns
                if (!preg_match('{(^urn:|^https?)}i', $trimmed)) {
                    $detail = $type;
                    $data = $identifierTrimmed;
                    $results[] = compact('data', 'detail');
                }
            }
        }
        return $results;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        $result = [];
        $xml = $this->getXmlRecord();
        foreach ([$xml->identifier, $xml->isFormatOf] as $field) {
            foreach ($field as $identifier) {
                $trimmed = str_replace('-', '', trim($identifier));
                if (
                    (string)$identifier['type'] === 'isbn'
                    || preg_match('{^[0-9]{9,12}[0-9xX]}', $trimmed)
                ) {
                    $result[] = $identifier;
                }
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * Get all record links related to the current record. Each link is returned as
     * array.
     * Format:
     * array(
     *        array(
     *               'title' => label_for_title
     *               'value' => link_name
     *               'link'  => link_URI
     *        ),
     *        ...
     * )
     *
     * @return null|array
     */
    public function getAllRecordLinks()
    {
        $xml = $this->getXmlRecord();
        $relations = [];
        foreach ($xml->isPartOf ?? [] as $isPartOf) {
            $relations[] = [
                'value' => (string)$isPartOf,
                'link' => [
                    'value' => (string)$isPartOf,
                    'type' => 'allFields',
                ],
            ];
        }
        foreach ($xml->relation ?? [] as $relation) {
            $attrs = $relation->attributes();
            if ('ispartof' === (string)($attrs->type ?? '')) {
                $relations[] = [
                    'value' => (string)$relation,
                    'link' => [
                        'value' => (string)$relation,
                        'type' => 'allFields',
                    ],
                ];
            }
        }
        return $relations;
    }

    /**
     * Return keywords
     *
     * @return array
     */
    public function getKeywords()
    {
        $result = [];
        foreach ($this->getXmlRecord()->keyword as $keyword) {
            $result[] = (string)$keyword;
        }
        return $result;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object. The exact nature of the data may
     * vary depending on the data source -- the important thing is that the
     * Record Driver + Search Results objects work together correctly.
     *
     * @return void
     */
    public function setRawData($data)
    {
        parent::setRawData($data);
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
            if (!$this->urlBlocked($url['url'] ?? '')) {
                if (!$this->maxAmountOfURLs()) {
                    $urls[] = $url;
                }
                $this->urlsCount++;
            }
        }
        $urls = $this->resolveUrlTypes($urls);
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

    /**
     * Get series information
     *
     * @return array
     */
    public function getSeries(): array
    {
        $locale = $this->getLocale();
        $xml = $this->getXmlRecord();
        $results = [];
        foreach ($xml->relation ?? [] as $relation) {
            $type = (string)$relation->attributes()->{'type'};
            $lang = (string)$relation->attributes()->{'lang'} ?: 'nolocale';
            $trimmed = trim((string)$relation);

            if ($key = $this->seriesInfoMappings[$type] ?? false) {
                // Initialize the result so that it contains the required elements:
                if (!isset($results[$lang])) {
                    $results[$lang] = [
                        'name' => '',
                    ];
                }
                if (empty($results[$lang][$key])) {
                    $results[$lang][$key] = $trimmed;
                }
            }
        }

        return isset($results[$locale])
            ? [$results[$locale]]
            : array_values($results);
    }

    /**
     * Get access rights
     *
     * @return array
     */
    public function getAccessRestrictions(): array
    {
        $xml = $this->getXmlRecord();
        $locale = $this->getLocale();
        $primary = [];
        $all = [];
        foreach ($xml->rights as $right) {
            $strRight = trim((string)$right);
            $type = trim((string)$right->attributes()->type);
            $rightLanguage = trim((string)$right->attributes()->lang);
            if ('accessrights' === $type) {
                $all[] = $strRight;
                if ((!$rightLanguage || $rightLanguage === $locale)) {
                    $primary[] = $strRight;
                }
            }
        }
        return $primary ?: $all;
    }

    /**
     * Get descriptions by type
     *
     * @param array $include Description types to include, otherwise all but excluded types
     *
     * @return array
     */
    protected function getDescriptionsByType(array $include = []): array
    {
        $xml = $this->getXmlRecord();
        $descriptions = [];
        $first = '';
        $exclude = $include ? [] : $this->excludedDescriptions;
        foreach ($xml->description ?? [] as $description) {
            $type = (string)$description['type'];
            if (($include && !in_array($type, $include)) || ($exclude && in_array($type, $exclude))) {
                continue;
            }
            if (($format = (string)$description['format']) && str_starts_with($format, 'image/')) {
                continue;
            }
            if ($trimmed = trim((string)$description)) {
                $lang = trim((string)$description['lang']) ?? self::NO_LOCALE;
                $first = $first ?: $lang;
                $descriptions[$lang][] = $trimmed;
            }
        }
        if ($descriptions) {
            foreach ($this->getPrioritizedLanguages() as $l) {
                if ($descriptions[$l] ?? []) {
                    return $descriptions[$l];
                }
            }
            return $descriptions[$first];
        }
        return [];
    }

    /**
     * Given a Solr field name, return an appropriate caption.
     *
     * @param string $field Solr field name
     *
     * @return mixed        Caption if found, false if none available.
     */
    public function getSnippetCaption($field)
    {
        return $field !== 'contents' ? parent::getSnippetCaption($field) : false;
    }
}
