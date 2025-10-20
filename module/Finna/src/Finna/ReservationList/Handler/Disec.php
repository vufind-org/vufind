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
        $orderUrl = $this->getApiUrl() . 'orders';
        $client = $this->getService(\VuFindHttp\HttpService::class)->createClient($orderUrl);
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

        $cardInfo = $this->getPreferredCardInfo($user);
        $patronId = $cardInfo['patron_id'];
        // Throw an error if patron id is not found as this should not be possible
        if (!$patronId) {
            throw new \Exception('Patron id not set');
        }
        if ($this->getUsePatronId()) {
            $data['kohaId'] = $patronId;
        } else {
            $data['customer'] = [
                'firstName' => $cardInfo['first_name'],
                'lastName' => $cardInfo['last_name'],
                'email' => $formValues['email'] ?? $cardInfo['email'],
            ];
        }
        $data['contentInfo'] .= 'id: ' . $patronId;

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
        $orderUrl = $this->getApiUrl() . 'orders';
        $formedUrl = implode('/', [$orderUrl, $externalId]);
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
     * @param string $institution List owner institution code
     * @param array  $config      List specific configuration as an array
     *
     * @return static
     */
    public function init(string $institution, array $config = []): static
    {
        parent::init($institution, $config);
        $this->requestHeaders[] = 'X-API-Key: ' . $this->getApiSecret();
        return $this;
    }
}
