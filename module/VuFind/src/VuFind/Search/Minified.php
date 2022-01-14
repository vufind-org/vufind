<?php
/**
 * VuFind Minified Search Object
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search;

/**
 * A minified search object used exclusively for trimming a search object down to its
 * barest minimum size before storage in a cookie or database.
 *
 * It still contains enough data granularity to programmatically recreate search
 * URLs.
 *
 * This class isn't intended for general use, but simply a way of storing/retrieving
 * data from a search object:
 *
 * eg. Store
 * $searchHistory[] = serialize($this->minify());
 *
 * eg. Retrieve
 * $searchObject = unserialize($search);
 * $searchObject->deminify($manager);
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Minified
{
    /**
     * Search terms
     *
     * @var array
     */
    public $t = [];

    /**
     * Filters
     *
     * @var array
     */
    public $f = [];

    /**
     * Hidden Filters
     *
     * @var array
     */
    public $hf = [];

    /**
     * ID, start tIme, query Speed, Result total, search TYpe, search CLass id
     */
    public $id;

    public $i;

    public $s;

    public $r;

    public $ty;

    public $cl;

    /**
     * Constructor. Building minified object from the
     *    searchObject passed in. Needs to be kept
     *    up-to-date with the deminify() function on
     *    searchObject.
     *
     * @param object $searchObject Search Object to minify
     */
    public function __construct($searchObject)
    {
        // Most values will transfer without changes
        $this->id = $searchObject->getSearchId();
        $this->i  = $searchObject->getStartTime();
        $this->s  = $searchObject->getQuerySpeed();
        $this->r  = $searchObject->getResultTotal();
        $this->ty = $searchObject->getParams()->getSearchType();
        $this->cl = $searchObject->getParams()->getSearchClassId();

        // Search terms, we'll shorten keys
        $query = $searchObject->getParams()->getQuery();
        $this->t = QueryAdapter::minify($query);

        // It would be nice to shorten filter fields too, but
        //      it would be a nightmare to maintain.
        $this->f = $searchObject->getParams()->getRawFilters();
        $this->hf = $searchObject->getParams()->getHiddenFilters();
    }

    /**
     * Turn the current object into search results.
     *
     * @param \VuFind\Search\Results\PluginManager $manager Search manager
     *
     * @return \VuFind\Search\Base\Results
     */
    public function deminify(\VuFind\Search\Results\PluginManager $manager)
    {
        // Figure out the parameter and result classes based on the search class ID:
        $this->populateClassNames();

        // Deminify everything:
        $results = $manager->get($this->cl);
        $results->getParams()->deminify($this);
        $results->deminify($this);

        return $results;
    }

    /**
     * Support method for deminify -- populate parameter class and results class
     * if missing (for legacy compatibility).
     *
     * @return void
     */
    protected function populateClassNames()
    {
        // If this is a legacy entry from VuFind 1.x, we need to figure out the
        // search class ID for the object we're about to construct:
        if (!isset($this->cl)) {
            $fixType = true;    // by default, assume we need to fix type
            switch ($this->ty) {
            case 'Summon':
            case 'SummonAdvanced':
                $this->cl = 'Summon';
                break;
            case 'WorldCat':
            case 'WorldCatAdvanced':
                $this->cl = 'WorldCat';
                break;
            case 'Authority':
            case 'AuthorityAdvanced':
                $this->cl = 'SolrAuth';
                break;
            default:
                $this->cl = 'Solr';
                $fixType = false;
                break;
            }

            // Now rewrite the type if necessary (only needed for legacy objects):
            if ($fixType) {
                $this->ty = (substr($this->ty, -8) == 'Advanced')
                    ? 'advanced' : 'basic';
            }
        }
    }
}
