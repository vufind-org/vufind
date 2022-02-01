<?php
/**
 * Database service for tags.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Pagination\Paginator;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\ResourceTags;

/**
 * Database service for tags.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class TagService extends AbstractService
{
    /**
     * Are tags case sensitive?
     *
     * @var bool
     */
    protected $caseSensitive;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param bool                $caseSensitive       Are tags case sensitive?
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        bool $caseSensitive
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->caseSensitive = $caseSensitive;
    }

    /**
     * Remove links from the resource_tags table based on an array of IDs.
     *
     * @param string[] $ids Identifiers from resource_tags to delete.
     *
     * @return int          Count of $ids
     */
    public function deleteLinksByResourceTagsIdArray(array $ids): int
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'WHERE rt.id IN (:ids)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('ids'));
        $query->execute();
        return count($ids);
    }

    /**
     * Get count of anonymous tags
     *
     * @return int count
     */
    public function getAnonymousCount(): int
    {
        $dql = "SELECT COUNT(rt.id) AS total "
            . "FROM " . $this->getEntityClass(ResourceTags::class) . " rt "
            . "WHERE rt.user IS NULL";
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats['total'];
    }
    /**
     * Given an array for sorting database results, make sure the tag field is
     * sorted in a case-insensitive fashion and that no illegal fields are
     * specified.
     *
     * @param array $order Order settings
     *
     * @return array
     */
    protected function formatTagOrder(array $order)
    {
        // This array defines legal sort fields:
        $legalSorts = ['tag', 'title', 'username'];
        $newOrder = [];
        foreach ($order as $next) {
            if (in_array($next, $legalSorts)) {
                $newOrder[] = $next . ' ASC';
            }
        }
        return $newOrder;
    }

    /**
     * Delete resource tags rows matching specified filter(s).
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return void
     */
    public function deleteResourceTags(
        $userId = null,
        $resourceId = null,
        $tagId = null
    ): void {
        $dql = ' DELETE FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt ';
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = "rt.user = :user";
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = "rt.resource = :resource";
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = "rt.tag = :tag";
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get Resource Tags
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     * @param string $order      The order in which to return the data
     * @param string $page       The page number to select
     * @param string $limit      The number of items to fetch
     *
     * @return Paginator
     */
    public function getResourceTags(
        $userId = null,
        $resourceId = null,
        $tagId = null,
        $order = null,
        $page = null,
        $limit = 20
    ): Paginator {
        $tag = $this->caseSensitive ? 't.tag' : 'lower(t.tag)';
        $dql = "SELECT rt.id, $tag AS tag, u.username AS username, r.title AS title,"
            . ' t.id AS tag_id, r.id AS resource_id, u.id AS user_id '
            . 'FROM ' . $this->getEntityClass(ResourceTags::class) . ' rt '
            . 'LEFT JOIN rt.resource r '
            . 'LEFT JOIN rt.tag t '
            . 'LEFT JOIN rt.user u';
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = "rt.user = :user";
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = "r.id = :resource";
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = "rt.tag = :tag";
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $sanitizedOrder = $this->formatTagOrder(
            (array)($order ?? ["username", "tag", "title"])
        );
        $dql .= ' ORDER BY ' . implode(', ', $sanitizedOrder);
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        if (null !== $page) {
            $query->setMaxResults($limit);
            $query->setFirstResult($limit * ($page - 1));
        }

        $paginator = new Paginator($query);
        $paginator->setUseOutputWalkers(false);
        return $paginator;
    }

    /**
     * Gets unique tagged resources from the database
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return array
     */
    public function getUniqueResources(
        string $userId = null,
        string $resourceId = null,
        string $tagId = null
    ): array {
        $dql = "SELECT r.id AS resource_id, MAX(rt.tag) AS tag_id, "
            . "MAX(rt.list) AS list_id, MAX(rt.user) AS user_id, MAX(rt.id) AS id, "
            . "r.title AS title "
            . "FROM " . $this->getEntityClass(ResourceTags::class) . " rt "
            . "LEFT JOIN rt.resource r ";
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = "rt.user = :user";
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = "r.id = :resource";
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = "rt.tag = :tag";
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= " GROUP BY resource_id, title"
            . " ORDER BY title";
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Gets unique tags from the database
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return array
     */
    public function getUniqueTags(
        string $userId = null,
        string $resourceId = null,
        string $tagId = null
    ): array {
        $tagClause = $this->caseSensitive ? "t.tag" : "LOWER(t.tag)";
        $dql = "SELECT MAX(r.id) AS resource_id, MAX(t.id) AS tag_id, "
            . "MAX(l.id) AS list_id, MAX(u.id) AS user_id, MAX(rt.id) AS id, "
            . $tagClause . " AS tag "
            . "FROM " . $this->getEntityClass(ResourceTags::class) . " rt "
            . "LEFT JOIN rt.resource r "
            . "LEFT JOIN rt.tag t "
            . "LEFT JOIN rt.list l "
            . "LEFT JOIN rt.user u";
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = "u.id = :user";
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = "r.id = :resource";
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = "t.id = :tag";
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= " GROUP BY tag"
            . " ORDER BY tag";
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Gets unique users from the database
     *
     * @param string $userId     ID of user
     * @param string $resourceId ID of the resource
     * @param string $tagId      ID of the tag
     *
     * @return array
     */
    public function getUniqueUsers(
        string $userId = null,
        string $resourceId = null,
        string $tagId = null
    ): array {
        $dql = "SELECT MAX(rt.resource) AS resource_id, MAX(rt.tag) AS tag_id, "
            . "MAX(rt.list) AS list_id, u.id AS user_id, MAX(rt.id) AS id, "
            . "u.username AS username "
            . "FROM " . $this->getEntityClass(ResourceTags::class) . " rt "
            . "INNER JOIN rt.user u ";
        $parameters = $dqlWhere = [];
        if (null !== $userId) {
            $dqlWhere[] = "rt.user = :user";
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dqlWhere[] = "rt.resource = :resource";
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dqlWhere[] = "rt.tag = :tag";
            $parameters['tag'] = $tagId;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= " GROUP BY user_id, username"
            . " ORDER BY username";
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Get statistics on use of tags.
     *
     * @param bool $extended Include extended (unique/anonymous) stats.
     *
     * @return array
     */
    public function getStatistics(bool $extended = false): array
    {
        $dql = "SELECT COUNT(DISTINCT(rt.user)) AS users, "
            . "COUNT(DISTINCT(rt.resource)) AS resources, "
            . "COUNT(rt.id) AS total "
            . "FROM " . $this->getEntityClass(ResourceTags::class) . " rt";
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        if ($extended) {
            $stats['unique'] = count($this->getUniqueTags());
            $stats['anonymous'] = $this->getAnonymousCount();
        }
        return $stats;
    }
}
