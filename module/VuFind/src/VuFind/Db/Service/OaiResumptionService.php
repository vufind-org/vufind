<?php

/**
 * Database service for OaiResumption.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\OaiResumptionEntityInterface;
use VuFind\Log\LoggerAwareTrait;

use function intval;

/**
 * Database service for OaiResumption.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class OaiResumptionService extends AbstractDbService implements
    LoggerAwareInterface,
    OaiResumptionServiceInterface,
    Feature\DeleteExpiredInterface
{
    use LoggerAwareTrait;

    /**
     * Remove expired tokens from the database.
     *
     * Removes tokens expired more than a day ago. This allows the ExpireOaiResumptionTokensCommand
     * to do the work in background if it's enabled.
     * Removal is done in a loop to avoid long database locks.
     *
     * @return void
     */
    public function removeExpired(): void
    {
        $dateThreshold = $this->getDateTime('now - 1 days');
        do {
            $count = $this->deleteExpired($dateThreshold, 1000);
        } while ($count > 0);
    }

    /**
     * Retrieve an unexpired row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $token The resumption token to retrieve.
     *
     * @return     ?OaiResumptionEntityInterface
     * @deprecated Use OaiResumptionService::findWithId
     */
    public function findToken($token): ?OaiResumptionEntityInterface
    {
        return $this->findWithId($token);
    }

    /**
     * Retrieve an unexpired row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $id Id to use for the search.
     *
     * @return ?OaiResumptionEntityInterface
     */
    public function findWithId(string $id): ?OaiResumptionEntityInterface
    {
        $dql = 'SELECT O FROM ' . OaiResumptionEntityInterface::class . ' O '
            . 'WHERE O.id = :id AND O.expires > :now';
        $now = $this->getDateTime();
        $parameters = compact('id', 'now');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getOneOrNullResult();
    }

    /**
     * Retrieve an unexpired row from the database based on token; return null if it
     * is not found.
     *
     * @param string $token Token used for the search.
     *
     * @return ?OaiResumptionEntityInterface
     */
    public function findWithToken(string $token): ?OaiResumptionEntityInterface
    {
        $dql = 'SELECT O FROM ' . OaiResumptionEntityInterface::class . ' O '
            . 'WHERE O.token = :token AND O.expires > :now';
        $now = $this->getDateTime();
        $parameters = compact('token', 'now');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getOneOrNullResult();
    }

    /**
     * Retrieve an unexpired row from the database based on primary key and where the token is null.
     *
     * @param int $id Id used for the search.
     *
     * @return ?OaiResumptionEntityInterface
     * @todo   In future, we should migrate data to prevent null token fields, which will make this method obsolete.
     */
    protected function findWithLegacyIdToken(int $id): ?OaiResumptionEntityInterface
    {
        $dql = 'SELECT O FROM ' . OaiResumptionEntityInterface::class . ' O '
            . 'WHERE O.token IS NULL AND O.id = :id AND O.expires > :now';
        $now = $this->getDateTime();
        $parameters = compact('id', 'now');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getOneOrNullResult();
    }

    /**
     * Try to find an unexpired row with token first, if not found then try to find with id where the token is null.
     *
     * @param string $tokenOrId Token or id
     *
     * @return ?OaiResumptionEntityInterface
     * @todo   In future, we should migrate data to prevent null token fields, which will make this method obsolete.
     */
    final public function findWithTokenOrLegacyIdToken(string $tokenOrId): ?OaiResumptionEntityInterface
    {
        $result = $this->findWithToken($tokenOrId);
        if (!$result && is_numeric($tokenOrId)) {
            $idInt = intval($tokenOrId);
            if ($idInt > 0) {
                return $this->findWithLegacyIdToken($idInt);
            }
        }
        return $result;
    }

    /**
     * Generate a random token using random_bytes and bin2hex
     *
     * @return string
     */
    protected function createRandomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create and persist a new resumption token.
     *
     * @param array    $params Parameters associated with the token.
     * @param DateTime $expiry Expiration time for the token.
     *
     * @return OaiResumptionEntityInterface
     * @throws \Exception
     */
    public function createAndPersistToken(array $params, DateTime $expiry): OaiResumptionEntityInterface
    {
        $row = null;
        // In extremely rare cases it might be possible that the generated random token already exists in the
        // database. Retry up to the limit, but the possibility for this to happen is close to 0.
        for ($i = 1; $i <= $this->retryCount; $i++) {
            try {
                $row = $this->createEntity()
                    ->setToken($this->createRandomToken())
                    ->setResumptionParameters($this->encodeParams($params))
                    ->setExpiry($expiry);
                $this->persistEntity($row);
                break;
            } catch (\Exception $e) {
                $this->logError('Could not save token: ' . $e->getMessage() . ', attempt: ' . $i);
                // Actually throw the error if this is the last attempt and it still did not work.
                if ($i >= $this->retryCount) {
                    throw $e;
                }
            }
        }
        return $row;
    }

    /**
     * Create a OaiResumption entity object.
     *
     * @return OaiResumptionEntityInterface
     */
    public function createEntity(): OaiResumptionEntityInterface
    {
        return $this->entityPluginManager->get(OaiResumptionEntityInterface::class);
    }

    /**
     * Encode an array of parameters into the object.
     *
     * @param array $params Parameters to save.
     *
     * @return string
     */
    protected function encodeParams(array $params): string
    {
        ksort($params);
        $processedParams = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $processedParams;
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
        $subQueryBuilder = $this->entityManager->createQueryBuilder();
        $subQueryBuilder->select('o.id')
            ->from(OaiResumptionEntityInterface::class, 'o')
            ->where('o.expires < :dateLimit')
            ->setParameter('dateLimit', $dateLimit);

        if ($limit) {
            $subQueryBuilder->setMaxResults($limit);
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(OaiResumptionEntityInterface::class, 'o')
            ->where('o.id IN (:tokens)')
            ->setParameter('tokens', $subQueryBuilder->getQuery()->getResult());

        return $queryBuilder->getQuery()->execute();
    }

    /**
     * Get current time or specified time as DateTime.
     *
     * NOTE: This method is useful because it helps with mocking dates in tests.
     *
     * @param string $datetime Time to return
     *
     * @return DateTime
     */
    protected function getDateTime(string $datetime = 'now'): DateTime
    {
        return new DateTime($datetime);
    }
}
