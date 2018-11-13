<?php
/**
 * Functions for reading MARC records.
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

/**
 * Functions for reading MARC records.
 *
 * Assumption: raw MARC data can be found in $this->fields['fullrecord'].
 *
 * Assumption: VuFind config available as $this->mainConfig
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait MarcReaderTrait
{
    /**
     * MARC record. Access only via getMarcRecord() as this is initialized lazily.
     *
     * @var \File_MARC_Record
     */
    protected $lazyMarcRecord = null;

    /**
     * Get access to the raw File_MARC object.
     *
     * @return \File_MARC_Record
     */
    public function getMarcRecord()
    {
        if (null === $this->lazyMarcRecord) {
            $marc = trim($this->fields['fullrecord']);

            // check if we are dealing with MARCXML
            if (substr($marc, 0, 1) == '<') {
                $marc = new \File_MARCXML($marc, \File_MARCXML::SOURCE_STRING);
            } else {
                // When indexing over HTTP, SolrMarc may use entities instead of
                // certain control characters; we should normalize these:
                $marc = str_replace(
                    ['#29;', '#30;', '#31;'], ["\x1D", "\x1E", "\x1F"], $marc
                );
                $marc = new \File_MARC($marc, \File_MARC::SOURCE_STRING);
            }

            $this->lazyMarcRecord = $marc->next();
            if (!$this->lazyMarcRecord) {
                throw new \File_MARC_Exception('Cannot Process MARC Record');
            }
        }

        return $this->lazyMarcRecord;
    }

    /**
     * Return an array of all values extracted from the specified field/subfield
     * combination.  If multiple subfields are specified and $concat is true, they
     * will be concatenated together in the order listed -- each entry in the array
     * will correspond with a single MARC field.  If $concat is false, the return
     * array will contain separate entries for separate subfields.
     *
     * @param string $field     The MARC field number to read
     * @param array  $subfields The MARC subfield codes to read
     * @param bool   $concat    Should we concatenate subfields?
     * @param string $separator Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getFieldArray($field, $subfields = null, $concat = true,
        $separator = ' '
    ) {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = ['a'];
        }

        // Initialize return array
        $matches = [];

        // Try to look up the specified field, return empty array if it doesn't
        // exist.
        $fields = $this->getMarcRecord()->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }

        // Extract all the requested subfields, if applicable.
        foreach ($fields as $currentField) {
            $next = $this
                ->getSubfieldArray($currentField, $subfields, $concat, $separator);
            $matches = array_merge($matches, $next);
        }

        return $matches;
    }

    /**
     * Get the first value matching the specified MARC field and subfields.
     * If multiple subfields are specified, they will be concatenated together.
     *
     * @param string $field     The MARC field to read
     * @param array  $subfields The MARC subfield codes to read
     *
     * @return string
     */
    protected function getFirstFieldValue($field, $subfields = null)
    {
        $matches = $this->getFieldArray($field, $subfields);
        return (is_array($matches) && count($matches) > 0) ?
            $matches[0] : null;
    }

    /**
     * Get the item's publication information
     *
     * @param string $subfield The subfield to retrieve ('a' = location, 'c' = date)
     *
     * @return array
     */
    protected function getPublicationInfo($subfield = 'a')
    {
        // Get string separator for publication information:
        $separator = isset($this->mainConfig->Record->marcPublicationInfoSeparator)
            ? $this->mainConfig->Record->marcPublicationInfoSeparator : ' ';

        // First check old-style 260 field:
        $results = $this->getFieldArray('260', [$subfield], true, $separator);

        // Now track down relevant RDA-style 264 fields; we only care about
        // copyright and publication places (and ignore copyright places if
        // publication places are present).  This behavior is designed to be
        // consistent with default SolrMarc handling of names/dates.
        $pubResults = $copyResults = [];

        $fields = $this->getMarcRecord()->getFields('264');
        if (is_array($fields)) {
            foreach ($fields as $currentField) {
                $currentVal = $this
                    ->getSubfieldArray($currentField, [$subfield], true, $separator);
                if (!empty($currentVal)) {
                    switch ($currentField->getIndicator('2')) {
                    case '1':
                        $pubResults = array_merge($pubResults, $currentVal);
                        break;
                    case '4':
                        $copyResults = array_merge($copyResults, $currentVal);
                        break;
                    }
                }
            }
        }
        $replace260 = isset($this->mainConfig->Record->replaceMarc260)
            ? $this->mainConfig->Record->replaceMarc260 : false;
        if (count($pubResults) > 0) {
            return $replace260 ? $pubResults : array_merge($results, $pubResults);
        } elseif (count($copyResults) > 0) {
            return $replace260 ? $copyResults : array_merge($results, $copyResults);
        }

        return $results;
    }

    /**
     * Return an array of non-empty subfield values found in the provided MARC
     * field.  If $concat is true, the array will contain either zero or one
     * entries (empty array if no subfields found, subfield values concatenated
     * together in specified order if found).  If concat is false, the array
     * will contain a separate entry for each subfield value found.
     *
     * @param object $currentField Result from File_MARC::getFields.
     * @param array  $subfields    The MARC subfield codes to read
     * @param bool   $concat       Should we concatenate subfields?
     * @param string $separator    Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getSubfieldArray($currentField, $subfields, $concat = true,
        $separator = ' '
    ) {
        // Start building a line of text for the current field
        $matches = [];

        // Loop through all subfields, collecting results that match the whitelist;
        // note that it is important to retain the original MARC order here!
        $allSubfields = $currentField->getSubfields();
        if (!empty($allSubfields)) {
            foreach ($allSubfields as $currentSubfield) {
                if (in_array($currentSubfield->getCode(), $subfields)) {
                    // Grab the current subfield value and act on it if it is
                    // non-empty:
                    $data = trim($currentSubfield->getData());
                    if (!empty($data)) {
                        $matches[] = $data;
                    }
                }
            }
        }

        // Send back the data in a different format depending on $concat mode:
        return $concat && $matches ? [implode($separator, $matches)] : $matches;
    }
}
