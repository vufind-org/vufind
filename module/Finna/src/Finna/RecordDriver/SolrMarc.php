<?php
/**
 * Model for MARC records in Solr.
 *
 * PHP version 7
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
class SolrMarc extends \VuFind\RecordDriver\SolrMarc
    implements \Laminas\Log\LoggerAwareInterface
{
    use SolrFinnaTrait;
    use MarcReaderTrait;
    use UrlCheckTrait;
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
        '656' => 'occupation'
    ];

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct($mainConfig = null, $recordConfig = null,
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
        $result = parent::getAllRecordLinks();
        if ($result !== null) {
            foreach ($result as &$link) {
                if (isset($link['value'])) {
                    $link['value'] = $this->stripTrailingPunctuation($link['value']);
                }
            }
        }

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

            if (($image || $pdf) && $this->urlAllowed($address)
                && ($pdf || $this->isUrlLoadable($address, $this->getUniqueID()))
            ) {
                $urls[$image ? 'images' : 'pdfs'][] = [
                    'urls' => [
                        'small' => $address,
                        'medium' => $address,
                        'large' => $address
                        ],
                    'description' => '',
                    'rights' => [],
                    'pdf' => $pdf
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
            if (is_array($fields)) {
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
            if (preg_match('/,\s*\w\.?\s*([\d,\-]+)/', $subfield, $matches)
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

        if ($isbn = $this->getCleanISBN()) {
            return 'http://s1.doria.fi/getText.php?query=' . $isbn;
        }
        return false;
    }

    /**
     * Get dissertation note for the record.
     * Use field 502 if available. If not, use local field 509 or 920.
     *
     * @return string dissertation notes
     */
    public function getDissertationNote()
    {
        $notes = $this->getFirstFieldValue('502', ['a', 'b', 'c']);
        if (!$notes) {
            // 509 used in Voyager
            $notes = $this->getFirstFieldValue('509', ['a', 'b', 'c']);
        }
        if (!$notes) {
            // 920 used in Alma
            $notes = $this->getFirstFieldValue('920', ['a', 'b', 'c']);
        }
        return $notes;
    }

    /**
     * Get an array of embedded component parts
     *
     * @return array Component parts
     */
    public function getEmbeddedComponentParts()
    {
        $componentParts = [];
        $partOrderCounter = 0;
        foreach ($this->getMarcReader()->getFields('979') as $field) {
            $partOrderCounter++;
            $partAuthors = [];
            $uniformTitle = '';
            $duration = '';
            $partTitle = '';
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

        // Try field 700 if 979 is empty
        if (!$componentParts) {
            foreach ($this->getMarcReader()->getFields('700') as $field) {
                if ($field['i2'] != 2 || !$this->getSubfield($field, 't')) {
                    continue;
                }
                $partOrderCounter++;

                $partTitle = $this->getSubfieldArray(
                    $field,
                    ['t', 'm', 'n', 'r', 'h', 'i', 'g', 'n', 'p', 's', 'l', 'o', 'k']
                );
                $partTitle = reset($partTitle);
                $partAuthors = $this->getSubfieldArray(
                    $field, ['a', 'q', 'b', 'c', 'd', 'e']
                );

                $partPresenters = [];
                $partArrangers = [];
                $partOtherAuthors = [];
                foreach ($partAuthors as $author) {
                    if (isset($this->recordConfig['Record']['presenter_roles'])) {
                        foreach ($this->recordConfig['Record']['presenter_roles']
                            as $role
                        ) {
                            $author = trim($author);
                            if (substr($author, -strlen($role) - 2) == ", $role") {
                                $partPresenters[] = $author;
                                continue 2;
                            }
                        }
                    }
                    if (isset($this->recordConfig['Record']['arranger_roles'])) {
                        foreach ($this->recordConfig['Record']['arranger_roles']
                            as $role
                        ) {
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
        }

        return $componentParts;
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
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        $record = clone $this->getMarcRecord();
        $record->deleteFields('520');
        $componentIds = $this->getFieldArray('979', 'a');
        if ($componentIds) {
            $record->deleteFields('979');
            $subfields = [];
            foreach ($componentIds as $id) {
                $subfields[] = new \File_MARC_Subfield('a', $id);
            }
            $record->appendField(new \File_MARC_Data_Field('979', $subfields));
        }
        return $record->toXML();
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

        if (!empty($this->fields['hierarchy_parent_id'])
            && count($this->fields['hierarchy_parent_id']) > count($fields)
        ) {
            // Can't use 773 fields since they don't represent the actual links
            foreach ($this->fields['hierarchy_parent_id'] as $key => $parentId) {
                if (isset($this->fields['hierarchy_parent_title'][$key])) {
                    $title = $this->fields['hierarchy_parent_title'][$key];
                } elseif (isset($this->fields['hierarchy_parent_title'][0])) {
                    $this->fields['hierarchy_parent_title'][0];
                } else {
                    $title = 'Title not available';
                }
                $result[] = [
                    'id' => $parentId,
                    'sourceId' => $sourceId,
                    'title' => $title,
                    'reference' => '',
                    'publishingInfo' => ''
                ];
            }
            return $result;
        }

        foreach ($fields as $field) {
            $id = '';
            $title = '';
            $reference = '';
            $publishingInfo = '';
            foreach ($this->getAllSubfields($field) as $subfield) {
                $data = $subfield['data'];
                switch ($subfield['code']) {
                case 'w':
                    $id = $data;
                    // Remove any source in parenthesis to create a working link
                    $id = preg_replace('/\\(.+\\)/', '', $id);
                    break;
                case 't':
                    $title = $this->stripTrailingPunctuation($data, '.-');
                    break;
                case 'g':
                    $reference = $data;
                    break;
                case 'd':
                    $publishingInfo = $this->stripTrailingPunctuation($data, '.-');
                    break;
                }
            }

            if (count($fields) === 1
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
                'sourceId' => $sourceId,
                'title' => $title,
                'reference' => $reference,
                'publishingInfo' => $publishingInfo
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
                    $this->getFieldArray($field, $subfields), '-'
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
            '022' => ['a']
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
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
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

                    $roles = $this->getSubfields($field, '4');
                    if (empty($roles)) {
                        $roles = $this->getSubfields($field, 'e');
                    }
                    $roles = array_map([$this, 'stripTrailingPunctuation'], $roles);
                    $role = implode(', ', $roles);
                    $role = mb_strtolower($role, 'UTF-8');
                    if ($role
                        && isset($this->mainConfig->Record->presenter_roles)
                        && in_array(
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

                    $altSubfields = $this->getLinkedMarcFieldContents(
                        $field, ['a', 'b', 'c']
                    );
                    $altSubfields = $this->stripTrailingPunctuation($altSubfields);

                    $id = $this->getSubfield($field, '0');
                    $result[] = [
                        'name' => $this->stripTrailingPunctuation($subfields[0]),
                        'name_alt' => $altSubfields,
                        'date' => !empty($dates) ? $dates[0] : '',
                        'role' => $role,
                        'id' => $id ?: null,
                        'type' => in_array($fieldCode, ['100', '700'])
                            ? 'Personal Name' : 'Corporate Name'
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
     * @return array
     */
    public function getPresenters()
    {
        $result = ['presenters' => [], 'details' => []];

        foreach (['100', '110', '700', '710'] as $fieldCode) {
            $fields = $this->getMarcReader()->getFields($fieldCode);
            if (is_array($fields)) {
                foreach ($fields as $field) {
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
                    if (!$role
                        || !isset($this->mainConfig->Record->presenter_roles)
                        || !in_array(
                            trim($role, ' .'),
                            $this->mainConfig->Record->presenter_roles->toArray()
                        )
                    ) {
                        continue;
                    }
                    $subfields = $this->getSubfieldArray($field, ['a', 'b', 'c']);
                    $dates = $this->getSubfieldArray($field, ['d']);
                    if (!empty($subfields)) {
                        $result['presenters'][] = [
                            'name' => $this->stripTrailingPunctuation($subfields[0]),
                            'date' => $dates ? $dates[0] : '',
                            'role' => $role
                        ];
                    }
                }
            }
        }
        $result['details'] = $this->stripTrailingPunctuation(
            $this->getFieldArray('511', ['a'])
        );
        return $result;
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
     * @return array
     */
    public function getProjectedPublicationDate()
    {
        $dateString = $this->getFirstFieldValue('263', ['a']);
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
     * Get an array of all series names containing the record.  Array entries may
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
            '830' => ['a', 'v']
        ];
        $matches = $this->getSeriesFromMARC($primaryFields);

        if (empty($matches)) {
            // Now check 490 and display it only if 440/800/830 were empty:
            $secondaryFields = ['490' => ['a', 'x']];
            $matches = $this->getSeriesFromMARC($secondaryFields);
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
        $field090 = $record->getField('090');
        $objectId = $field090 ? $this->getSubfield($field090, 'a') : '';
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
        return '';
    }

    /**
     * Get the short (pre-subtitle) title of the record in alternative script.
     *
     * @return string
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
            if (!$item
                || !in_array($item, ['kieli', 'språk', 'language'])
            ) {
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
            $url = $this->getSubfield($field, 'u');
            if ($terms || $source || $url) {
                $result[] = compact('material', 'terms', 'source', 'url');
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
                $results[] = implode(' ', $this->getSubfields($field, ''));
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
        $retVal = [];

        // Which fields/subfields should we check for URLs?
        $fieldsToCheck = [
            '856' => ['y', 'z', '3'], // Standard URL
            '555' => ['a']            // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->getMarcReader()->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $this->getSubfield($url, 'u');
                    // Require at least one dot surrounded by valid characters or a
                    // familiar scheme
                    if ($address
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
                            'url' => $address, 'desc' => $desc, 'part' => $part
                        ];
                        if (!$this->urlBlocked($address, $desc)
                            && !in_array($data, $retVal)
                        ) {
                            $retVal[] = $data;
                        }
                    }
                }
            }
        }
        $retVal = $this->resolveUrlTypes($retVal);
        return $retVal;
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
            if ($field['i2'] == 2 && $this->getSubfield($field, 't')) {
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
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     * - id: authority id (if defined)
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
            $authId = $authType = null;

            // Check if we can find an authority id with a matching heading
            foreach ($subjectIdFields as $type => $codes) {
                foreach ($codes as $code) {
                    foreach ($this->getMarcReader()->getFields($code) as $field) {
                        $subfield = $this->getSubfield($field, 'a');
                        if (!$subfield || trim($subfield) !== $heading) {
                            continue;
                        }
                        if ($authId = $this->getSubfield($field, '0')) {
                            $authType = $type;
                            break 3;
                        }
                    }
                }
            }
            $subject['id'] = $authId;
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

        $linkTypeSetting = isset($this->mainConfig->Record->marc_links_link_types)
            ? $this->mainConfig->Record->marc_links_link_types
            : 'id,oclc,dlc,isbn,issn,title';
        $linkTypes = explode(',', $linkTypeSetting);
        $linkFields = $this->getSubfields($field, 'w');

        // Run through the link types specified in the config.
        // For each type, check field for reference
        // If reference found, exit loop and go straight to end
        // If no reference found, check the next link type instead
        foreach ($linkTypes as $linkType) {
            switch (trim($linkType)) {
            case 'oclc':
                foreach ($linkFields as $current) {
                    if ($oclc = $this->getIdFromLinkingField($current, 'OCoLC')) {
                        $link = ['type' => 'oclc', 'value' => $oclc];
                    }
                }
                break;
            case 'dlc':
                foreach ($linkFields as $current) {
                    if ($dlc = $this->getIdFromLinkingField($current, 'DLC', true)) {
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
                        'exclude' => $this->getUniqueId()
                    ];
                }
                break;
            case 'issn':
                if ($issn = $this->getSubfield($field, 'x')) {
                    $link = [
                        'type' => 'isn', 'value' => trim($issn),
                        'exclude' => $this->getUniqueId()
                    ];
                }
                break;
            case 'title':
                $link = ['type' => 'title', 'value' => $title];
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
            'link'  => $link
        ];
    }

    /**
     * Get linked MARC field contents
     *
     * @param string|\File_MARC_Field $field     Field tag or actual field
     * @param array                   $subfields Subfields
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
        $parts = explode('-', $link);
        if (count($parts) != 2) {
            return '';
        }
        $linkedFieldCode = $parts[0];
        $linkedFieldNum = (int)$parts[1] - 1;
        $linkedFields = $marc->getFields($linkedFieldCode);
        if (!isset($linkedFields[$linkedFieldNum])) {
            return '';
        }
        $data = $this->getSubfieldArray($linkedFields[$linkedFieldNum], $subfields);
        return implode(' ', $data);
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
                        $currentField, $subfields, false
                    );
                    if ($name) {
                        $currentArray = [
                            'name' =>
                                $this->stripTrailingPunctuation(array_shift($name))
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
     * Check whether it is allowed to use an image or description URL.
     *
     * @param string $url URL to check
     *
     * @return boolean True if the url can be used
     */
    protected function urlAllowed($url)
    {
        // BTJ
        if (preg_match('/^(http|https):.*\.btj\.com\//', $url)) {
            if (!isset($this->mainConfig->Record->btj_links)
                || !$this->mainConfig->Record->btj_links
            ) {
                return false;
            }
        }

        // Kirjavälitys
        if (strstr($url, 'http://data.kirjavalitys.fi/')) {
            if (!isset($this->mainConfig->Record->kirjavalitys_links)
                || !$this->mainConfig->Record->kirjavalitys_links
            ) {
                return false;
            }
        }

        return true;
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
     * Get methodologoy from field 567.
     *
     * @return array
     */
    public function getMethodology()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('567') as $field) {
            foreach ($this->getSubfields($field, 'a') as $method) {
                $results[] = $this->stripTrailingPunctuation($method);
            }
        }
        return $results;
    }

    /**
     * Get format of notated music from field 348, subfields a, b and 2.
     *
     * @return string
     */
    public function getNotatedMusicFormat()
    {
        $results = '';
        $fields = ['348' => ['a', 'b', '2']];
        $matches = $this->getSeriesFromMARC($fields);
        foreach ($matches as $match) {
            $subfields[] =  $this->stripTrailingPunctuation($match);
        }
        if (!empty($subfields)) {
            $results = implode(', ', $subfields[0]);
        }
        return $results;
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
        foreach ($this->getMarcReader()->getFields('243') as $field) {
            if ($fields = $this->getFieldArray($field, ['a', 'k'])) {
                $results[] = $this->stripTrailingPunctuation(implode(' ', $fields));
            }
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
            case 0:
                $subfields[] = 'ISRC';
                break;
            case 1:
                $subfields[] = 'UPC';
                break;
            case 2:
                $subfields[] = 'ISMN';
                break;
            case 3:
                $subfields[] = 'EAN';
                break;
            case 4:
                $subfields[] = 'SICI';
                break;
            case 7:
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
        return $this->stripTrailingPunctuation(
            $this->getFieldArray('546', ['a', 'b'])
        );
    }

    /**
     * Get uncontrolled title from field 740, subfield a.
     *
     * @return array
     */
    public function getUncontrolledTitle()
    {
        $results = [];
        foreach ($this->getMarcReader()->getFields('740') as $field) {
            if ($subfield = $this->getSubfield($field, 'a')) {
                $subfield = $this->stripTrailingPunctuation($subfield);
                if (($ind1 = $field['i1']) && ctype_digit($ind1)) {
                    $results[] = substr($subfield, $ind1);
                } else {
                    $results[] = $this->stripTrailingPunctuation($subfield);
                }
            }
        }
        return $results;
    }
}
