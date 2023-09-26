<?php

/**
 * Orb cover loader factory
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Content
 * @author   Frédéric Demians <f.demians@tamil.fr>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:content_provider_components
 */

namespace VuFind\Content\Covers;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Orb cover loader factory
 *
 * @category VuFind
 * @package  Content
 * @author   Frédéric Demians <f.demians@tamil.fr>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:content_provider_components
 */
class OrbFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $configPluginManager = $container->get(\VuFind\Config\PluginManager::class);
        $config = $configPluginManager->get('config');
        $url = $config->Orb->url ?? 'api.base-orb.fr/v1';
        if (!isset($config->Orb->user)) {
            throw new \Exception("Orb 'user' not set in VuFind config");
        }
        if (!isset($config->Orb->key)) {
            throw new \Exception("Orb 'key' not set in VuFind config");
        }
        $orb = new $requestedName($url, $config->Orb->user, $config->Orb->key);
        $cachingDownloader = $container->get(\VuFind\Http\CachingDownloader::class);
        $orb->setCachingDownloader($cachingDownloader);
        return $orb;
    }
}
