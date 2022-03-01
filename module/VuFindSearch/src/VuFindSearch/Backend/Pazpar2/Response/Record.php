<?php

/**
 * Simple Pazpar2 record.
 *
 * PHP version 7
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\Pazpar2\Response;

use SimpleXMLElement;
use VuFindSearch\Response\RecordInterface;

/**
 * Simple Pazpar2 record.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Record implements RecordInterface
{
    /**
     * XML record.
     *
     * @var SimpleXMLElement
     */
    protected $xml;

    /**
     * Source identifier.
     *
     * @var string
     */
    protected $sourceIdentifier;

    /**
     * Used for identifying the search backend used to find the record
     *
     * @var string
     */
    protected $searchBackendIdentifier = 'Pazpar2';

    /**
     * Constructor.
     *
     * @param SimpleXMLElement $xml XML record
     *
     * @return void
     */
    public function __construct(SimpleXMLElement $xml)
    {
        $this->xml = $xml;
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
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
     * @param string $searchBackendId Search backend identifier
     *
     * @return void
     */
    public function setSourceIdentifiers($recordSourceId, $searchBackendId)
    {
        $this->sourceIdentifier = $recordSourceId;
        $this->searchBackendIdentifier = $searchBackendId;
    }

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier()
    {
        return $this->source;
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
     * Get the XML document representation
     *
     * @return SimpleXMLElement
     */
    public function getXML()
    {
        return $this->xml;
    }
}
