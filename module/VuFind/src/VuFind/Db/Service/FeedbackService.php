<?php

/**
 * Database service for feedback.
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

use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use DoctrineORMModule\Paginator\Adapter\DoctrinePaginator as DoctrinePaginatorAdapter;
use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\Feedback;
use VuFind\Db\Entity\FeedbackEntityInterface;

use function count;
use function intval;

/**
 * Database service for feedback.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class FeedbackService extends AbstractDbService implements FeedbackServiceInterface
{
    /**
     * Db column name to Doctrine entity field mapper
     *
     * @var array
     */
    protected $fieldMap = [
        'form_data' => 'formData',
        'form_name' => 'formName',
        'site_url' => 'siteUrl',
        'user_id' => 'user',
        'updated_by' => 'updatedBy',
    ];

    /**
     * Create a feedback entity object.
     *
     * @return FeedbackEntityInterface
     */
    public function createEntity(): FeedbackEntityInterface
    {
        return $this->entityPluginManager->get(FeedbackEntityInterface::class);
    }

    /**
     * Fetch a feedback entity by ID.
     *
     * @param int $id ID of feedback entity
     *
     * @return ?FeedbackEntityInterface
     */
    public function getFeedbackById(int $id): ?FeedbackEntityInterface
    {
        return $this->entityManager->find(FeedbackEntityInterface::class, $id);
    }

    /**
     * Get feedback by filter
     *
     * @param ?string $formName Form name (optional filter)
     * @param ?string $siteUrl  Site URL (optional filter)
     * @param ?string $status   Current status (optional filter)
     * @param ?int    $page     Current page (optional)
     * @param int     $limit    Limit per page
     *
     * @return Paginator
     */
    public function getFeedbackPaginator(
        ?string $formName = null,
        ?string $siteUrl = null,
        ?string $status = null,
        ?int $page = null,
        int $limit = 20
    ): Paginator {
        $dql = 'SELECT f AS feedback_entity FROM ' . FeedbackEntityInterface::class . ' f';
        $parameters = $dqlWhere = [];

        if (null !== $formName) {
            $dqlWhere[] = 'f.formName = :formName';
            $parameters['formName'] = $formName;
        }
        if (null !== $siteUrl) {
            $dqlWhere[] = 'f.siteUrl = :siteUrl';
            $parameters['siteUrl'] = $siteUrl;
        }
        if (null !== $status) {
            $dqlWhere[] = 'f.status = :status';
            $parameters['status'] = $status;
        }
        if (!empty($dqlWhere)) {
            $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        }
        $dql .= ' ORDER BY f.created DESC';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        $page = null === $page ? null : intval($page);
        if (null !== $page) {
            $query->setMaxResults($limit);
            $query->setFirstResult($limit * ($page - 1));
        }
        $paginator = new Paginator(new DoctrinePaginatorAdapter(new DoctrinePaginator($query)));
        if (null !== $page) {
            $paginator->setCurrentPageNumber($page);
            $paginator->setItemCountPerPage($limit);
        }
        return $paginator;
    }

    /**
     * Delete feedback by ids
     *
     * @param array $ids IDs
     *
     * @return int Count of deleted rows
     */
    public function deleteByIdArray(array $ids): int
    {
        // Do nothing if we have no IDs to delete!
        if (empty($ids)) {
            return 0;
        }
        $dql = 'DELETE FROM ' . FeedbackEntityInterface::class . ' fb '
            . 'WHERE fb.id IN (:ids)';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('ids'));
        $query->execute();
        return count($ids);
    }

    /**
     * Get values for a column
     *
     * @param string $column Column name
     *
     * @return array
     */
    public function getColumn(string $column): array
    {
        $dql = 'SELECT f.id, f.' . $this->mapField($column)
            . ' FROM ' . FeedbackEntityInterface::class . ' f '
            . 'ORDER BY f.' . $this->mapField($column);
        $query = $this->entityManager->createQuery($dql);
        return $query->getResult();
    }

    /**
     * Column mapper
     *
     * @param string $column Column name
     *
     * @return string
     */
    protected function mapField($column)
    {
        return $this->fieldMap[$column] ?? $column;
    }

    /**
     * Get unique values for a column of the feedback table
     *
     * @param string $column Column name
     *
     * @return array
     */
    public function getUniqueColumn(string $column): array
    {
        return array_unique(array_column($this->getColumn($column), $this->mapField($column)));
    }
}
