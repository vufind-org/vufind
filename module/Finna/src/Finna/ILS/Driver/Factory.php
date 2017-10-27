<?php
/**
 * ILS Driver Factory Class
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace Finna\ILS\Driver;

use Zend\ServiceManager\ServiceManager;

/**
 * ILS Driver Factory Class
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
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
     * Factory for KohaRest driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return KohaRest
     */
    public static function getKohaRest(ServiceManager $sm)
    {
        $sessionFactory = function ($namespace) use ($sm) {
            $manager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            return new \Zend\Session\Container("KohaRest_$namespace", $manager);
        };
        $kohaRest = new KohaRest(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sessionFactory
        );
        $kohaRest->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $kohaRest;
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
        $mb = new MultiBackend(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\ILSAuthenticator'),
            $sm
        );
        $mb->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $mb;
    }

    /**
     * Factory for Sierra REST driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SierraRest
     */
    public static function getSierraRest(ServiceManager $sm)
    {
        $sessionFactory = function ($namespace) use ($sm) {
            $manager = $sm->getServiceLocator()->get('VuFind\SessionManager');
            return new \Zend\Session\Container("SierraRest_$namespace", $manager);
        };

        $driver = new SierraRest(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sessionFactory
        );
        $driver->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $driver;
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
        $vr->setConfigReader($sm->getServiceLocator()->get('VuFind\Config'));
        $vr->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $vr;
    }

    /**
     * Factory for AxiellWebServices driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AxiellWebServices
     */
    public static function getAxiellWebServices(ServiceManager $sm)
    {
        $aws = new AxiellWebServices(
            $sm->getServiceLocator()->get('VuFind\DateConverter')
        );
        $aws->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $aws;
    }

    /**
     * Factory for Gemini driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Gemini
     */
    public static function getGemini(ServiceManager $sm)
    {
        $gemini = new Gemini(
            $sm->getServiceLocator()->get('VuFind\DateConverter')
        );
        $gemini->setCacheStorage(
            $sm->getServiceLocator()->get('VuFind\CacheManager')->getCache('object')
        );
        return $gemini;
    }
}
