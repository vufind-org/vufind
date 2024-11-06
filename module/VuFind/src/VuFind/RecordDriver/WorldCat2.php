<?php

/**
 * Model for WorldCat v2 records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

use function count;

/**
 * Model for WorldCat v2 records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class WorldCat2 extends DefaultRecord
{
    /**
     * Return the unique identifier of this record within the index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        if (!isset($this->fields['identifier']['oclcNumber'])) {
            throw new \Exception('ID not set!');
        }
        return $this->fields['identifier']['oclcNumber'];
    }

    /**
     * Get the call numbers associated with the record (empty array if none).
     *
     * @return array
     */
    public function getCallNumbers()
    {
        $retVal = [];
        foreach (['lc', 'dewey'] as $type) {
            $retVal = array_merge($retVal, (array)($this->fields['classification'][$type] ?? []));
        }
        return $retVal;
    }

    /**
     * Get the Dewey call number associated with this record (empty string if none).
     *
     * @return string
     */
    public function getDeweyCallNumber()
    {
        return ((array)($this->fields['classification']['dewey'] ?? []))[0] ?? '';
    }

    /**
     * Get a raw, unnormalized LCCN. (See DefaultRecord::getLCCN for normalization).
     *
     * @return string
     */
    protected function getRawLCCN()
    {
        return ((array)($this->fields['classification']['lc'] ?? []))[0] ?? '';
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        $formats = [];
        foreach (['generalFormat', 'specificFormat'] as $key) {
            if (isset($this->fields['format'][$key])) {
                $formats[] = $this->translate(
                    'WorldCatFormats::' . strtolower($this->fields['format'][$key]),
                    default: $this->fields['format'][$key]
                );
            }
        }
        return $formats;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        return (array)($this->fields['identifier']['isbns'] ?? []);
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        return (array)($this->fields['identifier']['issns'] ?? []);
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        return (array)($this->fields['language']['itemLanguage'] ?? []);
    }

    /**
     * Get the OCLC number of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        return array_merge(
            [$this->getUniqueID()],
            $this->fields['identifier']['mergedOclcNumbers'] ?? []
        );
    }

    /**
     * Get the item's place of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        return array_map(
            fn ($publisher) => ($publisher['publicationPlace'] ?? '') . ' :',
            $this->fields['publishers'] ?? []
        );
    }

    /**
     * Convert an author array into a string.
     *
     * @param array $data Author data
     *
     * @return string
     */
    protected function formatCreatorName(array $data): string
    {
        if (!empty($data['nonPersonName']['text'])) {
            return $data['nonPersonName']['text'];
        }
        return implode(
            ', ',
            array_filter(
                array_merge(
                    [
                        $data['secondName']['text'] ?? null,
                        $data['firstName']['text'] ?? null,
                    ],
                    $data['creatorNotes'] ?? []
                )
            )
        );
    }

    /**
     * Get the main authors of the record.
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        return array_values(
            array_map(
                [$this, 'formatCreatorName'],
                array_filter(
                    $this->fields['contributor']['creators'] ?? [],
                    fn ($creator) => ($creator['isPrimary'] ?? false) && ($creator['type'] ?? '') !== 'corporation'
                )
            )
        );
    }

    /**
     * Get the secondary authors of the record.
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return array_values(
            array_map(
                [$this, 'formatCreatorName'],
                array_filter(
                    $this->fields['contributor']['creators'] ?? [],
                    fn ($creator) => !($creator['isPrimary'] ?? false) && ($creator['type'] ?? '') !== 'corporation'
                )
            )
        );
    }

    /**
     * Get an array of all corporate authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getCorporateAuthors()
    {
        return array_map(
            [$this, 'formatCreatorName'],
            array_filter(
                $this->fields['contributor']['creators'] ?? [],
                fn ($creator) => ($creator['type'] ?? '') === 'corporation'
            )
        );
    }

    /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal). Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
        return (array)($this->fields['date']['publicationSequentialDesignationDate'] ?? []);
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return (array)($this->fields['date']['machineReadableDate'] ?? []);
    }

    /**
     * Get human readable publication dates for display purposes (may not be suitable
     * for computer processing -- use getPublicationDates() for that).
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        return (array)($this->fields['date']['publicationDate'] ?? []);
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return array_map(
            fn ($publisher) => $publisher['publisherName']['text'] ?? '',
            $this->fields['publishers'] ?? []
        );
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        return array_filter(
            array_map(
                fn ($entry) => $entry['relatedItemTitle'],
                (array)($this->fields['related']['succeedingEntries'] ?? [])
            )
        );
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        return array_filter(
            array_map(
                fn ($entry) => $entry['relatedItemTitle'],
                (array)($this->fields['related']['precedingEntries'] ?? [])
            )
        );
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return array_map(
            fn ($summary) => $summary['text'] ?? '',
            $this->fields['description']['summaries'] ?? []
        );
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $full = $this->fields['title']['mainTitles'][0]['text'] ?? '';
        $parts = explode(' / ', $full);
        return $parts[0];
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        $parts = explode(':', $this->getTitle(), 2);
        return trim($parts[0] . (count($parts) > 1 ? ':' : ''));
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        $parts = explode(':', $this->getTitle(), 2);
        return trim($parts[1] ?? '');
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return ((array)($this->fields['edition']['statement'] ?? []))[0] ?? '';
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        return ((array)($this->fields['description']['physicalDescription'] ?? []))[0] ?? '';
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
        // Get all the unique subject strings:
        $values = array_unique(
            array_map(
                fn ($subject) => $subject['subjectName']['text'] ?? '',
                $this->fields['subjects'] ?? []
            )
        );
        // Now convert to the expected format:
        return array_map(
            fn ($value) => [$value],
            array_values(array_filter($values))
        );
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        return (array)($this->fields['note']['awardNote'] ?? []);
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return array_filter(
            array_map(
                fn ($note) => $note['text'],
                array_filter(
                    (array)($this->fields['note']['generalNotes'] ?? []),
                    fn ($note) => $note['local'] === 'N'
                )
            )
        );
    }

    /**
     * Get notes on bibliography content.
     *
     * @return array
     */
    public function getBibliographyNotes()
    {
        return array_filter(
            array_map(
                fn ($note) => $note['text'],
                (array)($this->fields['description']['bibliographies'] ?? [])
            )
        );
    }

    /**
     * Get credits of people involved in production of the item.
     *
     * @return array
     */
    public function getProductionCredits()
    {
        return (array)($this->fields['note']['creditNotes'] ?? []);
    }

    /**
     * Get an array of publication frequency information.
     *
     * @return array
     */
    public function getPublicationFrequency()
    {
        return (array)($this->fields['date']['currentPublicationFrequency'] ?? []);
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
        $separator = '|||||';
        $raw = array_map(
            fn ($series) => ($series['seriesTitle'] ?? '') . $separator . ($series['volume'] ?? ''),
            (array)($this->fields['title']['seriesTitles'] ?? [])
        );
        return array_map(
            function ($series) use ($separator) {
                [$name, $number] = explode($separator, $series);
                return compact('name', 'number');
            },
            array_unique($raw)
        );
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        return array_map(
            fn ($toc) => $toc['contentNote']['text'] ?? '',
            (array)($this->fields['description']['contents'] ?? [])
        );
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
        if (!($this->recordConfig->Record->show_urls ?? false)) {
            return [];
        }
        $raw = array_map(
            fn ($loc) => $loc['uri'],
            (array)($this->fields['digitalAccessAndLocations'] ?? [])
        );
        $retVal = [];
        foreach ($raw as $current) {
            preg_match_all('|https?://[^ ]+|', $current, $matches);
            $retVal = array_merge($retVal, $matches[0] ?? []);
        }
        return array_map(
            fn ($url) => compact('url'),
            $retVal
        );
    }
}
