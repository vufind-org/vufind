<?php
/**
 * Factory for Blender search params objects.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search\Blender;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;

/**
 * Factory for Solr search params objects.
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParamsFactory extends \VuFind\Search\Params\ParamsFactory
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $facetHelper
            = $container->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class);
        $configLoader = $container->get(\VuFind\Config\PluginManager::class);
        $blenderConfig = $configLoader->get('Blender');
        if (!isset($blenderConfig['Secondary']['backend'])) {
            throw new \Exception('Secondary backend not defined in blender.ini');
        }
        $secondary = $blenderConfig['Secondary']['backend'];
        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $blenderMappings = $yamlReader->get("BlenderMappings$secondary.yaml");
        if (empty($blenderMappings)) {
            $blenderMappings = $yamlReader->get("BlenderMappings.yaml");
        }
        $paramsMgr = $container->get(\VuFind\Search\Params\PluginManager::class);
        $secondaryParams = $paramsMgr->get(
            'VuFind\\Search\\' . $blenderConfig['Secondary']['backend']
            . '\\Params'
        );
        return parent::__invoke(
            $container,
            $requestedName,
            [$facetHelper, $secondaryParams, $blenderConfig, $blenderMappings]
        );
    }
}
