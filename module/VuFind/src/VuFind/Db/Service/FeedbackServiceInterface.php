<?php

/**
 * Database service interface for feedback.
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

use Laminas\Paginator\Paginator;
use VuFind\Db\Entity\FeedbackEntityInterface;

/**
 * Database service interface for feedback.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface FeedbackServiceInterface extends DbServiceInterface
{
    /**
     * Create a feedback entity object.
     *
     * @return FeedbackEntityInterface
     */
    public function createEntity(): FeedbackEntityInterface;

    /**
     * Fetch a feedback entity by ID.
     *
     * @param int $id ID of feedback entity
     *
     * @return ?FeedbackEntityInterface
     */
    public function getFeedbackById(int $id): ?FeedbackEntityInterface;

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
    ): Paginator;

    /**
     * Delete feedback by ids
     *
     * @param array $ids IDs
     *
     * @return int Count of deleted rows
     */
    public function deleteByIdArray(array $ids): int;

    /**
     * Get unique values for a column of the feedback table
     *
     * @param string $column Column name
     *
     * @return array
     */
    public function getUniqueColumn(string $column): array;
}
