<?php

/**
 * OAuth2 scope repository implementation.
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

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use VuFind\OAuth2\Entity\ScopeEntity;

use function in_array;

/**
 * OAuth2 scope repository implementation.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ScopeRepository implements ScopeRepositoryInterface
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
     * Return information about a scope.
     *
     * @param string $identifier The scope identifier
     *
     * @return ScopeEntityInterface|null
     */
    public function getScopeEntityByIdentifier($identifier)
    {
        if (!isset($this->oauth2Config['Scopes'][$identifier])) {
            return null;
        }
        $config = $this->oauth2Config['Scopes'][$identifier];
        $config['identifier'] = $identifier;
        return new ScopeEntity($config);
    }

    /**
     * Given a client, grant type and optional user identifier validate the set of
     * scopes requested are valid and optionally append additional scopes or remove
     * requested scopes.
     *
     * @param ScopeEntityInterface[] $scopes         Scopes
     * @param string                 $grantType      Grant type
     * @param ClientEntityInterface  $clientEntity   Client
     * @param null|string            $userIdentifier User ID
     *
     * @return ScopeEntityInterface[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) {
        $clientId = $clientEntity->getIdentifier();
        // Apply any client-specific filter to scopes:
        if ($allowedScopes = $this->oauth2Config['Clients'][$clientId]['allowedScopes'] ?? null) {
            $scopes = array_values(
                array_filter(
                    $scopes,
                    function ($scope) use ($allowedScopes) {
                        return in_array($scope->getIdentifier(), $allowedScopes);
                    }
                )
            );
        }
        return $scopes;
    }
}
