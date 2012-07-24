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
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Row;
use VuFind\Db\Table\User as UserTable, Zend\Db\RowGateway\RowGateway;

/**
 * Row Definition for user_list
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UserList extends RowGateway
{
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
     * @return bool
     */
    public function editAllowed()
    {
        /* TODO
        $account = VF_Account_Manager::getInstance();
        $user = $account->isLoggedIn();
        if ($user && $user->id == $this->user_id) {
            return true;
        }
        return false;
         */
    }

    /**
     * Get an array of tags associated with this list.
     *
     * @return array
     */
    public function getTags()
    {
        $table = new UserTable();
        $user = $table->select(array('id' => $this->user_id))->current();
        if (empty($user)) {
            return array();
        }
        return $user->getTags(null, $this->id);
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param Zend_Controller_Request_Abstract $request Request to process
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws VF_Exception_ListPermission
     * @throws VF_Exception_MissingField
     */
    public function updateFromRequest($request)
    {
        /* TODO
        $this->title = $request->getParam('title');
        $this->description = $request->getParam('desc');
        $this->public = $request->getParam('public');
        return $this->save();
         */
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws VF_Exception_ListPermission
     * @throws VF_Exception_MissingField
     */
    public function save()
    {
        /* TODO
        if (!$this->editAllowed()) {
            throw new VF_Exception_ListPermission('list_access_denied');
        }
        if (empty($this->title)) {
            throw new VF_Exception_MissingField('list_edit_name_required');
        }

        $this->id = parent::save();
        $this->rememberLastUsed();
        return $this->id;
         */
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @return void
     */
    public function rememberLastUsed()
    {
        /* TODO
        $session = new Zend_Session_Namespace('List');
        $session->lastUsed = $this->id;
         */
    }

    /**
     * Retrieve the ID of the last list that was accessed, if any.
     *
     * @return mixed User_list ID (if set) or null (if not available).
     */
    public static function getLastUsed()
    {
        /* TODO
        $session = new Zend_Session_Namespace('List');
        return isset($session->lastUsed) ? $session->lastUsed : null;
         */
    }

    /**
     * Given an array of item ids, remove them from all lists
     *
     * @param array  $ids    IDs to remove from the list
     * @param string $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeResourcesById($ids, $source = 'VuFind')
    {
        /* TODO
        if (!$this->editAllowed()) {
            throw new VF_Exception_ListPermission('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resourceTable = new VuFind_Model_Db_Resource();
        $resources = $resourceTable->findResources($ids, $source);

        $resourceIDs = array();
        foreach ($resources as $current) {
            $resourceIDs[] = $current->id;
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceTable = new VuFind_Model_Db_UserResource();
        $userResourceTable->destroyLinks($resourceIDs, $this->user_id, $this->id);
         */
    }

    /**
     * Destroy the list.
     *
     * @param bool $force Should we force the delete without checking permissions?
     *
     * @return int The number of rows deleted.
     */
    public function delete($force = false)
    {
        /* TODO
        if (!$force && !$this->editAllowed()) {
            throw new VF_Exception_ListPermission('list_access_denied');
        }

        // Remove user_resource and resource_tags rows:
        $userResource = new VuFind_Model_Db_UserResource();
        $userResource->destroyLinks(null, $this->user_id, $this->id);

        // Remove the list itself:
        return parent::delete();
         */
    }
}
