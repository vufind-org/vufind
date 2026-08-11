<?php

/**
 * ServerProvider for Model Context Protocol (MCP).
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
use Psr\Log\LoggerInterface;
use VuFind\Config\Config;
use VuFind\Config\YamlReader;
use VuFind\Exception\ConfigException;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Formatter\RecordFormatter;

/**
 * ServerProvider for Model Context Protocol (MCP).
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
     * MCP Server, or null if the server is not enabled.
     */
    protected ?Server $server = null;

    /**
     * Directory to store MCP session files in.
     */
    protected string $sessionDir = LOCAL_CACHE_DIR . '/mcp/session';

    /**
     * Constructor.
     *
     * @param array           $mcpConfig       MCP configuration
     * @param Config          $topConfig       config.ini
     * @param YamlReader      $yamlReader      YAML configuration reader
     * @param Loader          $recordLoader    Record loader
     * @param RecordFormatter $recordFormatter Record formatter
     * @param SearchRunner    $searchRunner    Search runner
     * @param RouteHelper     $routeHelper     Route helper
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     * @param LoggerInterface $logger          Logger
     */
    #[Autowire]
    public function __construct(
        #[Autowire(config: 'ModelContextProtocol', configType: 'yaml')]
        protected array $mcpConfig,
        #[Autowire(config: 'config', configType: 'object')]
        protected Config $topConfig,
        YamlReader $yamlReader,
        Loader $recordLoader,
        RecordFormatter $recordFormatter,
        SearchRunner $searchRunner,
        RouteHelper $routeHelper,
        ServerUrlHelper $serverUrlHelper,
        #[Autowire(service: 'VuFind\Logger')]
        protected LoggerInterface $logger,
    ) {
        if (!($this->mcpConfig['General']['enabled'] ?? false)) {
            return;
        }

        $services = [
            $yamlReader,
            $recordLoader,
            $recordFormatter,
            $searchRunner,
            $routeHelper,
            $serverUrlHelper,
        ];
        $container = new Container();
        foreach ($services as $service) {
            // Provide these services to each capability class constructor
            $container->set($service::class, $service);
        }

        $builder = Server::builder()
            ->setSession(new FileSessionStore($this->sessionDir))
            ->setContainer($container)
            ->setLogger($this->logger);
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
        foreach (($this->mcpConfig['ResourceTemplates'] ?? []) as $name => $resourceTemplate) {
            $className = $this->getRequiredSetting($resourceTemplate, 'class', $name);
            $functionName = $this->getRequiredSetting($resourceTemplate, 'function', $name);
            $uriTemplate = $this->getRequiredSetting($resourceTemplate, 'uriTemplate', $name);
            $title = $resourceTemplate['title'] ?? null;
            $description = $resourceTemplate['description'] ?? null;
            $builder->addResourceTemplate(
                [$className, $functionName],
                uriTemplate: $uriTemplate,
                name: $name,
                title: $title,
                description: $description,
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
            $className = $this->getRequiredSetting($tool, 'class', $name);
            $functionName = $this->getRequiredSetting($tool, 'function', $name);
            $title = $tool['title'] ?? null;
            $description = $tool['description'] ?? null;
            $inputSchema = $tool['inputSchema'] ?? null;
            $builder->addTool(
                [$className, $functionName],
                name: $name,
                title: $title,
                description: $description,
                inputSchema: $inputSchema
            );
        }
    }

    /**
     * Get a required setting from an MCP capability config entry (a Tool or ResourceTemplate
     * definition), or throw a clear configuration error if it is missing.
     *
     * @param array  $entry     Config entry
     * @param string $key       Required setting name
     * @param string $entryName Name of the entry (its heading in ModelContextProtocol.yaml), for the
     * error message
     *
     * @return mixed
     *
     * @throws ConfigException
     */
    protected function getRequiredSetting(array $entry, string $key, string $entryName): mixed
    {
        if (!($entry[$key] ?? null)) {
            throw new ConfigException(
                "ModelContextProtocol.yaml: '$entryName' is missing required setting '$key'."
            );
        }
        return $entry[$key];
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
        return $this->server;
    }
}
