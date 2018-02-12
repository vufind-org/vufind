<?php
/**
 * Factory for controllers.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Controller;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends GenericFactory
{
    /**
     * Construct the BrowseController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return BrowseController
     */
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm,
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Construct the CartController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return BrowseController
     */
    public static function getCartController(ServiceManager $sm)
    {
        return new CartController(
            $sm,
            new \Zend\Session\Container(
                'cart_followup',
                $sm->get('Zend\Session\SessionManager')
            )
        );
    }

    /**
     * Construct the CollectionController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionController
     */
    public static function getCollectionController(ServiceManager $sm)
    {
        return new CollectionController(
            $sm,
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Construct the CollectionsController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionsController
     */
    public static function getCollectionsController(ServiceManager $sm)
    {
        return new CollectionsController(
            $sm,
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Construct the IndexController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getIndexController(ServiceManager $sm)
    {
        return new IndexController(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            $sm->get('VuFind\Auth\Manager')
        );
    }

    /**
     * Construct the RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm,
            $sm->get('VuFind\Config\PluginManager')->get('config')
        );
    }

    /**
     * Construct the UpgradeController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return UpgradeController
     */
    public static function getUpgradeController(ServiceManager $sm)
    {
        return new UpgradeController(
            $sm,
            $sm->get('VuFind\Cookie\CookieManager'),
            new \Zend\Session\Container(
                'upgrade', $sm->get('Zend\Session\SessionManager')
            )
        );
    }
}
