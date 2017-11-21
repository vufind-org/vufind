<?php
/**
 * Factory for controllers.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Controller\Factory
{
    /**
     * Construct a generic controller.
     *
     * @param string         $name Name of table to construct (fully qualified
     * class name, or else a class name within the current namespace)
     * @param ServiceManager $sm   Service manager
     *
     * @return object
     */
    public static function getGenericController($name, ServiceManager $sm)
    {
        // Prepend the current namespace unless we receive a FQCN:
        $class = (strpos($name, '\\') === false)
            ? __NAMESPACE__ . '\\' . $name : $name;
        if (!class_exists($class) && strpos($name, '\\') === false) {
            $class = "\\VuFind\\Controller\\$name";
        }
        if (!class_exists($class)) {
            throw new \Exception('Cannot construct ' . $class);
        }
        return new $class($sm->getServiceLocator());
    }

    /**
     * Construct the BrowseController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return BrowseController
     */
    public static function getBrowseController(ServiceManager $sm)
    {
        $serviceLocator = $sm->getServiceLocator();
        return new BrowseController(
            $serviceLocator,
            $serviceLocator->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the CacheController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return BrowseController
     */
    public static function getCacheController(ServiceManager $sm)
    {
        $serviceLocator = $sm->getServiceLocator();
        return new CacheController(
            $serviceLocator,
            $serviceLocator->get('VuFind\DbTablePluginManager')->get('FinnaCache'),
            $serviceLocator->get('VuFindTheme\ThemeInfo')
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
        $serviceLocator = $sm->getServiceLocator();
        return new RecordController(
            $serviceLocator,
            $serviceLocator->get('VuFind\Config')->get('config')
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
        $serviceLocator = $sm->getServiceLocator();
        return new CollectionController(
            $serviceLocator,
            $serviceLocator->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the CartController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CartController
     */
    public static function getCartController(ServiceManager $sm)
    {
        $serviceLocator = $sm->getServiceLocator();
        return new CartController(
            $serviceLocator,
            new \Zend\Session\Container(
                'cart_followup',
                $serviceLocator->get('VuFind\SessionManager')
            )
        );
    }

    /**
     * Construct the ListController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ListController
     */
    public static function getListController(ServiceManager $sm)
    {
        $serviceLocator = $sm->getServiceLocator();
        return new ListController(
            $serviceLocator,
            $serviceLocator->get('VuFind\SessionManager')
        );
    }

    /**
     * Construct the MyResearchController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MyResearchController
     */
    public static function getMyResearchController(ServiceManager $sm)
    {
        $serviceLocator = $sm->getServiceLocator();
        return new MyResearchController(
            $serviceLocator,
            $serviceLocator->get('VuFind\SessionManager')
        );
    }
}
