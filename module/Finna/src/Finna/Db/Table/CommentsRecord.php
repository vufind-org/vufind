<?php
/**
 * Table Definition for Comments-Record link table.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use VuFind\Db\Table\Gateway;

/**
 * Table Definition for Comments-Record link table.
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CommentsRecord extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('finna_comments_record', 'Finna\Db\Row\CommentsRecord');
        $this->table = 'finna_comments_record';
    }

    /**
     * Link comment with records.
     *
     * @param int   $comment Comment id
     * @param array $records Array of record IDs
     *
     * @return void
     */
    public function addLinks($comment, $records)
    {
        foreach ($records as $record) {
            $row = $this->createRow();
            $row->record_id = $record;
            $row->comment_id = $comment;
            $row->save();
        }
    }

    /**
     * Verify links to records
     *
     * @param string $comment Comment id
     * @param array  $records Array of record IDs
     *
     * @return boolean True if any links were fixed
     */
    public function verifyLinks($comment, $records)
    {
        $fixed = false;

        // Remove any orphaned links
        $links = $this->select(
            ['comment_id' => $comment]
        );
        foreach ($links as $link) {
            if (!in_array($link->record_id, $records)) {
                $link->delete();
                $fixed = true;
            }
        }

        // Add missing links
        foreach ($records as $recordId) {
            $data = ['record_id' => $recordId, 'comment_id' => $comment];
            if (empty($this->select($data)->current())) {
                $this->insert($data);
                $fixed = true;
            }
        }
        return $fixed;
    }
}
