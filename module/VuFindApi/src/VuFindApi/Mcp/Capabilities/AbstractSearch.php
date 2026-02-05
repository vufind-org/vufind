<?php

/**
 * Abstract search capability provider for Model Context Protocol (MCP)
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

use Exception;
use Mcp\Exception\InvalidArgumentException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use VuFind\Config\YamlReader;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFindApi\Formatter\RecordFormatter;

/**
 * Abstract search capability provider for Model Context Protocol (MCP)
 *
 * @category VuFind
 * @package  Mcp
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractSearch extends AbstractCapabilities
{
    /**
     * Record fields to return
     */
    protected array $responseFields = ['recordPageAbsoluteLink', 'title', 'authors'];

    /**
     * Limit for searches
     */
    protected int $limit = 20;

    /**
     * Constructor
     *
     * @param YamlReader      $yamlReader      YAML reader
     * @param Loader          $recordLoader    Record loader
     * @param RecordFormatter $recordFormatter Record formatter
     * @param SearchRunner    $searchRunner    Search runner
     * @param RouteHelper     $routeHelper     Route helper
     * @param ServerUrlHelper $serverUrlHelper Server URL helper
     */
    public function __construct(
        protected YamlReader $yamlReader,
        protected Loader $recordLoader,
        protected RecordFormatter $recordFormatter,
        protected SearchRunner $searchRunner,
        protected RouteHelper $routeHelper,
        protected ServerUrlHelper $serverUrlHelper
    ) {
        parent::__construct($yamlReader, $recordLoader, $recordFormatter, $searchRunner);
        $this->responseFields = $this->config['ResponseFields'] ?? $this->responseFields;
    }

    /**
     * Get the search class ID.
     *
     * @return string
     */
    abstract protected function getSearchClassId(): string;

    /**
     * Get the route name to perform a search.
     *
     * @return string
     */
    abstract protected function getSearchActionRoute(): string;

    /**
     * Return the request parameter name.
     *
     * @return string
     */
    protected function getRequestParam(): string
    {
        return 'lookfor';
    }

    /**
     * Search records by keywords and content type. Input schema and response fields are defined
     * in config file.
     *
     * @param string  $keywords    Keywords to search for
     * @param ?string $contentType A content type from the resources defined in the schema.
     *
     * @return array The records found for this search, and a URL to the search results page
     */
    public function searchRecords(string $keywords, ?string $contentType = null): array
    {
        $rawRequest = [$this->getRequestParam() => $keywords];
        if ($contentType) {
            if ($filter = $this->config['ContentTypes'][$contentType]['filter'] ?? null) {
                $rawRequest['filter'] = $filter;
            } else {
                throw new ResourceNotFoundException('Unknown content type: ' . $contentType);
            }
        }

        $results = $this->searchRunner->run(
            $rawRequest,
            $this->getSearchClassId(),
            function (
                $runner,
                $params,
                $searchId,
                $results
            ): void {
                $results->overrideStartRecord(1);
                $params->setLimit($this->limit);
            }
        );
        if ($results instanceof \VuFind\Search\EmptySet\Results) {
            throw new ResourceNotFoundException('No records found');
        }

        $records = $this->recordFormatter->format(
            $results->getResults(),
            $this->responseFields
        );
        $resultsPage = $this->serverUrlHelper->getBaseUrl() .
            $this->routeHelper->getUrlFromRoute(
                $this->getSearchActionRoute(),
                [],
                [$this->getRequestParam() => urlencode($keywords)]
            );
        return [
            'search_results' => $records,
            'search_results_page' => $resultsPage,
        ];
    }

    /**
     * Retrieve a record by record ID. Uri and other parameters are defined in config.
     *
     * @param string $recordId The record ID
     *
     * @return array The record
     */
    public function getRecord(string $recordId): array
    {
        if (!$recordId) {
            throw new InvalidArgumentException('Record ID required.');
        }

        try {
            $record = $this->recordLoader->load($recordId, $this->getSearchClassId());
        } catch (Exception $e) {
            throw new ResourceReadException(message: "Record not found for ID: {$recordId}", previous: $e);
        }

        $formattedRecord = $this->recordFormatter->format([$record], $this->responseFields)[0];
        return $formattedRecord;
    }
}
