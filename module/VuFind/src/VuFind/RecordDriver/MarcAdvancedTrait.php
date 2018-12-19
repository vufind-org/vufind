<?php
/**
 * Functions to add advanced MARC-driven functionality to a record driver already
 * powered by the standard index spec. Depends upon MarcReaderTrait.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2017.
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
namespace VuFind\RecordDriver;

use VuFind\View\Helper\Root\RecordLink;
use VuFind\XSLT\Processor as XSLTProcessor;

/**
 * Functions to add advanced MARC-driven functionality to a record driver already
 * powered by the standard index spec. Depends upon MarcReaderTrait.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
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
        '656' => 'occupation'
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
        '6' => 'rvm'
    ];

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
     * Get all subject headings associated with this record.  Each heading is
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
        // This is all the collected data:
        $retval = [];

        // Try each MARC field one at a time:
        foreach ($this->subjectFields as $field => $fieldType) {
            // Do we have any results for the current field?  If not, try the next.
            $results = $this->getMarcRecord()->getFields($field);
            if (!$results) {
                continue;
            }

            // If we got here, we found results -- let's loop through them.
            foreach ($results as $result) {
                // Start an array for holding the chunks of the current heading:
                $current = [];

                // Get all the chunks and collect them together:
                $subfields = $result->getSubfields();
                if ($subfields) {
                    foreach ($subfields as $subfield) {
                        // Numeric subfields are for control purposes and should not
                        // be displayed:
                        if (!is_numeric($subfield->getCode())) {
                            $current[] = $subfield->getData();
                        }
                    }
                    // If we found at least one chunk, add a heading to our result:
                    if (!empty($current)) {
                        if ($extended) {
                            $sourceIndicator = $result->getIndicator(2);
                            $source = '';
                            if (isset($this->subjectSources[$sourceIndicator])) {
                                $source = $this->subjectSources[$sourceIndicator];
                            } else {
                                $source = $result->getSubfield('2');
                                if ($source) {
                                    $source = $source->getData();
                                }
                            }
                            $retval[] = [
                                'heading' => $current,
                                'type' => $fieldType,
                                'source' => $source ?: ''
                            ];
                        } else {
                            $retval[] = $current;
                        }
                    }
                }
            }
        }

        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize', array_unique(array_map('serialize', $retval))
        );
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
        $leader = $this->getMarcRecord()->getLeader();
        $biblioLevel = strtoupper($leader[7]);

        switch ($biblioLevel) {
        case 'M': // Monograph
            return "Monograph";
        case 'S': // Serial
            return "Serial";
        case 'A': // Monograph Part
            return "MonographPart";
        case 'B': // Serial Part
            return "SerialPart";
        case 'C': // Collection
            return "Collection";
        case 'D': // Collection Part
            return "CollectionPart";
        default:
            return "Unknown";
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
        $record = clone $this->getMarcRecord();
        // The default implementation does not filter out any fields
        // $record->deleteFields('9', true);
        return $record->toXML();
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
        for ($x = 0; $x < count($times); $x++) {
            $times[$x] = substr($times[$x], 0, 2) . ':' .
                substr($times[$x], 2, 2) . ':' .
                substr($times[$x], 4, 2);
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
            $series = $this->getMarcRecord()->getFields($field);
            if (is_array($series)) {
                foreach ($series as $currentField) {
                    // Can we find a name using the specified subfield list?
                    $name = $this->getSubfieldArray($currentField, $subfields);
                    if (isset($name[0])) {
                        $currentArray = ['name' => $name[0]];

                        // Can we find a number in subfield v?  (Note that number is
                        // always in subfield v regardless of whether we are dealing
                        // with 440, 490, 800 or 830 -- hence the hard-coded array
                        // rather than another parameter in $fieldInfo).
                        $number
                            = $this->getSubfieldArray($currentField, ['v']);
                        if (isset($number[0])) {
                            $currentArray['number'] = $number[0];
                        }

                        // Save the current match:
                        $matches[] = $currentArray;
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return $this->getFieldArray('520');
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
        // Return empty array if we have no table of contents:
        $fields = $this->getMarcRecord()->getFields('505');
        if (!$fields) {
            return [];
        }

        // If we got this far, we have a table -- collect it as a string:
        $toc = [];
        foreach ($fields as $field) {
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                // Break the string into appropriate chunks, filtering empty strings,
                // and merge them into return array:
                $toc = array_merge(
                    $toc,
                    array_filter(explode('--', $subfield->getData()), 'trim')
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
        if ($fields = $this->getMarcRecord()->getFields('752')) {
            foreach ($fields as $field) {
                $subfields = $field->getSubfields();
                $current = [];
                foreach ($subfields as $subfield) {
                    if (!is_numeric($subfield->getCode())) {
                        $current[] = $subfield->getData();
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
            '555' => ['a']         // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->getMarcRecord()->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $url->getSubfield('u');
                    if ($address) {
                        $address = $address->getData();

                        // Is there a description?  If not, just use the URL itself.
                        foreach ($subfields as $current) {
                            $desc = $url->getSubfield($current);
                            if ($desc) {
                                break;
                            }
                        }
                        if ($desc) {
                            $desc = $desc->getData();
                        } else {
                            $desc = $address;
                        }

                        $retVal[] = ['url' => $address, 'desc' => $desc];
                    }
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
            = isset($this->mainConfig->Record->marc_links_use_visibility_indicator)
            ? $this->mainConfig->Record->marc_links_use_visibility_indicator : true;

        $retVal = [];
        foreach ($fieldsNames as $value) {
            $value = trim($value);
            $fields = $this->getMarcRecord()->getFields($value);
            if (!empty($fields)) {
                foreach ($fields as $field) {
                    // Check to see if we should display at all
                    if ($useVisibilityIndicator) {
                        $visibilityIndicator = $field->getIndicator('1');
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
        }
        return empty($retVal) ? null : $retVal;
    }

    /**
     * Support method for getFieldData() -- factor the relationship indicator
     * into the field number where relevant to generate a note to associate
     * with a record link.
     *
     * @param File_MARC_Data_Field $field Field to examine
     *
     * @return string
     */
    protected function getRecordLinkNote($field)
    {
        // If set, use relationship information from subfield i
        if ($subfieldI = $field->getSubfield('i')) {
            $data = trim($subfieldI->getData());
            if (!empty($data)) {
                return $data;
            }
        }

        // Normalize blank relationship indicator to 0:
        $relationshipIndicator = $field->getIndicator('2');
        if ($relationshipIndicator == ' ') {
            $relationshipIndicator = '0';
        }

        // Assign notes based on the relationship type
        $value = $field->getTag();
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
     * @param File_MARC_Data_Field $field Field to examine
     *
     * @return array|bool                 Array on success, boolean false if no
     * valid link could be found in the data.
     */
    protected function getFieldData($field)
    {
        // Make sure that there is a t field to be displayed:
        if ($title = $field->getSubfield('t')) {
            $title = $title->getData();
        } else {
            return false;
        }

        $linkTypeSetting = isset($this->mainConfig->Record->marc_links_link_types)
            ? $this->mainConfig->Record->marc_links_link_types
            : 'id,oclc,dlc,isbn,issn,title';
        $linkTypes = explode(',', $linkTypeSetting);
        $linkFields = $field->getSubfields('w');

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
                if ($isbn = $field->getSubfield('z')) {
                    $link = [
                        'type' => 'isn', 'value' => trim($isbn->getData()),
                        'exclude' => $this->getUniqueId()
                    ];
                }
                break;
            case 'issn':
                if ($issn = $field->getSubfield('x')) {
                    $link = [
                        'type' => 'isn', 'value' => trim($issn->getData()),
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
        // Make sure we have something to display:
        return !isset($link) ? false : [
            'title' => $this->getRecordLinkNote($field),
            'value' => $title,
            'link'  => $link
        ];
    }

    /**
     * Returns an id extracted from the identifier subfield passed in
     *
     * @param \File_MARC_Subfield $idField MARC field containing id information
     * @param string              $prefix  Prefix to search for in id field
     * @param bool                $raw     Return raw match, or normalize?
     *
     * @return string|bool                 ID on success, false on failure
     */
    protected function getIdFromLinkingField($idField, $prefix = null, $raw = false)
    {
        $text = $idField->getData();
        if (preg_match('/\(([^)]+)\)(.+)/', $text, $matches)) {
            // If prefix matches, return ID:
            if ($matches[1] == $prefix) {
                // Special case -- LCCN should not be stripped:
                return $raw
                    ? $matches[2]
                    : trim(str_replace(range('a', 'z'), '', ($matches[2])));
            }
        } elseif ($prefix == null) {
            // If no prefix was given or found, we presume it is a raw bib record
            return $text;
        }
        return false;
    }

    /**
     * Get Status/Holdings Information from the internally stored MARC Record
     * (support method used by the NoILS driver).
     *
     * @param array $field The MARC Field to retrieve
     * @param array $data  A keyed array of data to retrieve from subfields
     *
     * @return array
     */
    public function getFormattedMarcDetails($field, $data)
    {
        // Initialize return array
        $matches = [];
        $i = 0;

        // Try to look up the specified field, return empty array if it doesn't
        // exist.
        $fields = $this->getMarcRecord()->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }

        // Extract all the requested subfields, if applicable.
        foreach ($fields as $currentField) {
            foreach ($data as $key => $info) {
                $split = explode("|", $info);
                if ($split[0] == "msg") {
                    if ($split[1] == "true") {
                        $result = true;
                    } elseif ($split[1] == "false") {
                        $result = false;
                    } else {
                        $result = $split[1];
                    }
                    $matches[$i][$key] = $result;
                } else {
                    // Default to subfield a if nothing is specified.
                    if (count($split) < 2) {
                        $subfields = ['a'];
                    } else {
                        $subfields = str_split($split[1]);
                    }
                    $result = $this->getSubfieldArray(
                        $currentField, $subfields, true
                    );
                    $matches[$i][$key] = count($result) > 0
                        ? (string)$result[0] : '';
                }
            }
            $matches[$i]['id'] = $this->getUniqueID();
            $i++;
        }
        return $matches;
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
        // Special case for MARC:
        if ($format == 'marc21') {
            $xml = $this->getMarcRecord()->toXML();
            $xml = str_replace(
                [chr(27), chr(28), chr(29), chr(30), chr(31), chr(8)], ' ', $xml
            );
            $xml = simplexml_load_string($xml);
            if (!$xml || !isset($xml->record)) {
                return false;
            }

            // Set up proper namespacing and extract just the <record> tag:
            $xml->record->addAttribute('xmlns', "http://www.loc.gov/MARC21/slim");
            $xml->record->addAttribute(
                'xsi:schemaLocation',
                'http://www.loc.gov/MARC21/slim ' .
                'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
                'http://www.w3.org/2001/XMLSchema-instance'
            );
            $xml->record->addAttribute('type', 'Bibliographic');
            return $xml->record->asXML();
        }

        // Try the parent method:
        return parent::getXML($format, $baseUrl, $recordLink);
    }

    /**
     * Get an XML RDF representation of the data in this record.
     *
     * @return mixed XML RDF data (empty if unsupported or error).
     */
    public function getRDFXML()
    {
        return XSLTProcessor::process(
            'record-rdf-mods.xsl', trim($this->getMarcRecord()->toXML())
        );
    }

    /**
     * Return the list of "source records" for this consortial record.
     *
     * @return array
     */
    public function getConsortialIDs()
    {
        return $this->getFieldArray('035', 'a', true);
    }
}
