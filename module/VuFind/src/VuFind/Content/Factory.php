<?php

/**
 * Factory for instantiating content loaders
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for instantiating content loaders
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Get the configuration setting name to get content provider settings.
     *
     * @param string $name Requested service name
     *
     * @return string
     */
    protected function getConfigSettingName($name)
    {
        // Account for one special exception:
        $lcName = strtolower($name);
        return $lcName === 'authornotes' ? 'authorNotes' : $lcName;
    }

    /**
     * Get the plugin manager service name to build a content provider service.
     *
     * @param string $name Requested service name
     *
     * @return string
     */
    protected function getPluginManagerServiceName($name)
    {
        $lcName = strtolower($name);
        // Account for two special legacy exceptions:
        $exceptions = ['authornotes' => 'AuthorNotes', 'toc' => 'TOC'];
        $formattedName = $exceptions[$lcName] ?? ucfirst($lcName);
        return 'VuFind\Content\\' . $formattedName . '\PluginManager';
    }

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
     * @throws ContainerException&\Throwable if any other error occurs
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
        $pm = $container->get($this->getPluginManagerServiceName($requestedName));
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config');
        $setting = $this->getConfigSettingName($requestedName);
        $providers = $config->Content->$setting ?? '';
        return new Loader($pm, $providers);
    }
}
