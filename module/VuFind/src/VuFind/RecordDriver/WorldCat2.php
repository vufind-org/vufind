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
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        $formats = [];
        foreach (['generalFormat', 'specificFormat'] as $key) {
            if (isset($this->fields['format'][$key])) {
                $formats[] = $this->fields['format'][$key];
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
            fn ($publisher) => $publisher['publicationPlace'] ?? '',
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
        return array_map(
            [$this, 'formatCreatorName'],
            array_filter(
                $this->fields['contributor']['creators'] ?? [],
                fn ($creator) => $creator['isPrimary'] ?? false
            )
        );
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
        // WorldCat v2 API doesn't have a separate short title field.
        return $this->getTitle();
    }
}
