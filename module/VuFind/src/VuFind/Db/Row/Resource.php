<?php
/**
 * Row Definition for resource
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

use VuFind\Date\DateException;
use VuFind\Exception\LoginRequired as LoginRequiredException;

/**
 * Row Definition for resource
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Resource extends RowGateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

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
     */
    public function deleteTags($user, $list_id = null)
    {
        $unlinker = $this->getDbTable('ResourceTags');
        $unlinker->destroyResourceLinks($this->id, $user->id, $list_id);
    }

    /**
     * Add a tag to the current resource.
     *
     * @param string              $tagText The tag to save.
     * @param \VuFind\Db\Row\User $user    The user posting the tag.
     * @param string              $list_id The list associated with the tag
     * (optional).
     *
     * @return void
     */
    public function addTag($tagText, $user, $list_id = null)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('Tags');
            $tag = $tags->getByText($tagText);

            $linker = $this->getDbTable('ResourceTags');
            $linker->createLink(
                $this->id,
                $tag->id,
                is_object($user) ? $user->id : null,
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
     */
    public function deleteTag($tagText, $user, $list_id = null)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = $this->getDbTable('Tags');
            $tagIds = [];
            foreach ($tags->getByText($tagText, false, false) as $tag) {
                $tagIds[] = $tag->id;
            }
            if (!empty($tagIds)) {
                $linker = $this->getDbTable('ResourceTags');
                $linker->destroyResourceLinks(
                    $this->id,
                    $user->id,
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
        $row->user_id = $user->id;
        $row->resource_id = $this->id;
        $row->comment = $comment;
        $row->created = date('Y-m-d H:i:s');
        $row->save();
        return $row->id;
    }

    /**
     * Use a record driver to assign metadata to the current row.  Return the
     * current object to allow fluent interface.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    The record driver
     * @param \VuFind\Date\Converter            $converter Date converter
     *
     * @return \VuFind\Db\Row\Resource
     */
    public function assignMetadata($driver, \VuFind\Date\Converter $converter)
    {
        // Grab title -- we have to have something in this field!
        $this->title = mb_substr(
            $driver->tryMethod('getSortTitle'),
            0,
            255,
            "UTF-8"
        );
        if (empty($this->title)) {
            $this->title = $driver->getBreadcrumb();
        }

        // Try to find an author; if not available, just leave the default null:
        $author = mb_substr(
            $driver->tryMethod('getPrimaryAuthor'),
            0,
            255,
            "UTF-8"
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
}
