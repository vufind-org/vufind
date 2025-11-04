<?php

/**
 * Model for MARC records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2020.
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
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use function array_slice;
use function count;
use function in_array;
use function intval;
use function is_array;
use function is_string;
use function strlen;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrMarc extends \VuFind\RecordDriver\SolrMarc implements \Psr\Log\LoggerAwareInterface
{
    use Feature\SolrFinnaTrait;
    use Feature\FinnaMarcReaderTrait;
    use Feature\FinnaUrlCheckTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Fields that may contain subject headings, and their descriptions
     *
     * @var array
     */
    protected $subjectFields = [
        '600' => 'personal name',
        '610' => 'corporate name',
        '611' => 'meeting name',
        '630' => 'uniform title',
        '648' => 'chronological',
        '650' => 'topic',
        '651' => 'geographic',
        '653' => '',
        '656' => 'occupation',
        '690' => 'topic',
    ];

    /**
     * Accepted book binding strings mapped to translation key strings
     *
     * @var array
     */
    protected $bindingMappings = [
        'nidottu'         => 'stitched',
        'nid'             => 'stitched',
        'häftad'          => 'stitched',
        'hft'             => 'stitched',
        'sidottu'         => 'bound',
        'sid'             => 'bound',
        'inbunden'        => 'bound',
        'inb'             => 'bound',
        'pehmeäkantinen'  => 'paperback',
        'kovakantinen'    => 'hardcover',
    ];

    /**
     * Mappings for component part relations
     *
     * @var array
     */
    protected $relationMappings = [
        'Sisältyy kokoelmaan' => 'Included in collections',
    ];

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
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        $result = [];
        $fields = $this->getMarcReader()->getFields('506');
        foreach ($fields as $field) {
            if ($subfield = $this->getSubfield($field, 'a')) {
                $access = $this->stripTrailingPunctuation($subfield);
                if ($subfield = $this->getSubfield($field, 'e')) {
                    $access .= ' (' .
                        $this->stripTrailingPunctuation($subfield) . ')';
                }
                $result[] = $access;
            }
        }
        return $result;
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
        $fields = $this->getMarcReader()->getFields('506');
        foreach ($fields as $field) {
            if ($link = $this->getSubfield($field, 'u')) {
                return compact('link');
            }
        }

        return false;
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
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }
        // Load configurations:
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? explode(',', $this->mainConfig->Record->marc_links) : [];
        $useVisibilityIndicator
            = $this->mainConfig->Record->marc_links_use_visibility_indicator ?? true;

        // Temporarily add 730 as a link field by default, may be removed later
        if (!in_array('730', $fieldsNames)) {
            $fieldsNames[] = '730';
        }

        $result = [];
        foreach ($fieldsNames as $value) {
            $value = trim($value);
            $fields = $this->getMarcReader()->getFields($value);
            foreach ($fields as $field) {
                if ($value == '730') {
                    // Handle 730 separately so that ind2 can be checked.
                    if ($field['i2'] !== ' ') {
                        continue;
                    }
                } else {
                    // Check to see if we should display at all
                    if ($useVisibilityIndicator) {
                        $visibilityIndicator = $field['i1'];
                        if ($visibilityIndicator == '1') {
                            continue;
                        }
                    }
                }

                // Get data for field
                $tmp = $this->getFieldData($field);
                if (!$tmp) {
                    continue;
                }

                $tmp['isCollection'] = false;
                if ($value == '730') {
                    // getfieldData doesn't handle subfield a (it's not the same for
                    // other fields), so do it now if we didn't get a title:
                    if ('' === $tmp['value']) {
                        $tmp['value'] = $this->getSubfield($field, 'a');
                        if ('title' === $tmp['link']['type']) {
                            $tmp['link']['value'] = $tmp['value'];
                        }
                        // get also subfield g for related misc info
                        $tmp['misc'] = $this->getSubfield($field, 'g');
                    }
                } elseif ($value == '775' || $value == '776') {
                    // We need to display most of the subfields in this case
                    $line = [];
                    foreach ($this->getAllSubfields($field) as $subfield) {
                        if (!in_array($subfield['code'], ['i', 'l', 'w', '4', '6', '7', '8'])) {
                            $line[] = $subfield['data'];
                        }
                    }
                    $tmp['value'] = implode(' ', $line);
                } elseif ($value == '773') {
                    $relation =
                        $this->relationMappings[$this->stripTrailingPunctuation($this->getSubfield($field, 'i'), ':')]
                        ?? null;
                    if ($relation) {
                        // Use relation as the field heading:
                        $tmp['title'] = $relation;
                        $tmp['isCollection'] = true;
                    }
                }
                $result[] = $tmp;
            }
        }

        foreach ($result as &$link) {
            if (isset($link['value'])) {
                $link['value'] = $this->stripTrailingPunctuation($link['value']);
            }
        }
        unset($link);

        $this->cache[__FUNCTION__] = $result;
        return $result;
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

        $urls = [];
        foreach ($this->getMarcReader()->getFields('856') as $url) {
            $address = $this->getSubfield($url, 'u');
            if (!$address) {
                continue;
            }

            $type = $this->getSubfield($url, 'q');
            $image = 'image/jpeg' === $type || strcasecmp('image', $type) === 0;
            $pdf = 'application/pdf' === $type || preg_match('/\.pdf$/i', $address);
            // Check second indicator for exact presentation of the resource.
            // Ignore for images etc. since they can use value '2' e.g. to represent just the cover.
            if ($pdf && !in_array($url['i2'], [' ', '0'])) {
                continue;
            }
            if (!$image && !$pdf) {
                // Overdrive records only have a subfield 3, check for that
                $part = $this->getSubfield($url, '3');
                // Only take large image. Thumbnail is too small.
                $image = strcasecmp($part, 'Image') === 0;
                if (!$image) {
                    // Some Quria records do not have subfield q, check subfield z
                    $image = 'Kansikuva' === $this->getSubfield($url, 'z');
                }
            }

            if ($pdf || ($image && $this->isUrlLoadable($address, $this->getUniqueID()))) {
                $urls[$image ? 'images' : 'pdfs'][] = [
                    'urls' => [
                        'small' => $address,
                        'medium' => $address,
                        'large' => $address,
                    ],
                    'description' => '',
                    'rights' => [],
                    'downloadable' => false,
                    'pdf' => $pdf,
                ];
            }
        }

        if ($urls['images'] ?? []) {
            $result = $urls['images'];
        } elseif ($includePdf && ($urls['pdfs'] ?? false)) {
            $result = array_slice($urls['pdfs'], 0, 1);
        } else {
            $result = [];
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('246', ['a', 'b', 'f'])
        );
    }

    /**
     * Get an array of classifications for the record.
     *
     * @return array
     */
    public function getClassifications()
    {
        $result = [];

        foreach (['050', '060', '080', '084'] as $fieldCode) {
            $fields = $this->getMarcReader()->getFields($fieldCode);
            foreach ($fields as $field) {
                switch ($fieldCode) {
                    case '050':
                        $classification = 'dlc';
                        break;
                    case '060':
                        $classification = 'nlm';
                        break;
                    case '080':
                        $classification = 'udk';
                        $version = $this->getSubfield($field, '2');
                        $isFennicaOrFinuc = in_array(
                            $version,
                            ['1974/fin/fennica', '1974/fin/finuc-s']
                        );
                        if ($isFennicaOrFinuc) {
                            $classification .= 'f';
                        } elseif (
                            $version
                            && preg_match('/(\d{4})/', $version, $matches)
                            && (int)$matches[1] >= 2009
                        ) {
                            $classification .= '2';
                        } else {
                            $classification .= 'x';
                        }
                        break;
                    default:
                        $classification = $this->getSubfield($field, '2');
                        break;
                }
                // continue doesn't work inside the switch statement
                if (empty($classification)) {
                    continue;
                }

                $subfields = $this->getSubfieldArray($field, ['a', 'b']);
                if (!empty($subfields)) {
                    $class = $subfields[0];
                    if ($x = $this->getSubfield($field, 'x')) {
                        if (preg_match('/^\w/', $x)) {
                            $class .= '-';
                        }
                        $class .= $x;
                    }
                    $result[$classification][] = $class;
                }
            }
        }
        return $result;
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        if (!isset($this->cache[__FUNCTION__])) {
            $this->cache[__FUNCTION__] = parent::getCleanDOI();
        }
        return $this->cache[__FUNCTION__];
    }

    /**
     * Get just the first listed OCLC Number (or false if none available).
     *
     * @return mixed
     */
    public function getCleanOCLCNum()
    {
        if (!isset($this->cache[__FUNCTION__])) {
            $this->cache[__FUNCTION__] = parent::getCleanOCLCNum();
        }
        return $this->cache[__FUNCTION__];
    }

    /**
     * Get just the first listed UPC Number (or false if none available).
     *
     * @return mixed
     */
    public function getCleanUPC()
    {
        if (!isset($this->cache[__FUNCTION__])) {
            $this->cache[__FUNCTION__] = parent::getCleanUPC();
        }
        return $this->cache[__FUNCTION__];
    }

    /**
     * Get just the first listed national bibliography number (or false if none
     * available).
     *
     * @return mixed
     */
    public function getCleanNBN()
    {
        if (!isset($this->cache[__FUNCTION__])) {
            $this->cache[__FUNCTION__] = parent::getCleanNBN();
        }
        return $this->cache[__FUNCTION__];
    }

    /**
     * Get just the base portion of the first listed ISMN (or false if no ISSMs).
     *
     * @return mixed
     */
    public function getCleanISMN()
    {
        if (!isset($this->cache[__FUNCTION__])) {
            $this->cache[__FUNCTION__] = parent::getCleanISMN();
        }
        return $this->cache[__FUNCTION__];
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        foreach ($this->getMarcReader()->getFields('773') as $field) {
            $subfield = $this->getSubfield($field, 'g');
            if (!$subfield) {
                continue;
            }
            if (
                preg_match('/,\s*\w\.?\s*([\d,\-]+)/', $subfield, $matches)
                || preg_match('/^\w\.?\s*([\d,\-]+)/', $subfield, $matches)
            ) {
                $pages = explode('-', $matches[1]);
                if (isset($pages[1])) {
                    return $pages[1];
                }
            }
        }
        return '';
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        $result = parent::getContainerTitle();
        if (!$result) {
            foreach ($this->getMarcReader()->getFields('773') as $field) {
                if ($result = $this->getSubfield($field, 't')) {
                    break;
                }
            }
        }
        return $this->stripTrailingPunctuation($result, '\.-');
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     */
    public function getDescriptionURL()
    {
        $url = '';
        $type = '';
        foreach ($this->getMarcReader()->getFields('856') as $url) {
            if ($type = $this->getSubfield($url, 'q')) {
                $type = mb_strtolower($type, 'UTF-8');
                if ('text' === $type || 'text/html' === $type) {
                    if ($address = $this->getSubfield($url, 'u')) {
                        if ($this->isUrlLoadable($address, $this->getUniqueID())) {
                            return $address;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get an array of Dewey classifications for the record.
     *
     * @return array
     */
    public function getDeweyClassifications()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('082') as $field) {
            if ($result = $this->getSubfield($field, 'a')) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Get dissertation note for the record.
     * Use field 502 if available. If not, use local field 509 or 920.
     *
     * @return string dissertation notes
     */
    public function getDissertationNote()
    {
        $notes = $this->stripTrailingPunctuation($this->getFirstFieldValue('502', ['a', 'b', 'c', 'd']));
        if (!$notes) {
            // 509 used in Voyager
            // TODO: Is this used anymore anywhere?
            $notes = $this->stripTrailingPunctuation($this->getFirstFieldValue('509', ['a', 'b', 'c', 'd']));
        }
        if (!$notes) {
            // 920 used in Alma
            $notes = $this->stripTrailingPunctuation($this->getFirstFieldValue('920', ['a', 'b', 'c', 'd']));
        }
        return $notes;
    }

    /**
     * Get original version notes.
     * Each result contains:
     * - notes => Notes found
     *
     * @return array
     */
    public function getOriginalVersionNotes(): array
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('534') as $field) {
            $result = [];
            if ($subfields = $this->getSubfieldArray($field, ['p', 'c', 'n', 'l'])) {
                $result['notes'] = implode(' ', $subfields);
            }
            if ($result) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Get an array of embedded component parts
     *
     * @param boolean $onlyCollections Only get component parts that are collections
     *
     * @return array Component parts
     */
    public function getEmbeddedComponentParts($onlyCollections = false)
    {
        $componentParts = [];
        $partOrderCounter = 0;
        foreach ($this->getMarcReader()->getFields('979') as $field) {
            if ($onlyCollections && $field['i2'] !== '1') {
                continue;
            }
            $partOrderCounter++;
            $partAuthors = [];
            $uniformTitle = '';
            $duration = '';
            $partTitle = '';
            $partId = '';
            foreach ($this->getAllSubfields($field) as $subfield) {
                $data = trim($subfield['data']);
                if ('' === $data) {
                    continue;
                }
                switch ($subfield['code']) {
                    case 'a':
                        $partId = $data;
                        break;
                    case 'b':
                        $partTitle = $data;
                        break;
                    case 'c':
                        $partAuthors[] = $data;
                        break;
                    case 'd':
                        $partAuthors[] = $data;
                        break;
                    case 'e':
                        $uniformTitle = $data;
                        break;
                    case 'f':
                        $duration = $data;
                        if ($duration == '000000') {
                            $duration = '';
                        }
                        break;
                }
            }
            $partPresenters = [];
            $partArrangers = [];
            $partOtherAuthors = [];
            foreach ($partAuthors as $author) {
                if (isset($this->mainConfig->Record->presenter_roles)) {
                    foreach ($this->mainConfig->Record->presenter_roles as $role) {
                        $author = trim($author);
                        if (substr($author, -strlen($role) - 2) == ", $role") {
                            $partPresenters[] = $author;
                            continue 2;
                        }
                    }
                }
                if (isset($this->mainConfig->Record->arranger_roles)) {
                    foreach ($this->mainConfig->Record->arranger_roles as $role) {
                        if (substr($author, -strlen($role) - 2) == ", $role") {
                            $partArrangers[] = $author;
                            continue 2;
                        }
                    }
                }
                $partOtherAuthors[] = $author;
            }

            $componentParts[] = [
                'number' => $partOrderCounter,
                'id' => $partId,
                'title' => $partTitle,
                'authors' => $partAuthors,
                'uniformTitle' => $uniformTitle,
                'duration' => $duration
                    ? substr($duration, 0, 2) . ':' . substr($duration, 2, 2)
                        . ':' . substr($duration, 4, 2)
                    : '',
                'presenters' => $partPresenters,
                'arrangers' => $partArrangers,
                'otherAuthors' => $partOtherAuthors,
            ];
        }
        // Return here if only collections were requested as the indicator 2 differs from 979
        if ($onlyCollections) {
            return $componentParts;
        }

        // Try fields 700 and 730 if 979 is empty
        if (!$componentParts) {
            foreach ($this->getMarcReader()->getFields('700') as $field) {
                if ($field['i2'] != 2 || '' === $this->getSubfield($field, 't')) {
                    continue;
                }
                $partOrderCounter++;

                $partTitle = $this->getSubfieldArray(
                    $field,
                    ['t', 'm', 'n', 'r', 'h', 'i', 'g', 'n', 'p', 's', 'l', 'o', 'k']
                );
                $partTitle = reset($partTitle);
                $partAuthors = $this->getSubfieldArray(
                    $field,
                    ['a', 'q', 'b', 'c', 'd', 'e']
                );

                $partPresenters = [];
                $partArrangers = [];
                $partOtherAuthors = [];
                foreach ($partAuthors as $author) {
                    if (isset($this->recordConfig['Record']['presenter_roles'])) {
                        foreach ($this->recordConfig['Record']['presenter_roles'] as $role) {
                            $author = trim($author);
                            if (substr($author, -strlen($role) - 2) == ", $role") {
                                $partPresenters[] = $author;
                                continue 2;
                            }
                        }
                    }
                    if (isset($this->recordConfig['Record']['arranger_roles'])) {
                        foreach ($this->recordConfig['Record']['arranger_roles'] as $role) {
                            if (substr($author, -strlen($role) - 2) == ", $role") {
                                $partArrangers[] = $author;
                                continue 2;
                            }
                        }
                    }
                    $partOtherAuthors[] = $author;
                }

                $componentParts[] = [
                    'number' => $partOrderCounter,
                    'title' => $partTitle,
                    'link' => null,
                    'authors' => $partAuthors,
                    'uniformTitle' => '',
                    'duration' => '',
                    'presenters' => $partPresenters,
                    'arrangers' => $partArrangers,
                    'otherAuthors' => $partOtherAuthors,
                ];
            }

            foreach ($this->getMarcReader()->getFields('730') as $field) {
                if ($field['i2'] != 2) {
                    continue;
                }

                $partTitle = $this->getSubfieldArray(
                    $field,
                    ['m', 'n', 'r', 'h', 'i', 'g', 'n', 'p', 's', 'l', 'o', 'k']
                );
                $partTitle = reset($partTitle);

                // If there's only a uniform title without a title, use it as the
                // title. Otherwise leave uniform title to its own field.
                $partUniformTitle = $this->getSubfield($field, 'a');
                $partTitleMain = $this->getSubfield($field, 't');
                if ('' === $partTitleMain) {
                    if ('' === $partUniformTitle) {
                        continue;
                    }
                    $partTitle = "$partUniformTitle $partTitle";
                    $partUniformTitle = '';
                } else {
                    $partTitle = "$partTitleMain $partTitle";
                }

                $partOrderCounter++;

                $partAuthors = [];
                $partPresenters = [];
                $partArrangers = [];
                $partOtherAuthors = [];
                $componentParts[] = [
                    'number' => $partOrderCounter,
                    'title' => $partTitle,
                    'link' => null,
                    'authors' => [],
                    'uniformTitle' => $partUniformTitle,
                    'duration' => '',
                    'presenters' => [],
                    'arrangers' => [],
                    'otherAuthors' => [],
                ];
            }
        }

        return $componentParts;
    }

    /**
     * Get extended composition information from field 382.
     *
     * Returns an array where each entry contains a set of subfields with a type code
     * (see $typeMap below).
     *
     * @return array
     */
    public function getExtendedMusicCompositions()
    {
        $results = [];
        $typeMap = [
            'a' => 'medium',
            'b' => 'soloist',
            'd' => 'doublingInstrument',
            'e' => 'numEnsemblesOfSameType',
            'n' => 'numPerformersOfSameMedium',
            'p' => 'altMedium',
            'r' => 'numIndividualPerformers',
            's' => 'numPerformers',
            't' => 'numEnsembles',
            'v' => 'note',
            '3' => 'materials',
        ];
        $marc = $this->getMarcReader();
        foreach ($marc->getFields('382') as $field) {
            $allSubfields = $this->getAllSubfields($field);
            $items = [];
            foreach ($allSubfields as $subfield) {
                $code = $subfield['code'];
                if (
                    ($type = $typeMap[$code] ?? false)
                    && ($contents = trim($subfield['data']))
                ) {
                    $items[] = compact('type', 'contents');
                }
            }
            if ($items) {
                $results[] = [
                    'partial' => $field['i1'] === '1',
                    'items' => $items,
                ];
            }
        }
        return $results;
    }

    /**
     * Get an array of all extent information.
     *
     * @return array
     */
    public function getExtent()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('300') as $field) {
            foreach ($this->getSubfields($field, 'a') as $extent) {
                $results[] = $this->stripTrailingPunctuation($extent);
            }
        }
        return $results;
    }

    /**
     * Return full record as a filtered SimpleXMLElement for public APIs.
     *
     * This is not particularly beautiful, but the aim is to do the work with the
     * least effort.
     *
     * @return \SimpleXMLElement
     */
    public function getFilteredXMLElement(): \SimpleXMLElement
    {
        $collection = new \DOMDocument();
        $collection->preserveWhiteSpace = false;
        $collection->loadXML($this->getMarcReader()->toFormat('MARCXML'));
        $record = $collection->getElementsByTagName('record')->item(0);
        $fieldsToRemove = [];
        $componentPartIds = [];
        foreach ($record->getElementsByTagName('datafield') as $field) {
            $tag = $field->getAttribute('tag');
            // Delete 520 (summary etc. may contain material under copyright) and
            // 979 (we will add a new one with just component part ids):
            if ('520' === $tag) {
                $fieldsToRemove[] = $field;
            } elseif ('979' === $tag) {
                foreach ($field->getElementsByTagName('subfield') as $subfield) {
                    if ('a' === $subfield->getAttribute('code')) {
                        $componentPartIds[] = $subfield->textContent;
                    }
                }
                $fieldsToRemove[] = $field;
            }
        }
        foreach ($fieldsToRemove as $field) {
            $record->removeChild($field);
        }
        if ($componentPartIds) {
            $field = $collection->createElement('datafield');
            $tag = $collection->createAttribute('tag');
            $tag->value = '979';
            $field->appendChild($tag);
            $ind1 = $collection->createAttribute('ind1');
            $ind1->value = ' ';
            $field->appendChild($ind1);
            $ind2 = $collection->createAttribute('ind2');
            $ind2->value = ' ';
            $field->appendChild($ind2);
            foreach ($componentPartIds as $id) {
                $subfield = $collection->createElement('subfield');
                $code = $collection->createAttribute('code');
                $code->value = 'a';
                $subfield->appendChild($code);
                $subfield->appendChild($collection->createTextNode($id));
                $field->appendChild($subfield);
            }
            $record->appendChild($field);
        }

        return simplexml_import_dom($collection);
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
     * Return whether holds are allowed.
     *
     * @return boolean
     */
    public function getHoldsAllowed()
    {
        return empty($this->mainConfig->Catalog->disable_driver_hold_actions)
            || !array_intersect(
                $this->getFormats(),
                $this->mainConfig->Catalog->disable_driver_hold_actions->toArray()
            );
    }

    /**
     * Get an array of host records
     *
     * Return an array of arrays with the following keys:
     *   id
     *   title
     *   reference
     *   Place, publisher, and date of publication
     *
     * @return array
     */
    public function getHostRecords()
    {
        $result = [];
        $sourceId = $this->getSourceIdentifier();
        $fields = $this->getMarcReader()->getFields('773');

        if (
            !empty($this->fields['hierarchy_parent_id'])
            && count($this->fields['hierarchy_parent_id']) > count($fields)
        ) {
            // Can't use 773 fields since they don't represent the actual links
            foreach ($this->fields['hierarchy_parent_id'] as $key => $parentId) {
                $title = $this->fields['hierarchy_parent_title'][$key]
                    ?? $this->fields['hierarchy_parent_title'][0]
                    ?? 'Title not available';
                $result[] = [
                    'id' => $parentId,
                    'sourceId' => $sourceId,
                    'linkingId' => '',
                    'title' => $title,
                    'reference' => '',
                    'publishingInfo' => '',
                ];
            }
            return $result;
        }

        $recordSource = $this->getDataSource();
        $linkPrefixes = $this->getRecordLinkingPrefixes($recordSource);

        $useLegacyLinkingId = $this->datasourceSettings[$recordSource]['legacy_settings']['linking_id'] ?? false;
        foreach ($fields as $field) {
            $id = '';
            $linkingId = '';
            $title = '';
            $reference = '';
            $publishingInfo = '';
            $author = '';
            $relation = '';
            foreach ($this->getAllSubfields($field) as $subfield) {
                $data = $subfield['data'];
                switch ($subfield['code']) {
                    case 'w':
                        // Datasource has been forced to use legacy linking method
                        if ($useLegacyLinkingId) {
                            $id = $this->getIdFromLinkingField($data);
                            break;
                        }
                        foreach ($linkPrefixes as $prefix) {
                            if ($this->getIdFromLinkingField($data, $prefix)) {
                                $linkingId = $data;
                                break 2;
                            }
                        }
                        $found = $this->getIdFromLinkingField($data);
                        if (!$found) {
                            break;
                        }
                        // If id does not match any of the previous, then assume its bib id.
                        $id = $found;
                        break;
                    case 't':
                        $title = $this->stripTrailingPunctuation($data, '.-');
                        break;
                    case 'i':
                        $relation = $this->relationMappings[$this->stripTrailingPunctuation($data, ':')] ?? '';
                        break;
                    case 'g':
                        $reference = $data;
                        break;
                    case 'd':
                        $publishingInfo
                            = $this->stripTrailingPunctuation($data, '.-');
                        break;
                    case 'a':
                        $author = $this->stripTrailingPunctuation($data, '.-');
                        break;
                }
            }

            if (
                count($fields) === 1
                && !empty($this->fields['hierarchy_parent_id'])
            ) {
                // If we only have one field, use the hierarchy data for id
                $id = $this->fields['hierarchy_parent_id'];
                if (is_array($id)) {
                    $id = reset($id);
                }
            }

            $result[] = [
                'id' => $id,
                'linkingId' => $linkingId,
                'sourceId' => $sourceId,
                'title' => $title,
                'reference' => $reference,
                'publishingInfo' => $publishingInfo,
                'mainHeading' => $author,
                'relation' => $relation,
            ];
        }
        return $result;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }

        $fields = [
            '020' => ['a', 'q'],
            '773' => ['z'],
        ];
        $isbn = [];
        foreach ($fields as $field => $subfields) {
            $isbn = array_merge(
                $isbn,
                $this->stripTrailingPunctuation(
                    $this->getFieldArray($field, $subfields),
                    '-'
                )
            );
        }

        $result = array_values(array_unique(array_filter($isbn)));
        $this->cache[__FUNCTION__] = $result;
        return $result;
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        if (isset($this->cache[__FUNCTION__])) {
            return $this->cache[__FUNCTION__];
        }

        $fields = [
            '022' => ['a'],
            /* We don't want to display all ISSNs without further
             * explanation on their relationship with this record.
            '440' => ['x'],
            '490' => ['x'],
            '730' => ['x'],
            '773' => ['x'],
            '776' => ['x'],
            '780' => ['x'],
            '785' => ['x']
             */
        ];
        $issn = [];
        foreach ($fields as $field => $subfields) {
            $issn = array_merge(
                $issn,
                $this->stripTrailingPunctuation(
                    $this->getFieldArray($field, $subfields),
                    '-'
                )
            );
        }
        $result = array_values(array_unique(array_filter($issn)));
        $this->cache[__FUNCTION__] = $result;
        return $result;
    }

    /**
     * Get manufacturer
     *
     * @return string
     */
    public function getManufacturer()
    {
        // First check for manufacturer in field 264
        foreach ($this->getMarcReader()->getFields('264') as $field) {
            if ($field['i2'] != 3) {
                continue;
            }
            $result = $this->getSubfieldArray($field, ['a', 'b', 'c']);
            return $result ? $result[0] : '';
        }
        // Use 260 if 264 for manufacturer not found
        return $this->getFirstFieldValue('260', ['e', 'f', 'g']);
    }

    /**
     * Get producers
     *
     * @return array
     */
    public function getProducers()
    {
        $result = [];
        foreach ($this->getMarcReader()->getFields('264') as $field) {
            if ($field['i2'] == 0) {
                if ($name = $this->stripTrailingPunctuation($this->getSubfieldArray($field, ['a', 'b', 'c']))) {
                    $result[] = [
                        'name' => $name[0],
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Get all authors and primary presenters
     *
     * @return array
     */
    public function getPrimaryAuthors(): array
    {
        return array_column($this->getPrimaryAuthorsExtended(), 'name');
    }

    /**
     * Return extended author information
     *
     * @return array
     */
    public function getPrimaryAuthorsExtended(): array
    {
        return $this->getAuthorFields(true);
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        return $this->getAuthorFields();
    }

    /**
     * Gets all author fields
     *
     * @param bool $getPrimaryPresenters Whether the function returns primary presenters alongside authors (optional)
     *
     * @return array
     */
    private function getAuthorFields(bool $getPrimaryPresenters = false): array
    {
        $result = [];
        foreach (['100', '110', '700', '710'] as $fieldCode) {
            $fields = $this->getMarcReader()->getFields($fieldCode);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    // Leave out 700 fields containing subfield 't' (these go to the
                    // contents list)
                    if ($fieldCode == '700' && $this->getSubfield($field, 't')) {
                        continue;
                    }
                    $checkPresenterRole =
                        ($getPrimaryPresenters && in_array($fieldCode, ['100', '110'])) ? false : true;

                    $roles = $this->getSubfields($field, '4');
                    if (empty($roles)) {
                        $roles = $this->getSubfields($field, 'e');
                    }
                    $roles = array_map([$this, 'stripTrailingPunctuation'], $roles);
                    $role = implode(', ', $roles);
                    $role = mb_strtolower($role, 'UTF-8');
                    if ($role) {
                        // Check hidden roles
                        $hiddenRoles = (array)($this->mainConfig?->Record?->hidden_author_roles?->toArray() ?? []);
                        if ($hiddenRoles && in_array(trim($role, ' .'), $hiddenRoles)) {
                            continue;
                        }

                        // Check presenter roles
                        if (
                            $checkPresenterRole
                            && isset($this->mainConfig->Record->presenter_roles)
                            && in_array(
                                trim($role, ' .'),
                                $this->mainConfig->Record->presenter_roles->toArray()
                            )
                        ) {
                            continue;
                        }
                    }
                    $subfields = $this->getSubfieldArray($field, ['a', 'b', 'c']);
                    if (empty($subfields)) {
                        continue;
                    }
                    $dates = $this->getSubfieldArray($field, ['d']);

                    $altSubfields = $this->getLinkedMarcFieldContents(
                        $field,
                        ['a', 'b', 'c']
                    );
                    $altSubfields = $this->stripTrailingPunctuation($altSubfields);

                    $id = $this->getSubfield($field, '0');
                    $result[] = [
                        'name' => $this->stripTrailingPunctuation($subfields[0]),
                        'name_alt' => $altSubfields,
                        'date' => !empty($dates)
                            ? $this->stripTrailingPunctuation($dates[0]) : '',
                        'role' => $role,
                        'id' => $id ?: null,
                        'type' => in_array($fieldCode, ['100', '700'])
                            ? 'Personal Name' : 'Corporate Name',
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Get the "other links" from MARC field 787.
     *
     * @return array An array of keyed arrays with keys heading, title, author
     * and isn
     */
    public function getOtherLinks()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('787') as $link) {
            $heading = $this->getSubfield($link, 'i');
            // Normalize heading
            $heading = str_replace(':', '', $heading);
            $title = $this->getSubfield($link, 't');
            $author = $this->getSubfield($link, 'a');
            $isbn = $this->getSubfield($link, 'z');
            $issn = $this->getSubfield($link, 'x');
            if ($isbn) {
                $isn = $isbn;
            } elseif ($issn) {
                $isn = $issn;
            } else {
                $isn = '';
            }

            $results[] = compact('heading', 'title', 'author', 'isn');
        }
        return $results;
    }

    /**
     * Get presenters
     *
     * @param bool $getSecondaryPresentersOnly Whether returns only secondary presenters
     *
     * @return array
     */
    public function getPresenters($getSecondaryPresentersOnly = false): array
    {
        $result = ['presenters' => [], 'details' => []];

        foreach (['100', '110', '700', '710'] as $fieldCode) {
            $fields = $this->getMarcReader()->getFields($fieldCode);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if ($getSecondaryPresentersOnly && in_array($fieldCode, ['100', '110'])) {
                        continue;
                    }
                    // Leave out 700 fields containing subfield 't' (these go to the
                    // contents list)
                    if ($fieldCode == '700' && $this->getSubfield($field, 't')) {
                        continue;
                    }

                    $role = $this->getSubfield($field, '4');
                    if (empty($role)) {
                        $role = $this->getSubfield($field, 'e');
                    }
                    $role = mb_strtolower($role, 'UTF-8');
                    $role = $this->stripTrailingPunctuation($role);
                    if (
                        !$role
                        || !isset($this->mainConfig->Record->presenter_roles)
                        || !in_array(
                            trim($role, ' .'),
                            $this->mainConfig->Record->presenter_roles->toArray()
                        )
                    ) {
                        continue;
                    }
                    $subfields = $this->getSubfieldArray($field, ['a', 'b', 'c']);
                    if (empty($subfields)) {
                        continue;
                    }
                    $dates = $this->getSubfieldArray($field, ['d']);
                    $id = $this->getSubfield($field, '0');
                    $result['presenters'][] = [
                        'name' => $this->stripTrailingPunctuation($subfields[0]),
                        'date' => $dates
                            ? $this->stripTrailingPunctuation($dates[0]) : '',
                        'role' => $role,
                        'id' => $id ?: null,
                    ];
                }
            }
        }
        $result['details'] = $this->stripTrailingPunctuation(
            $this->getFieldArray('511', ['a'])
        );
        return $result;
    }

    /**
     * Get secondary presenters
     *
     * @return array
     */
    public function getSecondaryPresenters(): array
    {
        return $this->getPresenters(true);
    }

    /**
     * Get the main author of the record (without year and role).
     *
     * @return string
     */
    public function getPrimaryAuthorForSearch()
    {
        $authors = $this->getNonPresenterAuthors();
        if ($authors) {
            return $authors[0]['name'];
        }
        return '';
    }

    /**
     * Get the estimated publication date of the record.
     *
     * @return string
     */
    public function getProjectedPublicationDate()
    {
        $dateString = $this->getFirstFieldValue('263', ['a']) ?? '';
        if (strlen($dateString) === 8) {
            $year = intval(substr($dateString, 0, 4));
            $month = intval(substr($dateString, 4, 2));
            $day = intval(substr($dateString, 6, 2));
            return implode('.', [$day, $month, $year]);
        } elseif (strlen($dateString) === 6) {
            $year = intval(substr($dateString, 0, 4));
            $month = intval(substr($dateString, 4, 2));
            return implode('/', [$month, $year]);
        }
        return $dateString;
    }

    /**
     * Get the publication end date of the record
     *
     * @return number|false
     */
    public function getPublicationEndDate()
    {
        $field = $this->getMarcReader()->getField('008');
        if ($field) {
            $year = substr($field, 11, 4);
            $type = substr($field, 6, 1);
            if (is_numeric($year) && $year != 0 && $type != 'e') {
                return $year;
            }
        }
        return false;
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @param bool $onlyPersonalNames Whether to return only personal names (700)
     *
     * @return array
     */
    public function getSecondaryAuthors($onlyPersonalNames = false)
    {
        if (!$onlyPersonalNames) {
            return parent::getSecondaryAuthors();
        }
        $results = [];
        foreach ($this->getMarcReader()->getFields('700') as $field) {
            $name = $this->stripTrailingPunctuation($this->getSubfield($field, 'a'));
            if ($name) {
                $results[] = $name;
            }
        }
        return $results;
    }

    /**
     * Get an array of all series names containing the record. Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        $matches = [];

        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = [
            '440' => ['a', 'n', 'p'],
            '800' => ['a', 'b', 'c', 'd', 'f', 'n', 'p', 'q', 't', 'l', 'v'],
            '830' => ['a', 'v'],
        ];
        $matches = $this->getSeriesFromMARC($primaryFields);

        // Now check also 490:
        $secondaryFields = ['490' => ['a', 'v']];
        if (empty($matches)) {
            $matches = $this->getSeriesFromMARC($secondaryFields);
        } else {
            $matches = array_merge($matches, $this->getSeriesFromMARC($secondaryFields));
        }

        // Still no results found?  Resort to the Solr-based method just in case!
        if (empty($matches)) {
            $matches = parent::getSeries();
        }

        return $matches;
    }

    /**
     * Return SFX Object ID
     *
     * @return string
     */
    public function getSfxObjectId()
    {
        $record = $this->getMarcReader();
        $id = $record->getField('001');
        $field090 = $record->getField('090');
        $objectId = $field090 ? $this->getSubfield($field090, 'a') : '';
        if ($id === $objectId) {
            return $objectId;
        }
        return '';
    }

    /**
     * Return Alma MMS ID
     *
     * @return string
     */
    public function getAlmaMmsId()
    {
        $record = $this->getMarcReader();
        $id = $record->getField('001');
        foreach ($record->getFields('090') as $field090) {
            $objectId = $this->getSubfield($field090, 'a');
            if ($objectId) {
                if (strncmp($objectId, '(Alma)', 6) === 0) {
                    $objectId = substr($objectId, 6);
                } else {
                    $objectId = '';
                }
            }
            if ($id === $objectId) {
                return $objectId;
            }
        }
        return '';
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->stripTrailingPunctuation(
            $this->getFirstFieldValue('245', ['a', 'b', 'n', 'p']),
            '',
            true
        );
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->stripTrailingPunctuation(
            $this->getFirstFieldValue('245', ['a'])
        );
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->stripTrailingPunctuation(
            $this->getFirstFieldValue('245', ['b', 'n', 'p'])
        );
    }

    /**
     * Get the full titles of the record in alternative scripts.
     *
     * @return array
     */
    public function getTitlesAltScript(): array
    {
        if (!($titles = parent::getTitlesAltScript())) {
            $titles = $this->getMarcReader()
                ->getLinkedFieldsSubfields('880', '240', ['a', 'b']);
        }
        return $this->stripTrailingPunctuation($titles);
    }

    /**
     * Get the full titles of the record including section and part information in
     * alternative scripts.
     *
     * @return array
     */
    public function getFullTitlesAltScript(): array
    {
        if (!($titles = parent::getFullTitlesAltScript())) {
            $titles = $this->getMarcReader()
                ->getLinkedFieldsSubfields('880', '240', ['a', 'b', 'n', 'p']);
        }
        return $this->stripTrailingPunctuation($titles);
    }

    /**
     * Get the short (pre-subtitle) title of the record in alternative script.
     *
     * @return string
     *
     * @deprecated Use getTitlesAltScript instead
     */
    public function getShortTitleAltScript()
    {
        if (!($title = $this->getLinkedMarcFieldContents('245', ['a']))) {
            $title = $this->getLinkedMarcFieldContents('240', ['a', 'n', 'p']);
        }
        return $this->stripTrailingPunctuation($title);
    }

    /**
     * Get the subtitle of the record in alternative script.
     *
     * @return string
     *
     * @deprecated Use getTitlesAltScript instead
     */
    public function getSubtitleAltScript()
    {
        $title = $this->getLinkedMarcFieldContents('245', ['b', 'n', 'p']);
        return $this->stripTrailingPunctuation($title);
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @param string $language Language to return, if available
     *
     * @return array
     */
    public function getSummary($language = '')
    {
        $languageMappings = ['fin' => 'fi', 'swe' => 'sv', 'eng' => 'en-gb'];
        $languages = [];
        $marc = $this->getMarcReader();
        foreach ($marc->getFields('886') as $field) {
            $scope = $this->getSubfield($field, '2');
            if (!$scope || 'local' !== $scope) {
                continue;
            }
            $item = $this->getSubfield($field, 'a');
            if (!$item || !in_array($item, ['kieli', 'språk', 'language'])) {
                continue;
            }
            $link = $this->getSubfield($field, '8');
            $lng = $this->getSubfield($field, 'l');
            if ($link && $lng) {
                if (isset($languageMappings[$lng])) {
                    $lng = $languageMappings[$lng];
                }
                $languages[$link] = $lng;
            }
        }
        $summaries = [];
        foreach ($marc->getFields('520') as $field) {
            $summary = $this->getSubfield($field, 'a');
            if (!$summary) {
                continue;
            }
            $link = $this->getSubfield($field, '8');
            $lng = $link && isset($languages[$link]) ? $languages[$link] : '-';
            $summaries[$lng][] = $summary;
        }
        if ($language && isset($summaries[$language])) {
            return $summaries[$language];
        }
        $result = [];
        foreach ($summaries as $languageSummaries) {
            $result = array_merge($result, $languageSummaries);
        }
        return $result;
    }

    /**
     * Return terms governing use and reproduction as an array with the following
     * keys:
     * - material  Part of the material to which the field applies
     * - terms     Terms as text
     * - source    Source of authority for the restriction
     * - url       URL to terms
     * - rightsSource Source of the access licence (e.g. 'cc' for Creative Commons)
     * - rights    Licence code
     *
     * @return string
     */
    public function getTermsOfUse()
    {
        $result = [];
        foreach ($this->getMarcReader()->getFields('540') as $field) {
            $material = $this->getSubfield($field, '3');
            $terms = $this->getSubfield($field, 'a');
            $source = $this->getSubfield($field, 'c');
            $rightsSource = $this->getSubfield($field, '2');
            $rights = $this->getSubfield($field, 'f');
            $url = $this->getSubfield($field, 'u');
            if ($terms || $source || $url || ($rightsSource && $rights)) {
                $result[] = compact('material', 'terms', 'source', 'url', 'rightsSource', 'rights');
            }
        }
        return $result;
    }

    /**
     * Get the statement of responsibility that goes with the title (i.e. "by John
     * Smith").
     *
     * @return string
     */
    public function getTitleStatement()
    {
        return $this->stripTrailingPunctuation(parent::getTitleStatement());
    }

    /**
     * Get uniform titles.
     *
     * @return array
     */
    public function getUniformTitles()
    {
        $results = [];
        foreach (['130', '240'] as $fieldCode) {
            foreach ($this->getMarcReader()->getFields($fieldCode) as $field) {
                $results = [
                    ...$results,
                    ...$this->getSubfieldArray($field, range('a', 'z')),
                ];
            }
        }
        return $results;
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
        $retVal = [];

        // Which fields/subfields should we check for URLs?
        $fieldsToCheck = [
            '856' => ['y', 'z', '3'], // Standard URL
            '555' => ['a'],            // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->getMarcReader()->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $this->getSubfield($url, 'u');
                    // Require at least one dot surrounded by valid characters or a
                    // familiar scheme
                    if (
                        $address
                        && (preg_match('/[A-Za-z0-9]\.[A-Za-z0-9]/', $address)
                        || preg_match('/^(http|ftp)s?:\/\//', $address))
                    ) {
                        // Is there a description?  If not, just use the URL itself.
                        foreach ($subfields as $subfield) {
                            $desc = $this->getSubfield($url, $subfield);
                            if ($desc) {
                                break;
                            }
                        }
                        if (($note = $this->getSubfield($url, 'z')) === $desc) {
                            $note = '';
                        }
                        $part = '';
                        if ($desc) {
                            // Check for subfield 3 and include it as the part
                            // identifier if it's not used as the link description
                            if ($field == '856' && $subfield !== '3') {
                                $part = $this->stripTrailingPunctuation(
                                    $this->getSubfield($url, '3')
                                );
                            }
                        } else {
                            $desc = $address;
                        }

                        $data = [
                            'url' => $address, 'desc' => $desc, 'part' => $part, 'note' => $note,
                        ];
                        if (
                            !$this->urlBlocked($address, $desc)
                            && !in_array($data, $retVal)
                        ) {
                            if (!$this->maxAmountOfURLs()) {
                                $retVal[] = $data;
                            }
                            $this->urlsCount++;
                        }
                    }
                }
            }
        }
        $this->cache[__FUNCTION__] = $this->resolveUrlTypes($retVal);
        return $this->cache[__FUNCTION__];
    }

    /**
     * Does this record have embedded component parts
     *
     * @return bool Whether this record has embedded component parts
     */
    public function hasEmbeddedComponentParts()
    {
        if ($this->getMarcReader()->getFields('979')) {
            return true;
        }
        // Alternatively, are there titles in 700 fields?
        foreach ($this->getMarcReader()->getFields('700') as $field) {
            if ($field['i2'] == 2 && '' !== $this->getSubfield($field, 't')) {
                return true;
            }
        }
        // Or maybe in 730 fields?
        foreach ($this->getMarcReader()->getFields('730') as $field) {
            if (
                $field['i2'] == 2
                && ('' !== $this->getSubfield($field, 'a')
                || '' !== $this->getSubfield($field, 't'))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all subject headings associated with this record with extended data.
     * (see getAllSubjectHeadings).
     *
     * @return array
     */
    public function getAllSubjectHeadingsExtended()
    {
        return $this->getAllSubjectHeadings(true);
    }

    /**
     * Get all subject headings associated with this record. Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     * - id: first authority id (if defined)
     * - ids: multiple authority ids (if defined)
     * - authType: authority type (if id is defined)
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        $result = parent::getAllSubjectHeadings($extended);
        if (!$extended) {
            return $result;
        }

        $subjectIdFields = ['Personal Name' => ['600'], 'Corporate Name' => ['610']];
        foreach ($result as &$subject) {
            if (!$heading = $subject['heading'][0] ?? null) {
                continue;
            }
            $authType = null;

            // Check if we can find an authority id with a matching heading
            foreach ($subjectIdFields as $type => $codes) {
                foreach ($codes as $code) {
                    foreach ($this->getMarcReader()->getFields($code) as $field) {
                        $subfield = $this->getSubfield($field, 'a');
                        if (!$subfield || trim($subfield) !== $heading) {
                            continue;
                        }
                        if (isset($subject['id'])) {
                            $authType = $type;
                            break 3;
                        }
                    }
                }
            }
            $subject['authType'] = $authType;
        }
        return $result;
    }

    /**
     * Returns the array element for the 'getAllRecordLinks' method
     *
     * @param File_MARC_Data_Field $field Field to examine
     *
     * @return array|bool                 Array on success, boolean false if no
     * valid link could be found in the data.
     */
    protected function getFieldData($field)
    {
        // Make sure that there is a t field to be displayed:
        if (!($title = $this->getSubfield($field, 't'))) {
            $titleFields = [];
            if ($issn = $this->getSubfield($field, 'x')) {
                $titleFields[] = $issn;
            } elseif ($isbn = $this->getSubfield($field, 'z')) {
                $titleFields[] = $isbn;
            }
            $title = implode(' ', $titleFields);
        }

        if ($qualifyingInfo = $this->getSubfield($field, 'c')) {
            if ($title) {
                $title .= ' ';
            }
            $title .= $qualifyingInfo;
        }

        $linkTypeSetting = $this->mainConfig->Record->marc_links_link_types
            ?? 'id,oclc,dlc,isbn,issn,title';
        $linkTypes = explode(',', $linkTypeSetting);
        $linkFields = $this->getSubfields($field, 'w');
        $recordSource = $this->getDataSource();
        $linkPrefixes = $this->getRecordLinkingPrefixes($recordSource);

        $useLegacyLinkingId = $this->datasourceSettings[$recordSource]['legacy_settings']['linking_id'] ?? false;
        // Run through the link types specified in the config.
        // For each type, check field for reference
        // If reference found, exit loop and go straight to end
        // If no reference found, check the next link type instead
        foreach ($linkTypes as $linkType) {
            switch (trim($linkType)) {
                case 'oclc':
                    foreach ($linkFields as $current) {
                        $oclc = $this->getIdFromLinkingField($current, 'OCoLC');
                        if ($oclc) {
                            $link = ['type' => 'oclc', 'value' => $oclc];
                        }
                    }
                    break;
                case 'dlc':
                    foreach ($linkFields as $current) {
                        $dlc = $this->getIdFromLinkingField($current, 'DLC', true);
                        if ($dlc) {
                            $link = ['type' => 'dlc', 'value' => $dlc];
                        }
                    }
                    break;
                case 'id':
                    foreach ($linkFields as $current) {
                        if ($bibLink = $this->getIdFromLinkingField($current)) {
                            $link = ['type' => 'bib', 'value' => $bibLink];
                        }
                    }
                    break;
                case 'isbn':
                    if ($isbn = $this->getSubfield($field, 'z')) {
                        $link = [
                            'type' => 'isn', 'value' => trim($isbn),
                            'exclude' => $this->getUniqueId(),
                        ];
                    }
                    break;
                case 'issn':
                    if ($issn = $this->getSubfield($field, 'x')) {
                        $link = [
                            'type' => 'isn', 'value' => trim($issn),
                            'exclude' => $this->getUniqueId(),
                        ];
                    }
                    break;
                case 'title':
                    $link = ['type' => 'title', 'value' => $title];
                    break;
                case 'linkingId':
                    if ($useLegacyLinkingId) {
                        break;
                    }
                    foreach ($linkPrefixes as $prefix) {
                        foreach ($linkFields as $current) {
                            if ($this->getIdFromLinkingField($current, $prefix)) {
                                $link = ['type' => 'linkingId', 'value' => $current];
                                break 2;
                            }
                        }
                    }
                    break;
            }
            // Exit loop if we have a link
            if (isset($link)) {
                break;
            }
        }
        $note = $this->stripTrailingPunctuation($this->getRecordLinkNote($field));
        // Make sure we have something to display:
        return !isset($link) ? false : [
            'title' => $note,
            'value' => $title,
            'link'  => $link,
        ];
    }

    /**
     * Get linked MARC field contents
     *
     * @param string|array $field     Field tag or actual field
     * @param array        $subfields Subfields
     *
     * @return string
     */
    protected function getLinkedMarcFieldContents($field, $subfields)
    {
        $marc = $this->getMarcReader();
        if (is_string($field)) {
            $field = $marc->getField($field);
        }
        if (!$field) {
            return '';
        }
        $link = $this->getSubfield($field, '6');
        if (!$link) {
            return '';
        }
        $linkage = $marc->parseLinkageField($link);
        foreach ($marc->getFields($linkage['field']) as $linkedField) {
            if (!is_array($linkedField)) {
                $this->logError(
                    'Invalid linked field: ' . var_export($linkedField, true) . ', record id '
                    . ($this->fields['id'] ?? '??')
                );
                continue;
            }
            $sub6 = $marc->getSubfield($linkedField, '6');
            $targetLinkage = $marc->parseLinkageField($sub6);
            if (
                $targetLinkage['field'] == $field['tag']
                && $targetLinkage['occurrence'] === $linkage['occurrence']
            ) {
                $data = $this->getSubfieldArray($linkedField, $subfields);
                return implode(' ', $data);
            }
        }
        return '';
    }

    /**
     * Get component parts that are collections
     *
     * @return array
     */
    public function getChildCollections(): array
    {
        return $this->getEmbeddedComponentParts(true);
    }

    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @param array $fieldInfo Associative array of field => subfield information
     * (used to find series name)
     *
     * @return array
     */
    protected function getSeriesFromMARC($fieldInfo)
    {
        $matches = [];

        // Loop through the field specification....
        foreach ($fieldInfo as $field => $subfields) {
            // Did we find any matching fields?
            $series = $this->getMarcReader()->getFields($field);
            if (is_array($series)) {
                foreach ($series as $currentField) {
                    // Can we find a name using the specified subfield list?
                    $name = $this->getSubfieldArray(
                        $currentField,
                        $subfields,
                        false
                    );
                    if ($name) {
                        $currentArray = [
                            'name' =>
                                $this->stripTrailingPunctuation(array_shift($name)),
                        ];
                        $currentArray['additional'] = implode(' ', $name);

                        // Can we find an ISSN in subfield x? (Note that ISSN is
                        // always in subfield v regardless of whether we are dealing
                        // with 440, 490, 800 or 830 -- hence the hard-coded array
                        // rather than another parameter in $fieldInfo).
                        if ($issn = $this->getSubfield($currentField, 'x')) {
                            $currentArray['issn']
                                = $this->stripTrailingPunctuation($issn);
                        }

                        // Subfields n and p to show number of part/section of a
                        // series and name of that part/section for 830
                        if ($field == '830') {
                            if ($partName = $this->getSubfield($currentField, 'p')) {
                                $currentArray['partName']
                                    = $this->stripTrailingPunctuation($partName);
                            }
                            $partNumber = $this->getSubfield($currentField, 'n');
                            if ($partNumber) {
                                $currentArray['partNumber']
                                    = $this->stripTrailingPunctuation($partNumber);
                            }
                        }

                        // Save the current match:
                        $matches[] = $currentArray;
                    }
                }
            }
        }

        return array_values(array_unique($matches, SORT_REGULAR));
    }

    /**
     * Get an array of all acquisition information.
     *
     * @return array
     */
    public function getAcquisitionSource()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('037') as $field) {
            foreach ($this->getSubfields($field, 'b') as $acq) {
                $results[] = $this->stripTrailingPunctuation($acq);
            }
        }
        return $results;
    }

    /**
     * Get an array of all event information.
     *
     * @return array
     */
    public function getEventNotice()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('518') as $field) {
            foreach ($this->getSubfields($field, 'a') as $event) {
                $results[] = $this->stripTrailingPunctuation($event);
            }
        }
        return $results;
    }

    /**
     * Get an array of capture information.
     *
     * @return array
     */
    public function getCaptureInformation()
    {
        return $this->stripTrailingPunctuation($this->getFieldArray('518', ['3', 'o', 'd', 'p']));
    }

    /**
     * Get composition information from field 382.
     *
     * @return array
     */
    public function getMusicCompositions()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('382') as $field) {
            $matches = ['a','b','n','d','p','v'];
            $allSubfields = $this->getAllSubfields($field);
            $subfields = [];
            foreach ($allSubfields as $currentSubfield) {
                if (in_array($currentSubfield['code'], $matches)) {
                    $data = trim($currentSubfield['data']);
                    if ('' !== $data) {
                        if ($currentSubfield['code'] === 'n') {
                            $subfields[] = "($data)";
                        } else {
                            $subfields[] = $data;
                        }
                    }
                }
            }
            if ($subfields) {
                $results[] = implode(' ', $subfields);
            }
        }
        return $results;
    }

    /**
     * Get first lines of song lyrics from field 031 t.
     *
     * @return array
     */
    public function getFirstLyrics()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('031') as $field) {
            foreach ($this->getSubfields($field, 't') as $lyric) {
                $results[] = $this->stripTrailingPunctuation($lyric);
            }
        }
        return $results;
    }

    /**
     * Get methodology from field 567.
     *
     * @return array
     */
    public function getMethodology()
    {
        $results = [];
        $marcReader = $this->getMarcReader();
        foreach ($marcReader->getFields('567') as $field) {
            $results[] = [
                'description' => $marcReader->getSubfield($field, 'a'),
                'term' => $marcReader->getSubfield($field, 'b'),
                'url' => $marcReader->getSubfield($field, '0'),
            ];
        }
        return $results;
    }

    /**
     * Get format of notated music.
     *
     * @return string
     */
    public function getNotatedMusicFormat()
    {
        if ($result = $this->getFieldArray('348', ['a', 'b'])) {
            return $this->stripTrailingPunctuation(array_shift($result));
        }
        return '';
    }

    /**
     * Get trade availability note from field 366.
     *
     * @return array
     */
    public function getTradeAvailabilityNote()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('366') as $field) {
            foreach ($this->getSubfields($field, 'e') as $note) {
                $results[] = $this->stripTrailingPunctuation($note);
            }
        }
        return $results;
    }

    /**
     * Get age limit from field 049.
     *
     * @return array
     */
    public function getAgeLimit()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('049') as $field) {
            foreach ($this->getSubfields($field, 'c') as $subfield) {
                $results[] = $this->stripTrailingPunctuation($subfield);
            }
        }
        return $results;
    }

    /**
     * Get the map scale from field 255, subfield a.
     *
     * @return string
     */
    public function getMapScale()
    {
        foreach ($this->getMarcReader()->getFields('255') as $field) {
            if ($scale = $this->getSubfield($field, 'a')) {
                return $this->stripTrailingPunctuation($scale);
            }
        }
        return '';
    }

    /**
     * Get notes from fields 515 & 550, both subfields a.
     *
     * @return array
     */
    public function getNotes()
    {
        $results = [];
        foreach (['515', '550'] as $fieldCode) {
            foreach ($this->getMarcReader()->getFields($fieldCode) as $field) {
                if ($subfield = $this->getSubfield($field, 'a')) {
                    $results[] = $this->stripTrailingPunctuation($subfield);
                }
            }
        }
        return $results;
    }

    /**
     * Get local notes from field 594, subfield a.
     *
     * @return array
     */
    public function getLocalNotes()
    {
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('594', ['a'])
        );
    }

    /**
     * Get associated place of the record from field 370.
     *
     * @return string
     */
    public function getAssociatedPlace()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('370') as $field) {
            foreach ($this->getSubfields($field, 'g') as $place) {
                $results[] = $this->stripTrailingPunctuation($place);
            }
        }
        return implode(', ', $results);
    }

    /**
     * Get audience characteristics from field 385
     *
     * @return array
     */
    public function getAudienceCharacteristics()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('385') as $field) {
            foreach ($this->getSubfields($field, 'a') as $ch) {
                $results[] = $this->stripTrailingPunctuation($ch);
            }
        }
        return $results;
    }

    /**
     * Get creator/contributor characteristics from field 386
     *
     * @return array
     */
    public function getCreatorCharacteristics()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('386') as $field) {
            foreach ($this->getSubfields($field, 'a') as $ch) {
                $results[] = $this->stripTrailingPunctuation($ch);
            }
        }
        return $results;
    }

    /**
     * Get time period of creation from field 388.
     *
     * @return string
     */
    public function getTimePeriodOfCreation()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('388') as $field) {
            foreach ($this->getSubfields($field, 'a') as $time) {
                $results[] = $this->stripTrailingPunctuation($time);
            }
        }
        return implode(', ', $results);
    }

    /**
     * Get collective uniform title from field 243, subfields a and k.
     *
     * @return array
     */
    public function getCollectiveUniformTitle()
    {
        $results = [];
        if ($fields = $this->getFieldArray('243', ['a', 'k'])) {
            $results[] = $this->stripTrailingPunctuation(implode(' ', $fields));
        }
        return $results;
    }

    /**
     * Get standard codes from field 024, subfields a, d and q.
     *
     * @return array
     */
    public function getStandardCodes()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('024') as $field) {
            $subfields = [];
            switch ($field['i1']) {
                case '0':
                    $subfields[] = 'ISRC';
                    break;
                case '1':
                    $subfields[] = 'UPC';
                    break;
                case '2':
                    $subfields[] = 'ISMN';
                    break;
                case '3':
                    $subfields[] = 'EAN';
                    break;
                case '4':
                    $subfields[] = 'SICI';
                    break;
                case '7':
                    if ($sub2 = $this->getSubfield($field, '2')) {
                        $subfields[] = $this->stripTrailingPunctuation($sub2);
                    }
                    break;
            }
            $subfields = array_merge(
                $subfields,
                array_map(
                    [$this, 'stripTrailingPunctuation'],
                    $this->getSubfieldArray($field, ['a', 'd', 'q'])
                )
            );
            $results[] = implode(' ', $subfields);
        }
        return $results;
    }

    /**
     * Get standard report numbers from field 027, subfield a.
     *
     * @return array
     */
    public function getStandardReportNumbers()
    {
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('027', ['a'])
        );
    }

    /**
     * Get standard report numbers from field 526, subfields i and a.
     *
     * @return array
     */
    public function getStudyProgramNotes()
    {
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('526', ['i', 'a'])
        );
    }

    /**
     * Get publisher or distributor number from field 028, subfields b and a.
     *
     * @return array
     */
    public function getPubDistNumber()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('028') as $field) {
            $subfields = [];
            if ($b = $this->getSubfield($field, 'b')) {
                $subfields[] = $this->stripTrailingPunctuation($b);
            }
            if ($a = $this->getSubfield($field, 'a')) {
                $subfields[] = $this->stripTrailingPunctuation($a);
            }
            $results[] = implode(' ', $subfields);
        }
        return $results;
    }

    /**
     * Get time period from field 045, subfields a, b and c.
     *
     * @return array
     */
    public function getTimePeriod()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('045') as $field) {
            switch ($field['i1']) {
                case 0:
                case 1:
                    $subfields = [];
                    foreach ($this->getSubfields($field, 'b') as $time) {
                        $subfields[] = $this->stripTrailingPunctuation($time);
                    }
                    foreach ($this->getSubfields($field, 'c') as $time) {
                        $subfields[] = $this->stripTrailingPunctuation($time);
                    }
                    if ($subfields) {
                        $results[] = implode(', ', $subfields);
                    }
                    break;
                case 2:
                    $range = [];
                    foreach ($this->getSubfields($field, 'b') as $time) {
                        $range[] = $this->stripTrailingPunctuation($time);
                    }
                    foreach ($this->getSubfields($field, 'c') as $time) {
                        $range[] = $this->stripTrailingPunctuation($time);
                    }
                    if ($range) {
                        $results[] = implode(' – ', $range);
                    }
                    break;
                default:
                    if ($a = $this->getSubfield($field, 'a')) {
                        $results[] = $a;
                    }
            }
        }
        return $results;
    }

    /**
     * Get copyright notes from field 542, subfields a - u.
     *
     * @return array
     */
    public function getCopyrightNotes()
    {
        $subfields = range('a', 'u');
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('542', $subfields)
        );
    }

    /**
     * Get language notes from field 546, subfields a and b.
     *
     * @return array
     */
    public function getLanguageNotes()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('546') as $field) {
            $result = [];
            if ($subfield = $this->getSubfield($field, '3')) {
                $result['part'] = $this->stripTrailingPunctuation($subfield);
            }
            if ($a = $this->getSubfield($field, 'a')) {
                $result['details'][] = $this->stripTrailingPunctuation($a);
            }
            if ($b = $this->getSubfield($field, 'b')) {
                $result['details'][] = $this->stripTrailingPunctuation($b);
            }
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Get uncontrolled title from field 740, subfields a, n and p.
     *
     * @return array
     */
    public function getUncontrolledTitle()
    {
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('740', ['a', 'n', 'p'])
        );
    }

    /**
     * Get hardware requirements.
     *
     * @return array
     */
    public function getHardwareRequirements(): array
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('753') as $field) {
            $result = [];
            if ($subfield = $this->getSubfield($field, 'a')) {
                $subfield = $this->stripTrailingPunctuation($subfield);
                if (!in_array($subfield, array_column($results, 'make_model'))) {
                    $result['make_model'] = $subfield;
                }
            }
            if ($result) {
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Get System details from field 538
     *
     * @return array
     */
    public function getSystemDetails(): array
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('538') as $field) {
            $result = [];
            if ($subfield = $this->getSubfield($field, '3')) {
                $result['part'] = $this->stripTrailingPunctuation($subfield);
            }
            $result['details']
                = $this->stripTrailingPunctuation($this->getSubfield($field, 'a'));
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Get accessibility information from field 341, subfields a and b.
     * Additional information from field 532, subfield a
     *
     * @return array
     */
    public function getAccessibilityFeatures(): array
    {
        $results = [];
        $results = $this->getFieldArray('341', ['a', 'b', 'c', 'd', 'e'], true, ': ');
        foreach ($this->getMarcReader()->getFields('532') as $field) {
            if (
                in_array($field['i1'], ['0', '1'])
                && ($subfield = $this->getSubfield($field, 'a'))
            ) {
                $results[] = $this->stripTrailingPunctuation($subfield);
            }
        }
        return $results;
    }

    /**
     * Get accessibility hazards from field 532, subfield a.
     *
     * @return array
     */
    public function getAccessibilityHazards(): array
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('532') as $field) {
            if (
                ($field['i1'] === '2')
                && ($subfield = $this->getSubfield($field, 'a'))
            ) {
                $results[] = $this->stripTrailingPunctuation($subfield);
            }
        }
        return $results;
    }

    /**
     * Get security classification from field 355, subfield a.
     *
     * @return array
     */
    public function getSecurityClassification()
    {
        return $this->stripTrailingPunctuation($this->getFieldArray('355', ['a']));
    }

    /**
     * Get country from field 257, subfield a.
     *
     * @return array
     */
    public function getCountry()
    {
        return $this->stripTrailingPunctuation($this->getFieldArray('257', ['a']));
    }

    /**
     * Get abstract language from field 041, subfield b.
     *
     * @return array
     */
    public function getAbstractLanguage()
    {
        return $this->stripTrailingPunctuation($this->getFieldArray('041', ['b']));
    }

    /**
     * Get original languages from fields 041, subfield h and 979, subfields h and i
     *
     * @return array
     */
    public function getOriginalLanguages()
    {
        $result = [];
        foreach ($this->getMarcReader()->getFields('041') as $field) {
            if ($field['i1'] != 0) {
                $result[] = $this->stripTrailingPunctuation($this->getSubfield($field, 'h')) ?? '';
            }
        }
        foreach ($this->stripTrailingPunctuation($this->getFieldArray('979', ['h', 'i'], false)) as $lang) {
            $result[] = $lang;
        }
        return array_unique(array_filter($result));
    }

    /**
     * Get book binding from fields 020 subfield q, 340 subfield l or 500 subfield a
     *
     * @return string
     */
    public function getBinding(): string
    {
        $formatType = null;
        $formats = $this->getFormats();
        foreach ($formats as $format) {
            $parts = explode('/', $format);
            if ($parts[0] === '1' && isset($parts[2])) {
                $formatType = $parts[2];
                break;
            }
        }
        if ($formatType == 'Book') {
            $fields = [
                '020' => ['q'],
                '340' => ['l'],
                '500' => ['a'],
            ];
            $values = [];
            foreach ($fields as $field => $subfields) {
                $values = array_merge(
                    $values,
                    $this->getFieldArray($field, $subfields),
                );
            }
            $bindings = array_filter(array_map(
                function ($s) {
                    $s = mb_strtolower(mb_ereg_replace('[^A-ZÅÄÖa-zåäö]', '', $s));
                    return $this->bindingMappings[$s] ?? null;
                },
                $values
            ));
            if (count(array_unique($bindings)) === 1) {
                return reset($bindings);
            }
        }
        return '';
    }

    /**
     * Get record linking settings
     *
     * @param string $recordSource Record source
     *
     * @return array
     */
    protected function getRecordLinkingPrefixes(string $recordSource): array
    {
        if ($linkPrefixes = $this->datasourceSettings[$recordSource]['link_prefixes'] ?? []) {
            $linkPrefixes = explode(',', $linkPrefixes);
        }
        if ($this->datasourceSettings[$recordSource]['prefixIn003'] ?? null) {
            $field003 = $this->getMarcReader()->getField('003');
            $linkPrefixes[] = trim($field003);
        }
        return array_filter(array_unique($linkPrefixes));
    }
}
