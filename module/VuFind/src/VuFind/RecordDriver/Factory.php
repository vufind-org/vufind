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
     * Factory for EDS record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return EDS
     */
    public static function getEDS(ServiceManager $sm)
    {
        $eds = $sm->get('VuFind\Config\PluginManager')->get('EDS');
        return new EDS(
            $sm->get('VuFind\Config\PluginManager')->get('config'), $eds, $eds
        );
    }

    /**
     * Factory for EIT record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return EIT
     */
    public static function getEIT(ServiceManager $sm)
    {
        $eit = $sm->get('VuFind\Config\PluginManager')->get('EIT');
        return new EIT(
            $sm->get('VuFind\Config\PluginManager')->get('config'), $eit, $eit
        );
    }

    /**
     * Factory for Missing record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Missing
     */
    public static function getMissing(ServiceManager $sm)
    {
        return new Missing(
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Factory for Pazpar2 record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Pazpar2
     */
    public static function getPazpar2(ServiceManager $sm)
    {
        $pp2 = $sm->get('VuFind\Config\PluginManager')->get('Pazpar2');
        return new Pazpar2(
            $sm->get('VuFind\Config\PluginManager')->get('config'), $pp2, $pp2
        );
    }

    /**
     * Factory for Primo record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Primo
     */
    public static function getPrimo(ServiceManager $sm)
    {
        $primo = $sm->get('VuFind\Config\PluginManager')->get('Primo');
        $driver = new Primo(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            $primo, $primo
        );
        return $driver;
    }

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
     * Factory for SolrOverdrive record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrOvedrive
     */
    public static function getSolrOverdrive(ServiceManager $sm)
    {
        
          $driver = new SolrOverdrive(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            $sm->get('VuFind\Config\PluginManager')->get('Overdrive'),
            $sm->get('VuFind\DigitalContent\OverdriveConnector')
        );
        if ($sm->has('VuFind\ILS\Connection')) {
            $driver->attachILS(
                $sm->get('VuFind\ILS\Connection'),
                $sm->get('VuFind\ILS\Logic\Holds'),
                $sm->get('VuFind\ILS\Logic\TitleHolds')
            );
        }
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

    /**
     * Factory for Summon record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Summon
     */
    public static function getSummon(ServiceManager $sm)
    {
        $summon = $sm->get('VuFind\Config\PluginManager')->get('Summon');
        $driver = new Summon(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            $summon, $summon
        );
        $driver->setDateConverter($sm->get('VuFind\Date\Converter'));
        return $driver;
    }

    /**
     * Factory for WorldCat record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCat
     */
    public static function getWorldCat(ServiceManager $sm)
    {
        $wc = $sm->get('VuFind\Config\PluginManager')->get('WorldCat');
        return new WorldCat(
            $sm->get('VuFind\Config\PluginManager')->get('config'), $wc, $wc
        );
    }
}
