<?php
/**
 * Record Tab Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  RecordDrivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\RecordTab;
use Zend\ServiceManager\ServiceManager;

/**
 * Record Tab Factory Class
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Factory
{
    /**
     * Factory for CollectionHierarchyTree tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionHierarchyTree
     */
    public function getCollectionHierarchyTree(ServiceManager $sm)
    {
        return new CollectionHierarchyTree(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            $sm->getServiceLocator()->get('VuFind\RecordLoader')
        );
    }

    /**
     * Factory for CollectionList tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionList
     */
    public function getCollectionList(ServiceManager $sm)
    {
        return new CollectionList(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')->get('SolrCollection')
        );
    }

    /**
     * Factory for Excerpt tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Excerpt
     */
    public function getExcerpt(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $enabled = isset($config->Content->excerpts);
        return new Excerpt($enabled);
    }

    /**
     * Factory for HierarchyTree tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HierarchyTree
     */
    public function getHierarchyTree(ServiceManager $sm)
    {
        return new HierarchyTree(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Factory for HoldingsILS tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsILS
     */
    public function getHoldingsILS(ServiceManager $sm)
    {
        // If VuFind is configured to suppress the holdings tab when the
        // ILS driver specifies no holdings, we need to pass in a connection
        // object:
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        if (isset($config->Site->hideHoldingsTabWhenEmpty)
            && $config->Site->hideHoldingsTabWhenEmpty
        ) {
            $catalog = $sm->getServiceLocator()->get('VuFind\ILSConnection');
        } else {
            $catalog = false;
        }
        return new HoldingsILS($catalog);
    }

    /**
     * Factory for Map tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Map
     */
    public function getMap(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $enabled = isset($config->Content->recordMap);
        return new Map($enabled);
    }

    /**
     * Factory for Reviews tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reviews
     */
    public function getReviews(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $enabled = isset($config->Content->reviews);
        return new Reviews($enabled);
    }
}