<?php
/**
 * Author aspect of the Search Multi-class (Params)
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
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\SolrAuthor;

/**
 * Author Search Options
 *
 * @category VuFind2
 * @package  Search_SolrAuthor
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * Support method for _initSearch() -- handle basic settings.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (is_null($lookfor = $request->get('author'))) {
            return false;
        }

        // Force the search to be a phrase:
        $lookfor = '"' . str_replace('"', '\"', $lookfor) . '"';
        
        // Set the search (handler is always Author for this module):
        $this->setBasicSearch($lookfor, 'Author');
        return true;
    }

    /**
     * Build a string for onscreen display showing the
     *   query used in the search (not the filters).
     *
     * @return string user friendly version of 'query'
     */
    public function getDisplayQuery()
    {
        // For display purposes, undo the query manipulation performed above
        // in initBasicSearch():
        $q = parent::getDisplayQuery();
        return str_replace('\"', '"', substr($q, 1, -1));
    }
}