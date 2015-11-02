<?php
/**
 * Search results plugin factory
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Search\Results;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Search results plugin factory
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class PluginFactory extends \VuFind\Search\Results\PluginFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->defaultNamespace = 'Finna\Search';
    }

    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        $class = $this->getClassName($name, $requestedName);
        if (class_exists($class)) {
            $params = $serviceLocator->getServiceLocator()
                ->get('VuFind\SearchParamsPluginManager')->get($requestedName);
            return new $class($params);
        } else {
            $this->defaultNamespace = 'VuFind\Search';
            return parent::createServiceWithName(
                $serviceLocator, $name, $requestedName
            );
        }
    }
}
