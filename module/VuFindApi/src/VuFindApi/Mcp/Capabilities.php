<?php

/**
 * Capabilities (stub) for Model Context Protocol (MCP)
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

use Exception;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\InvalidArgumentException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use VuFind\Config\YamlReader;
use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFindApi\Formatter\RecordFormatter;

/**
 * Capabilities (stub) for Model Context Protocol (MCP)
 *
 * @category VuFind
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Capabilities
{
    /**
     * Search class Id
     */
    protected string $searchClassId = 'Solr';

    /**
     * Record fields to return
     */
    protected array $responseFields = ['recordPageFullUrl', 'title', 'authors'];

    /**
     * Limit for searches
     */
    protected int $limit = 50;

    /**
     * Config filename
     */
    protected string $configName = 'ModelContextProtocol';

    /**
     * Config for MCP
     */
    protected array $config;

    /**
     * Constructor
     *
     * @param YamlReader      $yamlReader      YAML reader
     * @param Loader          $recordLoader    Record loader
     * @param RecordFormatter $recordFormatter Record formatter
     * @param SearchRunner    $searchRunner    Search runner
     */
    public function __construct(
        protected YamlReader $yamlReader,
        protected Loader $recordLoader,
        protected RecordFormatter $recordFormatter,
        protected SearchRunner $searchRunner
    ) {
        $this->config = $this->yamlReader->get($this->configName . '.yaml');

        $this->responseFields = $this->config['ResponseFields'] ?? $this->responseFields;
    }

    /**
     * Add two numbers.  It's AI-powered magic!
     *
     * @param int $a One of those super interesting numbers
     * @param int $b A second really fantastic number
     *
     * @return int An even more amazing number that magically combines the first two!!!
     */
    #[McpTool]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Search records by keywords and content type. Input schema and response fields are defined
     * in config file.
     *
     * @param string  $keywords    Keywords to search for
     * @param ?string $contentType A content type from the resources defined in the schema.
     *
     * @return array The records found for this search
     */
    public function searchRecords(string $keywords, ?string $contentType = null): array
    {
        $limit = $this->limit;
        $rawRequest = ['lookfor' => urldecode($keywords)];
        if ($contentType) {
            if ($filter = $this->config['ContentTypes'][$contentType]['filter'] ?? null) {
                $rawRequest['filter'] = $filter;
            }
            else {
                throw new ResourceNotFoundException('Unknown content type: ' . $contentType);
            }
        }
        
        $results = $this->searchRunner->run(
            $rawRequest,
            $this->searchClassId,
            function (
                $runner,
                $params,
                $searchId,
                $results
            ) use (
                $limit
            ): void {
                $results->overrideStartRecord(1);
                $params->setLimit($limit);
            }
        );
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            throw new ResourceNotFoundException('No records found');
        }

        $records = $this->recordFormatter->format(
            $results->getResults(),
            $this->responseFields
        );
        return $records;
    }

    /**
     * Retrieve a record by record ID. Uri and other parameters are defined in config.
     *
     * @param string $recordId The record ID
     *
     * @return array The record
     */
    #[McpResourceTemplate(
        uriTemplate: 'catalog://record/{recordId}',
        name: 'getRecord',
        description: 'Get a catalog record by its ID.',
        mimeType: 'application/json'
    )]
    public function getRecord(string $recordId): array
    {
        if (!$recordId) {
            throw new InvalidArgumentException('Record ID required.');
        }

        try {
            $record = $this->recordLoader->load($recordId, $this->searchClassId);
        } catch (Exception $e) {
            throw new ResourceReadException(message: "Record not found for ID: {$recordId}", previous: $e);
        }

        $formattedRecord = $this->recordFormatter->format([$record], $this->responseFields)[0];
        return $formattedRecord;
    }
}
