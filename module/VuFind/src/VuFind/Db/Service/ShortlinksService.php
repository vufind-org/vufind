<?php

/**
 * Database service for shortlinks.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Exception;
use VuFind\Db\Entity\ShortlinksEntityInterface;

/**
 * Database service for shortlinks.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ShortlinksService extends AbstractDbService implements
    ShortlinksServiceInterface,
    Feature\TransactionInterface
{
    /**
     * Begin a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $this->entityManager->getConnection()->beginTransaction();
    }

    /**
     * Commit a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function commitTransaction(): void
    {
        $this->entityManager->getConnection()->commit();
    }

    /**
     * Roll back a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function rollBackTransaction(): void
    {
        $this->entityManager->getConnection()->rollBack();
    }

    /**
     * Create a short link entity.
     *
     * @return ShortlinksEntityInterface
     */
    public function createEntity(): ShortlinksEntityInterface
    {
        return $this->entityPluginManager->get(ShortlinksEntityInterface::class);
    }

    /**
     * Create and persist an entity for the provided path.
     *
     * @param string $path Path part of URL being shortened.
     *
     * @return ShortlinksEntityInterface
     */
    public function createAndPersistEntityForPath(string $path): ShortlinksEntityInterface
    {
        $shortlink = $this->createEntity()
            ->setPath($path)
            ->setCreated(new DateTime());
        $this->persistEntity($shortlink);
        return $shortlink;
    }

    /**
     * Look up a short link by hash value.
     *
     * @param string $hash Hash value.
     *
     * @return ?ShortlinksEntityInterface
     */
    public function getShortLinkByHash(string $hash): ?ShortlinksEntityInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s')
            ->from(ShortlinksEntityInterface::class, 's')
            ->where('s.hash = :hash')
            ->setParameter('hash', $hash);
        $query = $queryBuilder->getQuery();
        return $query->getResult()[0] ?? null;
    }

    /**
     * Get rows with missing hashes (for legacy upgrading).
     *
     * @return ShortlinksEntityInterface[]
     */
    public function getShortLinksWithMissingHashes(): array
    {
        return $this->entityManager
            ->getRepository(ShortlinksEntityInterface::class)
            ->findBy(['hash' => null]);
    }
}
