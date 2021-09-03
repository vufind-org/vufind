<?php

/**
 * Simple JSON-based record collection.
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
namespace VuFindSearch\Backend\Solr\Response\Json;

use VuFindSearch\Response\AbstractRecordCollection;

/**
 * Simple JSON-based record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollection extends AbstractRecordCollection
{
    /**
     * Template of deserialized SOLR response.
     *
     * @see \VuFindSearch\Backend\Solr\Response\Json\RecordCollection::__construct()
     *
     * @var array
     */
    protected static $template = [
        'responseHeader' => [],
        'response'       => ['numFound' => 0, 'start' => 0],
        'spellcheck'     => ['suggestions' => []],
        'facet_counts'   => [],
    ];

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
        if (array_key_exists('response', $response)
            && null === $response['response']
        ) {
            unset($response['response']);
        }
        $this->response = array_replace_recursive(static::$template, $response);
        $this->offset = $this->response['response']['start'];
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
            $this->spellcheck = new Spellcheck(
                $this->getRawSpellcheckSuggestions(),
                $this->getSpellcheckQuery()
            );
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
        return $this->response['grouped'] ?? [];
    }

    /**
     * Get highlighting details.
     *
     * @return array
     */
    public function getHighlighting()
    {
        return $this->response['highlighting'] ?? [];
    }

    /**
     * Get cursorMark.
     *
     * @return string
     */
    public function getCursorMark()
    {
        return $this->response['nextCursorMark'] ?? '';
    }

    /**
     * Get raw Solr input parameters from the response.
     *
     * @return array
     */
    protected function getSolrParameters()
    {
        return $this->response['responseHeader']['params'] ?? [];
    }

    /**
     * Extract the best matching Spellcheck query from the raw Solr input parameters.
     *
     * @return string
     */
    protected function getSpellcheckQuery()
    {
        $params = $this->getSolrParameters();
        return $params['spellcheck.q'] ?? ($params['q'] ?? '');
    }

    /**
     * Get raw Solr Spellcheck suggestions.
     *
     * @return array
     */
    protected function getRawSpellcheckSuggestions()
    {
        return $this->response['spellcheck']['suggestions'] ?? [];
    }
}
