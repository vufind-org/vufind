<?php

/**
 * Model for EAD records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use function count;
use function in_array;
use function is_array;

/**
 * Model for EAD records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan O'Carragain <Eoghan.OCarragan@gmail.com>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Lutz Biedinger <lutz.Biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrEad extends SolrDefault implements \Psr\Log\LoggerAwareInterface
{
    use Feature\SolrFinnaTrait {
        getSupportedCitationFormats as getSupportedCitationFormatsFinna;
    }
    use Feature\FinnaXmlReaderTrait;
    use Feature\FinnaUrlCheckTrait;
    use \VuFind\Log\LoggerAwareTrait;

    // add-data > parent elements with these level-attributes are archive series
    public const SERIES_LEVELS = ['series', 'subseries'];

    // add-data > parent elements with these level-attributes are archive files
    public const FILE_LEVELS = ['file'];

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
     * Get access restriction notes for the record.
     *
     * @return mixed Notes
     */
    public function getAccessRestrictions()
    {
        $record = $this->getXmlRecord();
        return $record->userestrict->p ?? $record->accessrestrict->p ?? [];
    }

    /**
     * Return type of access restriction for the record.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType($language)
    {
        $record = $this->getXmlRecord();
        $restrict = $record->userestrict->p ?? $record->accessrestrict->p ?? '';
        if (!($restrict = trim((string)$restrict))) {
            return false;
        }
        $data = [];
        $data['copyright'] = $copyright
            = $this->getMappedRights($restrict);
        if ($link = $this->getRightsLink($copyright, $language)) {
            $data['link'] = $link;
        }
        return $data;
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - urls        Image URLs
     *   - small     Small image (mandatory)
     *   - medium    Medium image (mandatory)
     *   - large     Large image (optional)
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
     * @return array
     */
    public function getAllImages($language = 'fi', $includePdf = true)
    {
        $cacheKey = __FUNCTION__ . "/$language/" . ($includePdf ? '1' : '0');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = [];
        // All images have same rights..
        $rights = $this->getImageRights($language, true);
        foreach ($this->getXmlRecord()->xpath('did/daogrp') as $daogrp) {
            $urls = [];
            foreach ($daogrp->daoloc as $daoloc) {
                $attributes = $daoloc->attributes();
                $url = (string)$attributes->href;
                $role = (string)$attributes->role;
                $size = '';
                switch ($role) {
                    case 'image_thumbnail':
                        $size = 'small';
                        break;
                    case 'image_reference':
                        $size = 'medium';
                        break;
                    case 'image_full':
                        $size = 'large';
                        break;
                }
                if (!$size || !$this->isUrlLoadable($url, $this->getUniqueID())) {
                    continue;
                }

                $urls[$size] = $url;
            }
            if (empty($urls)) {
                continue;
            }

            if (!isset($urls['small'])) {
                $urls['small'] = $urls['medium']
                    ?? $urls['large'];
            }
            if (!isset($urls['medium'])) {
                $urls['medium'] = $urls['large']
                    ?? $urls['small'];
            }

            if (isset($daogrp->dapdesc->p) && $daogrp->dapdesc->p != 'Fotografi') {
                $description = $daogrp->dapdesc->p;
            } else {
                $description = '';
            }
            if (!$this->maxAmountOfImages()) {
                $image = [
                    'urls' => $urls,
                    'description' => (string)$description,
                    'rights' => $rights,
                ];
                $image['downloadable'] = $this->allowRecordImageDownload($image);
                $result[] = $image;
            }
            $this->imagesCount++;
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Get notes on bibliography content.
     *
     * @return string[] Notes
     */
    public function getBibliographyNotes()
    {
        $record = $this->getXmlRecord();
        $bibliography = [];
        foreach ($record->xpath('//bibliography') as $node) {
            // Filter out Portti links since they're displayed in links
            $match = preg_match(
                '/(.+) (http:\/\/wiki\.narc\.fi\/portti.*)/',
                (string)$node->p
            );
            if (!$match) {
                $bibliography[] = (string)$node->p;
            }
        }
        return $bibliography;
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        $record = $this->getXmlRecord();
        $findingAids = [];
        if (isset($record->otherfindaid->p)) {
            foreach ($record->otherfindaid->p as $p) {
                $findingAids[] = (string)$p;
            }
        }
        return $findingAids;
    }

    /**
     * Get identifier
     *
     * @return array
     */
    public function getIdentifier()
    {
        $record = $this->getXmlRecord();
        $id = isset($record->did->unitid->attributes()->{'identifier'})
            ? (string)$record->did->unitid->attributes()->{'identifier'}
            : (string)$record->did->unitid;
        return [$id];
    }

    /**
     * Return image rights.
     *
     * @param string $language       Language
     * @param bool   $skipImageCheck Whether to check that images exist
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language, $skipImageCheck = false)
    {
        if (!$skipImageCheck && !$this->getAllImages()) {
            return false;
        }

        $rights = [];

        if ($type = $this->getAccessRestrictionsType($language)) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }

        [$language] = explode('-', $language);
        switch ($language) {
            case 'fi':
                $language = 'fin';
                break;
            case 'sv':
                $language = 'swe';
                break;
            case 'en':
                $language = 'eng';
                break;
        }

        $desc = $this->getAccessRestrictions();
        if ($desc && count($desc)) {
            $description = [];
            // First try with the language code
            foreach ($desc as $p) {
                $lang = (string)$p->attributes()->lang;
                if ($lang == $language) {
                    $description[] = (string)$p;
                }
            }
            // Fallback to anything
            if (empty($description)) {
                foreach ($desc as $p) {
                    $description[] = (string)$p;
                }
            }
            $rights['description'] = $description;
        }

        return isset($rights['copyright']) || isset($rights['description'])
            ? $rights : false;
    }

    /**
     * Get origination
     *
     * @return string
     */
    public function getOrigination()
    {
        $originations = $this->getOriginations();
        return $originations[0] ?? '';
    }

    /**
     * Get all originations
     *
     * @return array
     */
    public function getOriginations()
    {
        return array_map(
            function ($origination) {
                return $origination['name'];
            },
            $this->getOriginationExtended()
        );
    }

    /**
     * Get extended origination
     *
     * @return array
     */
    public function getOriginationExtended()
    {
        $record = $this->getXmlRecord();
        $result = [];
        foreach ($record->did->origination as $origination) {
            $name = (string)($origination->corpname
                ?? $origination->persname
                ?? $origination);
            if ($name) {
                $result[] = [
                    'name' => $name,
                    'date' => (string)($origination->ref->date ?? ''),
                ];
            }
        }
        return $result;
    }

    /**
     * Get origination Id
     *
     * @return string
     */
    public function getOriginationId()
    {
        $record = $this->getXmlRecord();
        return isset($record->did->origination->corpname)
            ? (string)$record->did->origination->corpname
                ->attributes()->authfilenumber
            : '';
    }

    /**
     * Get physical locations.
     *
     * @return string[] Physical Locations
     */
    public function getPhysicalLocations()
    {
        $record = $this->getXmlRecord();
        $locations = [];
        if (isset($record->did->physloc)) {
            foreach ($record->did->physloc as $physloc) {
                $locations[] = (string)$physloc;
            }
        }
        return $locations;
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        $physDesc = parent::getPhysicalDescriptions();
        if (isset($this->fields['material'])) {
            $physDesc = array_merge($physDesc, $this->fields['material']);
        }
        return $physDesc;
    }

    /**
     * Get an array of external service URLs
     *
     * @return array Array of urls with 'url' and 'desc' keys
     */
    public function getServiceURLs()
    {
        $urls = [];
        $source = $this->getDataSource();
        $config = $this->recordConfig->Record;
        if (
            isset($config->ead_document_order_link_template[$source])
            && !$this->isDigitized()
            && in_array('1/Document/ArchiveItem/', $this->getFormats())
        ) {
            $urls[] = [
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_document_order_link_template[$source]
                ),
                'desc' => 'ead_document_order',
            ];
        }
        if (
            isset($config->ead_usage_permission_request_link_template[$source])
            && $this->getAccessRestrictions()
        ) {
            $urls[] = [
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_usage_permission_request_link_template[$source]
                ),
                'desc' => 'ead_usage_permission_request',
            ];
        }
        if (isset($config->ead_external_link_template[$source])) {
            $urls[] = [
                'url' => $this->replaceURLPlaceholders(
                    $config->ead_external_link_template[$source]
                ),
                'desc' => 'ead_external_link_description',
            ];
        }
        return $urls;
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        // We need to return an array, so if we have a description, turn it into an
        // array as needed (it should be a flat string according to the default
        // schema, but we might as well support the array case just to be on the safe
        // side. If needed, handle a special case where the indexed description
        // consists of several joined paragraphs:
        if (
            isset($this->fields['description'])
            && !empty($this->fields['description'])
        ) {
            return is_array($this->fields['description'])
                ? $this->fields['description']
                : explode('   /   ', $this->fields['description']);
        }

        // If we got this far, no description was found:
        return [];
    }

    /**
     * Get unit ID (for reference)
     *
     * @return string Unit ID
     */
    public function getUnitID()
    {
        $unitId = $this->getXmlRecord()->xpath('did/unitid');
        if (count($unitId)) {
            return (string)$unitId[0];
        }
        return '';
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
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }
        $urls = [];
        $url = '';
        $record = $this->getXmlRecord();
        foreach ($record->xpath('//daoloc') as $node) {
            $url = (string)$node->attributes()->href;
            $image = isset($node->attributes()->role) && in_array(
                $node->attributes()->role,
                ['image_thumbnail', 'image_reference']
            );
            if ($image) {
                continue;
            }

            $desc = '';
            if ($node->daodesc) {
                if ($node->daodesc->p) {
                    $desc = (string)$node->daodesc->p;
                } else {
                    $desc = (string)$node->daodesc;
                }
            } else {
                if ($p = $node->xpath('parent::*/daodesc/p')) {
                    $desc = (string)$p[0];
                }
            }
            $desc = empty($desc) ? $url : $desc;
            if (!$this->urlBlocked($url, $desc)) {
                if (!$this->maxAmountOfURLs()) {
                    $urls[] = [
                        'url' => $url,
                        'desc' => $desc,
                    ];
                }
                $this->urlsCount++;
            }
        }

        // Portti links parsed from bibliography
        foreach ($record->xpath('//bibliography') as $node) {
            $match = preg_match(
                '/(.+) (http:\/\/wiki\.narc\.fi\/portti.*)/',
                (string)$node->p,
                $matches
            );
            if ($match && !$this->urlBlocked($matches[2], $matches[1])) {
                if (!$this->maxAmountOfURLs()) {
                    $urls[] = [
                        'url' => $matches[2],
                        'desc' => $matches[1],
                    ];
                }
                $this->urlsCount++;
            }
        }
        $this->cache[__FUNCTION__] = $this->resolveUrlTypes($urls);
        return $this->cache[__FUNCTION__];
    }

    /**
     * Get the value of whether or not this is a collection level record
     *
     * NOTE: \VuFind\Hierarchy\TreeDataFormatter\AbstractBase::isCollection()
     * duplicates some of this logic.
     *
     * @return bool
     */
    public function isCollection()
    {
        $result = parent::isCollection();
        if ($result) {
            $record = $this->getXmlRecord();
            if ('item' === (string)$record['level']) {
                return false;
            }
        }
        return $result;
    }

    /**
     * Check if the record is a fonds or a collection by format
     *
     * @return bool
     */
    public function isFondsOrCollection(): bool
    {
        return !empty(
            array_intersect(
                ['Document/ArchiveFonds', 'Document/ArchiveCollection'],
                $this->getFormats()
            )
        );
    }

    /**
     * Check if record is digitized.
     *
     * @return boolean True if the record is digitized
     */
    public function isDigitized()
    {
        $record = $this->getXmlRecord();
        return $record->did->daogrp ? true : false;
    }

    /**
     * Check if record is part of an archive series.
     *
     * A record is assumed to be part of a series if it has a parent that's not
     * the top-level record in the hierarchy.
     *
     * @return bool Whether the record is part of an archive series
     */
    public function isPartOfArchiveSeries()
    {
        return isset($this->fields['hierarchy_parent_id'][0])
            && isset($this->fields['hierarchy_top_id'][0])
            && $this->fields['hierarchy_parent_id'][0]
                != $this->fields['hierarchy_top_id'][0]
            && $this->fields['hierarchy_top_id'] != $this->fields['id'];
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php, getCitation()
     * method.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        $supportedFormats = $this->getSupportedCitationFormatsFinna();
        if (isset($this->fields['hierarchy_top_id'])) {
            $supportedFormats[] = 'Archive';
        }
        return $supportedFormats;
    }

    /**
     * Get parent archives
     *
     * @return array
     */
    public function getParentArchives(): array
    {
        // A fonds or a collection never belongs to an archive:
        if ($this->isFondsOrCollection()) {
            return [];
        }
        if ($this->isPartOfArchiveSeries()) {
            $topIds = $this->getHierarchyTopId();
            $topTitles = $this->getHierarchyTopTitle();
        } else {
            $topIds = $this->getHierarchyParentId();
            $topTitles = $this->getHierarchyParentTitle();
        }
        return $this->buildHierarchicalRecordArray($topIds, $topTitles);
    }

    /**
     * Get parent series
     *
     * @return array
     */
    public function getParentSeries(): array
    {
        // A fonds or a collection never belongs to a series:
        if ($this->isFondsOrCollection()) {
            return [];
        }
        return $this->buildHierarchicalRecordArray(
            $this->getHierarchyParentId(self::SERIES_LEVELS),
            $this->getHierarchyParentTitle(self::SERIES_LEVELS)
        );
    }

    /**
     * Get parent files
     *
     * @return array
     */
    public function getParentFiles(): array
    {
        // A fonds or a collection never belongs to a file:
        if ($this->isFondsOrCollection()) {
            return [];
        }
        return $this->buildHierarchicalRecordArray(
            $this->getHierarchyParentId(self::FILE_LEVELS),
            $this->getHierarchyParentTitle(self::FILE_LEVELS)
        );
    }

    /**
     * Get the hierarchy_parent_id(s) associated with this item (empty if none).
     *
     * @param string[] $levels Optional list of level types to return
     *
     * @return array
     */
    public function getHierarchyParentID(array $levels = []): array
    {
        if ($levels && !empty(array_diff($levels, self::SERIES_LEVELS))) {
            return [];
        }
        return (array)($this->fields['hierarchy_parent_id'] ?? []);
    }

    /**
     * Get the parent title(s) associated with this item (empty if none).
     *
     * @param string[] $levels Optional list of level types to return
     *
     * @return array
     */
    public function getHierarchyParentTitle(array $levels = []): array
    {
        if ($levels && !empty(array_diff($levels, self::SERIES_LEVELS))) {
            return [];
        }
        return (array)($this->fields['hierarchy_parent_title'] ?? []);
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
        $this->lazyXmlRecord = null;
    }

    /**
     * Get the unitdate field.
     *
     * @return string
     */
    public function getUnitDate()
    {
        $unitdate = $this->getXmlRecord()->xpath('did/unitdate');
        if (isset($unitdate[0])) {
            return (string)$unitdate[0];
        }
        return '';
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $results = [];
        // Main title might be already normalized, but do it again to make sure:
        $mainTitle = \Normalizer::normalize($this->getTitle(), \Normalizer::FORM_KC);
        $xml = $this->getXmlRecord();
        // Get unit id for comparison with it:
        $unitId = '';
        foreach ($xml->did->unitid ?? [] as $id) {
            $checkedId = (string)$id . ' ';
            if (str_starts_with($mainTitle, \Normalizer::normalize($checkedId, \Normalizer::FORM_KC))) {
                $unitId = $checkedId;
                break;
            }
        }

        foreach ($xml->did->unittitle ?? [] as $title) {
            $title = trim((string)$title);
            $normalized = \Normalizer::normalize($title, \Normalizer::FORM_KC);
            $normalizedWithId
                = \Normalizer::normalize($unitId . $title, \Normalizer::FORM_KC);
            // Compare with the beginning of main title since it may have additional
            // information appended to it:
            $len = mb_strlen($normalized, 'UTF-8');
            $lenId = mb_strlen($normalizedWithId, 'UTF-8');
            if (
                mb_substr($mainTitle, 0, $len, 'UTF-8') !== $normalized
                && mb_substr($mainTitle, 0, $lenId, 'UTF-8') !== $normalizedWithId
            ) {
                $results[] = $title;
            }
        }

        return $results;
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
        if ('oai_ead' === $format) {
            return $this->fields['fullrecord'];
        }
        return parent::getXML($format, $baseUrl, $recordLink);
    }

    /**
     * Replace placeholders in the URL with the values from the record
     *
     * @param string $url URL
     *
     * @return string URL with placeholders replaced
     */
    protected function replaceURLPlaceholders($url)
    {
        $originationId = $this->getOriginationId();
        [$id] = $this->getIdentifier();
        [, $nonPrefixedOriginationId] = explode('-', $originationId, 2);
        $url = str_replace(
            [
                '{id}',
                '{originationId}',
                '{nonPrefixedOriginationId}',
            ],
            [
                urlencode($id),
                urlencode($originationId),
                urlencode($nonPrefixedOriginationId),
            ],
            $url
        );
        return $url;
    }

    /**
     * Build a record array for hierarchy display
     *
     * @param array $ids    Record IDs
     * @param array $titles Record titles
     *
     * @return array
     */
    protected function buildHierarchicalRecordArray(array $ids, array $titles): array
    {
        $sourceId = $this->getSourceIdentifier();
        $result = [];
        while ($ids) {
            $result[] = [
                'id' => "$sourceId|" . array_shift($ids),
                'title' => $titles ? array_shift($titles) : '',
            ];
        }
        return $result;
    }
}
