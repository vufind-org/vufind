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
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for EAC-CPF records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrAuthEacCpf extends SolrAuthDefault
{
    use SolrAuthFinnaTrait;
    use XmlReaderTrait;

    /**
     * Get authority title
     *
     * @return string|null
     */
    public function getTitle()
    {
        $record = $this->getXmlRecord();
        return isset($record->cpfDescription->identity->nameEntry->part[0])
            ? (string)$record->cpfDescription->identity->nameEntry->part[0]
            : null;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @return array
     */
    public function getAlternativeTitles()
    {
        $titles = [];
        $path = 'cpfDescription/identity/nameEntryParallel/nameEntry';
        foreach ($this->getXmlRecord()->xpath($path) as $name) {
            $titles[] = $name->part[0];
        }
        return $titles;
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
        return $this->formatDate($this->fields['birth_date'] ?? '');
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
        return $this->formatDate($this->fields['death_date'] ?? '');
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
            if ($attr->placeEntry
                && !$attr->lang || in_array((string)$attr->lang, $languages)
            ) {
                $result[] = [
                    'data' => (string)$place->placeEntry,
                    'detail' => (string)$place->placeRole
                ];
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
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
                return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
            } elseif (preg_match('/^(\d{4})$/', $date)) {
                return $this->dateConverter->convertFromDisplayDate(
                    'Y',
                    $this->dateConverter->convertToDisplayDate('Y', $date)
                );
            } else {
                return $date;
            }
        } catch (\Exception $e) {
            return $date;
        }
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
