<?php

/**
 * Database service for Comments.
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

use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

use function is_int;

/**
 * Database service for Comments.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class CommentsService extends AbstractDbService implements CommentsServiceInterface, DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Add a comment to the current resource. Returns comment ID on success, null on failure.
     *
     * @param string                      $comment  The comment to save.
     * @param int|UserEntityInterface     $user     User object or identifier
     * @param int|ResourceEntityInterface $resource Resource object or identifier
     *
     * @return ?int
     */
    public function addComment(
        string $comment,
        int|UserEntityInterface $user,
        int|ResourceEntityInterface $resource
    ): ?int {
        $userVal = is_int($user)
            ? $this->getDbService(UserService::class)->getUserById($user)
            : $user;
        $resourceVal = is_int($resource)
            ? $this->getDbService(ResourceService::class)->getResourceById($resource)
            : $resource;
        return $resourceVal->addComment($comment, $userVal);
    }
}
