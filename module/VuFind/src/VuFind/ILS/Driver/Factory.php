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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\ILS\Driver;
use Zend\ServiceManager\ServiceManager;

/**
 * ILS Driver Factory Class
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
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
        return new DAIA(
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
        return new Demo(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sm->getServiceLocator()->get('VuFind\Search')
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
        return new VoyagerRestful(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $ils->getHoldsMode(), $ils->getTitleHoldsMode()
        );
    }
}