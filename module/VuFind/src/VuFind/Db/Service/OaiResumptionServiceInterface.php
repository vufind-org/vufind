<?php

/**
 * Database service interface for OaiResumption.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\OaiResumptionEntityInterface;

/**
 * Database service interface for OaiResumption.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface OaiResumptionServiceInterface extends DbServiceInterface
{
    /**
     * Remove all expired tokens from the database.
     *
     * @return void
     */
    public function removeExpired(): void;

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $token The resumption token to retrieve.
     *
     * @return     ?OaiResumptionEntityInterface
     * @deprecated Use OaiResumptionService::findWithId
     */
    public function findToken(string $token): ?OaiResumptionEntityInterface;

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $id Id to use for the search.
     *
     * @return ?OaiResumptionEntityInterface
     */
    public function findWithId(string $id): ?OaiResumptionEntityInterface;

    /**
     * Retrieve a row from the database based on token; return null if it
     * is not found.
     *
     * @param string $token Token used for the search.
     *
     * @return ?OaiResumptionEntityInterface
     */
    public function findWithToken(string $token): ?OaiResumptionEntityInterface;

    /**
     * Try to find with token first, if not found then try to find with id where the token is null.
     *
     * @param string $tokenOrId Token or id
     *
     * @return ?OaiResumptionEntityInterface
     * @todo   In future, we should migrate data to prevent null token fields, which will make this method obsolete.
     */
    public function findWithTokenOrLegacyIdToken(string $tokenOrId): ?OaiResumptionEntityInterface;

    /**
     * Create and persist a new resumption token.
     *
     * @param array    $params Parameters associated with the token.
     * @param DateTime $expiry Expiration time for the token.
     *
     * @return OaiResumptionEntityInterface
     * @throws \Exception
     */
    public function createAndPersistToken(array $params, DateTime $expiry): OaiResumptionEntityInterface;

    /**
     * Create a OaiResumption entity object.
     *
     * @return OaiResumptionEntityInterface
     */
    public function createEntity(): OaiResumptionEntityInterface;
}
