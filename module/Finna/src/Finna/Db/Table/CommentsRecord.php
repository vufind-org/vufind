<?php
/**
 * Table Definition for CommentsRecord link table.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\Gateway;
use VuFind\Db\Table\PluginManager;

/**
 * Table Definition for CommentsRecord link table.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CommentsRecord extends Gateway
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'finna_comments_record'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
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
        $linkedRecordIds = [];

        // Remove any orphaned links
        $links = $this->select(
            ['comment_id' => $comment]
        );
        foreach ($links as $link) {
            if (!in_array($link->record_id, $records)) {
                $link->delete();
                $fixed = true;
            } else {
                $linkedRecordIds[] = $link->record_id;
            }
        }

        // Add missing links
        $missingRecordIds = array_diff($records, $linkedRecordIds);
        foreach ($missingRecordIds as $recordId) {
            $data = ['record_id' => $recordId, 'comment_id' => $comment];
            $this->insert($data);
            $fixed = true;
        }
        return $fixed;
    }
}
