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
use Mcp\Exception\ResourceReadException;
use VuFind\Record\Loader;
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
    public function __construct(protected Loader $recordLoader, protected RecordFormatter $recordFormatter)
    {
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
     * Retrieve a record by record ID.
     *
     * @param string $recordId The record ID
     *
     * @return array The record
     */
    #[McpResourceTemplate(
        uriTemplate: 'record://{recordId}',
        name: 'record',
        description: 'Get a catalog record by its ID.',
        mimeType: 'application/json'
    )]
    public function getRecord(string $recordId): array
    {
        if (!$recordId) {
            throw new InvalidArgumentException('Record ID required.');
        }

        try {
            $searchClassId = 'Solr';
            $record = $this->recordLoader->load($recordId, $searchClassId);
        } catch (Exception $e) {
            throw new ResourceReadException(message: "Record not found for ID: {$recordId}", previous: $e);
        }

        $fields = ['title', 'authors', 'publicationDates'];
        $formattedRecord = $this->recordFormatter->format([$record], $fields)[0];
        return $formattedRecord;
    }
}
