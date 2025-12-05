<?php

/**
 * ServerProvider for Model Context Protocol (MCP)
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

use Laminas\ServiceManager\ServiceLocatorInterface;
use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;

/**
 * ServerProvider for Model Context Protocol (MCP)
 *
 * @category VuFind
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ServerProvider
{
    /**
     * MCP Server
     */
    private Server $server;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     */
    public function __construct(protected ServiceLocatorInterface $serviceLocator)
    {
        $container = new Container();
        foreach (
            [
            \VuFind\Record\Loader::class,
            \VuFindApi\Formatter\RecordFormatter::class,
            \VuFind\Search\SearchRunner::class,
            ] as $class
        ) {
            // Provide these services to each capability class constructor
            $container->set($class, $serviceLocator->get($class));
        }

        $this->server = Server::builder()
            ->setServerInfo(name: 'VuFind Server', version: '0.0.1', description: 'The library catalog')
            ->setDiscovery(__DIR__, ['../Mcp'])
            ->setSession(new FileSessionStore(LOCAL_CACHE_DIR . '/mcp/session'))
            ->setContainer($container)
            ->build();
    }

    /**
     * Return the MCP Server instance.
     *
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }
}
