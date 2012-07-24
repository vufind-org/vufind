<?php
/**
 * Row Definition for resource
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
use VuFind\Db\Table\Tags as TagsTable,
    VuFind\Db\Table\ResourceTags as ResourceTagsTable, Zend\Db\RowGateway\RowGateway;

/**
 * Row Definition for resource
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Resource extends RowGateway
{
    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'resource', $adapter);
    }

    /**
     * Remove tags from the current resource.
     *
     * @param Zend_Db_Table_Row $user    The user deleting the tags.
     * @param string            $list_id The list associated with the tags
     * (optional -- omitting this will delete ALL of the user's tags).
     *
     * @return void
     */
    public function deleteTags($user, $list_id = null)
    {
        /* TODO
        $unlinker = new VuFind_Model_Db_ResourceTags();
        $unlinker->destroyLinks($this->id, $user->id, $list_id);
         */
    }

    /**
     * Add a tag to the current resource.
     *
     * @param string            $tagText The tag to save.
     * @param Zend_Db_Table_Row $user    The user posting the tag.
     * @param string            $list_id The list associated with the tag (optional).
     *
     * @return void
     */
    public function addTag($tagText, $user, $list_id = null)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tags = new TagsTable();
            $tag = $tags->getByText($tagText);

            $linker = new ResourceTagsTable();
            $linker->createLink(
                $this->id, $tag->id, is_object($user) ? $user->id : null, $list_id
            );
        }
    }

    /**
     * Add a comment to the current resource.
     *
     * @param string            $comment The comment to save.
     * @param Zend_Db_Table_Row $user    The user posting the comment.
     *
     * @throws VF_Exception_LoginRequired
     * @return int                       ID of newly-created comment.
     */
    public function addComment($comment, $user)
    {
        /* TODO
        if (!isset($user->id)) {
            throw new VF_Exception_LoginRequired(
                "Can't add comments without logging in."
            );
        }

        $table = new VuFind_Model_Db_Comments();
        $row = $table->createRow();
        $row->user_id = $user->id;
        $row->resource_id = $this->id;
        $row->comment = $comment;
        $row->created = date('Y-m-d h:i:s');
        $row->save();
        return $row->id;
         */
    }

    /**
     * Use a record driver to assign metadata to the current row.  Return the
     * current object to allow fluent interface.
     *
     * @param VF_RecordDriver_Base $driver The record driver.
     *
     * @return VuFind_Model_Db_ResourceRow
     */
    public function assignMetadata($driver)
    {
        /* TODO
        // Grab title -- we have to have something in this field!
        $this->title = $driver->tryMethod('getSortTitle');
        if (empty($this->title)) {
            $this->title = $driver->getBreadcrumb();
        }

        // Try to find an author; if not available, just leave the default null:
        $author = $driver->tryMethod('getPrimaryAuthor');
        if (!empty($author)) {
            $this->author = $author;
        }

        // Try to find a year; if not available, just leave the default null:
        $dates = $driver->tryMethod('getPublicationDates');
        if (isset($dates[0]) && strlen($dates[0]) > 4) {
            $converter = new VF_Date_Converter();
            try {
                $year = $converter->convertFromDisplayDate('Y', $dates[0]);
            } catch (VF_Exception_Date $e) {
                // If conversion fails, don't store a date:
                $year = '';
            }
        } else {
            $year = isset($dates[0]) ? $dates[0] : '';
        }
        if (!empty($year)) {
            $this->year = intval($year);
        }

        return $this;
         */
    }
}
