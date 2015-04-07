<?php
/**
 * Row Definition for user_list
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Row;
use VuFind\Exception\ListPermission as ListPermissionException,
    VuFind\Exception\MissingField as MissingFieldException,
    Zend\Session\Container as SessionContainer;

/**
 * Row Definition for user_list
 *
 * @category VuFind2
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UserList extends RowGateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'user_list', $adapter);
    }

    /**
     * Is the current user allowed to edit this list?
     *
     * @param \VuFind\Db\Row\User|bool $user Logged-in user (false if none)
     *
     * @return bool
     */
    public function editAllowed($user)
    {
        if ($user && $user->id == $this->user_id) {
            return true;
        }
        return false;
    }

    /**
     * Get an array of tags associated with this list.
     *
     * @return array
     */
    public function getTags()
    {
        $table = $this->getDbTable('User');
        $user = $table->select(['id' => $this->user_id])->current();
        if (empty($user)) {
            return [];
        }
        return $user->getTags(null, $this->id);
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param \VuFind\Db\Row\User|bool $user    Logged-in user (false if none)
     * @param \Zend\Stdlib\Parameters  $request Request to process
     *
     * @return int ID of newly created row
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function updateFromRequest($user, $request)
    {
        $this->title = $request->get('title');
        $this->description = $request->get('desc');
        $this->public = $request->get('public');
        $this->save($user);
        return $this->id;
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @param \VuFind\Db\Row\User|bool $user Logged-in user (false if none)
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function save($user = false)
    {
        if (!$this->editAllowed($user)) {
            throw new ListPermissionException('list_access_denied');
        }
        if (empty($this->title)) {
            throw new MissingFieldException('list_edit_name_required');
        }

        parent::save();
        $this->rememberLastUsed();
        return $this->id;
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @return void
     */
    public function rememberLastUsed()
    {
        $session = new SessionContainer('List');
        $session->lastUsed = $this->id;
    }

    /**
     * Retrieve the ID of the last list that was accessed, if any.
     *
     * @return mixed User_list ID (if set) or null (if not available).
     */
    public static function getLastUsed()
    {
        $session = new SessionContainer('List');
        return isset($session->lastUsed) ? $session->lastUsed : null;
    }

    /**
     * Given an array of item ids, remove them from all lists
     *
     * @param \VuFind\Db\Row\User|bool $user   Logged-in user (false if none)
     * @param array                    $ids    IDs to remove from the list
     * @param string                   $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeResourcesById($user, $ids, $source = 'VuFind')
    {
        if (!$this->editAllowed($user)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resourceTable = $this->getDbTable('Resource');
        $resources = $resourceTable->findResources($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->id;
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceTable = $this->getDbTable('UserResource');
        $userResourceTable->destroyLinks($resourceIDs, $this->user_id, $this->id);
    }

    /**
     * Is this a public list?
     *
     * @return bool
     */
    public function isPublic()
    {
        return isset($this->public) && ($this->public == 1);
    }

    /**
     * Destroy the list.
     *
     * @param \VuFind\Db\Row\User|bool $user  Logged-in user (false if none)
     * @param bool                     $force Should we force the delete without
     * checking permissions?
     *
     * @return int The number of rows deleted.
     */
    public function delete($user = false, $force = false)
    {
        if (!$force && !$this->editAllowed($user)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Remove user_resource and resource_tags rows:
        $userResource = $this->getDbTable('UserResource');
        $userResource->destroyLinks(null, $this->user_id, $this->id);

        // Remove the list itself:
        return parent::delete();
    }
}
