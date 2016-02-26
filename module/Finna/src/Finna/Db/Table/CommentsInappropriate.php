<?php
/**
 * Table Definition for inappropriate comments.
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
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use VuFind\Db\Table\Gateway;

/**
 * Table Definition for inappropriate comments.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CommentsInappropriate extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(
            'finna_comments_inappropriate', 'Finna\Db\Row\CommentsInappropriate'
        );
    }

    /**
     * Get inappropriate comments for a record reported by the given user.
     *
     * @param string $userId   Reporter user ID
     * @param string $recordId Record ID
     *
     * @return array
     */
    public function getForRecord($userId, $recordId)
    {
        $callback = function ($select) use ($userId, $recordId) {
            $select->where->equalTo('user_id', $userId);
            $select->join(
                ['cr' => 'finna_comments_record'],
                'finna_comments_inappropriate.comment_id = cr.comment_id', []
            );

            $select->where->equalTo('record_id', $recordId);
            $select->where->equalTo('user_id', $userId);
        };

        $inappropriate = [];
        foreach ($this->select($callback) as $result) {
            $inappropriate[] = $result->comment_id;
        }
        return $inappropriate;
    }
}
