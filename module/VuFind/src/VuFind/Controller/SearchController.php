<?php
/**
 * Default Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

use VuFind\Cache\Manager as CacheManager, VuFind\Search\Solr\Params,
    VuFind\Search\Solr\Results;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SearchController extends AbstractSearch
{
    /**
     * Home action
     *
     * @return void
     */
    public function homeAction()
    {
        return array('results' => $this->getAdvancedFacets());
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache, and it is currently shared between the Home
     * page and the Advanced search screen.
     *
     * @return VF_Search_Solr_Results
     */
    protected function getAdvancedFacets()
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = CacheManager::getInstance()->getCache('object');
        if (!($results = $cache->getItem('solrSearchHomeFacets'))) {
            // Use advanced facet settings to get summary facets on the front page;
            // we may want to make this more flexible later.  Also keep in mind that
            // the template is currently looking for certain hard-coded fields; this
            // should also be made smarter.
            $params = new Params();
            $params->initAdvancedFacets();

            // We only care about facet lists, so don't get any results (this helps
            // prevent problems with serialized File_MARC objects in the cache):
            $params->setLimit(0);

            $results = new Results($params);
            /* TODO: fix caching:
            $cache->setItem($results, 'solrSearchHomeFacets');
             */
        }
        return $results;
    }
}
