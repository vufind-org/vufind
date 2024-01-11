<?php

/**
 * Functions for reading MARC records.
 *
 * PHP version 8
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

namespace VuFind\RecordDriver\Feature;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

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
     * MARC reader class to use.
     *
     * @var string
     */
    protected $marcReaderClass = \VuFind\Marc\MarcReader::class;

    /**
     * MARC reader. Access only via getMarcReader() as this is initialized lazily.
     */
    protected $lazyMarcReader = null;

    /**
     * Retrieve the raw MARC data for this record; note that format may vary
     * depending on what was indexed (e.g. XML vs. binary MARC).
     *
     * @return string
     */
    public function getRawMarcData()
    {
        // Set preferred MARC field from config or default, if it's not existing
        $preferredMarcFields = $this->mainConfig->Record->preferredMarcFields
            ?? 'fullrecord';
        $preferredMarcFieldArray = explode(',', $preferredMarcFields);
        $preferredMarcField = 'fullrecord';
        foreach ($preferredMarcFieldArray as $testField) {
            if (array_key_exists($testField, $this->fields)) {
                $preferredMarcField = $testField;
                break;
            }
        }
        if (empty($this->fields[$preferredMarcField])) {
            throw new \Exception('Missing MARC data in record ' . $this->getUniqueId());
        }
        return trim($this->fields[$preferredMarcField]);
    }

    /**
     * Get access to the MarcReader object.
     *
     * @return \VuFind\Marc\MarcReader
     */
    public function getMarcReader()
    {
        if (null === $this->lazyMarcReader) {
            $this->lazyMarcReader = new $this->marcReaderClass(
                $this->getRawMarcData()
            );
        }

        return $this->lazyMarcReader;
    }

    /**
     * Return an array of all values extracted from the specified field/subfield
     * combination. If multiple subfields are specified and $concat is true, they
     * will be concatenated together in the order listed -- each entry in the array
     * will correspond with a single MARC field. If $concat is false, the return
     * array will contain separate entries for separate subfields.
     *
     * @param string $field     The MARC field number to read
     * @param array  $subfields The MARC subfield codes to read
     * @param bool   $concat    Should we concatenate subfields?
     * @param string $separator Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getFieldArray(
        $field,
        $subfields = null,
        $concat = true,
        $separator = ' '
    ) {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = ['a'];
        }
        return $this->getMarcReader()->getFieldsSubfields(
            $field,
            $subfields,
            $concat ? $separator : null
        );
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
        return $matches[0] ?? '';
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
        $separator = $this->mainConfig->Record->marcPublicationInfoSeparator ?? ' ';

        // First check old-style 260 field:
        $results = $this->getFieldArray('260', [$subfield], true, $separator);

        // Now track down relevant RDA-style 264 fields; we only care about
        // copyright and publication places (and ignore copyright places if
        // publication places are present). This behavior is designed to be
        // consistent with default SolrMarc handling of names/dates.
        $pubResults = $copyResults = [];

        $fields = $this->getMarcReader()->getFields('264', [$subfield]);
        foreach ($fields as $currentField) {
            $currentVal = $this
                ->getSubfieldArray($currentField, [$subfield], true, $separator);
            if (!empty($currentVal)) {
                switch ($currentField['i2']) {
                    case '1':
                        $pubResults = array_merge($pubResults, $currentVal);
                        break;
                    case '4':
                        $copyResults = array_merge($copyResults, $currentVal);
                        break;
                }
            }
        }
        $replace260 = $this->mainConfig->Record->replaceMarc260 ?? false;
        if (count($pubResults) > 0) {
            return $replace260 ? $pubResults : array_merge($results, $pubResults);
        } elseif (count($copyResults) > 0) {
            return $replace260 ? $copyResults : array_merge($results, $copyResults);
        }

        return $results;
    }

    /**
     * Return first subfield with the given code in the provided MARC field
     *
     * @param array  $field    Result from MarcReader::getFields
     * @param string $subfield The MARC subfield code to get
     *
     * @return string
     */
    protected function getSubfield($field, $subfield)
    {
        return $this->getMarcReader()->getSubfield($field, $subfield);
    }

    /**
     * Return all subfields with the given code in the provided MARC field
     *
     * @param array  $field    Result from MarcReader::getFields
     * @param string $subfield The MARC subfield code to get
     *
     * @return array
     */
    protected function getSubfields($field, $subfield)
    {
        return $this->getMarcReader()->getSubfields($field, $subfield);
    }

    /**
     * Return an array of non-empty subfield values found in the provided MARC
     * field. If $concat is true, the array will contain either zero or one
     * entries (empty array if no subfields found, subfield values concatenated
     * together in specified order if found). If concat is false, the array
     * will contain a separate entry for each subfield value found.
     *
     * @param array  $currentField Result from MarcReader::getFields
     * @param array  $subfields    The MARC subfield codes to read
     * @param bool   $concat       Should we concatenate subfields?
     * @param string $separator    Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getSubfieldArray(
        $currentField,
        $subfields,
        $concat = true,
        $separator = ' '
    ) {
        // Start building a line of text for the current field
        $matches = [];

        // Loop through all subfields, collecting results that match the filter;
        // note that it is important to retain the original MARC order here!
        foreach ($currentField['subfields'] as $currentSubfield) {
            if (in_array($currentSubfield['code'], $subfields)) {
                // Grab the current subfield value and act on it if it is non-empty:
                $data = trim($currentSubfield['data']);
                if (!empty($data)) {
                    $matches[] = $data;
                }
            }
        }

        // Send back the data in a different format depending on $concat mode:
        return $concat && $matches ? [implode($separator, $matches)] : $matches;
    }
}
