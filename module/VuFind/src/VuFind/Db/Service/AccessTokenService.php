<?php

/**
 * Database service for access tokens.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Table\AccessToken;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for access tokens.
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AccessTokenService extends AbstractDbService implements
    AccessTokenServiceInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param AccessToken         $accessTokenTable    Access token table
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        protected AccessToken $accessTokenTable
    ) {
        parent::__construct($entityManager, $entityPluginManager);
    }

    /**
     * Retrieve an object from the database based on id and type; create a new
     * row if no existing match is found.
     *
     * @param string $id     Token ID
     * @param string $type   Token type
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return ?AccessTokenEntityInterface
     */
    public function getByIdAndType(
        string $id,
        string $type,
        bool $create = true
    ): ?AccessTokenEntityInterface {
        return $this->accessTokenTable->getByIdAndType($id, $type, $create);
    }

    /**
     * Add or replace an OpenID nonce for a user
     *
     * @param int     $userId User ID
     * @param ?string $nonce  Nonce
     *
     * @return void
     */
    public function storeNonce(int $userId, ?string $nonce): void
    {
        $this->accessTokenTable->storeNonce($userId, $nonce);
    }

    /**
     * Retrieve an OpenID nonce for a user
     *
     * @param int $userId User ID
     *
     * @return ?string
     */
    public function getNonce(int $userId): ?string
    {
        return $this->accessTokenTable->getNonce($userId);
    }
}
