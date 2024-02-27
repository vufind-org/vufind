<?php

/**
 * Relais connection class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Relais
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Connection;

use Laminas\Config\Config;
use Laminas\Http\Client;

/**
 * Relais connection class.
 *
 * @category VuFind
 * @package  Relais
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Relais implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * Relais configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Client $client HTTP client
     * @param Config $config Relais configuration
     */
    public function __construct(Client $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Get default data to send to API.
     *
     * @return array
     */
    protected function getDefaultData()
    {
        return [
            'ApiKey' => $this->config->apikey ?? null,
            'UserGroup' => 'PATRON',
            'PartnershipId' => $this->config->group ?? null,
            'LibrarySymbol' => $this->config->symbol ?? null,
        ];
    }

    /**
     * Format the parameters needed to look up an OCLC number in the API.
     *
     * @param string  $oclc   OCLC number to look up
     * @param ?string $patron Patron ID (null to use default from config)
     *
     * @return array
     */
    protected function getOclcRequestData($oclc, $patron)
    {
        return [
            'PickupLocation' => $this->config->pickupLocation ?? null,
            'Notes' => 'This request was made through the VuFind Catalog interface',
            'PatronId' => $patron ?? $this->config->patronForLookup ?? null,
            'ExactSearch' => [
                [
                    'Type' => 'OCLC',
                    'Value' => $oclc,
                ],
            ],
        ];
    }

    /**
     * Make an API request
     *
     * @param string $uri  Endpoint to request from
     * @param array  $data Data to send with request
     *
     * @return string
     */
    protected function request($uri, $data)
    {
        $this->client->resetParameters()
            ->setUri($uri)
            ->setMethod('POST');
        $requestBody = json_encode($data + $this->getDefaultData());
        $this->debug('Posting ' . $requestBody . ' to ' . $uri);
        $this->client->setRawBody($requestBody);
        $this->client->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type: application/json');
        $response = $this->client->send();
        $body = $response->getBody();
        $this->debug('Status: ' . $response->getStatusCode() . ', body: ' . $body);
        return $body;
    }

    /**
     * Authenticate a patron
     *
     * @param string $patron           Patron ID (null to use default from config)
     * @param bool   $returnFullObject True to return the full API response object;
     * false to return only the authorization ID.
     *
     * @return mixed
     * @throws \Exception
     */
    public function authenticatePatron($patron = null, $returnFullObject = false)
    {
        $uri = $this->config->authenticateurl ?? null;
        if (empty($uri)) {
            throw new \Exception('authenticateurl not configured!');
        }
        $data = ['PatronId' => $patron ?? $this->config->patronForLookup ?? null];
        $result = json_decode($this->request($uri, $data));
        return $returnFullObject ? $result : ($result->AuthorizationId ?? null);
    }

    /**
     * Place a request
     *
     * @param string $oclc   OCLC number to look up
     * @param string $auth   Authentication ID from authenticatePatron()
     * @param string $patron Patron ID (null to use default from config)
     *
     * @return string
     * @throws \Exception
     */
    public function placeRequest($oclc, $auth, $patron = null)
    {
        $uri = $this->config->addurl ?? null;
        if (empty($uri)) {
            throw new \Exception('addurl not configured!');
        }
        $data = $this->getOclcRequestData($oclc, $patron);
        return $this->request($uri . '?aid=' . urlencode($auth), $data);
    }

    /**
     * Perform a search
     *
     * @param string $oclc   OCLC number to look up
     * @param string $auth   Authentication ID from authenticatePatron()
     * @param string $patron Patron ID (null to use default from config)
     *
     * @return string
     * @throws \Exception
     */
    public function search($oclc, $auth, $patron = null)
    {
        $uri = $this->config->availableurl ?? null;
        if (empty($uri)) {
            throw new \Exception('availableurl not configured!');
        }
        $data = $this->getOclcRequestData($oclc, $patron);
        return $this->request($uri . '?aid=' . urlencode($auth), $data);
    }
}
