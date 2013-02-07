<?php

/**
 * Query parameter class file.
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
 * @category Search
 * @package  Query
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */


namespace VuFindSearch\Query;

/**
 * Query parameter class.
 *
 * @category Search
 * @package  Query
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */

class Params
{

    /**
     * Sort order.
     *
     * @var string
     */
    protected $sort = 'relevance';

    /**
     * Maximum number of records per result.
     *
     * @var int
     */
    protected $limit = 10;

    /**
     * Offset in entire result set.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * Facet settings.
     *
     * @var array
     */
    protected $facets = array();

    /**
     * Filter settings.
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Spellcheck dictionary.
     *
     * @var string
     */
    protected $spellcheckDictionary;

    /**
     * Is spellcheck enabled?
     *
     * @var boolean
     */
    protected $spellcheckEnabled;

    /**
     * Return facet settings.
     *
     * @return array
     */
    public function getFacets ()
    {
        return $this->facets;
    }

    /**
     * Set facet settings.
     *
     * @param array $facets Facet settings
     *
     * @return void
     */
    public function setFacets (array $facets)
    {
        $this->facets = $facets;
    }

    /**
     * Return filter settings.
     *
     * @return array
     */
    public function getFilters ()
    {
        return $this->filters;
    }

    /**
     * Set filter settings.
     *
     * @param string $field Filter field
     * @param string $value Filter value
     *
     * @return void
     */
    public function setFilter ($field, $value)
    {
        $this->filters[$field] = $value;
    }

    /**
     * Return selected shards.
     *
     * @return array
     */
    public function getShards ()
    {
        return array();
    }

    /**
     * Return spellcheck dictionary.
     *
     * @return array
     */
    public function getSpellcheckDictionary ()
    {
        return $this->spellcheckDictionary;
    }

    /**
     * Set spellcheck dictionary.
     *
     * @param  string $dictionary
     * @return void
     */
    public function setSpellcheckDictionary ($dictionary)
    {
        $this->spellcheckDictionary = $dictionary;
    }

    /**
     * Return true if spellcheck suggestions are enabled.
     *
     * @return boolean
     */
    public function isSpellcheckEnabled ()
    {
        return $this->spellcheckEnabled;
    }

    /**
     * Enable or disable spellcheck.
     *
     * @param  boolean $enable
     * @return void
     */
    public function enableSpellcheck ($enable)
    {
        $this->spellcheckEnabled = (boolean)$enable;
    }

    /**
     * Return highlighting settings.
     *
     * Dummy implementation
     *
     * @return array
     */
    public function getHighlighting ()
    {
        return array();
    }

    /**
     * Return sort order.
     *
     * @return string
     */
    public function getSort ()
    {
        return $this->sort;
    }

    /**
     * Set sort order.
     *
     * @param string $order
     *
     * @return void
     */
    public function setSort ($sort)
    {
        $this->sort = (string)$sort;
    }

    /**
     * Return limit.
     *
     * @return int
     */
    public function getLimit ()
    {
        return $this->limit;
    }

    /**
     * Set limit.
     *
     * @param int $limit
     *
     * @return void
     */
    public function setLimit ($limit)
    {
        $this->limit = (int)$limit;
    }

    /**
     * Return offset.
     *
     * @return int
     */
    public function getOffset ()
    {
        return $this->offset;
    }

    /**
     * Set offset.
     *
     * @param int $offset
     *
     * @return void
     */
    public function setOffset ($offset)
    {
        $this->offset = (int)$offset;
    }
}