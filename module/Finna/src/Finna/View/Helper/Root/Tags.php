<?php

/**
 * Tags view helper.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\TagServiceInterface;

/**
 * Tags view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Tags extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param TagServiceInterface $tagService Tag service
     */
    public function __construct(protected TagServiceInterface $tagService)
    {
    }

    /**
     * Get tags assigned to a user list. Returns an array of arrays with id and tag keys.
     *
     * @param UserListEntityInterface|int  $listOrId      List ID or entity
     * @param UserEntityInterface|int|null $userOrId      User ID or entity to look up (null for no filter).
     * @param bool                         $caseSensitive Treat tags as case-sensitive?
     *
     * @return array[]
     */
    public function getListTags(
        UserListEntityInterface|int $listOrId,
        UserEntityInterface|int|null $userOrId = null,
        $caseSensitive = false
    ): array {
        return $this->tagService->getListTags($listOrId, $userOrId, $caseSensitive);
    }
}
