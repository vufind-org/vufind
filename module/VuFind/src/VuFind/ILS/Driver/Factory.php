<?php
/**
 * ILS Driver Factory Class
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
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\ILS\Driver;
use Zend\ServiceManager\ServiceManager;

/**
 * ILS Driver Factory Class
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for Aleph driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Aleph
     */
    public static function getAleph(ServiceManager $sm)
    {
        return new Aleph(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sm->getServiceLocator()->get('VuFind\CacheManager')
        );
    }

    /**
     * Factory for DAIA driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return DAIA
     */
    public static function getDAIA(ServiceManager $sm)
    {
        $daia = new DAIA(
            $sm->getServiceLocator()->get('VuFind\DateConverter')
        );

        $daia->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );

        return $daia;
    }

    /**
     * Factory for LBS4 driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return LBS4
     */
    public static function getLBS4(ServiceManager $sm)
    {
        return new LBS4(
            $sm->getServiceLocator()->get('VuFind\DateConverter')
        );
    }

    /**
     * Factory for Demo driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Demo
     */
    public static function getDemo(ServiceManager $sm)
    {
        $sessionFactory = function () use ($sm) {
            $manager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            return new \Zend\Session\Container('DemoDriver', $manager);
        };
        return new Demo(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sm->getServiceLocator()->get('VuFind\Search'), $sessionFactory
        );
    }

    /**
     * Factory for Horizon driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Horizon
     */
    public static function getHorizon(ServiceManager $sm)
    {
        return new Horizon($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Factory for HorizonXMLAPI driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HorizonXMLAPI
     */
    public static function getHorizonXMLAPI(ServiceManager $sm)
    {
        return new HorizonXMLAPI(
            $sm->getServiceLocator()->get('VuFind\DateConverter')
        );
    }

    /**
     * Factory for MultiBackend driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MultiBackend
     */
    public static function getMultiBackend(ServiceManager $sm)
    {
        return new MultiBackend(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\ILSAuthenticator')
        );
    }

    /**
     * Factory for NoILS driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return NoILS
     */
    public static function getNoILS(ServiceManager $sm)
    {
        return new NoILS($sm->getServiceLocator()->get('VuFind\RecordLoader'));
    }

    /**
     * Factory for PAIA driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return PAIA
     */
    public static function getPAIA(ServiceManager $sm)
    {
        $paia = new PAIA(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sm->getServiceLocator()->get('VuFind\SessionManager')
        );

        $paia->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );

        return $paia;
    }

    /**
     * Factory for KohaILSDI driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return KohaILSDI
     */
    public static function getKohaILSDI(ServiceManager $sm)
    {
        return new KohaILSDI($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Factory for Unicorn driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Unicorn
     */
    public static function getUnicorn(ServiceManager $sm)
    {
        return new Unicorn($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Factory for Voyager driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Voyager
     */
    public static function getVoyager(ServiceManager $sm)
    {
        return new Voyager($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    /**
     * Factory for VoyagerRestful driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return VoyagerRestful
     */
    public static function getVoyagerRestful(ServiceManager $sm)
    {
        $ils = $sm->getServiceLocator()->get('VuFind\ILSHoldSettings');
        $vr = new VoyagerRestful(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $ils->getHoldsMode(), $ils->getTitleHoldsMode()
        );
        $vr->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $vr;
    }
}
