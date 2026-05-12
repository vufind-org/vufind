<?php

/**
 * Model for MARC records in ProQuest Federated Search Gateway.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use VuFind\RecordDriver\Feature\MarcAdvancedTrait;
use VuFind\RecordDriver\Feature\MarcBasicTrait;
use VuFind\RecordDriver\Feature\MarcReaderTrait;
use VuFind\String\PropertyString;

/**
 * Model for MARC records in ProQuest Federated Search Gateway.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class ProQuestFSG extends DefaultRecord
{
    use MarcReaderTrait, MarcAdvancedTrait, MarcBasicTrait {
        MarcBasicTrait::getNewerTitles insteadof MarcAdvancedTrait;
        MarcBasicTrait::getPreviousTitles insteadof MarcAdvancedTrait;
        MarcBasicTrait::getShortTitle as marcGetShortTitle;
        MarcBasicTrait::getTitle as marcGetTitle;
        MarcAdvancedTrait::getHumanReadablePublicationDates as marcGetHumanReadablePublicationDates;
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object. In this case, $data is a MARCXML
     * document.
     *
     * @return void
     */
    public function setRawData($data)
    {
        // Ensure that $driver->setRawData($driver->getRawData()) doesn't blow up:
        if (isset($data['fullrecord'])) {
            $data = $data['fullrecord'];
        }

        // Map the ProQuestFSG response into a format that the parent Solr-based
        // record driver can understand.
        parent::setRawData(['fullrecord' => $data]);
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return PropertyString::fromHtml($this->marcGetShortTitle());
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return PropertyString::fromHtml($this->marcGetTitle());
    }

    /**
     * Get the title of the item that contains this record (i.e. MARC 773s of a
     * journal).
     *
     * @return string
     */
    public function getContainerTitle()
    {
        // For books, don't display any container.
        if ($this->isBook()) {
            return '';
        }
        return $this->getFirstFieldValue('773', ['t']);
    }

    /**
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        // For books, don't display any container.
        if ($this->isBook()) {
            return '';
        }
        return $this->getFirstFieldValue('773', ['g']);
    }

    /**
     * Try to determine if this is a book, based on ProQuest's use of MARC.
     *
     * @return bool
     */
    protected function isBook()
    {
        $type = $this->getFirstFieldValue('513', ['a']);
        return str_contains($type, 'Book') && !str_contains($type, 'Book Review');
    }

    /**
     * Get the item's source.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->getFirstFieldValue('786', ['t']);
    }

    /**
     * Get human readable publication dates for display purposes (may not be suitable
     * for computer processing -- use getPublicationDates() for that).
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        $dates = $this->marcGetHumanReadablePublicationDates();
        // For books, we should only display the year
        if ($this->isBook()) {
            foreach ($dates as $i => $date) {
                if (($pos = strpos($date, ',')) !== false) {
                    $dates[$i] = trim(substr($date, $pos + 1));
                }
            }
        }
        return $dates;
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $identifiers = $this->getFieldArray('024', ['a', '2'], true, '~');
        foreach ($identifiers as $identifier) {
            [$value, $identifierType] = explode('~', $identifier);
            if ('doi' === $identifierType) {
                return $value;
            }
        }
        return false;
    }
}
