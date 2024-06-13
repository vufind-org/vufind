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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\FeedbackEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

use function count;

/**
 * Database service for feedback.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class FeedbackService extends AbstractDbService implements DbTableAwareInterface, FeedbackServiceInterface
{
    use DbTableAwareTrait;

    /**
     * Create a feedback entity object.
     *
     * @return FeedbackEntityInterface
     */
    public function createEntity(): FeedbackEntityInterface
    {
        return $this->getDbTable('feedback')->createRow();
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
        return $this->getDbTable('feedback')->select(['id' => $id])->current();
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
        // The template expects a different format than what is returned by Laminas\Db; we need to do
        // some data conversion and then populate a new paginator with the remapped results. We'll use
        // a padded array and the array adapter to make this work. Probably not the most robust solution,
        // but good enough for the current needs of the software; this will go away in a future database
        // layer migration.
        $feedbackTable = $this->getDbTable('feedback');
        $paginator = $feedbackTable->getFeedbackByFilter($formName, $siteUrl, $status, $page, $limit);
        $results = array_fill(0, count($paginator->getAdapter()), []);
        $index = (($page ?? 1) - 1) * $limit;
        foreach ($paginator as $current) {
            $row = (array)$current;
            $row['feedback_entity'] = $feedbackTable->createRow()->populate($row);
            $results[$index] = $row;
            $index++;
        }
        $newPaginator = new Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($results));
        $newPaginator->setCurrentPageNumber($page ?? 1);
        $newPaginator->setItemCountPerPage($limit);
        return $newPaginator;
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
        return $this->getDbTable('feedback')->deleteByIdArray($ids);
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
        $feedbackTable = $this->getDbTable('feedback');
        $feedback = $feedbackTable->select(
            function ($select) use ($column) {
                $select->columns(['id', $column]);
                $select->order($column);
            }
        );
        $feedbackArray = $feedback->toArray();
        return array_unique(array_column($feedbackArray, $column));
    }
}
