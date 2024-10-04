<?php

/**
 * Model for MARC records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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

use function array_key_exists;
use function in_array;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrMarc extends SolrDefault
{
    use Feature\IlsAwareTrait {
        Feature\IlsAwareTrait::getURLs as getIlsURLs;
    }
    use Feature\MarcReaderTrait;
    use Feature\MarcAdvancedTrait {
        Feature\MarcAdvancedTrait::getURLs as getMarcURLs;
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
        return array_merge(
            $this->getMarcURLs(),
            $this->getIlsURLs()
        );
    }

    /**
     * Takes a Marc field that notes are stored in (ex: 950) and a list of
     * sub fields (ex: ['a','b']) optionally as well as what indicator
     * numbers and values to filter for and concatenates the subfields
     * together and returns the fields back as an array
     * (ex: ['subA subB subC', 'field2SubA field2SubB'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     * @param array  $indData  Array containing the indicator number as the key
     *                         and the value as an array of strings for the
     *                         allowed indicator values
     *                         ex: [['1' => '1', '2', '2' => '']]
     *                         would filter ind1 = 1 or 2 and ind2 = blank
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
            // Check if that field has either indicator (MARC only has up to 2 indicators)
            foreach (range(1, 2) as $indNum) {
                if (array_key_exists($indNum, $indData)) {
                    if (in_array(trim(($marc_data['i' . $indNum] ?? '')), $indData[$indNum])) {
                        $subfields = $marc_data['subfields'];
                        foreach ($subfields as $subfield) {
                            $field_vals[] = $subfield['data'];
                        }
                    }
                }
            }
            if (!empty($field_vals)) {
                $vals[] = implode(' ', $field_vals);
            }
        }
        return array_unique($vals);
    }

    /**
     * Get the abstract and summary notes
     *
     * @return array Note fields from the MARC record
     */
    public function getAbstractAndSummaryNotes()
    {
        return $this->getMarcFieldWithInd('520', null, [1 => ['', '0', '2', '3', '8']]);
    }

    /**
     * Get the location of other archival materials notes
     *
     * @return array Note fields from the MARC record
     */
    public function getLocationOfArchivalMaterialsNotes()
    {
        return $this->getMarcFieldWithInd('544', null, [1 => ['', '0']]);
    }
}
