<?php
/**
 * Record Driver Factory Class
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\RecordDriver;

use Zend\ServiceManager\ServiceManager;

/**
 * Record Driver Factory Class
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for SolrAuth record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrAuth
     */
    public static function getSolrAuth(ServiceManager $sm)
    {
        return new SolrAuth(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            null,
            $sm->get('VuFind\Config\PluginManager')->get('searches')
        );
    }

    /**
     * Factory for SolrDefault record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrDefault
     */
    public static function getSolrDefault(ServiceManager $sm)
    {
        $driver = new SolrDefault(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            null,
            $sm->get('VuFind\Config\PluginManager')->get('searches')
        );
        $driver->attachSearchService($sm->get('VuFindSearch\Service'));
        return $driver;
    }

    /**
     * Factory for SolrMarc record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMarc
     */
    public static function getSolrMarc(ServiceManager $sm)
    {
        $driver = new SolrMarc(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            null,
            $sm->get('VuFind\Config\PluginManager')->get('searches')
        );
        if ($sm->has('VuFind\ILS\Connection')) {
            $driver->attachILS(
                $sm->get('VuFind\ILS\Connection'),
                $sm->get('VuFind\ILS\Logic\Holds'),
                $sm->get('VuFind\ILS\Logic\TitleHolds')
            );
        }
        $driver->attachSearchService($sm->get('VuFindSearch\Service'));
        return $driver;
    }

    /**
     * Factory for SolrMarcRemote record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMarc
     */
    public static function getSolrMarcRemote(ServiceManager $sm)
    {
        $driver = new SolrMarcRemote(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            null,
            $sm->get('VuFind\Config\PluginManager')->get('searches')
        );
        if ($sm->has('VuFind\ILS\Connection')) {
            $driver->attachILS(
                $sm->get('VuFind\ILS\Connection'),
                $sm->get('VuFind\ILS\Logic\Holds'),
                $sm->get('VuFind\ILS\Logic\TitleHolds')
            );
        }
        $driver->attachSearchService($sm->get('VuFindSearch\Service'));
        return $driver;
    }

    /**
     * Factory for SolrReserves record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrReserves
     */
    public static function getSolrReserves(ServiceManager $sm)
    {
        return new SolrReserves(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            null,
            $sm->get('VuFind\Config\PluginManager')->get('searches')
        );
    }

    /**
     * Factory for SolrWeb record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrWeb
     */
    public static function getSolrWeb(ServiceManager $sm)
    {
        $web = $sm->get('VuFind\Config\PluginManager')->get('website');
        return new SolrWeb(
            $sm->get('VuFind\Config\PluginManager')->get('config'), $web, $web
        );
    }
}
