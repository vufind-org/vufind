<?php
/**
 * Record Driver Factory Class
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
        $eds = $sm->getServiceLocator()->get('VuFind\Config')->get('EDS');
        return new EDS(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'), $eds, $eds
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
        $eit = $sm->getServiceLocator()->get('VuFind\Config')->get('EIT');
        return new EIT(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'), $eit, $eit
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
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
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
        $pp2 = $sm->getServiceLocator()->get('VuFind\Config')->get('Pazpar2');
        return new Pazpar2(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'), $pp2, $pp2
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
        $primo = $sm->getServiceLocator()->get('VuFind\Config')->get('Primo');
        $driver = new Primo(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
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
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
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
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
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
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
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
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachILS(
            $sm->getServiceLocator()->get('VuFind\ILSConnection'),
            $sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
            $sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
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
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
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
        $web = $sm->getServiceLocator()->get('VuFind\Config')->get('website');
        return new SolrWeb(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'), $web, $web
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
        $summon = $sm->getServiceLocator()->get('VuFind\Config')->get('Summon');
        $driver = new Summon(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            $summon, $summon
        );
        $driver->setDateConverter(
            $sm->getServiceLocator()->get('VuFind\DateConverter')
        );
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
        $wc = $sm->getServiceLocator()->get('VuFind\Config')->get('WorldCat');
        return new WorldCat(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'), $wc, $wc
        );
    }
}
