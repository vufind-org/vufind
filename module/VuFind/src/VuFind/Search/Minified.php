<?php
/**
 * VuFind Minified Search Object
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Search;

/**
 * A minified search object used exclusively for trimming
 *  a search object down to it's barest minimum size
 *  before storage in a cookie or database.
 *
 * It's still contains enough data granularity to
 *  programmatically recreate search urls.
 *
 * This class isn't intended for general use, but simply
 *  a way of storing/retrieving data from a search object:
 *
 * eg. Store
 * $searchHistory[] = serialize($this->minify());
 *
 * eg. Retrieve
 * $searchObject  = SearchObjectFactory::initSearchObject();
 * $searchObject->deminify(unserialize($search));
 *
 * Note: codingStandardsIgnore settings within this class are used to suppress
 *       warnings related to the name not meeting PEAR standards; since there
 *       are serialized versions of this class stored in databases in the wild,
 *       it is too late to easily rename it for standards compliance.
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_search_object Wiki
 */
class Minified
{
    public $t = array();
    public $f = array();
    public $id, $i, $s, $r, $ty, $pc, $rc;

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
        $this->ty = $searchObject->getSearchType();
        $this->cl = $searchObject->getSearchClassId();

        // Search terms, we'll shorten keys
        $tempTerms = $searchObject->getSearchTerms();
        foreach ($tempTerms as $term) {
            $newTerm = array();
            foreach ($term as $k => $v) {
                switch ($k) {
                case 'join':
                    $newTerm['j'] = $v;
                    break;
                case 'index':
                    $newTerm['i'] = $v;
                    break;
                case 'lookfor':
                    $newTerm['l'] = $v;
                    break;
                case 'group':
                    $newTerm['g'] = array();
                    foreach ($v as $line) {
                        $search = array();
                        foreach ($line as $k2 => $v2) {
                            switch ($k2) {
                            case 'bool':
                                $search['b'] = $v2;
                                break;
                            case 'field':
                                $search['f'] = $v2;
                                break;
                            case 'lookfor':
                                $search['l'] = $v2;
                                break;
                            }
                        }
                        $newTerm['g'][] = $search;
                    }
                    break;
                }
            }
            $this->t[] = $newTerm;
        }

        // It would be nice to shorten filter fields too, but
        //      it would be a nightmare to maintain.
        $this->f = $searchObject->getFilters();
    }

    /**
     * Turn the current object into search results.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function deminify()
    {
        // Figure out the parameter and result classes based on the search class ID:
        $this->populateClassNames();

        // Deminify everything:
        $params = new $this->pc();
        $params->deminify($this);
        $results = new $this->rc($params);
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
        // Simple case -- this is a recently-built object which has a search class ID
        // populated:
        if (isset($this->cl)) {
            $this->pc = 'VuFind\\Search\\' . $this->cl . '\\Params';
            $this->rc = 'VuFind\\Search\\' . $this->cl . '\\Results';
            return;
        }

        // If we got this far, it's a legacy entry from VuFind 1.x.  We need to
        // figure out the engine type for the object we're about to construct:
        switch($this->ty) {
        case 'Summon':
        case 'SummonAdvanced':
            $this->pc = 'VuFind\\Search\\Summon\\Params';
            $this->rc = 'VuFind\\Search\\Summon\\Results';
            $fixType = true;
            break;
        case 'WorldCat':
        case 'WorldCatAdvanced':
            $this->pc = 'VuFind\\Search\\WorldCat\\Params';
            $this->rc = 'VuFind\\Search\\WorldCat\\Results';
            $fixType = true;
            break;
        case 'Authority':
        case 'AuthorityAdvanced':
            $this->pc = 'VuFind\\Search\\SolrAuth\\Params';
            $this->rc = 'VuFind\\Search\\SolrAuth\\Results';
            $fixType = true;
            break;
        default:
            $this->pc = 'VuFind\\Search\\Solr\\Params';
            $this->rc = 'VuFind\\Search\\Solr\\Results';
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
