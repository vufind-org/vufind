<?php

/**
 * Database service for login_token table.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\LoginTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\LoginToken as LoginTokenException;

/**
 * Database service for login_token table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class LoginTokenService extends AbstractDbService implements
    LoginTokenServiceInterface,
    Feature\DeleteExpiredInterface
{
    /**
     * Create a new login token entity.
     *
     * @return LoginTokenEntityInterface
     */
    public function createEntity(): LoginTokenEntityInterface
    {
        return $this->entityPluginManager->get(LoginTokenEntityInterface::class);
    }

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
    ): LoginTokenEntityInterface {
        $row = $this->createEntity()
            ->setToken(hash('sha256', $token))
            ->setSeries($series)
            ->setUser($user)
            ->setLastLogin(new DateTime())
            ->setBrowser($browser)
            ->setPlatform($platform)
            ->setExpires($expires)
            ->setLastSessionId($sessionId);
        $this->persistEntity($row);
        return $row;
    }

    /**
     * Check if a login token matches one in database.
     *
     * @param array $token array containing user id, token and series
     *
     * @return ?LoginTokenEntityInterface
     * @throws LoginTokenException
     */
    public function matchToken(array $token): ?LoginTokenEntityInterface
    {
        $userId = null;
        foreach ($this->getBySeries($token['series']) as $row) {
            $userId = $row->getUser()->getId();
            if (hash_equals($row->getToken(), hash('sha256', $token['token']))) {
                if (time() > $row->getExpires()) {
                    $this->deleteById($row->getId());
                    return null;
                }
                return $row;
            }
        }
        if ($userId) {
            throw new LoginTokenException('Tokens do not match', $userId);
        }
        return null;
    }

    /**
     * Delete a token with given id.
     *
     * @param int $id id
     *
     * @return void
     */
    protected function deleteById(int $id): void
    {
        $dql = 'DELETE FROM ' . LoginTokenEntityInterface::class . ' lt '
            . 'WHERE lt.id == :id';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('id', $id);
        $query->execute();
    }

    /**
     * Delete all tokens in a given series.
     *
     * @param string $series         series
     * @param ?int   $currentTokenId Current token ID to keep
     *
     * @return void
     */
    public function deleteBySeries(string $series, ?int $currentTokenId = null): void
    {
        $params = compact('series');
        $dql = 'DELETE FROM ' . LoginTokenEntityInterface::class . ' lt '
            . 'WHERE lt.series = :series';
        if ($currentTokenId !== null) {
            $dql .= ' AND lt.id != :currentTokenId';
            $params['currentTokenId'] = $currentTokenId;
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($params);
        $query->execute();
    }

    /**
     * Delete all tokens for a user.
     *
     * @param UserEntityInterface|int $userOrId User entity object or identifier
     *
     * @return void
     */
    public function deleteByUser(UserEntityInterface|int $userOrId): void
    {
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $dql = 'DELETE FROM ' . LoginTokenEntityInterface::class . ' lt '
            . 'WHERE lt.user = :user';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('user', $user);
        $query->execute();
    }

    /**
     * Get tokens for a given user.
     *
     * @param UserEntityInterface|int $userOrId User entity object or identifier
     * @param bool                    $grouped  Whether to return results grouped by series
     *
     * @return LoginTokenEntityInterface[]
     */
    public function getByUser(UserEntityInterface|int $userOrId, bool $grouped = true): array
    {
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        if ($grouped) {
            // Use different DQL for grouping logic
            $dql = 'SELECT lt '
                . 'FROM ' . LoginTokenEntityInterface::class . ' lt '
                . 'WHERE lt.user = :user AND lt.lastLogin = ('
                . '    SELECT MAX(subLt.lastLogin) '
                . '    FROM ' . LoginTokenEntityInterface::class . ' subLt '
                . '    WHERE subLt.user = :user AND subLt.series = lt.series AND subLt.browser = lt.browser '
                . '        AND subLt.platform = lt.platform AND subLt.expires = lt.expires '
                . ') '
                . 'ORDER BY lt.lastLogin DESC';
        } else {
            $dql = 'SELECT lt '
                . 'FROM ' . LoginTokenEntityInterface::class . ' lt '
                . 'WHERE lt.user = :user '
                . 'ORDER BY lt.lastLogin DESC';
        }

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('user', $user);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Get token by series.
     *
     * @param string $series Series identifier
     *
     * @return LoginTokenEntityInterface[]
     */
    public function getBySeries(string $series): array
    {
        $dql = 'SELECT lt '
            . 'FROM ' . LoginTokenEntityInterface::class . ' lt '
            . 'WHERE lt.series = :series';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('series', $series);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Delete expired records. Allows setting a limit so that rows can be deleted in small batches.
     *
     * @param DateTime $dateLimit Date threshold of an "expired" record.
     * @param ?int     $limit     Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired(DateTime $dateLimit, ?int $limit = null): int
    {
        // Date limit ignored since login token already contains an expiration time.
        $subQueryBuilder = $this->entityManager->createQueryBuilder();
        $subQueryBuilder->select('lt.id')
            ->from(LoginTokenEntityInterface::class, 'lt')
            ->where('lt.expires < :dateLimit')
            ->setParameter('dateLimit', $dateLimit->getTimestamp());
        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(LoginTokenEntityInterface::class, 'lt')
            ->where('lt.id IN (:tokens)')
            ->setParameter('tokens', $subQueryBuilder->getQuery()->getResult());
        return $queryBuilder->getQuery()->execute();
    }
}
