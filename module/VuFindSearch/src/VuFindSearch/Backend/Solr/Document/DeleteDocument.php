<?php

/**
 * SOLR delete document class.
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
namespace VuFindSearch\Backend\Solr\Document;

use XMLWriter;

/**
 * SOLR delete document class.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class DeleteDocument extends AbstractDocument
{
    /**
     * Unique keys to delete.
     *
     * @var array
     */
    protected $keys;

    /**
     * Delete queries.
     *
     * @var array
     */
    protected $queries;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->keys    = [];
        $this->queries = [];
    }

    /**
     * Return serialized JSON representation.
     *
     * @return string
     */
    public function asJSON()
    {
        // @todo Implement
    }

    /**
     * Return serialize XML representation.
     *
     * @return string
     */
    public function asXML()
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument();
        $writer->startElement('delete');
        foreach ($this->keys as $key) {
            $writer->writeElement('id', $key);
        }
        foreach ($this->queries as $query) {
            $writer->writeElement('query', $query);
        }
        $writer->endElement();
        $writer->endDocument();
        return $writer->flush();
    }

    /**
     * Add unique key to delete.
     *
     * @param string $key Unique key
     *
     * @return void
     */
    public function addKey($key)
    {
        $this->keys[] = $key;
    }

    /**
     * Add array of unique keys to delete.
     *
     * @param array $keys Unique keys
     *
     * @return void
     */
    public function addKeys($keys)
    {
        $this->keys = array_merge($this->keys, $keys);
    }

    /**
     * Add delete query.
     *
     * @param string $query Delete query
     *
     * @return void
     */
    public function addQuery($query)
    {
        $this->queries[] = $query;
    }
}
