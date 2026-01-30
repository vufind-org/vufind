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

use Mcp\Capability\Registry\Container;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Session\FileSessionStore;
use VuFind\Config\Config;

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
    protected Server $server;

    /**
     * Constructor
     *
     * @param array  $mcpConfig MCP configuration
     * @param Config $topConfig config.ini
     * @param array  $services  Services to register with the MCP container
     */
    public function __construct(
        protected array $mcpConfig,
        protected Config $topConfig,
        array $services
    ) {
        if (!($this->mcpConfig['General']['enabled'] ?? false)) {
            return;
        }

        $container = new Container();
        foreach ($services as $service) {
            // Provide these services to each capability class constructor
            $container->set($service::class, $service);
        }

        $builder = Server::builder()
            ->setSession(new FileSessionStore(LOCAL_CACHE_DIR . '/mcp/session'))
            ->setContainer($container);
        $this->setServerInfo($builder);
        $this->addResourceTemplates($builder);
        $this->addTools($builder);
        $this->addAutoDiscovery($builder);
        $this->server = $builder->build();
    }

    /**
     * Add server info metadata to the Server Builder.
     *
     * @param Builder $builder The server builder
     *
     * @return void
     */
    protected function setServerInfo(Builder $builder): void
    {
        $name = $this->mcpConfig['General']['name'] ?? 'VuFind® Server';

        $baseVersion = $this->topConfig['Site']['generator'];
        $baseVersion = str_replace('VuFind ', '', $baseVersion);
        $version = $baseVersion . ($this->mcpConfig['General']['versionSuffix'] ?? '');

        $description = $this->mcpConfig['General']['description'] ?? $this->topConfig['Site']['title'];

        $builder->setServerInfo(name: $name, version: $version, description: $description);
    }

    /**
     * Add resource templates from config to the Server Builder.
     *
     * @param Builder $builder The server builder
     *
     * @return void
     */
    protected function addResourceTemplates(Builder $builder): void
    {
        foreach (($this->mcpConfig['ResourceTemplates'] ?? []) as $resourceTemplate) {
            $className = $resourceTemplate['class'];
            $functionName = $resourceTemplate['function'];
            $uriTemplate = $resourceTemplate['uriTemplate'];
            $builder->addResourceTemplate(
                [$className, $functionName],
                uriTemplate: $uriTemplate,
            );
        }
    }

    /**
     * Add tools from config to the Server Builder.
     *
     * @param Builder $builder The server builder
     *
     * @return void
     */
    protected function addTools(Builder $builder): void
    {
        foreach (($this->mcpConfig['Tools'] ?? []) as $name => $tool) {
            $description = $tool['description'];
            $className = $tool['class'];
            $functionName = $tool['function'];
            $inputSchema = $tool['inputSchema'];
            $builder->addTool(
                [$className, $functionName],
                name: $name,
                description: $description,
                inputSchema: $inputSchema
            );
        }
    }

    /**
     * Set the server builder to auto-discover capabilities in configured folders.
     *
     * @param Builder $builder The server builder
     *
     * @return void
     */
    protected function addAutoDiscovery(Builder $builder): void
    {
        if ($discovery = ($this->mcpConfig['AutoDiscovery'] ?? [])) {
            $params = [$discovery['basePath'] ?? __DIR__];
            if ($scanDirs = $discovery['scanDirs'] ?? []) {
                $params['scanDirs'] = $scanDirs;
            }
            if ($excludeDirs = $discovery['excludeDirs'] ?? []) {
                $params['excludeDirs'] = $excludeDirs;
            }
            $builder->setDiscovery(...$params);
        }
    }

    /**
     * Return the MCP Server instance, or null if the server is not enabled.
     *
     * @return ?Server
     */
    public function getServer(): ?Server
    {
        return $this->server ?? null;
    }
}
