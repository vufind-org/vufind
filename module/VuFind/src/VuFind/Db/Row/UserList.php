<?php
/**
 * Row Definition for user_list
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Row;

use Laminas\Session\Container;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Tags;

/**
 * Row Definition for user_list
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $title
 * @property string $description
 * @property string $created
 * @property bool   $public
 */
class UserList extends RowGateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Session container for last list information.
     *
     * @var Container
     */
    protected $session = null;

    /**
     * Tag parser.
     *
     * @var Tags
     */
    protected $tagParser;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter   Database adapter
     * @param Tags                        $tagParser Tag parser
     * @param Container                   $session   Session container
     */
    public function __construct($adapter, Tags $tagParser, Container $session = null)
    {
        $this->tagParser = $tagParser;
        $this->session = $session;
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
     * Get an array of resource tags associated with this list.
     *
     * @return array
     */
    public function getResourceTags()
    {
        $table = $this->getDbTable('User');
        $user = $table->select(['id' => $this->user_id])->current();
        if (empty($user)) {
            return [];
        }
        return $user->getTags(null, $this->id);
    }

    /**
     * Get an array of tags assigned to this list.
     *
     * @return array
     */
    public function getListTags()
    {
        $table = $this->getDbTable('User');
        $user = $table->select(['id' => $this->user_id])->current();
        if (empty($user)) {
            return [];
        }
        return $user->getListTags($this->id, $this->user_id);
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param \VuFind\Db\Row\User|bool   $user    Logged-in user (false if none)
     * @param \Laminas\Stdlib\Parameters $request Request to process
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

        if (null !== ($tags = $request->get('tags'))) {
            $linker = $this->getDbTable('resourcetags');
            $linker->destroyListLinks($this->id, $user->id);
            foreach ($this->tagParser->parse($tags) as $tag) {
                $this->addListTag($tag, $user);
            }
        }

        return $this->id;
    }

    /**
     * Add a tag to the list.
     *
     * @param string              $tagText The tag to save.
     * @param \VuFind\Db\Row\User $user    The user posting the tag.
     *
     * @return void
     */
    public function addListTag($tagText, $user)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('tags');
            $tag = $tags->getByText($tagText);
            $linker = $this->getDbTable('resourcetags');
            $linker->createLink(
                null,
                $tag->id,
                $user->id,
                $this->id
            );
        }
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
     * Set session container
     *
     * @param Container $session Session container
     *
     * @return void
     */
    public function setSession(Container $session)
    {
        $this->session = $session;
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @return void
     */
    public function rememberLastUsed()
    {
        if (null !== $this->session) {
            $this->session->lastUsed = $this->id;
        }
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
    public function removeResourcesById(
        $user,
        $ids,
        $source = DEFAULT_SEARCH_BACKEND
    ) {
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
        $userResourceTable->destroyLinks(
            $resourceIDs,
            $this->user_id,
            $this->id
        );
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

        // Remove resource_tags rows for list tags:
        $linker = $this->getDbTable('resourcetags');
        $linker->destroyListLinks($this->id, $user->id);

        // Remove the list itself:
        return parent::delete();
    }
}
