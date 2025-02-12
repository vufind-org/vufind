<?php

/**
 * Disec handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\ReservationList\Handler;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Disec handler
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Disec extends AbstractBase
{
    /**
     * Disec orders url
     *
     * @param string
     */
    protected $ordersUrl;

    /**
     * API key used for disec authorization
     *
     * @param string
     */
    protected $apiKey;

    /**
     * Use catalog id to send user data instead of users first and last name
     *
     * @param bool
     */
    protected bool $useCatId = false;

    /**
     * Common headers in requests
     *
     * @var array
     */
    protected array $requestHeaders = [
        'Content-Type: application/json',
        'accept: */*',
    ];

    /**
     * Places an order
     *
     * @param array               $formValues Values gathered from submitted form
     * @param UserEntityInterface $user       User entity
     *
     * @return array [
     *  external_id: Id in external service or null,
     *  success: true or false,
     *  pickup_date: date for preferred pickup,
     *  connection Type of the connection
     * ]
     */
    public function placeOrder(array $formValues, UserEntityInterface $user): array
    {
        $data = [];
        $client = $this->getService(\VuFindHttp\HttpService::class)->createClient($this->ordersUrl);
        $client->setHeaders($this->requestHeaders);
        $client->setMethod(\Laminas\Http\Request::METHOD_POST);

        $resources = [];
        $recordLoader = $this->getService(\VuFind\Record\Loader::class);
        foreach ($recordLoader->loadBatch($formValues['record_source_and_ids']) as $record) {
            if ($identifiers = $record->tryMethod('getIdentifier', [])) {
                $resources[] = array_shift($identifiers);
            }
        }
        $data = [
            'resourceIds' => $resources,
            'contentInfo' => $formValues['message'] . PHP_EOL,
        ];
        $data['contentInfo'] .= 'Delivery date: ' . $formValues['pickup_date'] . PHP_EOL;
        if ($catId = $user->getCatId()) {
            [, $id] = explode('.', $catId);
            if ($this->useCatId) {
                $data['kohaId'] = (int)$id;
            }
            $data['contentInfo'] .= 'cat_id: ' . $id;
        }
        if (empty($data['kohaId'])) {
            $data['customer'] = [
                'firstName' => $formValues['firstName'] ?? $user->getFirstname(),
                'lastName' => $formValues['lastName'] ?? $user->getLastname(),
                'email' => $formValues['email'] ?? $user->getEmail(),
            ];
        }
        $client->setRawBody(json_encode($data));
        $response = $client->send();

        if ($response->isSuccess()) {
            $body = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'external_id' => $body['id'],
                'pickup_date' => $formValues['pickup_date'],
                'connection' => 'disec',
            ];
        }
        $this->debug(__CLASS__ . ': Failed to place order: ' . $response->getBody());
        return [
            'success' => false,
            'external_id' => null,
            'pickup_date' => null,
            'connection' => 'disec',
        ];
    }

    /**
     * Check list status. Used for external services.
     *
     * @param FinnaResourceListEntityInterface $list List to check for status
     *
     * @return string
     */
    public function getListStatus(FinnaResourceListEntityInterface $list): string
    {
        $externalId = $list->getExternalId();
        $formedUrl = implode('/', [$this->ordersUrl, $externalId]);
        $client = $this->getService(\VuFindHttp\HttpService::class)->createClient($formedUrl);
        $client->setHeaders($this->requestHeaders);
        $client->setMethod(\Laminas\Http\Request::METHOD_GET);
        $response = $client->send();
        $status = ReservationListStatus::UNKNOWN;
        if ($response->isSuccess()) {
            $body = json_decode($response->getBody(), true);
            $status = ReservationListStatus::mapEnumFromString($body['status'] ?? '');
        } else {
            $this->debug(__CLASS__ . ': failed to fetch status for list: ' . $response->getBody());
        }
        return $status->getTranslationKey();
    }

    /**
     * Initialize connection handler
     *
     * @param array $config List specific configuration from ReservationList.yaml
     *
     * @return static
     * @throws \Exception If Disec connection is not configured properly
     */
    public function init(array $config): static
    {
        parent::init($config);
        try {
            $baseUrl = $config['Connection']['base_url'];
            if (!str_ends_with($baseUrl, '/')) {
                $baseUrl .= '/';
            }
            $this->ordersUrl = $baseUrl . 'orders';
            $this->requestHeaders[] = 'X-API-Key: ' . $config['Connection']['secret'];
            $this->useCatId = $config['Connection']['use_cat_id'] ?? false;
        } catch (\Exception $e) {
            throw new \Exception(__CLASS__ . ': Invalid configuration');
        }
        return $this;
    }
}
