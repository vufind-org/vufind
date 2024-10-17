<?php

/**
 * Record interface file.
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
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFindSearch\Response;

/**
 * Record interface.
 *
 * Every record must implement this.
 *
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
interface RecordInterface
{
    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     *
     * @deprecated Use setSourceIdentifiers instead
     */
    public function setSourceIdentifier($identifier);

    /**
     * Set the source backend identifiers.
     *
     * @param string $recordSourceId  Record source identifier
     * @param string $searchBackendId Search backend identifier (if different from
     * $recordSourceId)
     *
     * @return void
     */
    public function setSourceIdentifiers($recordSourceId, $searchBackendId = '');

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier();

    /**
     * Return the search backend identifier used to find the record.
     *
     * @return string
     */
    public function getSearchBackendIdentifier();

    /**
     * Sets the result set identifier for the record collection.
     *
     * @param string $uuid A valid UUID associated with the data set.
     *
     * @return void
     */
    public function setResultSetIdentifier(string $uuid);

    /**
     * Retrieves the unique result set identifier.
     *
     * This method returns the UUID or similar identifier associated with the result set.
     * If no identifier has been set, it will return null.
     *
     * @return string|null The UUID of the result set, or null if not set.
     */
    public function getResultSetIdentifier();

    /**
     * Add a label for the record
     *
     * @param string $label Label, may be a translation key
     * @param string $class Label class
     *
     * @return void
     */
    public function addLabel(string $label, string $class);

    /**
     * Set the labels for the record
     *
     * @param array $labels An array of associative arrays with keys 'label' and
     * 'class'
     *
     * @return void
     */
    public function setLabels(array $labels);

    /**
     * Return all labels for the record
     *
     * @return array An array of associative arrays with keys 'label' and 'class'
     */
    public function getLabels();
}
