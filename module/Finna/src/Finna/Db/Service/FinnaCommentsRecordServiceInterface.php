<?php

/**
 * Finna comments-record service interface
 *
 * PHP version 8
 *
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaCommentsRecordEntityInterface;
use VuFind\Db\Entity\CommentsEntityInterface;
use VuFind\Db\Service\DbServiceInterface;

/**
 * Finna comments-record service interface
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
interface FinnaCommentsRecordServiceInterface extends DbServiceInterface
{
    /**
     * Create a FinnaCommentsRecord entity object.
     *
     * @return FinnaCommentsRecordEntityInterface
     */
    public function createEntity(): FinnaCommentsRecordEntityInterface;

    /**
     * Find links by comment.
     *
     * @param CommentsEntityInterface $comment Comment
     *
     * @return FinnaCommentsRecordEntityInterface[]
     */
    public function findByComment(CommentsEntityInterface $comment): array;
}
