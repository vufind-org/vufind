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
     * number and value to filter for
     * and concatonates the subfields together and returns the fields back
     * as an array
     * (ex: ['subA subB subC', 'field2SubA field2SubB'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     * @param string $indNum   The Marc indicator to filter for
     * @param string $indValue The indicator value to check for
     *
     * @return array The values within the subfields under the field
     */
    public function getMarcFieldWithInd(
        string $field,
        ?array $subfield = null,
        string $indNum = '',
        string $indValue = ''
    ) {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $field_vals = [];
            if (trim(($marc_data['i' . $indNum] ?? '')) == $indValue) {
                $subfields = $marc_data['subfields'];
                foreach ($subfields as $subfield) {
                    $field_vals[] = $subfield['data'];
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
        return array_merge(
            $this->getMarcFieldWithInd('520', null, '1', ''),
            $this->getMarcFieldWithInd('520', null, '1', '0'),
            $this->getMarcFieldWithInd('520', null, '1', '2'),
            $this->getMarcFieldWithInd('520', null, '1', '3'),
            $this->getMarcFieldWithInd('520', null, '1', '8'),
        );
    }

    /**
     * Get the location of other archival materials notes
     *
     * @return array Note fields from the MARC record
     */
    public function getLocationOfArchivalMaterialsNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('544', null, '1', ''),
            $this->getMarcFieldWithInd('544', null, '1', '0')
        );
    }

    /**
     * Get the raw call numbers
     *
     * @return array Contents from the Solr field callnumber-raw
     */
    public function getCallNumbers()
    {
        return $this->fields['callnumber-raw'] ?? [];
    }

    /**
     * Get the topics
     *
     * @return array Topics from the MARC record
     */
    public function getTopics()
    {
        $topics = [];
        $subjects = $this->getAllSubjectHeadings();
        if (is_array($subjects)) {
            foreach ($subjects as $subj) {
                $topics[] = implode(' -- ', $subj);
            }
        }
        return $topics;
    }
}
