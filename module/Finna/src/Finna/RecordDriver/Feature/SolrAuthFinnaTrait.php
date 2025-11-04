<?php

/**
 * Additional functionality for Finna SolrAuth records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver\Feature;

/**
 * Additional functionality for Finna SolrAuth records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SolrAuthFinnaTrait
{
    /**
     * Return record format.
     *
     * @deprecated Use getRecordFormat()
     *
     * @return string
     */
    public function getRecordType()
    {
        return $this->getRecordFormat();
    }

    /**
     * Return record format.
     *
     * @return string
     */
    public function getRecordFormat()
    {
        return $this->fields['record_format'] ?? '';
    }

    /**
     * Return corporate record type.
     *
     * @return string
     */
    public function getCorporateType()
    {
        return '';
    }

    /**
     * Get additional identifiers (isni etc).
     *
     * @return array
     */
    public function getOtherIdentifiers()
    {
        return [];
    }

    /**
     * Is this an authority index record?
     *
     * @return bool
     */
    public function isAuthorityRecord()
    {
        return true;
    }

    /**
     * Return birth date.
     *
     * @param bool $force Return established date for corporations?
     *
     * @return string
     */
    public function getBirthDate($force = false)
    {
        return '';
    }

    /**
     * Return birth place.
     *
     * @param bool $force Return established date for corporations?
     *
     * @return string
     */
    public function getBirthPlace($force = false)
    {
        return $this->fields['birth_place'] ?? '';
    }

    /**
     * Return death date.
     *
     * @param bool $force Return terminated date for corporations?
     *
     * @return string
     */
    public function getDeathDate($force = false)
    {
        return '';
    }

    /**
     * Return death place.
     *
     * @param bool $force Return terminated date for corporations?
     *
     * @return string
     */
    public function getDeathPlace($force = false)
    {
        return $this->fields['death_place'] ?? '';
    }

    /**
     * Return birth place and date.
     *
     * @param boolean $force Return established date for corporations?
     *
     * @return array
     */
    public function getBirthDateAndPlace($force = false)
    {
        return [
            'data' => $this->getBirthDate($force),
            'detail' => $this->getBirthPlace(),
        ];
    }

    /**
     * Return death place and date.
     *
     * @param boolean $force Return established date for corporations?
     *
     * @return array
     */
    public function getDeathDateAndPlace($force = false)
    {
        return [
            'data' => $this->getDeathDate($force),
            'detail' => $this->getDeathPlace(),
        ];
    }

    /**
     * Return corporation establishment date and place.
     *
     * @return string
     */
    public function getEstablishedDate()
    {
        return '';
    }

    /**
     * Return corporation termination date and place.
     *
     * @return string
     */
    public function getTerminatedDate()
    {
        return '';
    }

    /**
     * Return awards.
     *
     * @return string[]
     */
    public function getAwards()
    {
        return [];
    }

    /**
     * Return associated place.
     *
     * @return string
     */
    public function getAssociatedPlace()
    {
        return '';
    }

    /**
     * Return related places.
     *
     * @return array
     */
    public function getRelatedPlaces()
    {
        return array_map(
            function ($place) {
                return ['data' => $place];
            },
            $this->fields['related_place'] ?? []
        );
    }

    /**
     * Return summary
     *
     * @return array
     */
    public function getSummary()
    {
        return [];
    }

    /**
     * Return historical information.
     *
     * @return array|null
     */
    public function getHistory()
    {
        return null;
    }

    /**
     * Return description (for backward compatibility)
     *
     * @return string|null
     */
    public function getDescription()
    {
        return $this->getSummary();
    }

    /**
     * Return authority data sources
     *
     * @return array|null
     */
    public function getSources()
    {
        return null;
    }

    /**
     * Return fields of activity.
     *
     * @return array
     */
    public function getFieldsOfActivity()
    {
        return $this->fields['field_of_activity'] ?? [];
    }

    /**
     * Return occupations.
     *
     * @return array
     */
    public function getOccupations()
    {
        return $this->fields['occupation'] ?? [];
    }

    /**
     * Return relations to other authority records.
     *
     * @return array
     */
    public function getRelations()
    {
        return [];
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return [$this->fields['record_type']];
    }

    /**
     * Get data source id
     *
     * @return string
     */
    public function getDataSource()
    {
        return isset($this->fields['datasource_str_mv'])
            ? ((array)$this->fields['datasource_str_mv'])[0]
            : '';
    }

    /**
     * Get the institutions holding the record.
     *
     * @return array
     */
    public function getInstitutions()
    {
        return $this->fields['institution'] ?? [];
    }

    /**
     * Return additional information.
     *
     * @return string
     */
    public function getAdditionalInformation()
    {
        return '';
    }

    /**
     * Get organisation info ID (Kirjastohakemisto Finna ID).
     *
     * @return string
     */
    public function getOrganisationInfoId()
    {
        if ($institutions = $this->getInstitutions()) {
            return $institutions[0];
        }
        return null;
    }

    /**
     * Get an array of related publications for the record.
     *
     * @return array
     */
    public function getRelatedPublications()
    {
        return [];
    }

    /**
     * Is this a Person authority record?
     *
     * @return boolean
     */
    public function isPerson()
    {
        return $this->fields['record_type'] === 'Personal Name';
    }

    /**
     * Checks the current record if it's supported for generating OpenURLs.
     *
     * @return bool
     */
    public function supportsOpenUrl()
    {
        return false;
    }

    /**
     * Get online URLs
     *
     * @param bool $raw Whether to return raw data
     *
     * @return array
     */
    public function getOnlineURLs($raw = false)
    {
        return [];
    }

    /**
     * Is rating allowed.
     *
     * @return bool
     */
    public function isRatingAllowed(): bool
    {
        return false;
    }
}
