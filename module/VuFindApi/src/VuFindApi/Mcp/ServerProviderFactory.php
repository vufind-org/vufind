<?php

/**
 * ServerProviderFactory for Model Context Protocol (MCP)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindApi\Mcp;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Config\YamlReader;

/**
 * ServerProviderFactory for Model Context Protocol (MCP)
 *
 * @category VuFind
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ServerProviderFactory implements FactoryInterface
{
    /**
     * MCP Config name
     */
    protected string $mcpConfigName = 'ModelContextProtocol';

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
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $yamlReader = $container->get(YamlReader::class);
        $mcpConfig = $yamlReader->get($this->mcpConfigName . '.yaml');

        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $topConfig = $configManager->getConfigObject('config');

        $services = array_map(
            fn ($className) => $container->get($className),
            [
                \VuFind\Config\YamlReader::class,
                \VuFind\Record\Loader::class,
                \VuFindApi\Formatter\RecordFormatter::class,
                \VuFind\Search\SearchRunner::class,
                \VuFind\Http\RouteHelper::class,
                \VuFind\Http\ServerUrlHelper::class,
            ]
        );

        return new $requestedName(
            $mcpConfig,
            $topConfig,
            $services,
        );
    }
}
