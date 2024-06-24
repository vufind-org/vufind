<?php

/**
 * Row Definition for resource_tags
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
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\ResourceTagsEntityInterface;
use VuFind\Db\Entity\TagsEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserServiceInterface;

/**
 * Row Definition for resource_tags
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property int    $resource_id
 * @property int    $tag_id
 * @property int    $list_id
 * @property int    $user_id
 * @property string $posted
 */
class ResourceTags extends RowGateway implements
    \VuFind\Db\Entity\ResourceTagsEntityInterface,
    \VuFind\Db\Table\DbTableAwareInterface,
    DbServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'resource_tags', $adapter);
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
     * Get resource.
     *
     * @return ?ResourceEntityInterface
     */
    public function getResource(): ?ResourceEntityInterface
    {
        return $this->resource_id
        ? $this->getDbServiceManager()->get(ResourceServiceInterface::class)->getResourceById($this->resource_id)
        : null;
    }

    /**
     * Set resource.
     *
     * @param ?ResourceEntityInterface $resource Resource
     *
     * @return ResourceTagsEntityInterface
     */
    public function setResource(?ResourceEntityInterface $resource): ResourceTagsEntityInterface
    {
        $this->resource_id = $resource?->getId();
        return $this;
    }

    /**
     * Get tag.
     *
     * @return TagsEntityInterface
     */
    public function getTag(): TagsEntityInterface
    {
        return $this->tag_id
            ? $this->getDbServiceManager()->get(TagServiceInterface::class)->getTagById($this->tag_id)
            : null;
    }

    /**
     * Set tag.
     *
     * @param TagsEntityInterface $tag Tag
     *
     * @return ResourceTagsEntityInterface
     */
    public function setTag(TagsEntityInterface $tag): ResourceTagsEntityInterface
    {
        $this->tag_id = $tag->getId();
        return $this;
    }

    /**
     * Get user list.
     *
     * @return ?UserListEntityInterface
     */
    public function getUserList(): ?UserListEntityInterface
    {
        return $this->list_id
        ? $this->getDbServiceManager()->get(UserListServiceInterface::class)->getUserListById($this->list_id)
        : null;
    }

    /**
     * Set user list.
     *
     * @param ?UserListEntityInterface $list User list
     *
     * @return ResourceTagsEntityInterface
     */
    public function setUserList(?UserListEntityInterface $list): ResourceTagsEntityInterface
    {
        $this->list_id = $list?->getId();
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

    /**
     * Set user.
     *
     * @param ?UserEntityInterface $user User
     *
     * @return ResourceTagsEntityInterface
     */
    public function setUser(?UserEntityInterface $user): ResourceTagsEntityInterface
    {
        $this->user_id = $user?->getId();
        return $this;
    }

    /**
     * Get created date.
     *
     * @return DateTime
     */
    public function getPosted(): DateTime
    {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->posted);
    }

    /**
     * Set created date.
     *
     * @param DateTime $dateTime Created date
     *
     * @return ResourceTagsEntityInterface
     */
    public function setPosted(DateTime $dateTime): ResourceTagsEntityInterface
    {
        $this->posted = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }
}
