<?php

/**
 * SOLR facet information.
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

use ArrayObject;

/**
 * SOLR facet information.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Facets
{
    /**
     * Facet fields.
     *
     * @var ArrayObject
     */
    protected $fields;

    /**
     * Facet queries.
     *
     * @var ArrayObject
     */
    protected $queries;

    /**
     * SOLR facet information.
     *
     * @var array
     */
    protected $facets;

    /**
     * SOLR pivot facet information.
     *
     * @var ArrayObject
     */
    protected $pivotFacets = null;

    /**
     * Constructor.
     *
     * @param array $facets SOLR facet information
     *
     * @return void
     *
     * @todo Implement facet_queries et al.
     */
    public function __construct(array $facets)
    {
        $this->facets = $facets;
    }

    /**
     * Return facet fields.
     *
     * @return ArrayObject
     */
    public function getFieldFacets()
    {
        if (!$this->fields) {
            $this->fields = new ArrayObject();
            if (isset($this->facets['facet_fields'])) {
                foreach ($this->facets['facet_fields'] as $name => $info) {
                    $this->fields->offsetSet($name, new NamedList($info));
                }
            }
        }
        return $this->fields;
    }

    /**
     * Return facet queries.
     *
     * @return ArrayObject
     */
    public function getQueryFacets()
    {
        if (!$this->queries) {
            $this->queries = new ArrayObject();
            if (isset($this->facets['facet_queries'])) {
                $this->queries->exchangeArray($this->facets['facet_queries']);
            }
        }
        return $this->queries;
    }

    /**
     * Return facet pivot information.
     *
     * @return ArrayObject
     */
    public function getPivotFacets()
    {
        if (null === $this->pivotFacets) {
            $this->pivotFacets = new ArrayObject();
            if (isset($this->facets['facet_pivot'])) {
                foreach ($this->facets['facet_pivot'] as $facetdata) {
                    foreach ($facetdata as $onefacet) {
                        // Gives us an ArrayObject with the field value
                        // as the key and the full data for that field,
                        // including count and pivot, as the value.
                        $this->pivotFacets->offsetSet($onefacet['value'], $onefacet);
                    }
                }
            }
        }
        return $this->pivotFacets;
    }
}
