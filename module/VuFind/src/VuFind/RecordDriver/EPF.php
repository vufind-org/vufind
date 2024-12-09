<?php

/**
 * Model for EPF records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

/**
 * Model for EPF records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class EPF extends EDS
{
    /**
     * Return the unique identifier of this record within the EPF.
     *
     * @return string Unique identifier.
     */
    public function getUniqueId()
    {
        return $this->fields['Header']['PublicationId'];
    }

    /**
     * Return a URL to a thumbnail preview of the record, if available; false
     * otherwise.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string
     */
    public function getThumbnail($size = 'small')
    {
        // Override EDS parent class and get default implementation
        return DefaultRecord::getThumbnail($size);
    }

    /**
     * Get ISSNs (of containing record)
     *
     * @return array
     */
    public function getISSNs()
    {
        return $this->getFilteredIdentifiers(['issn-print', 'issn-online']);
    }

    /**
     * Get an array of ISBNs
     *
     * @return array
     */
    public function getISBNs()
    {
        return $this->getFilteredIdentifiers(['isbn-print', 'isbn-online']);
    }

    /**
     * Get the list of full text holdings for the record
     *
     * @return array
     */
    public function getFullTextHoldings()
    {
        return $this->fields['FullTextHoldings'] ?? [];
    }
}
