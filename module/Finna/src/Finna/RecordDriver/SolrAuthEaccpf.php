<?php

/**
 * Model for EAC-CPF records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use function in_array;

/**
 * Model for EAC-CPF records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrAuthEaccpf extends SolrAuthDefault
{
    use Feature\SolrAuthFinnaTrait {
        getOccupations as _getOccupations;
    }
    use Feature\FinnaXmlReaderTrait;

    /**
     * Get authority title
     *
     * @return string
     */
    public function getTitle()
    {
        $firstTitle = '';
        $record = $this->getXmlRecord();
        if (isset($record->cpfDescription->identity->nameEntry)) {
            $languages = $this->mapLanguageCode($this->getLocale());
            $name = $record->cpfDescription->identity->nameEntry;
            if (!isset($name->part)) {
                return '';
            }
            $lang = (string)$name->attributes()->lang;
            foreach ($name->part as $part) {
                if ($title = (string)$part) {
                    if (!$firstTitle) {
                        $firstTitle = $title;
                    }
                    if ($lang && in_array($lang, $languages)) {
                        return $title;
                    }
                }
            }
        }
        return $firstTitle;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $titles = [];
        $dates = [];
        $path = 'cpfDescription/identity/nameEntryParallel';
        foreach ($this->getXmlRecord()->xpath($path) as $name) {
            $title = '';
            foreach ($name->nameEntry->part ?? [] as $part) {
                $localType = (string)$part->attributes()->localType;
                if ($localType === 'http://rdaregistry.info/Elements/a/P50103') {
                    $title = (string)$part;
                }
            }
            if (!empty($name->useDates)) {
                $dates = $this->parseDates($name->useDates);
            }
            $titles[] = ['data' => $title, 'detail' => implode(', ', $dates)];
        }
        return $titles;
    }

    /**
     * Get dates from either date or dateRange elements
     *
     * @param \SimpleXmlElement $dateElement date element
     *
     * @return array array of dates
     */
    public function parseDates($dateElement)
    {
        $dates = [];
        foreach ($dateElement->dateRange ?? [] as $range) {
            $fromDate = $range->fromDate->attributes()->standardDate
                ?? $range->fromDate
                ?? '';
            $fromDate = $this->formatDate((string)($fromDate));
            $toDate = $range->toDate->attributes()->standardDate
                ?? $range->toDate
                ?? '';
            $toDate = $this->formatDate((string)($toDate));
            $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
            if ($fromDate && $toDate) {
                $dates[] = "$fromDate $ndash $toDate";
            }
        }
        foreach ($dateElement->date ?? [] as $dateEl) {
            if (!empty($dateEl->attributes()->standardDate)) {
                $dates[] = $this->formatDate(
                    (string)$dateEl->attributes()->standardDate
                );
            } elseif ($d = $this->formatDate((string)$dateEl)) {
                $dates[] = $d;
            }
        }
        if (!empty($dateElement->dateSet)) {
            $dates = array_merge($dates, $this->parseDates($dateElement->dateSet));
        }
        return $dates;
    }

    /**
     * Return description
     *
     * @return array|null
     */
    public function getSummary()
    {
        $record = $this->getXmlRecord();
        if (isset($record->cpfDescription->description->biogHist->p)) {
            return [(string)$record->cpfDescription->description->biogHist->p];
        }
        return null;
    }

    /**
     * Return birth date.
     *
     * @param boolean $force Return established date for corporations?
     *
     * @return string
     */
    public function getBirthDate($force = false)
    {
        if (!$this->isPerson() && !$force) {
            return '';
        }
        return $this->formatDate(
            $this->getExistDate('http://rdaregistry.info/Elements/a/P50121') ?? ''
        );
    }

    /**
     * Return death date.
     *
     * @param boolean $force Return terminated date for corporations?
     *
     * @return string
     */
    public function getDeathDate($force = false)
    {
        if (!$this->isPerson() && !$force) {
            return '';
        }
        return $this->formatDate(
            $this->getExistDate('http://rdaregistry.info/Elements/a/P50120') ?? ''
        );
    }

    /**
     * Return exist date
     *
     * @param string $localType localType attribute
     *
     * @return null|string
     */
    protected function getExistDate(string $localType): ?string
    {
        $record = $this->getXmlRecord();
        foreach ($record->cpfDescription->description->existDates->dateSet->date ?? [] as $date) {
            $attrs = $date->attributes();
            $type = (string)$attrs->localType;
            if ($localType === $type) {
                return (string)$attrs->standardDate;
            }
        }
        return null;
    }

    /**
     * Get related places
     *
     * @return array
     */
    public function getRelatedPlaces()
    {
        $record = $this->getXmlRecord();
        if (!isset($record->cpfDescription->description->places->place)) {
            return '';
        }
        $result = [];
        $languages = $this->mapLanguageCode($this->getLocale());
        foreach ($record->cpfDescription->description->places->place as $place) {
            $attr = $place->attributes();
            if ($attr->placeEntry && !$attr->lang || in_array((string)$attr->lang, $languages)) {
                $result[] = [
                    'data' => (string)$place->placeEntry,
                    'detail' => (string)$place->placeRole,
                ];
            }
        }
        return $result;
    }

    /**
     * Return relations to other authority records.
     *
     * @return array
     */
    public function getRelations()
    {
        $record = $this->getXmlRecord();
        $result = [];
        $sourceId = $this->getDataSource();

        foreach ($record->cpfDescription->relations->cpfRelation ?? [] as $relation) {
            $attr = $relation->attributes();
            $id = (string)$attr->href;
            $name = (string)$relation->relationEntry;
            if ($id && $name) {
                $result[] = [
                    'id' => "$sourceId.$id",
                    'name' => $name,
                    'role' => (string)$attr->title,
                ];
            }
        }

        return $result;
    }

    /**
     * Return occupations.
     *
     * @return array
     */
    public function getOccupations()
    {
        $result = [];
        $record = $this->getXmlRecord();
        if (isset($record->cpfDescription->description->occupations)) {
            $languages = $this->mapLanguageCode($this->getLocale());
            foreach ($record->cpfDescription->description->occupations as $occupations) {
                if (!isset($occupations->occupation)) {
                    continue;
                }
                foreach ($occupations->occupation as $occupation) {
                    if (!isset($occupation->term)) {
                        continue;
                    }
                    $term = $occupation->term;
                    $attr = $term->attributes();
                    if ($attr->lang && in_array((string)$attr->lang, $languages)) {
                        $result[] = (string)$term;
                    }
                }
            }
        }
        return $result ?: $this->_getOccupations();
    }

    /**
     * Return sources
     *
     * @return array
     */
    public function getSources()
    {
        $result = [];
        $record = $this->getXmlRecord();
        if (isset($record->control->sources)) {
            $languages = $this->mapLanguageCode($this->getLocale());
            foreach ($record->control->sources->source as $source) {
                if (isset($source->sourceEntry)) {
                    $title = '';
                    foreach ($source->sourceEntry as $entry) {
                        if (in_array($entry->attributes()->lang, $languages)) {
                            $title = (string)$entry;
                        }
                    }
                    $result[] = [
                        'title' => $title ? $title : (string)$source->sourceEntry,
                        'url' => (string)($source->attributes()->href ?? ''),
                        'subtitle' => '',
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Get an array of related publications for the record.
     *
     * @return array
     */
    public function getRelatedPublications()
    {
        $result = [];
        $record = $this->getXmlRecord();
        foreach ($record->cpfDescription->description->localDescriptions->localDescription ?? [] as $description) {
            $type = $description->attributes()->localType ?? '';
            if ($type != 'TJ17') {
                continue;
            }
            foreach ($description->citation ?? [] as $citation) {
                if ($title = trim((string)$citation ?? '')) {
                    $result[] = [
                        'title' => $title,
                        'searchTitle' => '',
                        'label' => '',
                        'url' => (string)($citation->attributes()->href ?? ''),
                        'isbn' => '',
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Set preferred language for display strings.
     *
     * @param string $language Language
     *
     * @return void
     */
    public function setPreferredLanguage($language)
    {
    }

    /**
     * Format date
     *
     * @param string $date Date
     *
     * @return string
     */
    protected function formatDate($date)
    {
        if (!$this->dateConverter) {
            return $date;
        }
        try {
            if (preg_match('/^(unknown|open)/', $date)) {
                return '';
            }
            // Handle date formats like 1977-uu-uu
            if (preg_match('/^(\d{4})-([a-z]{2})-([a-z]{2})$/', $date, $matches)) {
                return $this->dateConverter->convertFromDisplayDate(
                    'Y',
                    $this->dateConverter->convertToDisplayDate('Y', $matches[1])
                );
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
                return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
            } elseif (preg_match('/^(\d{4})$/', $date)) {
                return $this->dateConverter->convertFromDisplayDate(
                    'Y',
                    $this->dateConverter->convertToDisplayDate('Y', $date)
                );
            }
        } catch (\Exception $e) {
        }
        return $this->translate(ucfirst($date), [], $date);
    }

    /**
     * Convert Finna language codes to EAD3 codes.
     *
     * @param string $languageCode Language code
     *
     * @return string[]
     */
    protected function mapLanguageCode($languageCode)
    {
        $langMap
            = ['fi' => ['fi','fin'], 'sv' => ['sv','swe'], 'en' => ['en','eng']];
        return $langMap[$languageCode] ?? [$languageCode];
    }
}
