<?php

/**
 * Database service for OaiResumption.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\OaiResumption;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for OaiResumption.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class OaiResumptionService extends AbstractDbService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Remove all expired tokens from the database.
     *
     * @return void
     */
    public function removeExpired(): void
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(OaiResumption::class) . ' O '
            . 'WHERE O.expires <= :now';
        $parameters['now'] = new \DateTime();
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $token The resumption token to retrieve.
     *
     * @return ?OaiResumption
     */
    public function findToken($token): ?OaiResumption
    {
        $dql = 'SELECT O '
        . 'FROM ' . $this->getEntityClass(OaiResumption::class) . ' O '
        . 'WHERE O.id = :token';
        $parameters = compact('token');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return current($records);
    }

    /**
     * Create a new resumption token
     *
     * @param array $params Parameters associated with the token.
     * @param int   $expire Expiration time for token (Unix timestamp).
     *
     * @return int          ID of new token
     */
    public function saveToken($params, $expire): int
    {
        $row = $this->createEntity()
            ->setResumptionParameters($this->encodeParams($params))
            ->setExpiry(\DateTime::createFromFormat('U', $expire));
        try {
            $this->persistEntity($row);
        } catch (\Exception $e) {
            $this->logError('Could not save token: ' . $e->getMessage());
            return false;
        }
        return $row->getId();
    }

    /**
     * Create a OaiResumption entity object.
     *
     * @return OaiResumption
     */
    public function createEntity(): OaiResumption
    {
        $class = $this->getEntityClass(OaiResumption::class);
        return new $class();
    }

    /**
     * Encode an array of parameters into the object.
     *
     * @param array $params Parameters to save.
     *
     * @return string
     */
    public function encodeParams($params): string
    {
        ksort($params);
        $processedParams = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $processedParams;
    }
}
