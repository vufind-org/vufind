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
     */
    public function __construct(protected ServiceLocatorInterface $serviceLocator)
    {
        $container = new Container();

        $recordLoader = $serviceLocator->get(\VuFind\Record\Loader::class);
        $container->set(\VuFind\Record\Loader::class, $recordLoader);

        $recordFormatter = $serviceLocator->get(\VuFindApi\Formatter\RecordFormatter::class);
        $container->set(\VuFindApi\Formatter\RecordFormatter::class, $recordFormatter);

        $this->server = Server::builder()
            ->setServerInfo('VuFind Server', '0.0.1')
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
