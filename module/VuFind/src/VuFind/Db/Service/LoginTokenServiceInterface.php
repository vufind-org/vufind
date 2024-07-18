<?php

/**
 * Database service interface for login_token table.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\LoginTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\LoginToken as LoginTokenException;

/**
 * Database service interface for login_token table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface LoginTokenServiceInterface extends DbServiceInterface
{
    /**
     * Create a new login token entity.
     *
     * @return LoginTokenEntityInterface
     */
    public function createEntity(): LoginTokenEntityInterface;

    /**
     * Create and persist a token.
     *
     * @param UserEntityInterface $user      User identifier
     * @param string              $token     Login token
     * @param string              $series    Series the token belongs to
     * @param string              $browser   User browser
     * @param string              $platform  User platform
     * @param int                 $expires   Token expiration timestamp
     * @param string              $sessionId Session associated with the token
     *
     * @return LoginTokenEntityInterface
     */
    public function createAndPersistToken(
        UserEntityInterface $user,
        string $token,
        string $series,
        string $browser = '',
        string $platform = '',
        int $expires = 0,
        string $sessionId = ''
    ): LoginTokenEntityInterface;

    /**
     * Check if a login token matches one in database.
     *
     * @param array $token array containing user id, token and series
     *
     * @return ?LoginTokenEntityInterface
     * @throws LoginTokenException
     */
    public function matchToken(array $token): ?LoginTokenEntityInterface;

    /**
     * Delete all tokens in a given series.
     *
     * @param string $series         series
     * @param ?int   $currentTokenId Current token ID to keep
     *
     * @return void
     */
    public function deleteBySeries(string $series, ?int $currentTokenId = null): void;

    /**
     * Delete all tokens for a user.
     *
     * @param UserEntityInterface|int $userOrId User entity object or identifier
     *
     * @return void
     */
    public function deleteByUser(UserEntityInterface|int $userOrId): void;

    /**
     * Get tokens for a given user.
     *
     * @param UserEntityInterface|int $userOrId User entity object or identifier
     * @param bool                    $grouped  Whether to return results grouped by series
     *
     * @return LoginTokenEntityInterface[]
     */
    public function getByUser(UserEntityInterface|int $userOrId, bool $grouped = true): array;

    /**
     * Get token by series.
     *
     * @param string $series Series identifier
     *
     * @return LoginTokenEntityInterface[]
     */
    public function getBySeries(string $series): array;
}
