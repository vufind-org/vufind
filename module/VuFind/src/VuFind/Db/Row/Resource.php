<?php

/**
 * Row Definition for resource
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

use VuFind\Date\DateException;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Exception\LoginRequired as LoginRequiredException;

use function intval;
use function strlen;

/**
 * Row Definition for resource
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property string  $record_id
 * @property string  $title
 * @property ?string $author
 * @property ?int    $year
 * @property string  $source
 * @property ?string $extra_metadata
 */
class Resource extends RowGateway implements DbServiceAwareInterface, DbTableAwareInterface, ResourceEntityInterface
{
    use DbServiceAwareTrait;
    use DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'resource', $adapter);
    }

    /**
     * Remove tags from the current resource.
     *
     * @param \VuFind\Db\Row\User $user    The user deleting the tags.
     * @param string              $list_id The list associated with the tags
     * (optional -- omitting this will delete ALL of the user's tags).
     *
     * @return void
     *
     * @deprecated Use ResourceTagsServiceInterface::destroyResourceTagsLinksForUser()
     */
    public function deleteTags($user, $list_id = null)
    {
        $this->getDbService(ResourceTagsServiceInterface::class)
            ->destroyResourceTagsLinksForUser($this->getId(), $user, $list_id);
    }

    /**
     * Add a tag to the current resource.
     *
     * @param string              $tagText The tag to save.
     * @param UserEntityInterface $user    The user posting the tag.
     * @param string              $list_id The list associated with the tag
     * (optional).
     *
     * @return void
     *
     * @deprecated Use \VuFind\Tags\TagService::linkTagToResource()
     */
    public function addTag($tagText, $user, $list_id = null)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('Tags');
            $tag = $tags->getByText($tagText);

            $this->getDbService(ResourceTagsServiceInterface::class)->createLink(
                $this,
                $tag->id,
                $user,
                $list_id
            );
        }
    }

    /**
     * Remove a tag from the current resource.
     *
     * @param string              $tagText The tag to delete.
     * @param \VuFind\Db\Row\User $user    The user deleting the tag.
     * @param string              $list_id The list associated with the tag
     * (optional).
     *
     * @return void
     *
     * @deprecated Use \VuFind\Tags\TagsService::unlinkTagFromResource()
     */
    public function deleteTag($tagText, $user, $list_id = null)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('Tags');
            $tagIds = [];
            foreach ($tags->getByText($tagText, false, false) as $tag) {
                $tagIds[] = $tag->getId();
            }
            if (!empty($tagIds)) {
                $this->getDbService(ResourceTagsServiceInterface::class)->destroyResourceTagsLinksForUser(
                    $this->getId(),
                    $user,
                    $list_id,
                    $tagIds
                );
            }
        }
    }

    /**
     * Add a comment to the current resource.
     *
     * @param string              $comment The comment to save.
     * @param \VuFind\Db\Row\User $user    The user posting the comment.
     *
     * @throws LoginRequiredException
     * @return int                         ID of newly-created comment.
     */
    public function addComment($comment, $user)
    {
        if (!isset($user->id)) {
            throw new LoginRequiredException(
                "Can't add comments without logging in."
            );
        }

        $table = $this->getDbTable('Comments');
        $row = $table->createRow();
        $row->setUser($user)
            ->setResource($this)
            ->setComment($comment)
            ->setCreated(new \DateTime());
        $row->save();
        return $row->getId();
    }

    /**
     * Add or update user's rating for the current resource.
     *
     * @param int  $userId User ID
     * @param ?int $rating Rating (null to delete)
     *
     * @throws LoginRequiredException
     * @throws \Exception
     * @return int ID of rating added, deleted or updated
     */
    public function addOrUpdateRating(int $userId, ?int $rating): int
    {
        if (null !== $rating && ($rating < 0 || $rating > 100)) {
            throw new \Exception('Rating value out of range');
        }

        $ratings = $this->getDbTable('Ratings');
        $callback = function ($select) use ($userId) {
            $select->where->equalTo('ratings.resource_id', $this->id);
            $select->where->equalTo('ratings.user_id', $userId);
        };
        if ($existing = $ratings->select($callback)->current()) {
            if (null === $rating) {
                $existing->delete();
            } else {
                $existing->rating = $rating;
                $existing->save();
            }
            return $existing->id;
        }

        if (null === $rating) {
            return 0;
        }

        $row = $ratings->createRow();
        $row->user_id = $userId;
        $row->resource_id = $this->id;
        $row->rating = $rating;
        $row->created = date('Y-m-d H:i:s');
        $row->save();
        return $row->id;
    }

    /**
     * Use a record driver to assign metadata to the current row. Return the
     * current object to allow fluent interface.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    The record driver
     * @param \VuFind\Date\Converter            $converter Date converter
     *
     * @return \VuFind\Db\Row\Resource
     *
     * @deprecated Use \VuFind\Record\ResourcePopulator::assignMetadata()
     */
    public function assignMetadata($driver, \VuFind\Date\Converter $converter)
    {
        // Grab title -- we have to have something in this field!
        $this->title = mb_substr(
            $driver->tryMethod('getSortTitle'),
            0,
            255,
            'UTF-8'
        );
        if (empty($this->title)) {
            $this->title = $driver->getBreadcrumb();
        }

        // Try to find an author; if not available, just leave the default null:
        $author = mb_substr(
            $driver->tryMethod('getPrimaryAuthor'),
            0,
            255,
            'UTF-8'
        );
        if (!empty($author)) {
            $this->author = $author;
        }

        // Try to find a year; if not available, just leave the default null:
        $dates = $driver->tryMethod('getPublicationDates');
        if (isset($dates[0]) && strlen($dates[0]) > 4) {
            try {
                $year = $converter->convertFromDisplayDate('Y', $dates[0]);
            } catch (DateException $e) {
                // If conversion fails, don't store a date:
                $year = '';
            }
        } else {
            $year = $dates[0] ?? '';
        }
        if (!empty($year)) {
            $this->year = intval($year);
        }

        if ($extra = $driver->tryMethod('getExtraResourceMetadata')) {
            $this->extra_metadata = json_encode($extra);
        }
        return $this;
    }

    /**
     * Id getter
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Record Id setter
     *
     * @param string $recordId recordId
     *
     * @return ResourceEntityInterface
     */
    public function setRecordId(string $recordId): ResourceEntityInterface
    {
        $this->record_id = $recordId;
        return $this;
    }

    /**
     * Record Id getter
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->record_id;
    }

    /**
     * Title setter
     *
     * @param string $title Title of the record.
     *
     * @return ResourceEntityInterface
     */
    public function setTitle(string $title): ResourceEntityInterface
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Title getter
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Author setter
     *
     * @param ?string $author Author of the title.
     *
     * @return ResourceEntityInterface
     */
    public function setAuthor(?string $author): ResourceEntityInterface
    {
        $this->author = $author;
        return $this;
    }

    /**
     * Year setter
     *
     * @param ?int $year Year title is published.
     *
     * @return ResourceEntityInterface
     */
    public function setYear(?int $year): ResourceEntityInterface
    {
        $this->year = $year;
        return $this;
    }

    /**
     * Source setter
     *
     * @param string $source Source (a search backend ID).
     *
     * @return ResourceEntityInterface
     */
    public function setSource(string $source): ResourceEntityInterface
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Source getter
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Extra Metadata setter
     *
     * @param ?string $extraMetadata ExtraMetadata.
     *
     * @return ResourceEntityInterface
     */
    public function setExtraMetadata(?string $extraMetadata): ResourceEntityInterface
    {
        $this->extra_metadata = $extraMetadata;
        return $this;
    }

    /**
     * Extra Metadata getter
     *
     * @return ?string
     */
    public function getExtraMetadata(): ?string
    {
        return $this->extra_metadata;
    }
}
