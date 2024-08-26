<?php

/**
 * Row Definition for user_list
 *
 * PHP version 8
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

use DateTime;
use Laminas\Session\Container;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Tags\TagsService;

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
class UserList extends RowGateway implements
    \VuFind\Db\Table\DbTableAwareInterface,
    UserListEntityInterface,
    DbServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter     Database adapter
     * @param TagsService                 $tagsService Tags service
     * @param ?Container                  $session     Session container for last list information
     */
    public function __construct($adapter, protected TagsService $tagsService, protected ?Container $session = null)
    {
        parent::__construct('id', 'user_list', $adapter);
    }

    /**
     * Is the current user allowed to edit this list?
     *
     * @param ?UserEntityInterface $user Logged-in user (null if none)
     *
     * @return bool
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::userCanEditList()
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
     *
     * @deprecated Use \VuFind\Db\Service\TagServiceInterface::getListTags()
     */
    public function getListTags()
    {
        return $this->getDbTable('Tags')->getForList($this->getId(), $this->getUser()->getId());
    }

    /**
     * Add a tag to the list.
     *
     * @param string              $tagText The tag to save.
     * @param UserEntityInterface $user    The user posting the tag.
     *
     * @return void
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::addListTag()
     */
    public function addListTag($tagText, $user)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('tags');
            $tag = $tags->getByText($tagText);
            $this->getDbService(ResourceTagsServiceInterface::class)->createLink(
                null,
                $tag->id,
                $user,
                $this
            );
        }
    }

    /**
     * Set session container.
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
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::rememberLastUsedList()
     */
    public function rememberLastUsed()
    {
        if (null !== $this->session) {
            $this->session->lastUsed = $this->id;
        }
    }

    /**
     * Given an array of item ids, remove them from all lists.
     *
     * @param UserEntityInterface|bool $user   Logged-in user (false if none)
     * @param array                    $ids    IDs to remove from the list
     * @param string                   $source Type of resource identified by IDs
     *
     * @return void
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::removeListResourcesById()
     */
    public function removeResourcesById(
        $user,
        $ids,
        $source = DEFAULT_SEARCH_BACKEND
    ) {
        if (!$this->editAllowed($user ?: null)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resources = $this->getDbService(ResourceServiceInterface::class)->getResourcesByRecordIds($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->getId();
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
    public function isPublic(): bool
    {
        return isset($this->public) && ($this->public == 1);
    }

    /**
     * Destroy the list.
     *
     * @param \VuFind\Db\Row\User|bool $user  Logged-in user (false if none)
     * @param bool                     $force Should we force the delete without checking permissions?
     *
     * @return int The number of rows deleted.
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::destroyList()
     */
    public function delete($user = false, $force = false)
    {
        if (!$force && !$this->editAllowed($user ?: null)) {
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

    /**
     * Get identifier (returns null for an uninitialized or non-persisted object).
     *
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    /**
     * Set title.
     *
     * @param string $title Title
     *
     * @return UserListEntityInterface
     */
    public function setTitle(string $title): UserListEntityInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    /**
     * Set description.
     *
     * @param ?string $description Description
     *
     * @return UserListEntityInterface
     */
    public function setDescription(?string $description): UserListEntityInterface
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description.
     *
     * @return ?string
     */
    public function getDescription(): ?string
    {
        return $this->description ?? null;
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return UserListEntityInterface
     */
    public function setCreated(DateTime $dateTime): UserListEntityInterface
    {
        $this->created = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created);
    }

    /**
     * Set whether the list is public.
     *
     * @param bool $public Is the list public?
     *
     * @return UserListEntityInterface
     */
    public function setPublic(bool $public): UserListEntityInterface
    {
        $this->public = $public ? '1' : '0';
        return $this;
    }

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User owning the list.
     *
     * @return UserListEntityInterface
     */
    public function setUser(?UserEntityInterface $user): UserListEntityInterface
    {
        $this->user_id = $user?->getId();
        return $this;
    }

    /**
     * Get user.
     *
     * @return ?UserEntityInterface
     */
    public function getUser(): ?UserEntityInterface
    {
        return $this->user_id
            ? $this->getDbServiceManager()->get(UserServiceInterface::class)->getUserById($this->user_id)
            : null;
    }
}
