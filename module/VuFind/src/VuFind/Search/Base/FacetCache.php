<?php
/**
 * Abstract Base FacetCache.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search\Base;

use VuFind\Cache\Manager as CacheManager;

/**
 * Solr FacetCache Factory.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class FacetCache
{
    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Currently selected language
     *
     * @var string
     */
    protected $language;

    /**
     * Search results object.
     *
     * @var Results
     */
    protected $results;

    /**
     * Constructor
     *
     * @param Results      $r        Search results object
     * @param CacheManager $cm       Cache manager
     * @param string       $language Active UI language
     */
    public function __construct(Results $r, CacheManager $cm, $language = 'en')
    {
        $this->results = $r;
        $this->cacheManager = $cm;
        $this->language = $language;
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache.
     *
     * @param string $context Context of list to retrieve ('Advanced' or 'HomePage')
     *
     * @return array
     */
    abstract public function getList($context = 'Advanced');

    /**
     * Get results object used to retrieve facets.
     *
     * @return Results
     */
    public function getResults()
    {
        return $this->results;
    }
}
