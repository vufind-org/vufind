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
use Mcp\Server\Builder;
use Mcp\Server\Session\FileSessionStore;
use VuFind\Config\YamlReader;

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
     * Config name
     */
    private string $configName = 'ModelContextProtocol';

    /**
     * Config array
     */
    protected array $config;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     */
    public function __construct(protected ServiceLocatorInterface $serviceLocator)
    {
        $yamlReader = $serviceLocator->get(YamlReader::class);
        $this->config = $yamlReader->get($this->configName . '.yaml');

        if (!($this->config['General']['enabled'] ?? false)) {
            return;
        }

        $container = new Container();
        foreach (
            [
            \VuFind\Config\YamlReader::class,
            \VuFind\Record\Loader::class,
            \VuFindApi\Formatter\RecordFormatter::class,
            \VuFind\Search\SearchRunner::class,
            ] as $class
        ) {
            // Provide these services to each capability class constructor
            $container->set($class, $serviceLocator->get($class));
        }

        $builder = Server::builder()
            ->setServerInfo(name: 'VuFind Server', version: '0.0.1', description: 'The library catalog')
            ->setSession(new FileSessionStore(LOCAL_CACHE_DIR . '/mcp/session'))
            ->setContainer($container);
        $this->addResourceTemplates($builder);
        $this->addTools($builder);
        $this->addAutoDiscovery($builder);
        $this->server = $builder->build();
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
        foreach (($this->config['ResourceTemplates'] ?? []) as $resourceTemplate) {
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
        foreach (($this->config['Tools'] ?? []) as $name => $tool) {
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
        if ($discovery = ($this->config['AutoDiscovery'] ?? [])) {
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
