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
     * Gets unique tags from the table
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
        $parameters = [];
        if (null !== $userId) {
            $dql .= " WHERE u.id = :user";
            $parameters['user'] = $userId;
        }
        if (null !== $resourceId) {
            $dql .= " WHERE r.id = :resource";
            $parameters['resource'] = $resourceId;
        }
        if (null !== $tagId) {
            $dql .= " WHERE t.id = :tag";
            $parameters['tag'] = $tagId;
        }
        $dql .= " GROUP BY tag"
            . " ORDER BY tag";
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
