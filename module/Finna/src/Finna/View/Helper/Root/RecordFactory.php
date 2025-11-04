<?php

/**
 * Record helper factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2019.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stdlib\Parameters;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Tags\TagsService;

/**
 * Record helper factory.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordFactory implements FactoryInterface
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
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $helper = new Record(
            $container->get(TagsService::class),
            $container->get(\VuFind\Config\PluginManager::class)->get('config'),
            $container->get(\VuFind\Record\Loader::class),
            $container->get('ViewHelperManager')->get('recordImage'),
            $container->get(\Finna\Search\Solr\AuthorityHelper::class),
            $container->get('ViewHelperManager')->get('url'),
            $container->get('ViewHelperManager')->get('recordLinker'),
            $container->get(\Finna\RecordTab\TabManager::class),
            $container->get(\VuFind\Form\Form::class),
            $container->get(\Finna\Service\UserPreferenceService::class),
            function ($options) use ($container) {
                $result = $container
                    ->get(\VuFind\Search\Results\PluginManager::class)
                    ->get(\Finna\Search\EncapsulatedRecords\Results::class);
                $result->getParams()->initFromRequest(new Parameters($options));
                return $result;
            }
        );
        if ('cli' !== php_sapi_name()) {
            $helper->setCoverRouter(
                $container->get(\VuFind\Cover\Router::class)
            );
        }
        $helper->setSearchMemory($container->get(\VuFind\Search\Memory::class));
        return $helper;
    }
}
