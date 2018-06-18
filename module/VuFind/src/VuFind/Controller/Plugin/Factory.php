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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Controller\Plugin;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controller plugins.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Favorites plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Zend\Mvc\Controller\Plugin\Favorites
     */
    public static function getFavorites(ServiceManager $sm)
    {
        return new Favorites(
            $sm->getServiceLocator()->get('VuFind\RecordLoader'),
            $sm->getServiceLocator()->get('VuFind\RecordCache'),
            $sm->getServiceLocator()->get('VuFind\Tags')
        );
    }

    /**
     * Construct the FlashMessenger plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Zend\Mvc\Controller\Plugin\FlashMessenger
     */
    public static function getFlashMessenger(ServiceManager $sm)
    {
        $plugin = new \Zend\Mvc\Controller\Plugin\FlashMessenger();
        $sessionManager = $sm->getServiceLocator()->get('VuFind\SessionManager');
        $plugin->setSessionManager($sessionManager);
        return $plugin;
    }

    /**
     * Construct the Followup plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Followup
     */
    public static function getFollowup(ServiceManager $sm)
    {
        return new Followup(
            new \Zend\Session\Container(
                'Followup', $sm->getServiceLocator()->get('VuFind\SessionManager')
            )
        );
    }

    /**
     * Construct the Holds plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Holds
     */
    public static function getHolds(ServiceManager $sm)
    {
        return new Holds(
            $sm->getServiceLocator()->get('VuFind\HMAC'),
            $sm->getServiceLocator()->get('VuFind\SessionManager')
        );
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
            $sm->getServiceLocator()->get('VuFind\HMAC'),
            $sm->getServiceLocator()->get('VuFind\SessionManager')
        );
    }

    /**
     * Construct the NewItems plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return NewItems
     */
    public static function getNewItems(ServiceManager $sm)
    {
        $search = $sm->getServiceLocator()->get('VuFind\Config')->get('searches');
        $config = isset($search->NewItem)
            ? $search->NewItem : new \Zend\Config\Config([]);
        return new NewItems($config);
    }

    /**
     * Construct the Permission plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Permission
     */
    public static function getPermission(ServiceManager $sm)
    {
        $pdm = $sm->getServiceLocator()->get('VuFind\Role\PermissionDeniedManager');
        $pm = $sm->getServiceLocator()->get('VuFind\Role\PermissionManager');
        $auth = $sm->getServiceLocator()->get('VuFind\AuthManager');
        return new Permission($pm, $pdm, $auth);
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
        $ss = $useIndex ? $sm->getServiceLocator()->get('VuFind\Search') : null;
        return new Reserves($useIndex, $ss);
    }

    /**
     * Construct the ResultScroller plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ResultScroller
     */
    public static function getResultScroller(ServiceManager $sm)
    {
        return new ResultScroller(
            new \Zend\Session\Container(
                'ResultScroller',
                $sm->getServiceLocator()->get('VuFind\SessionManager')
            ),
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
        );
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
            $sm->getServiceLocator()->get('VuFind\HMAC'),
            $sm->getServiceLocator()->get('VuFind\SessionManager')
        );
    }
}
