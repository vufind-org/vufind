<?php

/**
 * Record trait that implements common interface methods.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFindSearch\Response;

/**
 * Record trait that implements common interface methods.
 *
 * @category Search
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
trait RecordTrait
{
    /**
     * Used for identifying record source backend
     *
     * @var string
     */
    protected $sourceIdentifier = '';

    /**
     * The unique identifier for the result set.
     *
     * This property stores a UUID or similar identifier that uniquely identifies
     * the result set. It is typically set by calling the `setResultSetIdentifier` method.
     *
     * @var string|null
     */
    protected $resultSetIdentifier = null;

    /**
     * Used for identifying the search backend used to find the record
     *
     * @var string
     */
    protected $searchBackendIdentifier = '';

    /**
     * Labels for the record
     *
     * @var array
     */
    protected $labels = [];

    /**
     * Set the record source backend identifier.
     *
     * @param string $identifier Record source identifier
     *
     * @return void
     *
     * @deprecated Use setSourceIdentifiers instead
     */
    public function setSourceIdentifier($identifier)
    {
        $this->setSourceIdentifiers($identifier, $identifier);
    }

    /**
     * Set the source backend identifiers.
     *
     * @param string $recordSourceId  Record source identifier
     * @param string $searchBackendId Search backend identifier (if different from
     * $recordSourceId)
     *
     * @return void
     */
    public function setSourceIdentifiers($recordSourceId, $searchBackendId = '')
    {
        $this->sourceIdentifier = $recordSourceId;
        $this->searchBackendIdentifier = $searchBackendId ?: $recordSourceId;
    }

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier()
    {
        return $this->sourceIdentifier;
    }

    /**
     * Return the search backend identifier used to find the record.
     *
     * @return string
     */
    public function getSearchBackendIdentifier()
    {
        return $this->searchBackendIdentifier;
    }

    /**
     * Sets the unique result set identifier.
     *
     * This method assigns a UUID or similar identifier to the result set.
     *
     * @param string $uuid A valid UUID or identifier to assign to the result set.
     *
     * @return void
     */
    public function setResultSetIdentifier(string $uuid)
    {
        $this->resultSetIdentifier = $uuid;
    }

    /**
     * Retrieves the unique result set identifier.
     *
     * This method returns the UUID or similar identifier associated with the result set.
     * If no identifier has been set, it will return null.
     *
     * @return string|null The UUID of the result set, or null if not set.
     */
    public function getResultSetIdentifier(): ?string
    {
        return $this->resultSetIdentifier;
    }

    /**
     * Add a label for the record
     *
     * @param string $label Label, may be a translation key
     * @param string $class Label class
     *
     * @return void
     */
    public function addLabel(string $label, string $class)
    {
        $this->labels[] = compact('label', 'class');
    }

    /**
     * Set the labels for the record
     *
     * @param array $labels An array of associative arrays with keys 'label' and
     * 'class'
     *
     * @return void
     */
    public function setLabels(array $labels)
    {
        $this->labels = $labels;
    }

    /**
     * Return all labels for the record
     *
     * @return array An array of associative arrays with keys 'label' and 'class'
     */
    public function getLabels()
    {
        return $this->labels;
    }
}
