<?php
/**
 * Search Results Object Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search\Results;

use Zend\ServiceManager\ServiceManager;

/**
 * Search Results Object Factory Class
 *
 * @category VuFind
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
     * Factory for Favorites results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Favorites\Results
     */
    public static function getFavorites(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $tm = $sm->getServiceLocator()->get('VuFind\DbTablePluginManager');
        $obj = $factory->createServiceWithName(
            $sm, 'favorites', 'Favorites',
            [$tm->get('Resource'), $tm->get('UserList'), $tm->get('UserResource')]
        );
        $init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
        $init->initialize($obj, $sm);
        return $obj;
    }

    /**
     * Factory for Solr results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Solr\Results
     */
    public static function getSolr(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        $solr = $factory->createServiceWithName($sm, 'solr', 'Solr');
        $config = $sm->getServiceLocator()
            ->get('VuFind\Config')->get('config');
        $spellConfig = isset($config->Spelling)
            ? $config->Spelling : null;
        $solr->setSpellingProcessor(
            new \VuFind\Search\Solr\SpellingProcessor($spellConfig)
        );
        return $solr;
    }

    /**
     * Factory for Primo results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Primo\Results
     */
    public static function getPrimo(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        return $factory->createServiceWithName($sm, 'primo', 'Primo');
    }

    /**
     * Factory for Combined results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Combined\Results
     */
    public static function getCombined(ServiceManager $sm)
    {
        $factory = new PluginFactory();
        return $factory->createServiceWithName($sm, 'combined', 'Combined');
    }
}
