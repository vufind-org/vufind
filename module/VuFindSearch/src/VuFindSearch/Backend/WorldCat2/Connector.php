<?php

/**
 * Class for accessing OCLC WorldCat search API v2.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindSearch\Backend\WorldCat2;

use Exception;
use Laminas\Http\Client\Exception\RuntimeException as ExceptionRuntimeException;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Exception\RuntimeException;
use Laminas\Http\Response;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use VuFind\Log\LoggerAwareTrait;
use VuFindSearch\ParamBag;

/**
 * Class for accessing OCLC WorldCat search API v2.
 *
 * @category VuFind
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Connector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * OAuth2 provider
     *
     * @var GenericProvider
     */
    protected $authProvider;

    /**
     * API base URL
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param \Laminas\Http\Client $client  An HTTP client object
     * @param Container            $session Session container for persisting data
     * @param array                $options Additional config settings
     */
    public function __construct(
        protected \Laminas\Http\Client $client,
        protected Container $session,
        protected array $options = []
    ) {
        if (empty($options['base_url'])) {
            throw new \Exception('base_url setting is required');
        }
        $this->baseUrl = $options['base_url'];
        $authOptions = [
            'clientId' => $options['wskey'],
            'clientSecret' => $options['secret'],
            'urlAuthorize' => 'https://oauth.oclc.org/auth',
            'urlAccessToken' => 'https://oauth.oclc.org/token',
            'urlResourceOwnerDetails' => '',
        ];
        $optionProvider = new HttpBasicAuthOptionProvider();
        $this->authProvider = new GenericProvider($authOptions, compact('optionProvider'));
    }

    /**
     * Get an OAuth2 token.
     *
     * @return string
     * @throws IdentityProviderException
     */
    protected function getToken(): string
    {
        return $this->authProvider->getAccessToken('client_credentials', ['scope' => 'wcapi'])->getToken();
    }

    /**
     * Make an API call.
     *
     * @param string $path Path to query (including GET parameters)
     *
     * @return Response
     * @throws IdentityProviderException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ExceptionRuntimeException
     */
    protected function makeApiCall(string $path)
    {
        if (!isset($this->session->token)) {
            $this->session->token = $this->getToken();
        }
        $headers = ['Authorization: Bearer ' . $this->session->token];
        $this->client->setHeaders($headers);
        $this->client->setUri($this->baseUrl . $path);
        $this->debug($path);
        $response = $this->client->send();
        // If authorization failed, the token may be expired; re-request and try again:
        if ($response->getStatusCode() === 401) {
            $this->session->token = $this->getToken();
            $headers = ['Authorization: Bearer ' . $this->session->token];
            $this->client->setHeaders($headers);
            $response = $this->client->send();
        }
        return $response;
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return array
     */
    public function getRecord($id, ParamBag $params = null)
    {
        $response = $this->makeApiCall('/bibs/' . urlencode($id));
        $record = json_decode($response->getBody(), true);
        $found = isset($record['identifier']['oclcNumber']);
        return [
            'docs' => $found ? [$record] : [],
            'offset' => 0,
            'total' => $found ? 1 : 0,
        ];
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param int      $offset Search offset
     * @param int      $limit  Search limit
     *
     * @return array
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        $response = $this->makeApiCall('/bibs?' . implode('&', $params->request()));
        $result = json_decode($response->getBody(), true);
        if (!isset($result['bibRecords']) && !isset($result['numberOfRecords'])) {
            $msgParts = [];
            $errorFields = ['type', 'title', 'detail'];
            foreach ($errorFields as $field) {
                if (isset($result[$field])) {
                    $msgParts[] = $field . ': ' . $result[$field];
                }
            }
            $msg = empty($msgParts) ? 'Unexpected response format.' : implode('; ', $msgParts);
            throw new Exception($msg);
        }
        $docs = $result['bibRecords'] ?? [];
        $total = $result['numberOfRecords'];
        $facets = $result['searchFacets'] ?? [];
        return compact('docs', 'offset', 'total', 'facets');
    }
}
