<?php

/**
 * SOLR delete document class.
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
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr\Document;

use XMLWriter;

/**
 * SOLR delete document class.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class DeleteDocument implements DocumentInterface
{
    /**
     * Unique keys to delete.
     *
     * @var string[]
     */
    protected $keys = [];

    /**
     * Delete queries.
     *
     * @var string[]
     */
    protected $queries = [];

    /**
     * Return content MIME type.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return 'text/xml; charset=UTF-8';
    }

    /**
     * Return serialized representation.
     *
     * @return string
     */
    public function getContent(): string
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
    public function addKey(string $key): void
    {
        $this->keys[] = $key;
    }

    /**
     * Add array of unique keys to delete.
     *
     * @param string[] $keys Unique keys
     *
     * @return void
     */
    public function addKeys(array $keys): void
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
    public function addQuery(string $query): void
    {
        $this->queries[] = $query;
    }
}
