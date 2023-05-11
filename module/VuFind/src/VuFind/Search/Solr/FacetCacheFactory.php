<?php

/**
 * Solr FacetCache Factory.
 *
 * PHP version 8
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search\Solr;

use Psr\Container\ContainerInterface;

/**
 * Solr FacetCache Factory.
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FacetCacheFactory extends \VuFind\Search\Base\FacetCacheFactory
{
    /**
     * Create a results object with hidden filters pre-populated.
     *
     * @param ContainerInterface $container Service manager
     * @param string             $name      Name of results object to load (based
     * on name of FacetCache service name)
     *
     * @return \VuFind\Search\Base\Results
     */
    protected function getResults(ContainerInterface $container, $name)
    {
        $filters = $container->get(\VuFind\Search\SearchTabsHelper::class)
            ->getHiddenFilters($name);
        $results = $container->get(\VuFind\Search\Results\PluginManager::class)
            ->get($name);
        $params = $results->getParams();
        foreach ($filters as $key => $subFilters) {
            foreach ($subFilters as $filter) {
                $params->addHiddenFilter("$key:$filter");
            }
        }
        return $results;
    }
}
