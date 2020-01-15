<?php

/**
 * Service for modifying User Lists
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * Favorites service
 *
 * @category VuFind
 * @package  Favorites
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Favorites;

use VuFind\Db\Table\Resource as ResourceTable;
use VuFind\Db\Table\UserList as UserListTable;
use VuFind\Db\Table\UserResource as UserResourceTable;
use VuFind\Record\Cache as RecordCache;

/**
 *  Favorites service
 *
 * @category VuFind
 * @package  Favorites
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FavoritesService extends \VuFind\Favorites\FavoritesService
{
    /**
     * UserResource table
     *
     * @var UserResourceTable
     */
    protected $userResourceTable;

    /**
     * Constructor
     *
     * @param UserListTable     $userList          UserList table object
     * @param ResourceTable     $resource          Resource table object
     * @param RecordCache       $cache             Record cache
     * @param UserResourceTable $userResourceTable User Resource join table
     */
    public function __construct(UserListTable $userList, ResourceTable $resource,
        RecordCache $cache = null, UserResourceTable $userResourceTable
    ) {
        $this->recordCache = $cache;
        $this->userListTable = $userList;
        $this->resourceTable = $resource;
        parent::__construct($userList, $resource, $cache);
        $this->userResourceTable = $userResourceTable;
    }

    /**
     * Save this record to the user's favorites.
     *
     * @param array                 $params  Array with some or all of these keys:
     *  <ul>
     *    <li>mytags - Tag array to associate with record (optional)</li>
     *    <li>notes - Notes to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param \VuFind\Db\Row\User   $user    The user saving the record
     * @param array  RecordDriver[] $drivers Record drivers for record being saved
     *
     * @return array list information
     */
    public function saveMany(
        array $params,
        \VuFind\Db\Row\User $user,
        array $drivers
    ) {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }
        $listId = $params['list'] ?? '';
        // Get or create a list object as needed:
        $list = $this->getListObject(
            $listId,
            $user
        );

        // check if list has custom order, if so add custom order keys for new items
        $index = $this->userResourceTable->getNextAvailableCustomOrderIndex($listId);

        // if target list is not in custom order then reverse
        if (! $this->userResourceTable->isCustomOrderAvailable($listId)) {
            $drivers = array_reverse($drivers);
        }

        // Get or create a resource object as needed:
        $resources = array_map(
            function ($driver) {
                $resource = $this->resourceTable->findResource(
                    $driver->getUniqueId(),
                    $driver->getSourceIdentifier(),
                    true,
                    $driver
                );
                // Persist record in the database for "offline" use
                $this->persistToCache($driver, $resource);
                return $resource;
            },
            $drivers
        );

        // Add the information to the user's account:
        $user->saveResources(
            $resources,
            $list,
            $params['mytags'] ?? [],
            $params['notes'] ?? '',
            true,
            $index
        );
        return ['listId' => $list->id];
    }
}
