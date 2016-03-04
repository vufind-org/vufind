<?php
/**
 * Model for MARC records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    use SolrFinna;

    /**
     * Datasource configuration
     *
     * @var \Zend\Config\Config
     */
    protected $datasourceConfig;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig       VuFind main configuration (omit
     * for built-in defaults)
     * @param \Zend\Config\Config $recordConfig     Record-specific configuration
     * file (omit to use $mainConfig as  $recordConfig)
     * @param \Zend\Config\Config $searchSettings   Search-specific configuration
     * file
     * @param PluginManager       $results          Results plugin manager
     * @param \Zend\Config\Config $datasourceConfig Datasource configuration
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null,
        \VuFind\Search\Results\PluginManager $results = null,
        \Zend\Config\Config $datasourceConfig = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings, $results);

        $this->datasourceConfig = $datasourceConfig;
    }

    /**
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        $result = [];
        $fields = $this->getMarcRecord()->getFields('506');
        foreach ($fields as $field) {
            if ($subfield = $field->getSubfield('a')) {
                $access = $this->stripTrailingPunctuation($subfield->getData());
                if ($subfield = $field->getSubfield('e')) {
                    $access .= ' (' .
                        $this->stripTrailingPunctuation($subfield->getData()) . ')';
                }
                $result[] = $access;
            }
        }
        return $result;
    }

    /**
     * Return type of access restriction for the record.
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType()
    {
        $fields = $this->getMarcRecord()->getFields('506');
        foreach ($fields as $field) {
            if ($subfield = $field->getSubfield('u')) {
                return ['link' => $subfield->getData()];
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
        $result = parent::getAllRecordLinks();
        if ($result !== null) {
            foreach ($result as &$link) {
                if (isset($link['value'])) {
                    $link['value'] = $this->stripTrailingPunctuation($link['value']);
                }
            }
        }

        return $result;
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        // These are the fields that may contain subject headings:
        $fields = [
            '600', '610', '611', '630', '648', '650', '651', '653', '656'
        ];

        // This is all the collected data:
        $retval = [];

        // Try each MARC field one at a time:
        foreach ($fields as $field) {
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
                        $retval[] = $current;
                    }
                }
            }
        }

        // Send back everything we collected:
        return $retval;
    }

    /**
     * Return an associative array of image URLs associated with this record
     * (key = URL, value = description).
     *
     * @param string $size Size of requested images
     *
     * @return array
     */
    public function getAllThumbnails($size = 'large')
    {
        $urls = [];
        $url = '';
        $type = '';
        foreach ($this->getMarcRecord()->getFields('856') as $url) {
            $type = $url->getSubfield('q');
            if ($type) {
                $type = $type->getData();
                if ("IMAGE" == $type || "image/jpeg" == $type) {
                    $address = $url->getSubfield('u');
                    if ($address && $this->urlAllowed($address->getData())) {
                        $address = $address->getData();
                        $urls[$address] = '';
                    }
                }
            }
        }
        return $urls;
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

        foreach (['050', '080', '084'] as $fieldCode) {
            $fields = $this->getMarcRecord()->getFields($fieldCode);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    switch ($fieldCode) {
                    case '050':
                        $classification = 'dlc';
                        break;
                    case '080':
                        $classification = 'udk';
                        break;
                    default:
                        $classification = $this->getSubfieldArray($field, ['2']);
                        if (!empty($classification)) {
                            $classification = $classification[0];
                        }
                        break;
                    }
                    // continue doesn't work inside the switch statement
                    if (empty($classification)) {
                        continue;
                    }

                    $subfields = $this->getSubfieldArray($field, ['a', 'b']);
                    if (!empty($subfields)) {
                        $result[$classification][] = $subfields[0];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        foreach ($this->getMarcRecord()->getFields('773') as $field) {
            $subfield = $field->getSubfield('g');
            if (!$subfield) {
                continue;
            }
            $subfield = $subfield->getData();
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
        foreach ($this->getMarcRecord()->getFields('856') as $url) {
            $type = $url->getSubfield('q');
            if ($type) {
                $type = $type->getData();
                if ("TEXT" == $type || "text/html" == $type) {
                    $address = $url->getSubfield('u');
                    if ($address && !$this->urlBlacklisted($address->getData())) {
                        $address = $address->getData();
                        return $address;
                    }
                }
            }
        }

        if ($isbn = $this->getCleanISBN()) {
            return 'http://siilo-kk.lib.helsinki.fi/getText.php?query=' . $isbn;
        }
        return false;
    }

    /**
     * Get dissertation note for the record.
     * Use field 502 if available. If not use local field 509
     *
     * @return string dissertation notes
     */
    public function getDissertationNote()
    {
        $notes = $this->getFirstFieldValue('502', ['a', 'b', 'c']);
        if (!$notes) {
            $notes = $this->getFirstFieldValue('509', ['a', 'b', 'c']);
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
        foreach ($this->getMarcRecord()->getFields('979') as $field) {
            $partOrderCounter++;
            $partAuthors = [];
            $uniformTitle = '';
            $duration = '';
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                $subfieldCode = $subfield->getCode();
                switch ($subfieldCode) {
                case 'a':
                    $partId = $subfield->getData();
                    break;
                case 'b':
                    $partTitle = $subfield->getData();
                    break;
                case 'c':
                    $partAuthors[] = $subfield->getData();
                    break;
                case 'd':
                    $partAuthors[] = $subfield->getData();
                    break;
                case 'e':
                    $uniformTitle = $subfield->getData();
                    break;
                case 'f':
                    $duration = $subfield->getData();
                    if ($duration == '000000') {
                        $duration = '';
                    }
                    break;
                }
            }
            // Filter out any empty fields
            $partAuthors = array_filter($partAuthors);

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
            foreach ($this->getMarcRecord()->getFields('700') as $field) {
                if (!$field->getSubfield('t')) {
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
                    if (isset($configArray['Record']['presenter_roles'])) {
                        foreach ($configArray['Record']['presenter_roles']
                            as $role
                        ) {
                            $author = trim($author);
                            if (substr($author, -strlen($role) - 2) == ", $role") {
                                $partPresenters[] = $author;
                                continue 2;
                            }
                        }
                    }
                    if (isset($configArray['Record']['arranger_roles'])) {
                        foreach ($configArray['Record']['arranger_roles'] as $role) {
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
        foreach ($this->getMarcRecord()->getFields('300') as $field) {
            foreach ($field->getSubfields('a') as $extent) {
                $results[] = $this->stripTrailingPunctuation($extent->getData());
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
        $record = clone($this->getMarcRecord());
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
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        $fields = [
            '020' => ['a'],
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
        return array_values(array_unique($isbn));
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
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
        return array_values(array_unique(array_filter($issn)));
    }

    /**
     * Get manufacturer
     *
     * @return string
     */
    public function getManufacturer()
    {
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
            $fields = $this->getMarcRecord()->getFields($fieldCode);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    // Leave out 700 fields containing subfield 't' (these go to the
                    // contents list)
                    if ($fieldCode == '700' && $field->getSubfield('t')) {
                        continue;
                    }

                    $role = $this->getSubfieldArray($field, ['e']);
                    $role = empty($role) ? '' : mb_strtolower($role[0], 'UTF-8');
                    if ($role
                        && isset($this->mainConfig->Record->presenter_roles)
                        && in_array(
                            $role,
                            $this->mainConfig->Record->presenter_roles->toArray()
                        )
                    ) {
                        continue;
                    }
                    $subfields = $this->getSubfieldArray(
                        $field, ['a', 'b', 'c', 'd']
                    );
                    if (!empty($subfields)) {
                        $result[] = [
                            'name' => $this->stripTrailingPunctuation($subfields[0]),
                            'role' => $role
                        ];
                    }
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
        foreach ($this->getMarcRecord()->getFields('787') as $link) {
            $heading = $link->getSubfield('i');
            if ($heading) {
                $heading = $heading->getData();
            } else {
                $heading = '';
            }
            // Normalize heading
            $heading = str_replace(':', '', $heading);
            $title = $link->getSubfield('t');
            if ($title) {
                $title = $title->getData();
            } else {
                $title = '';
            }
            $author = $link->getSubfield('a');
            if ($author) {
                $author = $author->getData();
            } else {
                $author = '';
            }
            $isbn = $link->getSubfield('z');
            $issn = $link->getSubfield('x');
            if ($isbn) {
                $isn = $isbn->getData();
            } else if ($issn) {
                $isn = $issn->getData();
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
        global $configArray;
        $result = ['presenters' => [], 'details' => []];

        foreach (['100', '110', '700', '710'] as $fieldCode) {
            $fields = $this->getMarcRecord()->getFields($fieldCode);
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    // Leave out 700 fields containing subfield 't' (these go to the
                    // contents list)
                    if ($fieldCode == '700' && $field->getSubfield('t')) {
                        continue;
                    }

                    $role = $this->getSubfieldArray($field, ['e']);
                    $role = empty($role) ? '' : mb_strtolower($role[0], 'UTF-8');
                    if (!$role
                        || !isset($this->mainConfig->Record->presenter_roles)
                        || !in_array(
                            $role,
                            $this->mainConfig->Record->presenter_roles->toArray()
                        )
                    ) {
                        continue;
                    }
                    $subfields = $this->getSubfieldArray(
                        $field, ['a', 'b', 'c', 'd']
                    );
                    if (!empty($subfields)) {
                        $result['presenters'][] = [
                            'name' => $this->stripTrailingPunctuation($subfields[0]),
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
        return $this->stripTrailingPunctuation(
            $this->getFirstFieldValue('100', ['a', 'b', 'c'])
        );
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
        } else if (strlen($dateString) === 6) {
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
        $field = $this->getMarcRecord()->getField('008');
        if ($field) {
            $data = $field->getData();
            $year = substr($data, 11, 4);
            $type = substr($data, 6, 1);
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
        foreach ($this->getMarcRecord()->getFields('700') as $field) {
            if ($name = $field->getSubfield('a')) {
                $results[] = $this->stripTrailingPunctuation($name->getData());
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
            '440' => ['a', 'p'],
            '800' => ['a', 'b', 'c', 'd', 'f', 'p', 'q', 't'],
            '830' => ['a', 'p', 'x']];
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

        foreach ($matches as &$match) {
            if (isset($match['number'])) {
                $match['number'] = $this->stripTrailingPunctuation($match['number']);
            }
        }

        return $matches;
    }

    /**
     * Return SFX Object ID
     *
     * @return string.
     */
    public function getSfxObjectId()
    {
        $field001 = $this->getMarcRecord()->getField('001');
        $id = $field001 ? $field001->getData() : '';
        $field090 = $this->getMarcRecord()->getField('090');
        $objectId = $field090 ? $field090->getSubfield('a')->getData() : '';
        if ($id == $objectId) {
            return $objectId;
        }
        return '';
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
            foreach ($this->getMarcRecord()->getFields($fieldCode) as $field) {
                foreach ($field->getSubfields() as $subfield) {
                    $subfields[] = $subfield->getData();
                }
                $results[] = implode(' ', $subfields);
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
            $urls = $this->getMarcRecord()->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $url->getSubfield('u');
                    // Require at least one dot surrounded by valid characters or a
                    // familiar scheme
                    if ($address
                        && (preg_match('/[A-Za-z0-9]\.[A-Za-z0-9]/', $address)
                        || preg_match('/^(http|ftp)s?:\/\//', $address))
                    ) {
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

                        $data = ['url' => $address, 'desc' => $desc];
                        if (!$this->urlBlacklisted($address, $desc)
                            && !in_array($data, $retVal)
                        ) {
                            $retVal[] = $data;
                        }
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * Does this record have embedded component parts
     *
     * @return bool Whether this record has embedded component parts
     */
    public function hasEmbeddedComponentParts()
    {
        if ($this->getMarcRecord()->getFields('979')) {
            return true;
        }
        // Alternatively, are there titles in 700 fields?
        foreach ($this->getMarcRecord()->getFields('700') as $field) {
            if ($field->getSubfield('t')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a datasource has patron functions in order to show or hide the
     * patron login
     *
     * @return bool
     */
    public function hasPatronFunctions()
    {
        $details = $this->getInstitutionDetails();
        $datasource = $details['datasource'];
        if (isset($this->datasourceConfig->$datasource->disablePatronFunctions)
            && $datasourceConfig->$datasource->disablePatronFunctions
        ) {
            return false;
        }
        return true;
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
            $titleFields = [];
            if ($relInfo = $field->getSubfield('i')) {
                $titleFields[] = $relInfo->getData();
            }
            if ($issn = $field->getSubfield('x')) {
                $titleFields[] = $issn->getData();
            } else if ($isbn = $field->getSubfield('z')) {
                $titleFields[] = $isbn->getData();
            }
            $title = implode(' ', $titleFields);
        }

        if ($qualifyingInfo = $field->getSubfield('c')) {
            if ($title) {
                $title .= ' ';
            }
            $title .= $qualifyingInfo->getData();
        }

        if (!$title) {
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
            switch (trim($linkType)){
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
                    $name = $this->getSubfieldArray(
                        $currentField, $subfields, false
                    );
                    if (isset($name[0])) {
                        $currentArray = ['name' =>
                            $this->stripTrailingPunctuation(array_shift($name))
                        ];
                        $currentArray['additional'] = implode(' ', $name);

                        // Can we find a number in subfield v?  (Note that number is
                        // always in subfield v regardless of whether we are dealing
                        // with 440, 490, 800 or 830 -- hence the hard-coded array
                        // rather than another parameter in $fieldInfo).
                        $number = $this->getSubfieldArray($currentField, ['v']);
                        if (isset($number[0])) {
                            $currentArray['number'] = $number[0];
                        }

                        // Can we find an ISSN in subfield x? (same note as above)
                        $issn = $this->getSubfieldArray($currentField, ['x']);
                        if (isset($issn[0])) {
                            $currentArray['issn'] = $this->stripTrailingPunctuation(
                                $issn[0]
                            );
                        }

                        // Save the current match:
                        $matches[] = $currentArray;
                    }
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * Strip trailing spaces and punctuation characters from a string
     *
     * @param string|string[] $input      String to strip
     * @param string          $additional Additional punctuation characters
     *
     * @return string
     */
    protected function stripTrailingPunctuation($input, $additional = '')
    {
        $array = is_array($input);
        if (!$array) {
            $input = [$input];
        }
        foreach ($input as &$str) {
            $str = preg_replace("/[\s\/:;\,=\($additional]+\$/", '', $str);
            // Don't replace an initial letter (e.g. string "Smith, A.") followed by
            // period
            $thirdLast = substr($str, -3, 1);
            if (substr($str, -1) == '.' && $thirdLast != ' ') {
                if (!in_array(
                    substr($str, -4),
                    ['nid.', 'sid.', 'kuv.', 'ill.', 'säv.', 'col.']
                )) {
                    $str = substr($str, 0, -1);
                }
            }
        }
        return $array ? $input : $input[0];
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
}
