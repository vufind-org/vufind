<?php

/**
 * Default model for GVI records -- used when a more specific model based on
 * the record_format field cannot be found.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use function array_map;
use function explode;
use function in_array;
use function str_replace;
use function strlen;
use function trim;

/**
 * Default model for GVI records -- used when a more specific model based on
 * the record_format field cannot be found.
 *
 * This should be used as the base class for all Solr-based record models.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class GVIDefault extends SolrMarc
{
    /**
     * Used for identifying search backends.
     *
     * @var string
     */
    use Feature\MarcAdvancedTrait {
        Feature\MarcAdvancedTrait::getShortTitlesAltScript as getMarcTitles;
    }

    protected $sourceIdentifier = 'GVI';

    /**
     * GVI configuration (from GVI.ini, passed as $searchSettings).
     *
     * @var object
     */
    protected $gviConfig;

    /**
     * Constructor.
     *
     * @param object $mainConfig     VuFind main configuration
     * @param object $recordConfig   Record-specific configuration
     * @param object $searchSettings GVI search configuration (GVI.ini)
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        $this->gviConfig = $searchSettings;
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }

    /**
     * Get the Hierarchy Type (false if none).
     *
     * @return string|bool
     */
    public function getHierarchyType()
    {
        return parent::getHierarchyType() ? 'gvi' : false;
    }

    /**
     * Get the full title of the record.
     *
     * Combines MARC 245 subfields a (main title), b (subtitle), n (number of
     * part/section) and p (name of part/section).
     *
     * @return string
     */
    public function getTitle()
    {
        $parts = $this->getFieldArray('245', ['a', 'b', 'n', 'p'], false);
        return implode(' ', array_filter(array_map('trim', $parts)));
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getFirstFieldValue('245', ['a']);
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->getFirstFieldValue('245', ['b']);
    }

    /**
     * Get the main authors of the record.
     *
     * Returns names from MARC 100 (personal name) and 110 (corporate name).
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        return array_values(array_filter(array_merge(
            $this->getFieldArray('100', ['a', 'b', 'c', 'd'], true),
            $this->getFieldArray('110', ['a', 'b'], true)
        )));
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthors()).
     *
     * Returns names from MARC 700 (personal name) and 710 (corporate name).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return array_values(array_filter(array_merge(
            $this->getFieldArray('700', ['a', 'b', 'c', 'd'], true),
            $this->getFieldArray('710', ['a', 'b'], true)
        )));
    }

    /**
     * Get an array of all corporate authors.
     *
     * Returns names from MARC 110 and 710.
     *
     * @return array
     */
    public function getCorporateAuthors()
    {
        return array_values(array_filter(array_merge(
            $this->getFieldArray('110', ['a', 'b'], true),
            $this->getFieldArray('710', ['a', 'b'], true)
        )));
    }

    /**
     * Get the places of publication.
     *
     * Reads from MARC 260 subfield a and 264 subfield a.
     *
     * @return array
     */
    public function getPublicationPlaces()
    {
        return $this->getPublicationInfo('a');
    }

    /**
     * Get the publishers of the record.
     *
     * Reads from MARC 264 (indicator2=1) and falls back to 260, subfield b.
     *
     * @return array
     */
    public function getPublishers()
    {
        return $this->getPublicationInfo('b');
    }

    /**
     * Get the publication dates of the record.
     *
     * Reads from MARC 264 (indicator2=1) and falls back to 260, subfield c.
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return $this->getPublicationInfo('c');
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return $this->getFirstFieldValue('250', ['a']);
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        return $this->getFieldArray('300', ['a', 'b', 'c', 'e'], true);
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * Reads from MARC 020, subfield a. Strips dashes for normalization.
     *
     * @return array
     */
    public function getISBNs()
    {
        $isbns = $this->getFieldArray('020', ['a'], false);
        foreach ($isbns as $key => $isbn) {
            $isbns[$key] = str_replace('-', '', $isbn);
        }
        return array_unique($isbns);
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * Reads from MARC 022, subfield a.
     *
     * @return array
     */
    public function getISSNs()
    {
        return array_unique($this->getFieldArray('022', ['a'], false));
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * Reads from the fixed-length field 008 (positions 35–37) and from
     * MARC 041 subfield a (excluding transliteration codes, indicator2=7).
     *
     * @return array
     */
    public function getLanguages()
    {
        $retVal = [];
        $field = $this->getMarcReader()->getField('008');
        if ($field && strlen($field) >= 38) {
            $lang = substr($field, 35, 3);
            if ($lang && trim($lang) !== '') {
                $retVal[] = $lang;
            }
        }
        $fields = $this->getMarcReader()->getFields('041');
        foreach ($fields as $field) {
            if ($field['i2'] !== '7') {
                foreach ($this->getSubfields($field, 'a') as $subfield) {
                    $retVal[] = $subfield;
                }
            }
        }
        return array_unique($retVal);
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * Reads from MARC 024 where indicator1=7 and subfield 2 equals 'doi',
     * since the GVI Solr schema does not include the doi_str_mv field.
     *
     * @return string|false
     */
    public function getCleanDOI()
    {
        $fields = $this->getMarcReader()->getFields('024');
        foreach ($fields as $field) {
            if ($field['i1'] === '7') {
                $source = strtolower(trim((string)$this->getSubfield($field, '2')));
                if ($source === 'doi' && $doi = $this->getSubfield($field, 'a')) {
                    return $doi;
                }
            }
        }
        return false;
    }

    /**
     * Get content of MARC 924 as an array of associative arrays.
     *
     * Subfield codes are mapped to descriptive keys:
     *   a => local_idn, b => isil, c => region, d => ill_indicator,
     *   g => call_number, k => url, l => url_label, z => issue.
     *
     * @return array
     */
    public function getField924(): array
    {
        $f924 = $this->getMarcReader()->getFields('924');
        $mappings = [
            'a' => 'local_idn',
            'b' => 'isil',
            'c' => 'region',
            'd' => 'ill_indicator',
            'g' => 'call_number',
            'k' => 'url',
            'l' => 'url_label',
            'z' => 'issue',
        ];
        $result = [];
        foreach ($f924 as $field) {
            $entry = [];
            foreach ($field['subfields'] ?? [] as $subfield) {
                $code = $subfield['code'];
                $data = $subfield['data'];
                if (isset($mappings[$code])) {
                    $key = $mappings[$code];
                    $entry[$key] = isset($entry[$key])
                        ? $entry[$key] . ' | ' . $data : $data;
                }
            }
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * Check whether the record has holdings at the local institution.
     *
     * Compares the ISIL in MARC 924 subfield b against the list of
     * local ISILs configured in [ILL] local_isils in GVI.ini.
     *
     * @return bool
     */
    public function hasLocalHoldings(): bool
    {
        $localIsils = $this->getLocalIsilsFromConfig();
        if (empty($localIsils)) {
            return false;
        }
        foreach ($this->getField924() as $field) {
            if (
                isset($field['isil'])
                && in_array($field['isil'], $localIsils, true)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the translation key for the local holdings message, or null
     * if the record is not locally held.
     *
     * @return ?string
     */
    public function getLocalHoldingsMessage(): ?string
    {
        return $this->hasLocalHoldings() ? 'ill_local_holdings' : null;
    }

    /**
     * Get the PPN (field 001) of the record.
     *
     * @return string
     */
    public function getPPN(): string
    {
        $f001 = $this->getMarcReader()->getField('001');
        return is_string($f001) ? $f001 : '';
    }

    /**
     * Get the first ISIL from MARC 924 subfield b.
     *
     * @return string
     */
    public function getFirstIsil(): string
    {
        foreach ($this->getField924() as $field) {
            if (isset($field['isil']) && !empty($field['isil'])) {
                return $field['isil'];
            }
        }
        return '';
    }

    /**
     * Construct the ILL form URL.
     *
     * @param string $baseUrl The base URL for the ILL form
     *
     * @return string
     */
    public function getIllUrl(string $baseUrl): string
    {
        $title = $this->getShortTitle();
        $publishers = $this->getPublishers();
        $publisher = $publishers[0] ?? '';
        $places = $this->getPublicationPlaces();
        $place = $places[0] ?? '';
        $isil = $this->getFirstIsil();
        $ppn = $this->getPPN();
        $remark = 'Via VuFind/GVI';
        if (!empty($isil) && !empty($ppn)) {
            $remark .= ' (' . $isil . ')' . $ppn;
        }

        return $baseUrl . '?' . http_build_query([
            'EOrt' => $place,
            'Verlag' => $publisher,
            'Titel' => $title,
            'Bemerkung' => $remark,
        ]);
    }

    /**
     * Read the list of local ISILs from the [ILL] section of GVI.ini.
     *
     * @return array
     */
    private function getLocalIsilsFromConfig(): array
    {
        $raw = $this->gviConfig->ILL->local_isils ?? '';
        if (empty($raw)) {
            return [];
        }
        return array_map('trim', explode(',', $raw));
    }
}
