<?php

/**
 * Simple JSON-based record collection.
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

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * Simple JSON-based record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection extends AbstractRecordCollection
{
    /**
     * Template of deserialized SOLR response.
     *
     * @see self::__construct()
     *
     * @var array
     */
    protected static $template = array(
        'responseHeader' => array(),
        'response'       => array('start' => 0),
        'spellcheck'     => array('suggestions' => array()),
        'facet_counts'   => array(),
    );

    /**
     * Deserialized SOLR response.
     *
     * @var array
     */
    protected $response;

    /**
     * Facets.
     *
     * @var Facets
     */
    protected $facets;

    /**
     * Spellcheck information.
     *
     * @var Spellcheck
     */
    protected $spellcheck;

    /**
     * Constructor.
     *
     * @param array $response Deserialized SOLR response
     *
     * @return void
     */
    public function __construct(array $response)
    {
        $this->response = array_replace_recursive(static::$template, $response);
        $this->rewind();
    }

    /**
     * Return spellcheck information.
     *
     * @return Spellcheck
     */
    public function getSpellcheck()
    {
        if (!$this->spellcheck) {
            $params = isset($this->response['responseHeader']['params'])
                ? $this->response['responseHeader']['params'] : array();
            $sq = isset($params['spellcheck.q'])
                ? $params['spellcheck.q'] : $params['q'];
            $sugg = isset($this->response['spellcheck']['suggestions'])
                ? $this->response['spellcheck']['suggestions'] : array();
            $this->spellcheck = new Spellcheck($sugg, $sq);
        }
        return $this->spellcheck;
    }

    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->response['response']['numFound'];
    }

    /**
     * Return SOLR facet information.
     *
     * @return array
     */
    public function getFacets()
    {
        if (!$this->facets) {
            $this->facets = new Facets($this->response['facet_counts']);
        }
        return $this->facets;
    }

    /**
     * Get grouped results.
     *
     * @return array
     */
    public function getGroups()
    {
        return isset($this->response['grouped'])
            ? $this->response['grouped'] : array();
    }

    /**
     * Get highlighting details.
     *
     * @return array
     */
    public function getHighlighting()
    {
        return isset($this->response['highlighting'])
            ? $this->response['highlighting'] : array();
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->response['response']['start'];
    }
}