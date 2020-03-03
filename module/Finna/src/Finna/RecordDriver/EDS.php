<?php
/**
 * Model for EDS records.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2017.
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
namespace Finna\RecordDriver;

/**
 * Model for EDS records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class EDS extends \VuFind\RecordDriver\EDS
{
    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        // EDS is not export-friendly, but try anyway.
        return false;
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $dois = $this->getIdentifiers('doi');
        return !empty($dois) ? $dois[0] : false;
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        $titles = $this->getField(
            'RecordInfo/BibRecord/BibRelationships/IsPartOfRelationships/0'
            . '/BibEntity/Titles'
        );
        if (!$titles) {
            return '';
        }
        foreach ($titles as $title) {
            if ('main' === $title['Type']) {
                return $title['TitleFull'];
            }
        }
    }

    /**
     * Get the volume of the item that contains this record (i.e. MARC 773v of a
     * journal).
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return $this->getNumbering('volume');
    }

    /**
     * Get the issue of the item that contains this record (i.e. MARC 773l of a
     * journal).
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return $this->getNumbering('issue');
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        $pagination = $this->getField(
            'RecordInfo/BibRecord/BibEntity/PhysicalDescription/Pagination'
        );
        return $pagination['StartPage'] ?? '';
    }

    /**
     * Obtain an array or authors indicated on the record
     *
     * @return array
     */
    public function getCreators()
    {
        return $this->getItemsAuthorsArray();
    }

    /**
     * Get the abstract (summary) of the record.
     *
     * @return array
     */
    public function getHighlightedSummary()
    {
        return [$this->getItemsAbstract()];
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return (array)strtolower($this->getPubType());
    }

    /**
     * Get the full text availability of the record.
     *
     * @return bool
     */
    public function getFullTextAvailable()
    {
        return !empty($this->getPLink());
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        $isbns = array_merge(
            $this->getIdentifiers('isbn'),
            $this->getIdentifiers('isbn-print'),
            $this->getIdentifiers('isbn', true),
            $this->getIdentifiers('isbn-print', true)
        );
        return array_unique($isbns);
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $issns = array_merge(
            $this->getIdentifiers('issn'),
            $this->getIdentifiers('issn-print'),
            $this->getIdentifiers('issn', true),
            $this->getIdentifiers('issn-print', true)
        );
        return array_unique($issns);
    }

    /**
     * Get identifiers of the specified type.
     *
     * @param string $type   Identifier type
     * @param bool   $parent Whether to look for parent identifiers
     *
     * @return array
     */
    protected function getIdentifiers($type, $parent = false)
    {
        $identifiers = $this->getField(
            $parent
                ? 'RecordInfo/BibRecord/BibRelationships/IsPartOfRelationships'
                    . '/0/BibEntity/Identifiers'
                : 'RecordInfo/BibRecord/BibEntity/Identifiers'
        );
        if (!$identifiers) {
            return [];
        }

        $result = [];
        foreach ($identifiers as $identifier) {
            if ($identifier['Type'] === $type) {
                $result[] = $identifier['Value'];
            }
        }
        return $result;
    }

    /**
     * Support method for getOpenUrl() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenUrlFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if (in_array('Book', $formats)) {
            return 'Book';
        } elseif (in_array('Academic Journal', $formats)
            || in_array('Magazine', $formats)
            || in_array('Periodical', $formats)
        ) {
            return 'Article';
        } elseif (strlen($this->getCleanISSN()) > 0) {
            return 'Journal';
        } elseif (strlen($this->getCleanISBN()) > 0) {
            return 'Book';
        }
        return 'UnknownFormat';
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $dates = $this->getField(
            'RecordInfo/BibRecord/BibRelationships/IsPartOfRelationships/0'
            . '/BibEntity/Dates'
        );
        if (!$dates) {
            return [];
        }
        $results = [];
        foreach ($dates as $date) {
            if ('published' === $date['Type'] && !empty($date['Y'])) {
                $result = $date['Y'];
                if (!empty($date['M'])) {
                    $result .= '-' . $date['M'];
                }
                if (!empty($date['D'])) {
                    $result .= '-' . $date['D'];
                }
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * Get the abstract (summary) of the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return [$this->getItemsAbstract()];
    }

    /**
     * Returns an array of parameter to send to Finna's cover generator.
     * Falls back to VuFind's getThumbnail if no record image with the
     * given index was found.
     *
     * @param string $size  Size of thumbnail
     * @param int    $index Image index
     *
     * @return array|bool
     */
    public function getRecordImage($size = 'small', $index = 0)
    {
        $params = parent::getThumbnail($size);
        if ($params && !is_array($params)) {
            $params = ['url' => $params];
        }
        return $params;
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

        if ($url = $this->getPLink()) {
            $urlParts = parse_url($url);
            $retVal[] = [
                'desc' => 'View in EDS',
                'url' => $url,
                'citation' => true
            ];
        }

        if ($url = $this->getPdfLink()) {
            $urlParts = parse_url($url);
            $retVal[] = [
                'desc' => 'PDF Full Text',
                'url' => $url
            ];
        }

        if ($this->hasHTMLFullTextAvailable()) {
            $retVal[] = [
                'desc' => 'HTML Full Text',
                'url' => '#html'
            ];
        }

        $customLinks
            = array_merge($this->getFTCustomLinks(), $this->getCustomLinks());
        foreach ($customLinks as $link) {
            $retVal[] = [
                'desc' => $link['Text'] ?: $link['Name'],
                'url' => $link['Url']
            ];
        }

        return $retVal;
    }

    /**
     * Get a field from record fields with the given path
     *
     * @param string $fieldPath Slash-separated field path
     *
     * @return mixed
     */
    protected function getField($fieldPath)
    {
        $parts = explode('/', $fieldPath);
        $field = $this->fields;
        foreach ($parts as $part) {
            if (!isset($field[$part])) {
                return null;
            }
            $field = $field[$part];
        }
        return $field;
    }

    /**
     * Get a numbering field value
     *
     * @param string $type Field type
     *
     * @return string
     */
    protected function getNumbering($type)
    {
        $numbering = $this->getField(
            'RecordInfo/BibRecord/BibRelationships/IsPartOfRelationships/0'
            . '/BibEntity/Numbering'
        );
        if (!$numbering) {
            return '';
        }
        foreach ($numbering as $item) {
            if ($type === $item['Type']) {
                return $item['Value'];
            }
        }
    }
}
