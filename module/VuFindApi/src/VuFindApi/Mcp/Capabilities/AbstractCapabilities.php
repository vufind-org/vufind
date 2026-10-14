<?php

/**
 * Abstract capability provider for Model Context Protocol (MCP).
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

namespace VuFindApi\Mcp\Capabilities;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFindApi\Formatter\RecordFormatter;

/**
 * Abstract capability provider for Model Context Protocol (MCP).
 *
 * @category VuFind
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractCapabilities
{
    /**
     * Config filename.
     */
    protected string $configName = 'ModelContextProtocol';

    /**
     * Config for MCP.
     */
    protected array $config;

    /**
     * Constructor.
     *
     * @param ConfigManagerInterface $configManager   Config manager
     * @param Loader                 $recordLoader    Record loader
     * @param RecordFormatter        $recordFormatter Record formatter
     * @param SearchRunner           $searchRunner    Search runner
     */
    public function __construct(
        protected ConfigManagerInterface $configManager,
        protected Loader $recordLoader,
        protected RecordFormatter $recordFormatter,
        protected SearchRunner $searchRunner
    ) {
        $this->config = $this->configManager->getConfigArray($this->configName);
    }
}
