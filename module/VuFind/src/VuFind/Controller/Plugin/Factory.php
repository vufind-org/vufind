<?php
/**
 * Factory for controller plugins.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Controller\Plugin;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controller plugins.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Holds plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Holds
     */
    public static function getHolds(ServiceManager $sm)
    {
        return new Holds($sm->getServiceLocator()->get('VuFind\HMAC'));
    }

    /**
     * Construct the NewItems plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reserves
     */
    public static function getNewItems(ServiceManager $sm)
    {
        $search = $sm->getServiceLocator()->get('VuFind\Config')->get('searches');
        $config = isset($search->NewItem)
            ? $search->NewItem : new \Zend\Config\Config([]);
        return new NewItems($config);
    }

    /**
     * Construct the ILLRequests plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ILLRequests
     */
    public static function getILLRequests(ServiceManager $sm)
    {
        return new ILLRequests(
            $sm->getServiceLocator()->get('VuFind\HMAC')
        );
    }

    /**
     * Construct the Recaptcha plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Recaptcha
     */
    public static function getRecaptcha(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new Recaptcha(
            $sm->getServiceLocator()->get('VuFind\Recaptcha'),
            $config
        );
    }

    /**
     * Construct the Reserves plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reserves
     */
    public static function getReserves(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $useIndex = isset($config->Reserves->search_enabled)
            && $config->Reserves->search_enabled;
        return new Reserves($useIndex);
    }

    /**
     * Construct the StorageRetrievalRequests plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return StorageRetrievalRequests
     */
    public static function getStorageRetrievalRequests(ServiceManager $sm)
    {
        return new StorageRetrievalRequests(
            $sm->getServiceLocator()->get('VuFind\HMAC')
        );
    }
}