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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\Shortlinks;
use VuFind\Log\LoggerAwareTrait;

use function count;

/**
 * Database service for shortlinks.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ShortlinksService extends AbstractDbService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Create a shortlinks entity object.
     *
     * @return Shortlinks
     */
    public function createEntity(): Shortlinks
    {
        $class = $this->getEntityClass(Shortlinks::class);
        return new $class();
    }

    /**
     * Generate a short hash using the base62 algorithm (and write a row to the
     * database).
     *
     * @param string $path Path to store in database
     *
     * @return string
     */
    public function getBase62Hash(string $path): string
    {
        $shortlink = $this->createEntity();
        $now = new \DateTime();
        $shortlink->setPath($path)
            ->setCreated($now);
        try {
            $this->entityManager->persist($shortlink);
            $this->entityManager->flush();
            $id = $shortlink->getId();
            $b62 = new \VuFind\Crypt\Base62();
            $shortlink->setHash($b62->encode($id));
            $this->entityManager->persist($shortlink);
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->logError('Could not save shortlink: ' . $e->getMessage());
            throw $e;
        }
        return $shortlink->getHash();
    }

    /**
     * Pick a shortened version of a hash and write it to the database as needed.
     *
     * @param string $path          Path to store in database
     * @param string $hash          Hash of $path
     * @param int    $length        Minimum number of characters from hash to use for
     *                              lookups (may be increased to enforce uniqueness)
     * @param int    $maxHashLength The maximum allowed hash length
     *
     * @throws Exception
     * @return string
     */
    public function saveAndShortenHash($path, $hash, $length, $maxHashLength)
    {
        // Validate hash length:
        if ($length > $maxHashLength) {
            throw new Exception(
                'Could not generate unique hash under ' . $maxHashLength
                . ' characters in length.'
            );
        }
        $shorthash = str_pad(substr($hash, 0, $length), $length, '_');

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s')
            ->from($this->getEntityClass(Shortlinks::class), 's')
            ->where('s.hash = :hash')
            ->setParameter('hash', $shorthash);
        $query = $queryBuilder->getQuery();
        $results = $query->getResult();

        // Brand new hash? Create row and return:
        if (count($results) == 0) {
            $shortlink = $this->createEntity();
            $now = new \DateTime();
            // Generate short hash within a transaction to avoid odd timing-related
            // problems:
            $this->entityManager->getConnection()->beginTransaction();
            $shortlink->setHash($shorthash)
                ->setPath($path)
                ->setCreated($now);
            try {
                $this->entityManager->persist($shortlink);
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();
            } catch (Exception $e) {
                $this->logError('Could not save shortlink: ' . $e->getMessage());
                $this->entityManager->getConnection()->rollBack();
                throw $e;
            }
            return $shorthash;
        }

        // If we got this far, the hash already exists; let's check if it matches
        // the path...
        if (current($results)->getPath() === $path) {
            return $shorthash;
        }

        // If we got here, we have encountered an unexpected hash collision. Let's
        // disambiguate by making it one character longer:
        return $this->saveAndShortenHash($path, $hash, $length + 1, $maxHashLength);
    }

    /**
     * Resolve URL from Database via id.
     *
     * @param string $input   hash
     * @param string $baseUrl Base URL of current VuFind site
     *
     * @return string
     *
     * @throws Exception
     */
    public function resolve($input, $baseUrl)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s')
            ->from($this->getEntityClass(Shortlinks::class), 's')
            ->where('s.hash = :hash')
            ->setParameter('hash', $input);
        $query = $queryBuilder->getQuery();
        $result = $query->getResult();
        if (count($result) !== 1) {
            throw new Exception('Shortlink could not be resolved: ' . $input);
        }
        return $baseUrl . current($result)->getPath();
    }

    /**
     * Generate base62 encoding to migrate old shortlinks and return total number of migrated links.
     *
     * @return int
     */
    public function fixshortlinks()
    {
        $base62 = new \VuFind\Crypt\Base62();
        $results = $this->entityManager->getRepository($this->getEntityClass(Shortlinks::class))
            ->findBy(['hash' => null]);
        foreach ($results as $result) {
            $result->setHash($base62->encode($result->getId()));
        }
        $this->entityManager->flush();
        return count($results);
    }
}
