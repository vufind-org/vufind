<?php
/**
 * Finto connection class.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Connection;

use Laminas\Config\Config;
use Laminas\Http\Client;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Log\LoggerAwareTrait;

/**
 * Finto connection class.
 *
 * @category VuFind
 * @package  Connection
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Finto implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Key for result type.
     *
     * @var string
     */
    public const RESULT_TYPE = 'result_type';

    /**
     * Result type value for non-descriptor results.
     *
     * @var string
     */
    public const TYPE_NONDESCRIPTOR = 'nondescriptor';

    /**
     * Result type value for specifier results.
     *
     * @var string
     */
    public const TYPE_SPECIFIER = 'specifier';

    /**
     * Result type value for hyponym results.
     *
     * @var string
     */
    public const TYPE_HYPONYM = 'hyponym';

    /**
     * Result type value for other results.
     *
     * @var string
     */
    public const TYPE_OTHER = 'other';

    /**
     * Key for results.
     *
     * @var string
     */
    public const RESULTS = 'results';

    /**
     * Key for narrower results.
     *
     * @var string
     */
    public const NARROWER_RESULTS = 'narrower_results';

    /**
     * Finto configuration.
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * HTTP client.
     *
     * @var \Laminas\Http\Client
     */
    protected $client;

    /**
     * Finto constructor.
     *
     * @param Config $config Finto configuration
     * @param Client $client HTTP client
     */
    public function __construct($config, Client $client)
    {
        $this->config = $config ?? new Config([]);
        $this->client = $client;

        // Set options
        $this->client->setOptions(
            [
                'timeout' => $this->config->get('http_timeout', 30),
                'useragent' => 'VuFind',
                'keepalive' => true
            ]
        );

        // Set Accept header
        $this->client->getRequest()->getHeaders()->addHeaderLine(
            'Accept', 'application/json'
        );
    }

    /**
     * Is the language supported by Finto.
     *
     * Can be used to determine whether to make an API call or not.
     *
     * @param string $lang Language code, e.g. "en" or "fi"
     *
     * @return bool
     */
    public function isSupportedLanguage(string $lang): bool
    {
        return in_array($lang, ['fi', 'sv', 'en']);
    }

    /**
     * Search concepts and collections by query term.
     *
     * @param string      $query The term to search for
     * @param string|null $lang  Language of labels to match, e.g. "en" or "fi"
     * @param array|null  $other Keyed array of other parameters accepted by Finto
     *                           API's /search method
     *
     * @return array Results
     * @throws \Exception
     */
    public function search(
        string $query, ?string $lang = null, ?array $other = null
    ): array {
        // Set default values for parameters.
        $params = [
            'vocab' => 'yso',
        ];

        // Override defaults with other provided values.
        if (is_array($other)) {
            $params = array_merge($params, $other);
        }
        $params['query'] = trim($query);
        if (!empty($lang)) {
            $params['lang'] = $lang;
        }

        // Make request and return results.
        return $this->makeRequest(['search'], $params);
    }

    /**
     * Narrower concepts of the requested concept.
     *
     * @param string      $vocid A Skosmos vocabulary identifier e.g. "stw" or "yso"
     * @param string      $uri   URI of the concept whose narrower concept to return
     * @param string|null $lang  Label language, e.g. "en" or "fi"
     * @param boolean     $sort  Whether to sort results alphabetically or not
     *
     * @return array Results
     * @throws \Exception
     */
    public function narrower(
        string $vocid, string $uri, ?string $lang = null, bool $sort = false
    ): array {
        // Set parameters.
        $params = ['vocid' => $vocid, 'uri' => $uri];
        if (!empty($lang)) {
            $params['lang'] = $lang;
        }

        // Make request.
        $response = $this->makeRequest([$vocid, 'narrower'], $params);

        // Sort values alphabetically if required.
        if ($sort && $lang && !empty($response['narrower'])) {
            $collator = collator_create($lang);
            usort(
                $response['narrower'],
                function ($a, $b) use ($collator) {
                    return $collator->compare($a['prefLabel'], $b['prefLabel']);
                }
            );
        }

        return $response['narrower'];
    }

    /**
     * Search concepts and collections by query term. Extend results with result type
     * and results from possible further queries.
     *
     * @param string      $query    The term to search for
     * @param string|null $lang     Language of labels to match, e.g. "en" or "fi"
     * @param array|null  $other    Keyed array of other parameters accepted by
     *                              Finto API's /search method
     * @param bool        $narrower Look for narrower concepts if applicable
     *
     * @return array Extended results or empty array if none
     * @throws \Exception
     */
    public function extendedSearch(
        string $query, ?string $lang = null, ?array $other = null,
        bool $narrower = true
    ): array {
        // Set up extended results array.
        $extendedResults = [];

        // Make search query.
        $results = $this->search($query, $lang, $other);

        // Early return if there is no results.
        if (count($results['results']) === 0) {
            return $extendedResults;
        }

        // Set results.
        $extendedResults[Finto::RESULTS] = $results;

        // Determine type and do further queries if applicable.
        if (count($results['results']) > 1) {
            // If there are multiple results they are considered to be
            // specifier results.
            $extendedResults[Finto::RESULT_TYPE] = Finto::TYPE_SPECIFIER;
        } elseif (count($results['results']) === 1) {
            // There is only one result.
            $result = reset($results['results']);

            if (((isset($result['altLabel'])
                && $result['altLabel'] === $query)
                || (isset($result['hiddenLabel'])
                && $result['hiddenLabel'] === $query))
            ) {
                // The result has an altLabel or hiddenLabel so it is considered
                // to be a non-descriptor result.
                $extendedResults[Finto::RESULT_TYPE] = Finto::TYPE_NONDESCRIPTOR;
            } elseif ($narrower) {
                // The result is not a non-descriptor so we will make an additional
                // API call to see if there are narrower concepts.
                if ($narrowerResults = $this->narrower(
                    $result['vocab'], $result['uri'], $result['lang'], true
                )
                ) {
                    $extendedResults[Finto::RESULT_TYPE] = Finto::TYPE_HYPONYM;
                    $extendedResults[Finto::NARROWER_RESULTS] = $narrowerResults;
                }
            }
            // If no type has been determined set to "other".
            if (!isset($extendedResults[Finto::RESULT_TYPE])) {
                $extendedResults[Finto::RESULT_TYPE] = Finto::TYPE_OTHER;
            }
        }

        return $extendedResults;
    }

    /**
     * Make Request.
     *
     * Makes a request to the Finto REST API
     *
     * @param array      $hierarchy Array of values to embed in the URL path of
     *                              the request
     * @param array|null $params    A keyed array of query data
     * @param string     $method    The http request method to use (Default is
     *                              GET)
     *
     * @return array JSON response decoded to an associative array.
     *
     * @throws \Exception
     */
    protected function makeRequest(
        array $hierarchy, ?array $params = null, string $method = 'GET'
    ): array {
        // Set up the request
        $apiUrl = $this->config->get('base_url', 'https://api.finto.fi/rest/v1');

        // Add hierarchy
        foreach ($hierarchy as $value) {
            $apiUrl .= '/' . urlencode($value);
        }

        $client = $this->client->setUri($apiUrl);

        // Add params
        if ($method == 'GET') {
            $client->setParameterGet($params);
        } else {
            if (is_string($params)) {
                $client->getRequest()->setContent($params);
            } else {
                $client->setParameterPost($params);
            }
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $response = $client->setMethod($method)->send();
        $result = $response->getBody();

        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $method request $apiUrl" . PHP_EOL . 'response: ' . PHP_EOL
            . $result
        );

        // Handle errors as complete failures only if the API call didn't return
        // valid JSON that the caller can handle
        $decodedResult = json_decode($result, true);
        if (!$response->isSuccess() && null === $decodedResult) {
            $params = $method == 'GET'
                ? $client->getRequest()->getQuery()->toString()
                : $client->getRequest()->getPost()->toString();
            $this->logError(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new \Exception('Problem with Finto REST API.');
        }

        return $decodedResult;
    }
}
