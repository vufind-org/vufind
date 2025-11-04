<?php

/**
 * Database service for access tokens.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Service\AccessTokenService as VuFindAccessTokenService;

/**
 * Database service for access tokens.
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AccessTokenService extends VuFindAccessTokenService implements AccessTokenServiceInterface
{
    /**
     * Api key type in the table
     *
     * @var string
     */
    protected const TYPE_API_KEY = 'api_key';

    /**
     * Check if api key is active in database
     *
     * @param string $token Token for the access_token
     *
     * @return bool
     */
    public function isApiKeyActive(string $token): bool
    {
        $token = $this->entityManager->getRepository(AccessTokenEntityInterface::class)
            ->findOneBy(['data' => $token, 'type' => self::TYPE_API_KEY]);

        return $token && !$token->isRevoked();
    }
}
