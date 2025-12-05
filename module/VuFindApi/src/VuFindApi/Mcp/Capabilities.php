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
    protected array $recordFields = ['title', 'authors', 'publicationDates'];

    /**
     * Limit for searches
     */
    protected $limit = 50;

    /**
     * Constructor
     *
     * @param Loader          $recordLoader    Record loader
     * @param RecordFormatter $recordFormatter Record formatter
     * @param SearchRunner    $searchRunner    Search runner
     */
    public function __construct(
        protected Loader $recordLoader,
        protected RecordFormatter $recordFormatter,
        protected SearchRunner $searchRunner
    ) {
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
     * Search records by keywords
     *
     * @param string $keywords Keywords
     *
     * @return array The records found
     */
    #[McpResourceTemplate(
        uriTemplate: 'catalog://record/{keywords}',
        name: 'searchRecords',
        description: 'Search catalog records by keywords.',
        mimeType: 'application/json'
    )]
    public function searchRecords($keywords)
    {
        $limit = $this->limit;
        $results = $this->searchRunner->run(
            ['lookfor' => urldecode($keywords)],
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
            $this->recordFields
        );
        return $records;
    }

    /**
     * Retrieve a record by record ID.
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

        $formattedRecord = $this->recordFormatter->format([$record], $this->recordFields)[0];
        return $formattedRecord;
    }
}
