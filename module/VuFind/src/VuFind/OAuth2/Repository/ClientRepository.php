<?php

/**
 * OAuth2 client repository implementation.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\OAuth2\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use VuFind\OAuth2\Entity\ClientEntity;

/**
 * OAuth2 client repository implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ClientRepository implements ClientRepositoryInterface
{
    /**
     * OAuth2 server configuration
     *
     * @var array
     */
    protected $oauth2Config = [];

    /**
     * Constructor
     *
     * @param array $config OAuth2 configuration
     */
    public function __construct(array $config)
    {
        $this->oauth2Config = $config;
    }

    /**
     * Get a client.
     *
     * @param string $clientIdentifier The client's identifier
     *
     * @return ClientEntityInterface|null
     */
    public function getClientEntity($clientIdentifier)
    {
        if (!($config = $this->oauth2Config['Clients'][$clientIdentifier] ?? null)) {
            return null;
        }
        $config['identifier'] = $clientIdentifier;
        return new ClientEntity($config);
    }

    /**
     * Validate a client's secret.
     *
     * @param string      $clientIdentifier The client's identifier
     * @param null|string $clientSecret     The client's secret (if sent)
     * @param null|string $grantType        The type of grant the client is using (if
     * sent)
     *
     * @return bool
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        if (!($config = $this->oauth2Config['Clients'][$clientIdentifier] ?? null)) {
            return false;
        }

        if (
            ($config['isConfidential'] ?? false)
            && (empty($config['secret'])
            || !password_verify($clientSecret ?? '', $config['secret']))
        ) {
            return false;
        }

        return true;
    }
}
