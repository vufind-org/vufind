<?php

/**
 * Database service for UserResource.
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

use VuFind\Db\Entity\UserResource;

/**
 * Database service for UserResource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserResourceService extends AbstractService
{
    /**
     * Get a list of duplicate rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return array
     */
    public function getDuplicates()
    {
        $dql = 'SELECT MIN(ur.resource) as resource_id, MIN(ur.list) as list_id, '
            . 'MIN(ur.user) as user_id, COUNT(ur.resource) as cnt, MIN(ur.id) as id '
            . 'FROM ' . $this->getEntityClass(UserResource::class) . ' ur '
            . 'GROUP BY ur.resource, ur.list, ur.user '
            . 'HAVING COUNT(ur.resource) > 1';
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate()
    {
        $repo = $this->entityManager->getRepository($this->getEntityClass(UserResource::class));
        foreach ($this->getDuplicates() as $dupe) {
            // Do this as a transaction to prevent odd behavior:
            $this->entityManager->getConnection()->beginTransaction();

            // Merge notes together...
            $mainCriteria = [
                'resource' => $dupe['resource_id'],
                'list' => $dupe['list_id'],
                'user' => $dupe['user_id'],
            ];
            try {
                $dupeRows = $repo->findBy($mainCriteria);
                $notes = [];
                foreach ($dupeRows as $row) {
                    if (!empty($row->getNotes())) {
                        $notes[] = $row->getNotes();
                    }
                }
                $userResource =  $this->entityManager->getReference(UserResource::class, $dupe['id']);
                $userResource->setNotes(implode(' ', $notes));
                $this->entityManager->flush();

                // Now delete extra rows...
                // match on all relevant IDs in duplicate group
                // getDuplicates returns the minimum id in the set, so we want to
                // delete all of the duplicates with a higher id value.
                $dql = 'DELETE FROM ' . $this->getEntityClass(UserResource::class) . ' ur '
                    . 'WHERE ur.resource = :resource AND ur.list = :list '
                    . 'AND ur.user = :user AND ur.id > :id';
                $mainCriteria['id'] = $dupe['id'];
                $query = $this->entityManager->createQuery($dql);
                $query->setParameters($mainCriteria);
                $query->execute();
                // Done -- commit the transaction:
                $this->entityManager->getConnection()->commit();
            } catch (\Exception $e) {
                // If something went wrong, roll back the transaction and rethrow the error:
                $this->entityManager->getConnection()->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Get statistics on use of UserResource.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $dql = 'SELECT COUNT(DISTINCT(u.user)) AS users, '
            . 'COUNT(DISTINCT(u.list)) AS lists, '
            . 'COUNT(DISTINCT(u.resource)) AS resources, '
            . 'COUNT(u.id) AS total '
            . 'FROM ' . $this->getEntityClass(UserResource::class) . ' u';
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats;
    }
}
