<?php
/**
 * Search Results Object Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search\Results;
use Finna\Search\UrlQueryHelper,
    \VuFind\Search\Results\PluginFactory,
    Zend\ServiceManager\ServiceManager;

/**
 * Search Results Object Factory Class
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Search\Results\Factory
{
    /**
     * Factory for Solr results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getSolr(ServiceManager $sm)
    {
        $solr = parent::getSolr($sm);
        return Factory::initUrlQueryHelper($solr, $sm);
    }

    /**
     * Factory for Primo results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getPrimo(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $primo = $factory->createServiceWithName($sm, 'primo', 'Primo');
        return Factory::initUrlQueryHelper($primo, $sm);
    }

    /**
     * Internal utility function for initializing
     * UrlQueryHelper for a Results-object with search ids for all tabs.
     *
     * @param ResultsManager $results Search results.
     * @param ServiceManager $sm      Service manager.
     *
     * @return Results Search results with initialized UrlQueryHelper
     */
    public static function initUrlQueryHelper(
        \VuFind\Search\Base\Results $results, $sm
    ) {
        $helper = new UrlQueryHelper($results->getParams());
        $savedSearches
            = $sm->getServiceLocator()->get('Request')->getQuery('search');
        if ($savedSearches) {
            $helper->setDefaultParameter('search', $savedSearches);
        }
        $results->setHelper('urlQuery', $helper);

        return $results;
    }
}
