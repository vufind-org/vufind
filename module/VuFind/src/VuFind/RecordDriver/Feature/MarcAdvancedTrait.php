<?php

/**
 * Functions to add advanced MARC-driven functionality to a record driver already
 * powered by the standard index spec. Depends upon MarcReaderTrait.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Feature;

use VuFind\View\Helper\Root\RecordLinker;
use VuFind\XSLT\Processor as XSLTProcessor;

use function count;
use function in_array;
use function is_array;

/**
 * Functions to add advanced MARC-driven functionality to a record driver already
 * powered by the standard index spec. Depends upon MarcReaderTrait.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait MarcAdvancedTrait
{
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
        '655' => 'genre/form',
        '656' => 'occupation',
    ];

    /**
     * Mappings from subject source indicators (2nd indicator of subject fields in
     * MARC 21) to the their codes.
     *
     * @var  array
     * @link https://www.loc.gov/marc/bibliographic/bd6xx.html     Subject field docs
     * @link https://www.loc.gov/standards/sourcelist/subject.html Code list
     */
    protected $subjectSources = [
        '0' => 'lcsh',
        '1' => 'lcshac',
        '2' => 'mesh',
        '3' => 'nal',
        '4' => 'unknown',
        '5' => 'cash',
        '6' => 'rvm',
    ];

    /**
     * Type to export in getXML().
     *
     * @var string
     */
    protected $xmlType = 'Bibliographic';

    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        return $this->getFieldArray('506');
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
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        if (($this->mainConfig->Record->marcSubjectHeadingsSort ?? '') === 'numerical') {
            $returnValues = $this->getAllSubjectHeadingsNumericalOrder($extended);
        } else {
            // Default | value === 'record'
            $returnValues = $this->getAllSubjectHeadingsRecordOrder($extended);
        }

        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize',
            array_unique(array_map('serialize', $returnValues))
        );
    }

    /**
     * Get all subject headings associated with this record. Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific. Sorted in the same way it is saved for the record.
     *
     * @param bool $extended Whether to return a keyed array with the following
     *  keys:
     *  - heading: the actual subject heading chunks
     *  - type: heading type
     *  - source: source vocabulary
     *
     * @return array
     */
    protected function getAllSubjectHeadingsRecordOrder(bool $extended = false): array
    {
        $returnValues = [];
        $allFields = $this->getMarcReader()->getAllFields();
        $subjectFieldsKeys = array_keys($this->subjectFields);
        // Go through all the fields and handle them if they are part of what we want
        foreach ($allFields as $field) {
            if (isset($field['tag']) && in_array($field['tag'], $subjectFieldsKeys)) {
                $fieldType = $this->subjectFields[$field['tag']];
                if ($nextLine = $this->processSubjectHeadings($field, $extended, $fieldType)) {
                    $returnValues[] = $nextLine;
                }
            }
        }
        return $returnValues;
    }

    /**
     * Get all subject headings associated with this record. Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific. Sorted numerically on marc fields.
     *
     * @param bool $extended Whether to return a keyed array with the following
     *  keys:
     *  - heading: the actual subject heading chunks
     *  - type: heading type
     *  - source: source vocabulary
     *
     * @return array
     */
    protected function getAllSubjectHeadingsNumericalOrder(bool $extended = false): array
    {
        $returnValues = [];
        // Try each MARC field one at a time:
        foreach ($this->subjectFields as $field => $fieldType) {
            // Do we have any results for the current field?  If not, try the next.
            $fields = $this->getMarcReader()->getFields($field);
            if (!$fields) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($fields as $f) {
                if ($nextLine = $this->processSubjectHeadings($f, $extended, $fieldType)) {
                    $returnValues[] = $nextLine;
                }
            }
        }
        return $returnValues;
    }

    /**
     * Get subject headings of a given record field.
     * The heading is returned as a chunk, increasing from least specific to most specific.
     *
     * @param array  $field     field to handle
     * @param bool   $extended  Whether to return a keyed array with the following keys:
     *                          - heading: the actual subject heading chunks - type:
     *                          heading type - source: source vocabulary
     * @param string $fieldType Type of the field
     *
     * @return ?array
     */
    protected function processSubjectHeadings(
        array $field,
        bool $extended,
        string $fieldType
    ): ?array {
        // Start an array for holding the chunks of the current heading:
        $current = [];

        // Get all the chunks and collect them together:
        foreach ($field['subfields'] as $subfield) {
            // Numeric subfields are for control purposes and should not
            // be displayed:
            if (!is_numeric($subfield['code'])) {
                $current[] = $subfield['data'];
            }
        }
        // If we found at least one chunk, add a heading to our result:
        if (!empty($current)) {
            if ($extended) {
                $sourceIndicator = $field['i2'];
                $source = $this->subjectSources[$sourceIndicator]
                    ?? $this->getSubfield($field, '2');
                return [
                    'heading' => $current,
                    'type' => $fieldType,
                    'source' => $source,
                    'id' => $this->getSubfield($field, '0'),
                ];
            } else {
                return $current;
            }
        }
        return null;
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        return $this->getFieldArray('586');
    }

    /**
     * Get the bibliographic level of the current record.
     *
     * @return string
     */
    public function getBibliographicLevel()
    {
        $leader = $this->getMarcReader()->getLeader();
        $biblioLevel = strtoupper($leader[7]);

        switch ($biblioLevel) {
            case 'M': // Monograph
                return 'Monograph';
            case 'S': // Serial
                return 'Serial';
            case 'A': // Monograph Part
                return 'MonographPart';
            case 'B': // Serial Part
                return 'SerialPart';
            case 'C': // Collection
                return 'Collection';
            case 'D': // Collection Part
                return 'CollectionPart';
            case 'I': // Integrating Resource
                return 'IntegratingResource';
            default:
                return 'Unknown';
        }
    }

    /**
     * Get notes on bibliography content.
     *
     * @return array
     */
    public function getBibliographyNotes()
    {
        return $this->getFieldArray('504');
    }

    /**
     * Return full record as filtered XML for public APIs.
     *
     * @return string
     */
    public function getFilteredXML()
    {
        // The default implementation does not filter out any fields. You can do
        // simple filtering using MarcReader's getFilteredRecord method, or more
        // complex changes by using the XML DOM.
        //
        // Example for removing field 520, 9xx fields and subfield 0 from all fields
        // with getFilteredRecord:
        //
        //
        // return $this->getMarcReader()->getFilteredRecord(
        //     [
        //         [
        //             'tag' => '520',
        //         ],
        //         [
        //             'tag' => '9..',
        //         ],
        //         [
        //             'tag' => '...',
        //             'subfields' => '0',
        //         ],
        //     ]
        // )->toFormat('MARCXML');
        //
        //
        // Example for removing field 520 using DOM (note that the fields must be
        // removed in a second loop to not affect the iteration of fields) and adding
        // a new 955 field:
        //
        // $collection = new \DOMDocument();
        // $collection->preserveWhiteSpace = false;
        // $collection->loadXML($this->getMarcReader()->toFormat('MARCXML'));
        // $record = $collection->getElementsByTagName('record')->item(0);
        // $fieldsToRemove = [];
        // foreach ($record->getElementsByTagName('datafield') as $field) {
        //     $tag = $field->getAttribute('tag');
        //     if ('520' === $tag) {
        //         $fieldsToRemove[] = $field;
        //     }
        // }
        // foreach ($fieldsToRemove as $field) {
        //     $record->removeChild($field);
        // }
        //
        // $field = $collection->createElement('datafield');
        // $tag = $collection->createAttribute('tag');
        // $tag->value = '955';
        // $field->appendChild($tag);
        // $ind1 = $collection->createAttribute('ind1');
        // $ind1->value = ' ';
        // $field->appendChild($ind1);
        // $ind2 = $collection->createAttribute('ind2');
        // $ind2->value = ' ';
        // $field->appendChild($ind2);
        // $subfield = $collection->createElement('subfield');
        // $code = $collection->createAttribute('code');
        // $code->value = 'a';
        // $subfield->appendChild($code);
        // $subfield->appendChild($collection->createTextNode('VuFind'));
        // $field->appendChild($subfield);
        // $record->appendChild($field);
        //
        // return $collection->saveXML();

        return $this->getMarcReader()->toFormat('MARCXML');
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        return $this->getFieldArray('555');
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return $this->getFieldArray('500');
    }

    /**
     * Get human readable publication dates for display purposes (may not be suitable
     * for computer processing -- use getPublicationDates() for that).
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        return $this->getPublicationInfo('c');
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        // If the MARC links are being used, return blank array
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? array_map('trim', explode(',', $this->mainConfig->Record->marc_links))
            : [];
        return in_array('785', $fieldsNames) ? [] : parent::getNewerTitles();
    }

    /**
     * Get the item's places of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        return $this->getPublicationInfo();
    }

    /**
     * Get an array of playing times for the record (if applicable).
     *
     * @return array
     */
    public function getPlayingTimes()
    {
        $times = $this->getFieldArray('306', ['a'], false);

        // Format the times to include colons ("HH:MM:SS" format).
        foreach ($times as $x => $time) {
            if (!preg_match('/\d\d:\d\d:\d\d/', $time)) {
                $times[$x] = substr($time, 0, 2) . ':' .
                    substr($time, 2, 2) . ':' .
                    substr($time, 4, 2);
            }
        }
        return $times;
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        // If the MARC links are being used, return blank array
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? array_map('trim', explode(',', $this->mainConfig->Record->marc_links))
            : [];
        return in_array('780', $fieldsNames) ? [] : parent::getPreviousTitles();
    }

    /**
     * Get credits of people involved in production of the item.
     *
     * @return array
     */
    public function getProductionCredits()
    {
        return $this->getFieldArray('508');
    }

    /**
     * Get an array of publication frequency information.
     *
     * @return array
     */
    public function getPublicationFrequency()
    {
        return $this->getFieldArray('310', ['a', 'b']);
    }

    /**
     * Get an array of strings describing relationships to other items.
     *
     * @return array
     */
    public function getRelationshipNotes()
    {
        return $this->getFieldArray('580');
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
        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = [
            '440' => ['a', 'p'],
            '800' => ['a', 'b', 'c', 'd', 'f', 'p', 'q', 't'],
            '830' => ['a', 'p']];
        $matches = $this->getSeriesFromMARC($primaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Now check 490 and display it only if 440/800/830 were empty:
        $secondaryFields = ['490' => ['a']];
        $matches = $this->getSeriesFromMARC($secondaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Still no results found?  Resort to the Solr-based method just in case!
        return parent::getSeries();
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
            foreach ($series as $currentField) {
                // Can we find a name using the specified subfield list?
                $name = $this->getSubfieldArray($currentField, $subfields);
                if (isset($name[0])) {
                    $currentArray = ['name' => $name[0]];

                    // Can we find a number in subfield v?  (Note that number is
                    // always in subfield v regardless of whether we are dealing
                    // with 440, 490, 800 or 830 -- hence the hard-coded array
                    // rather than another parameter in $fieldInfo).
                    $number = $this->getSubfieldArray($currentField, ['v']);
                    if (isset($number[0])) {
                        $currentArray['number'] = $number[0];
                    }

                    // Save the current match:
                    $matches[] = $currentArray;
                }
            }
        }

        return $matches;
    }

    /**
     * Get an array of technical details on the item represented by the record.
     *
     * @return array
     */
    public function getSystemDetails()
    {
        return $this->getFieldArray('538');
    }

    /**
     * Get an array of note about the record's target audience.
     *
     * @return array
     */
    public function getTargetAudienceNotes()
    {
        return $this->getFieldArray('521');
    }

    /**
     * Get the text of the part/section portion of the title.
     *
     * @return string
     */
    public function getTitleSection()
    {
        return $this->getFirstFieldValue('245', ['n', 'p']);
    }

    /**
     * Get the statement of responsibility that goes with the title (i.e. "by John
     * Smith").
     *
     * @return string
     */
    public function getTitleStatement()
    {
        return $this->getFirstFieldValue('245', ['c']);
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        $toc = [];
        if (
            $fields = $this->getMarcReader()->getFields(
                '505',
                ['a', 'g', 'r', 't', 'u']
            )
        ) {
            foreach ($fields as $field) {
                // Implode all the subfields into a single string, then explode
                // on the -- separators (filtering out empty chunks). Due to
                // inconsistent application of subfield codes, this is the most
                // reliable way to split up a table of contents.
                $str = '';
                foreach ($field['subfields'] as $subfield) {
                    $str .= trim($subfield['data']) . ' ';
                }
                $toc = array_merge(
                    $toc,
                    array_filter(array_map('trim', preg_split('/[.\s]--/', $str)))
                );
            }
        }
        return $toc;
    }

    /**
     * Get hierarchical place names (MARC field 752)
     *
     * Returns an array of formatted hierarchical place names, consisting of all
     * alpha-subfields, concatenated for display
     *
     * @return array
     */
    public function getHierarchicalPlaceNames()
    {
        $placeNames = [];
        if ($fields = $this->getMarcReader()->getFields('752')) {
            foreach ($fields as $field) {
                $current = [];
                foreach ($field['subfields'] as $subfield) {
                    if (!is_numeric($subfield['code'])) {
                        $current[] = $subfield['data'];
                    }
                }
                $placeNames[] = implode(' -- ', $current);
            }
        }
        return $placeNames;
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
            '856' => ['y', 'z', '3'],   // Standard URL
            '555' => ['a'],              // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->getMarcReader()->getFields($field);
            foreach ($urls as $url) {
                // Is there an address in the current field?
                $address = $this->getSubfield($url, 'u');
                if ($address) {
                    // Is there a description?  If not, just use the URL itself.
                    foreach ($subfields as $current) {
                        $desc = $this->getSubfield($url, $current);
                        if ($desc) {
                            break;
                        }
                    }

                    $retVal[] = ['url' => $address, 'desc' => $desc ?: $address];
                }
            }
        }

        return $retVal;
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
        // Load configurations:
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? explode(',', $this->mainConfig->Record->marc_links) : [];
        $useVisibilityIndicator
            = $this->mainConfig->Record->marc_links_use_visibility_indicator ?? true;

        $retVal = [];
        foreach ($fieldsNames as $value) {
            $value = trim($value);
            $fields = $this->getMarcReader()->getFields($value);
            foreach ($fields as $field) {
                // Check to see if we should display at all
                if ($useVisibilityIndicator) {
                    $visibilityIndicator = $field['i1'];
                    if ($visibilityIndicator == '1') {
                        continue;
                    }
                }

                // Get data for field
                $tmp = $this->getFieldData($field);
                if (is_array($tmp)) {
                    $retVal[] = $tmp;
                }
            }
        }
        return empty($retVal) ? null : $retVal;
    }

    /**
     * Support method for getFieldData() -- factor the relationship indicator
     * into the field number where relevant to generate a note to associate
     * with a record link.
     *
     * @param array $field Field to examine
     *
     * @return string
     */
    protected function getRecordLinkNote($field)
    {
        // If set, use relationship information from subfield i
        if ($subfieldI = $this->getSubfield($field, 'i')) {
            // VuFind will add a colon to the label, so prevent double colons:
            $data = rtrim($subfieldI, ':');
            if (!empty($data)) {
                return $data;
            }
        }

        // Normalize blank relationship indicator to 0:
        $relationshipIndicator = $field['i2'];
        if ($relationshipIndicator == ' ') {
            $relationshipIndicator = '0';
        }

        // Assign notes based on the relationship type
        $value = $field['tag'];
        switch ($value) {
            case '780':
                if (in_array($relationshipIndicator, range('0', '7'))) {
                    $value .= '_' . $relationshipIndicator;
                }
                break;
            case '785':
                if (in_array($relationshipIndicator, range('0', '8'))) {
                    $value .= '_' . $relationshipIndicator;
                }
                break;
        }

        return 'note_' . $value;
    }

    /**
     * Returns the array element for the 'getAllRecordLinks' method
     *
     * @param array $field Field to examine
     *
     * @return array|bool  Array on success, boolean false if no valid link could be
     * found in the data.
     */
    protected function getFieldData($field)
    {
        // Make sure that there is a t field to be displayed:
        if (!($title = $this->getSubfield($field, 't'))) {
            return false;
        }

        $linkTypeSetting = $this->mainConfig->Record->marc_links_link_types
            ?? 'id,oclc,dlc,isbn,issn,title';
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
                            'type' => 'isn', 'value' => $isbn,
                            'exclude' => $this->getUniqueId(),
                        ];
                    }
                    break;
                case 'issn':
                    if ($issn = $this->getSubfield($field, 'x')) {
                        $link = [
                            'type' => 'isn', 'value' => $issn,
                            'exclude' => $this->getUniqueId(),
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
        // Make sure we have something to display:
        return !isset($link) ? false : [
            'title' => $this->getRecordLinkNote($field),
            'value' => $title,
            'link'  => $link,
        ];
    }

    /**
     * Returns an id extracted from the identifier subfield passed in
     *
     * @param string $idField MARC subfield containing id information
     * @param string $prefix  Prefix to search for in id field
     * @param bool   $raw     Return raw match, or normalize?
     *
     * @return string|bool    ID on success, false on failure
     */
    protected function getIdFromLinkingField($idField, $prefix = null, $raw = false)
    {
        if (preg_match('/\(([^)]+)\)(.+)/', $idField, $matches)) {
            // If prefix matches, return ID:
            if ($matches[1] == $prefix) {
                // Special case -- LCCN should not be stripped:
                return $raw
                    ? $matches[2]
                    : trim(str_replace(range('a', 'z'), '', ($matches[2])));
            }
        } elseif ($prefix == null) {
            // If no prefix was given or found, we presume it is a raw bib record
            return $idField;
        }
        return false;
    }

    /**
     * Support method for getFormattedMarcDetails() -- extract a single result
     *
     * @param array $currentField Result from MarcReader::getFields
     * @param array $details      Parsed instructions from getFormattedMarcDetails()
     *
     * @return string|bool
     */
    protected function extractSingleMarcDetail($currentField, $details)
    {
        // Simplest case -- "msg" mode (just return a configured message):
        if ($details['mode'] === 'msg') {
            // Map 'true' and 'false' to boolean equivalents:
            $msgMap = ['true' => true, 'false' => false];
            return $msgMap[$details['params']] ?? $details['params'];
        }

        // Standard case -- "marc" mode (extract subfield data):
        $result = $this->getSubfieldArray(
            $currentField,
            // Default to subfield a if nothing is specified:
            str_split($details['params'] ?? 'a'),
            true
        );
        return count($result) > 0 ? (string)$result[0] : '';
    }

    /**
     * Get Status/Holdings Information from the internally stored MARC Record
     * (support method used by the NoILS driver).
     *
     * @param string $defaultField The MARC Field to retrieve if $data commands do
     * not request something more specific
     * @param array  $data         The type of data to retrieve from the MARC field;
     * an array of pipe-delimited commands where the first part determines the data
     * retrieval mode, the second part provides further instructions, and the
     * optional third part provides a field to override $defaultField; supported
     * modes: "msg" (for a hard-coded message) and "marc" (for fetching subfield
     * data)
     *
     * @return array
     */
    public function getFormattedMarcDetails($defaultField, $data)
    {
        // First, parse the instructions into a more useful format, so we know
        // which fields we're going to have to look up.
        $instructions = [];
        foreach ($data as $key => $rawInstruction) {
            $instructionParts = explode('|', $rawInstruction);
            $instructions[$key] = [
                'mode' => $instructionParts[0],
                'params' => $instructionParts[1] ?? null,
                'field' => $instructionParts[2] ?? $defaultField,
            ];
        }

        // Now fetch all of the MARC data that we need.
        $getTagCallback = function ($instruction) {
            return $instruction['field'];
        };
        $fields = [];
        foreach (array_unique(array_map($getTagCallback, $instructions)) as $field) {
            $fields[$field] = $this->getMarcReader()->getFields($field);
        }

        // Initialize return array
        $matches = [];

        // Process the instructions on the requested data.
        foreach ($instructions as $key => $details) {
            foreach ($fields[$details['field']] as $i => $currentField) {
                if (!isset($matches[$i])) {
                    $matches[$i] = ['id' => $this->getUniqueId()];
                }
                $matches[$i][$key] = $this->extractSingleMarcDetail(
                    $currentField,
                    $details
                );
            }
        }
        return $matches;
    }

    /**
     * Return an XML representation of the record using the specified format.
     * Return false if the format is unsupported.
     *
     * @param string       $format  Name of format to use (corresponds with
     * OAI-PMH metadataPrefix parameter).
     * @param string       $baseUrl Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLinker $linker  Record linker helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $linker = null)
    {
        // Special case for MARC:
        if ($format == 'marc21') {
            try {
                $xml = $this->getMarcReader()->toFormat('MARCXML');
            } catch (\Exception) {
                return false;
            }
            $xml = simplexml_load_string($xml);
            if (!$xml || !isset($xml->record)) {
                return false;
            }

            // Set up proper namespacing and extract just the <record> tag:
            $xml->record->addAttribute('xmlns', 'http://www.loc.gov/MARC21/slim');
            // There's a quirk in SimpleXML that strips the first namespace
            // declaration, hence the double xmlns: prefix:
            $xml->record->addAttribute(
                'xmlns:xmlns:xsi',
                'http://www.w3.org/2001/XMLSchema-instance'
            );
            $xml->record->addAttribute(
                'xsi:schemaLocation',
                'http://www.loc.gov/MARC21/slim ' .
                'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
                'http://www.w3.org/2001/XMLSchema-instance'
            );
            $xml->record->addAttribute('type', $this->xmlType);
            return $xml->record->asXML();
        }

        // Try the parent method:
        return parent::getXML($format, $baseUrl, $linker);
    }

    /**
     * Get an XML RDF representation of the data in this record.
     *
     * @return mixed XML RDF data (empty if unsupported or error).
     */
    public function getRDFXML()
    {
        try {
            $xml = $this->getMarcReader()->toFormat('MARCXML');
        } catch (\Exception $e) {
            return '';
        }
        return XSLTProcessor::process(
            'record-rdf-mods.xsl',
            trim($xml)
        );
    }

    /**
     * Return the list of "source records" for this consortial record.
     *
     * @return array
     */
    public function getConsortialIDs()
    {
        return $this->getFieldArray('035');
    }

    /**
     * Return first ISMN found for this record, or false if no one found
     *
     * @return mixed
     */
    public function getCleanISMN()
    {
        $fields024 = $this->getMarcReader()->getFields('024');
        foreach ($fields024 as $field) {
            if (
                $field['i1'] == 2
                && $subfield = $this->getSubfield($field, 'a')
            ) {
                return $subfield;
            }
        }
        return false;
    }

    /**
     * Return first national bibliography number found, or false if not found
     *
     * @return mixed
     */
    public function getCleanNBN()
    {
        $field = $this->getMarcReader()->getField('015');
        if ($field && $nbn = $this->getSubfield($field, 'a')) {
            $result = compact('nbn');
            if ($source = $this->getSubfield($field, '7')) {
                $result['source'] = $source;
            }
            return $result;
        }
        return false;
    }

    /**
     * Get the full titles of the record in alternative scripts.
     *
     * @return array
     */
    public function getTitlesAltScript(): array
    {
        return $this->getMarcReader()
            ->getLinkedFieldsSubfields('880', '245', ['a', 'b']);
    }

    /**
     * Get the full titles of the record including section and part information in
     * alternative scripts.
     *
     * @return array
     */
    public function getFullTitlesAltScript(): array
    {
        return $this->getMarcReader()
            ->getLinkedFieldsSubfields('880', '245', ['a', 'b', 'n', 'p']);
    }

    /**
     * Get the short (pre-subtitle) title of the record in alternative scripts.
     *
     * @return array
     */
    public function getShortTitlesAltScript(): array
    {
        return $this->getMarcReader()->getLinkedFieldsSubfields('880', '245', ['a']);
    }

    /**
     * Get the subtitle of the record in alternative script.
     *
     * @return array
     */
    public function getSubtitlesAltScript(): array
    {
        return $this->getMarcReader()->getLinkedFieldsSubFields('880', '245', ['b']);
    }

    /**
     * Get the text of the part/section portion of the title in alternative scripts.
     *
     * @return array
     */
    public function getTitleSectionsAltScript(): array
    {
        return $this->getMarcReader()
            ->getLinkedFieldsSubfields('880', '245', ['n', 'p']);
    }

    /**
     * Get an array of textual holdings for the holdings on a record.
     *
     * @return array
     */
    public function getTextualHoldings()
    {
        return $this->getFieldArray('866');
    }

    /**
     * Check if an array of indicator filters match the provided marc data
     *
     * @param array $marc_data MARC data for a specific field
     * @param array $indFilter Array with up to 2 keys ('1', and '2') with an array as their value
     * containing what to match on in the marc indicator.
     * ex: ['1' => ['0','1']] would filter ind1 with 0 or 1
     *
     * @return bool
     */
    protected function checkIndicatorFilter($marc_data, $indFilter): bool
    {
        foreach (range(1, 2) as $indNum) {
            if (isset($indFilter[$indNum])) {
                if (!in_array(trim(($marc_data['i' . $indNum] ?? '')), (array)$indFilter[$indNum])) {
                    return false;
                }
            }
        }
        // If we got this far, no non-matching filters were encountered.
        return true;
    }

    /**
     * Check if the indicator filters match the provided marc data
     *
     * @param array $marc_data MARC data for a specific field
     * @param array $indData   Indicator filters as described in getMarcFieldWithInd()
     *
     * @return bool
     */
    protected function checkIndicatorFilters($marc_data, $indData): bool
    {
        foreach ($indData as $indFilter) {
            if ($this->checkIndicatorFilter($marc_data, $indFilter)) {
                return true;
            }
        }
        // If we got this far, either $indData is empty (no filters defined -- return true)
        // or it is non-empty (no filters matched -- return false)
        return empty($indData);
    }

    /**
     * Takes a Marc field that notes are stored in (ex: 950) and a list of
     * sub fields (ex: ['a','b']) optionally as well as what indicator
     * numbers and values to filter for and concatenates the subfields
     * together and returns the fields back as an array
     * (ex: ['subA subB subC', 'field2SubA field2SubB'])
     *
     * @param string $field    Marc field to search within
     * @param ?array $subfield Sub-fields to return or empty for all
     * @param array  $indData  Array of filter arrays, each in the format indicator number =>
     * array of allowed indicator values. If any one of the filter arrays fully matches the indicator
     * values in the field, data will be returned. If no filter arrays are defined, data will always
     * be returned regardless of indicators.
     * ex: [['1' => ['1', '2']], ['2' => ['']]] would filter fields ind1 = 1 or 2 or ind2 = blank
     * ex: [['1' => ['1'], '2' => ['7']]] would filter fields with ind1 = 1 and ind2 = 7
     * ex: [] would apply no filtering based on indicators
     *
     * @return array The values within the subfields under the field
     */
    public function getMarcFieldWithInd(
        string $field,
        ?array $subfield = null,
        array $indData = []
    ) {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $field_vals = [];
            if ($this->checkIndicatorFilters($marc_data, $indData)) {
                $subfields = $marc_data['subfields'];
                foreach ($subfields as $subfield) {
                    $field_vals[] = $subfield['data'];
                }
            }
            $newVal = implode(' ', $field_vals);
            if (!empty($field_vals) && !in_array($newVal, $vals)) {
                $vals[] = $newVal;
            }
        }
        return $vals;
    }

    /**
     * Get the location of other archival materials notes
     *
     * @return array Note fields from the MARC record
     */
    public function getLocationOfArchivalMaterialsNotes()
    {
        return $this->getMarcFieldWithInd('544', range('a', 'z'), [[1 => ['', '0']]]);
    }

    /**
     * Get an array of summary strings for the record with only the 'a' subfield.
     *
     * @return array
     */
    public function getSummary()
    {
        return $this->getMarcFieldWithInd('520', ['a'], [[1 => ['', '0', '2', '8']]]);
    }

    /**
     * Get the summary note
     *
     * @return array Note fields from the MARC record
     */
    public function getSummaryNotes()
    {
        return $this->getMarcFieldWithInd('520', range('a', 'z'), [[1 => ['', '0', '2', '8']]]);
    }

    /**
     * Get the abstract notes
     *
     * @return array Note fields from the MARC record
     */
    public function getAbstractNotes()
    {
        return $this->getMarcFieldWithInd('520', range('a', 'z'), [[1 => ['3']]]);
    }
}
