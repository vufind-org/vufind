<?php

/**
 * Model for Primo Central records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use function in_array;

/**
 * Model for Primo Central records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class Primo extends DefaultRecord
{
    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getTitle();
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->fields['title'] ?? '';
    }

    /**
     * Get a highlighted title string, if available.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return $this->fields['highlightDetails']['title'][0] ?? '';
    }

    /**
     * Get the main authors of the record.
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        return isset($this->fields['creator'])
            ? array_map('trim', $this->fields['creator']) : [];
    }

    /**
     * Get the authors of the record.
     *
     * @return array
     */
    public function getCreators()
    {
        return $this->fields['creator'] ?? [];
    }

    /**
     * Get an array of all subject headings associated with the record
     * (may be empty).
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
        $base = $this->fields['subjects'] ?? [];
        $callback = function ($str) use ($extended) {
            $s = array_map('trim', explode(' -- ', $str));
            return $extended
                ? ['heading' => $s, 'type' => '', 'source' => '']
                : $s;
        };
        return array_map($callback, $base);
    }

    /**
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        $parts = explode(',', $this->getIsPartOf(), 2);
        return isset($parts[1]) ? trim($parts[1]) : '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        return $this->fields['container_end_page'] ?? '';
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        // Convert to displayable words and return as an array:
        return array_map(
            function ($s) {
                return ucwords(str_replace('_', ' ', $s));
            },
            (array)($this->fields['format'])
        );
    }

    /**
     * Get the item's "is part of".
     *
     * @return string
     */
    public function getIsPartOf()
    {
        return $this->fields['ispartof'] ?? '';
    }

    /**
     * Get the item's description.
     *
     * @return array
     */
    public function getDescription()
    {
        return $this->fields['description'] ?? [];
    }

    /**
     * Get the item's source.
     *
     * @return array
     */
    public function getSource()
    {
        $base = $this->fields['source'] ?? '';
        // Trim off unwanted image and any other tags:
        return strip_tags($base);
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $issns = [];
        if (isset($this->fields['issn'])) {
            $issns = $this->fields['issn'];
        }
        return $issns;
    }

    /**
     * Get the language associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        return isset($this->fields['language'])
            ? (array)$this->fields['language'] : [];
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small')
    {
        if ($isbn = $this->getCleanISBN()) {
            return ['size' => $size, 'isn' => $isbn];
        }
        return ['size' => $size, 'contenttype' => 'JournalArticle'];
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

        if (isset($this->fields['url'])) {
            $retVal[] = [];
            $retVal[0]['url'] = $this->fields['url'];
            if (isset($this->fields['fulltext'])) {
                $desc = $this->fields['fulltext'] == 'fulltext'
                    ? 'Get full text' : 'Request full text';
                $retVal[0]['desc'] = $this->translate($desc);
            }
        }

        return $retVal;
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        return $this->fields['recordid'];
    }

    /**
     * Get citation references
     *
     * @return array
     */
    public function getCitations(): array
    {
        return [
            'cites' => $this->fields['cites'] ?? [],
            'cited_by' => $this->fields['cited_by'] ?? [],
        ];
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none). For possible legal values,
     * see /application/themes/root/helpers/Citation.php, getCitation()
     * method.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        return [];
    }

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
        // Support export for EndNote and RefWorks
        return !in_array($format, ['EndNote', 'RefWorks']);
    }
}
