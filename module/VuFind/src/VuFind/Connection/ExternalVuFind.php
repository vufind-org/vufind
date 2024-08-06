<?php

/**
 * External VuFind API connection class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Connection
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Connection;

use Exception;
use Laminas\Log\LoggerAwareInterface;

/**
 * External VuFind API connection class.
 *
 * @category VuFind
 * @package  Connection
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ExternalVuFind implements
    \VuFindHttp\HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base URL of the LibGuides API
     *
     * @var string
     */
    protected $baseUrl = null;

    /**
     * Set the API base URL.
     *
     * @param string $baseUrl The base url
     *
     * @return void
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Execute a search against the remote VuFind API.
     *
     * @param string $queryString   Query string
     * @param string $requestParam  Request parameter for the query string
     * @param int    $limit         Maximum number of results to return
     * @param array  $searchFilters Query filters
     *
     * @return array The JSON-decoded response from the API.
     */
    public function search(
        string $queryString,
        string $requestParam,
        int $limit,
        array $searchFilters = []
    ): array {
        if (!$this->baseUrl) {
            $this->logError('Must call setBaseUrl() before searching.');
            return [];
        }

        $params = [];
        $params[] = $requestParam . '=' . urlencode($queryString);
        $params[] = "limit=$limit";

        foreach ($searchFilters as $filter) {
            $params[] = 'filter[]=' . urlencode($filter);
        }

        try {
            $response = $this->httpService->get($this->baseUrl . '/search', $params);
        } catch (Exception $ex) {
            $this->logError(
                'Exception during request: ' .
                $ex->getMessage()
            );
            return [];
        }

        if ($response->isServerError()) {
            $this->logError(
                'ExternalVuFind API HTTP Error: ' .
                $response->getStatusCode()
            );
            return [];
        }

        $responseData = trim($response->getBody());
        $arr = json_decode($responseData, true);
        return $arr ?? [];
    }
}
