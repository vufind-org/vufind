<?php

/**
 * Simple, schema-less SOLR record.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Solr\Response\Json;

use VuFindSearch\Response\RecordInterface;

/**
 * Simple, schema-less SOLR record.
 *
 * This record primarily serves as an example or blueprint for a schema-less
 * record. All SOLR fields are exposed via object properties.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Record implements RecordInterface
{
    /**
     * SOLR fields.
     *
     * @var array
     */
    protected $fields;

    /**
     * Source identifier.
     *
     * @var string
     */
    protected $source;

    /**
     * Constructor.
     *
     * @param array $fields SOLR document fields
     *
     * @return void
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setSourceIdentifier($identifier)
    {
        $this->source = $identifier;
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
     * __get()
     *
     * @param string $name Field name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->fields[$name]) ? $this->fields[$name] : null;
    }
}